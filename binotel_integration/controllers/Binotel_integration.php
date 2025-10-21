<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Binotel_integration extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('leads_model');
        $this->load->model('clients_model'); // Завантажуємо модель клієнтів
        $this->load->model('binotel_integration/Binotel_integration_model'); // Завантажуємо модель для статистики дзвінків
        $this->load->helper('url'); // Завантажуємо хелпер URL
        $this->load->library('app_modules');
    }

    public function receive_call() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json_response(['status' => 'error', 'message' => 'Only POST requests are allowed.'], 405);
        }

        // Перевірка, чи запит йде від дозволених IP-адрес
        $allowed_ips = [
            '194.88.218.116', '194.88.218.114', '194.88.218.117', '194.88.218.118',
            '194.88.219.67', '194.88.219.78', '194.88.219.70', '194.88.219.71',
            '194.88.219.72', '194.88.219.79', '194.88.219.80', '194.88.219.81',
            '194.88.219.82', '194.88.219.83', '194.88.219.84', '194.88.219.85',
            '194.88.219.86', '194.88.219.87', '194.88.219.88', '194.88.219.89',
            '194.88.219.92', '194.88.218.119', '194.88.218.120', '185.100.66.145',
            '185.100.66.146', '45.91.130.82', '45.91.130.36'
        ];

        if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
            $this->json_response([
                'status'  => 'error',
                'message' => 'Access denied: ' . $_SERVER['REMOTE_ADDR'],
            ], 403);
        }

        // Отримання та розбір даних у вигляді querystring
        parse_str(file_get_contents('php://input'), $data);

        if (empty($data)) {
            $this->json_response(['status' => 'error', 'message' => 'Invalid data received.'], 400);
        }

        $phone_number = isset($data['callDetails']['externalNumber']) ? $data['callDetails']['externalNumber'] : null;
        $call_recording_link = isset($data['callDetails']['linkToCallRecordInMyBusiness']) ? $data['callDetails']['linkToCallRecordInMyBusiness'] : '';
        $call_type = isset($data['callDetails']['callType']) ? $data['callDetails']['callType'] : '';
        $disposition = isset($data['callDetails']['disposition']) ? $data['callDetails']['disposition'] : '';
        $waiting_time = isset($data['callDetails']['waitingTime']) ? $data['callDetails']['waitingTime'] : '';
        $call_duration = isset($data['callDetails']['callDuration']) ? $data['callDetails']['callDuration'] : '';
        $current_datetime = date('Y-m-d H:i:s');

        if ($phone_number) {
        // Перевірка наявності клієнта або ліда з цим номером телефону
        $client = $this->find_client_by_phone($phone_number);
        $lead = $this->find_lead_by_phone($phone_number);
        $is_new_lead = false;

        if ($client) {
            // Запис розмови в таблицю статистики для клієнтів
            $this->insert_client_call_statistics($client->userid, $call_type, $current_datetime, $call_recording_link, $waiting_time, $call_duration, $phone_number);
        } elseif ($lead) {
            // Оновлення поля "останній контакт" для існуючого ліда
            $this->update_lead_last_contact($lead->id, $current_datetime);
            // Запис розмови в таблицю статистики для лідів
            $this->insert_lead_call_statistics($lead->id, $call_type, $current_datetime, $call_recording_link, $waiting_time, $call_duration, $phone_number);
        } else {
            // Створення нового ліда з описом, включаючи посилання на запис розмови
            $lead_data = [
                'name'         => $phone_number,
                'phonenumber'  => $phone_number,
                'dateadded'    => date('Y-m-d H:i:s'),
                'status'       => 2,
                'source'       => 7,
                'assigned'     => 1,
                'addedfrom'    => 1,
                'lastcontact'  => $current_datetime, // Запис дати та часу вхідного виклику
                'description'  => '',
                'address'      => '',
                'email'        => '',
            ];

            $this->db->insert(db_prefix() . 'leads', $lead_data);
            $new_lead_id = $this->db->insert_id();
            $is_new_lead = true;

            // Запис розмови в таблицю статистики для нового ліда
            $this->insert_lead_call_statistics($new_lead_id, $call_type, $current_datetime, $call_recording_link, $waiting_time, $call_duration, $phone_number);

            // Оновлюємо об'єкт ліда для сповіщення
            $lead = $this->find_lead_by_phone($phone_number);
        }

        // Створення сповіщення для існуючого клієнта або ліда
        if ($client || !$is_new_lead) {
            if ($call_type == '1') { // Вихідний дзвінок
                if ($disposition == 'CANCEL') {
                    $this->create_notification($phone_number, $client, $lead, 'missed_outgoing');
                } else {
                    $this->create_notification($phone_number, $client, $lead, 'outgoing');
                }
            } else { // Вхідний дзвінок
                if ($call_recording_link) {
                    $this->create_notification($phone_number, $client, $lead, 'accepted');
                } else {
                    $this->create_notification($phone_number, $client, $lead, 'missed');
                }
            }
        } else {
            // Створення сповіщення для нового ліда
            if ($call_type == '1') { // Вихідний дзвінок
                if ($disposition == 'CANCEL') {
                    $this->create_notification_for_new_lead($phone_number, $lead->name, $lead->id, 'missed_outgoing');
                } else {
                    $this->create_notification_for_new_lead($phone_number, $lead->name, $lead->id, 'outgoing');
                }
            } else { // Вхідний дзвінок
                if ($call_recording_link) {
                    $this->create_notification_for_new_lead($phone_number, $lead->name, $lead->id, 'accepted');
                } else {
                    $this->create_notification_for_new_lead($phone_number, $lead->name, $lead->id, 'missed');
                }
            }
        }
        $this->json_response(['status' => 'success']);
    } else {
        $this->json_response(['status' => 'error', 'message' => 'Номер телефону не надано.'], 400);
    }
}

private function update_lead_last_contact($lead_id, $datetime) {
    $this->db->where('id', $lead_id);
    $this->db->update(db_prefix() . 'leads', ['lastcontact' => $datetime]);
}
    private function insert_client_call_statistics($client_id, $call_type, $call_time, $recording_link, $waiting_time, $call_duration, $external_number) {
        $data = [
            'client_id' => $client_id,
            'call_type' => $call_type,
            'call_time' => $call_time,
            'recording_link' => $recording_link,
            'waiting_time' => $waiting_time,
            'call_duration' => $call_duration,
            'contact_name' => $external_number,
        ];

        $this->db->insert(db_prefix() . 'binotel_call_statistics_clients', $data);
    }

    private function insert_lead_call_statistics($lead_id, $call_type, $call_time, $recording_link, $waiting_time, $call_duration, $external_number) {
        $data = [
            'lead_id' => $lead_id,
            'call_type' => $call_type,
            'call_time' => $call_time,
            'recording_link' => $recording_link,
            'waiting_time' => $waiting_time,
            'call_duration' => $call_duration,
            'contact_name' => $external_number,
        ];

        $this->db->insert(db_prefix() . 'binotel_call_statistics_leads', $data);
    }

    private function find_client_by_phone($phone_number) {
        $this->db->like('phonenumber', $phone_number);
        $query = $this->db->get(db_prefix() . 'clients');

        if ($query->num_rows() > 0) {
            return $query->row(); // Повертає дані клієнта
        } else {
            return false; // Клієнта з таким номером не існує
        }
    }

    private function find_lead_by_phone($phone_number) {
        $this->db->like('phonenumber', $phone_number);
        $query = $this->db->get(db_prefix() . 'leads');

        if ($query->num_rows() > 0) {
            return $query->row(); // Повертає дані ліда
        } else {
            return false; // Ліда з таким номером не існує
        }
    }

    private function create_notification($phone_number, $client = false, $lead = false, $type = 'accepted') {
        $icons = [
            'accepted' => '<i class="fa fa-phone" style="color:green;"></i>',
            'missed' => '<i class="fas fa-phone-slash" style="color:red;"></i>',
            'outgoing' => '<i class="fa fa-phone" style="color:blue;"></i>',
            'missed_outgoing' => '<i class="fas fa-phone-slash" style="color:orange;"></i>'
        ];

        $message = $icons[$type] . ' ';
        $message .= ($type == 'accepted' || $type == 'missed') ? ($type == 'accepted' ? "Вхідний дзвінок від" : "Неприйнятий виклик від") : ($type == 'outgoing' ? "Вихідний дзвінок до" : "Неприйнятий вихідний виклик до");

        if ($client) {
            $message .= " клієнта {$client->company}";
            $link = 'clients/client/' . $client->userid . '?group=call_statistics'; // Посилання на профіль клієнта
        } elseif ($lead) {
            $message .= " ліда {$lead->name}";
           $link = 'leads/index/' . $lead->id ; // Посилання на ліда
        } else {
            $message .= " номером {$phone_number}";
            $link = 'leads/index/0'; // Посилання на відповідну сторінку
        }

        $notification_data = [
            'description' => $message,
            'touserid' => 1, // ID користувача, який отримає сповіщення
            'fromcompany' => 0,
            'link' => $link,
            'additional_data' => serialize([$phone_number]),
            'date' => date('Y-m-d H:i:s') // Додаємо правильну дату
        ];

        // Додаємо запис у таблицю сповіщень
        $this->db->insert(db_prefix() . 'notifications', $notification_data);
    }

    private function create_notification_for_new_lead($phone_number, $lead_name, $lead_id, $type = 'accepted') {
        $icons = [
            'accepted' => '<i class="fa fa-phone" style="color:green;"></i>',
            'missed' => '<i class="fas fa-phone-slash" style="color:red;"></i>',
            'outgoing' => '<i class="fa fa-phone" style="color:blue;"></i>',
            'missed_outgoing' => '<i class="fas fa-phone-slash" style="color:orange;"></i>'
        ];

        $message = $icons[$type] . ' ';
        $message .= ($type == 'accepted' || $type == 'missed') ? ($type == 'accepted' ? "Вхідний дзвінок від нового ліда" : "Неприйнятий виклик від нового ліда") : ($type == 'outgoing' ? "Вихідний дзвінок до нового ліда" : "Неприйнятий вихідний виклик до нового ліда");
        $message .= " {$lead_name}";

        $notification_data = [
            'description' => $message,
            'touserid' => 1, // ID користувача, який отримає сповіщення (може бути динамічним)
            'fromcompany' => 0,
            'link' => 'leads/index/' . $lead_id, // Посилання на ліда
            'additional_data' => serialize([$phone_number]),
            'date' => date('Y-m-d H:i:s') // Додаємо правильну дату
        ];

        // Додаємо запис у таблицю сповіщень
        $this->db->insert(db_prefix() . 'notifications', $notification_data);
    }

    // Функція для виклику з CRM
    public function make_call() {
        $phone_number = $this->input->post('phone');

        if (empty($phone_number)) {
            $this->json_response(['status' => 'error', 'message' => 'Номер телефону не вказано.'], 400);
        }

        $apiKey = '11111111'; // Змініть на ваш ключ
        $secret = '000000000'; // Змініть на ваш секретний ключ

        $response = $this->make_binotel_call($phone_number, $apiKey, $secret);

        $this->json_response(['status' => 'success', 'message' => $response]);
    }
    
    

    private function make_binotel_call($phone, $apiKey, $secret) {
        $url = 'https://api.binotel.com/api/4.0/calls/internal-number-to-external-number.json';
        $internalNumber = '000'; // Змініть на ваш внутрішній номер у Binotel

        $data = [
            'internalNumber' => $internalNumber,
            'externalNumber' => $phone,
            'key' => $apiKey,
            'secret' => $secret
        ];

        $json_data = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code == 200) {
            return $response;
        } else {
            return "Помилка при дзвінку: HTTP " . $http_code . " - " . $response;
        }
    }
    
    // Функція для фільтрування дзвінків по даті в картці ліда
  public function get_filtered_calls_for_lead() {
    if (!$this->input->is_ajax_request()) {
        show_404();
    }

    $lead_id = $this->input->post('lead_id');
    $start_date = $this->input->post('start_date');
    $end_date = $this->input->post('end_date');

    // Виклик моделі для отримання дзвінків
    $this->load->model('binotel_integration/Binotel_integration_model');
    $call_statistics = $this->Binotel_integration_model->get_lead_call_statistics($lead_id, $start_date, $end_date);

    if (!empty($call_statistics)) {
        $this->load->view('binotel_integration/call_statistics_partial_view', ['call_statistics' => $call_statistics]);
    } else {
        echo "<p>Записів розмов за цей період не знайдено</p>";
    }
}

// Функція для фільтрування дзвінків по даті в картці ліда
  public function get_filtered_calls_for_client() {
    if (!$this->input->is_ajax_request()) {
        show_404();
    }

    $client_id = $this->input->post('client_id');
    $start_date = $this->input->post('start_date');
    $end_date = $this->input->post('end_date');

    $this->load->model('binotel_integration/Binotel_integration_model');
    $call_statistics = $this->Binotel_integration_model->get_client_call_statistics($client_id, $start_date, $end_date);

    if (!empty($call_statistics)) {
        $this->load->view('binotel_integration/call_statistics_partial_view_clients', ['call_statistics' => $call_statistics]);
    } else {
        echo "<p>Записів розмов за цей період не знайдено.</p>";
    }
}


    private function convert_lead_to_client($lead_id, $client_id) {
        // Перенесення записів дзвінків з таблиці лідів у таблицю клієнтів
        $this->db->where('lead_id', $lead_id);
        $call_records = $this->db->get(db_prefix() . 'binotel_call_statistics_leads')->result_array();

        foreach ($call_records as $record) {
            unset($record['id']);
            $record['client_id'] = $client_id;
            $record['lead_id'] = null;
            $this->db->insert(db_prefix() . 'binotel_call_statistics_clients', $record);
        }

        // Видалення записів дзвінків з таблиці лідів
        $this->db->where('lead_id', $lead_id);
        $this->db->delete(db_prefix() . 'binotel_call_statistics_leads');
    }
    
    public function get_contacts() {
    // Отримання контактів з бази даних
    $clients = $this->db->select('company as name, phonenumber as number')
                        ->from(db_prefix() . 'clients')
                        ->get()
                        ->result_array();

    $leads = $this->db->select('name, phonenumber as number')
                      ->from(db_prefix() . 'leads')
                      ->get()
                      ->result_array();

    // Об'єднуємо клієнтів та лідів
    $contacts = array_merge($clients, $leads);

    // Формуємо масив для JSON-відповіді
    $items = [];

    foreach ($contacts as $contact) {
        $items[] = [
            'number' => $contact['number'],
            'name' => $contact['name'],
            'presence' => 0,
            // Можете додати інші поля за потреби
        ];
    }

    // Формуємо остаточний масив
    $response = [
        'refresh' => 3600, // Частота оновлення в секундах
        'items' => $items,
    ];

        header('Cache-Control: max-age=3600');
        $this->json_response($response);
    }

    private function json_response(array $data, int $statusCode = 200)
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data);
        exit;
    }

    public function view()
    {
        $this->load->view('binotel_integration_view');
    }
   
    
}



