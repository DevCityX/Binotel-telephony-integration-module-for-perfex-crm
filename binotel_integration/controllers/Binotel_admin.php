<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Binotel_admin extends AdminController {

    public function __construct() {
        parent::__construct();
        $this->load->model('binotel_integration/Binotel_integration_model');
    }

    public function index() {
        $data['title'] = 'Binotel Integration';
        $this->load->view('binotel_integration/view_admin', $data);
    }
    
    
     public function binotel_notifications() {
        $data['title'] = 'Усі дзвінки Binotel';

        // Отримання параметрів фільтрації з GET-запиту
        $start_date = $this->input->get('start_date');
        $end_date   = $this->input->get('end_date');
        
         $per_page = 100;
        $page = max(1, (int) $this->input->get('page'));
        $offset = ($page - 1) * $per_page;
        

        // Фільтрація дзвінків за датою
       
        if (!empty($start_date) && !empty($end_date)) {
            $this->db->where('date >=', $start_date . ' 00:00:00');
            $this->db->where('date <=', $end_date . ' 23:59:59');
        }
         $data['total_notifications'] = $this->db->count_all_results(db_prefix().'binotel_notifications', false);
        $this->db->order_by('date', 'DESC');
        $this->db->limit($per_page, $offset);
        $data['notifications'] = $this->db->get()->result();
        $data['current_page'] = $page;
        $data['per_page'] = $per_page;
        $data['total_pages'] = (int) ceil($data['total_notifications'] / $per_page);

        // Підрахунок нових лідів за період
        if (!empty($start_date) && !empty($end_date)) {
            $this->db->where('dateadded >=', $start_date . ' 00:00:00');
            $this->db->where('dateadded <=', $end_date . ' 23:59:59');
            $data['new_leads_count'] = $this->db->count_all_results(db_prefix().'leads');
        } else {
            $data['new_leads_count'] = 0;
        }

        // Готуємо дані для графіків, якщо період обрано
        if (!empty($start_date) && !empty($end_date)) {
            // Графік дзвінків від лідів (виключаємо записи, що містять "клієнт" або "клієнта")
            $this->db->select('description, COUNT(*) as count');
            $this->db->where('date >=', $start_date . ' 00:00:00');
            $this->db->where('date <=', $end_date . ' 23:59:59');
            $this->db->not_like('description', 'клієнт');
            $this->db->not_like('description', 'клієнта');
            $this->db->group_by('description');
            $data['calls_per_lead'] = $this->db->get(db_prefix().'binotel_notifications')->result();

            // Графік дзвінків від клієнтів
            $this->db->select('description, COUNT(*) as count');
            $this->db->where('date >=', $start_date . ' 00:00:00');
            $this->db->where('date <=', $end_date . ' 23:59:59');
            $this->db->like('description', 'Вхідний дзвінок від клієнта');
            $this->db->group_by('description');
            $data['calls_per_client'] = $this->db->get(db_prefix().'binotel_notifications')->result();
        } else {
            $data['calls_per_lead']   = [];
            $data['calls_per_client'] = [];
        }
        
        $phones = [];
        foreach ($data['notifications'] as $note) {
            $additional = @unserialize($note->additional_data);
            if (is_array($additional) && !empty($additional[0])) {
                $phones[] = trim($additional[0]);
            }
        }

        $recording_links = $this->find_recording_links_by_phones($phones);


        // Для кожного сповіщення підтягуємо recording_link із таблиць статистики
        foreach ($data['notifications'] as &$note) {
            $phone = '';
            $additional = @unserialize($note->additional_data);
            if (is_array($additional) && !empty($additional[0])) {
                $phone = trim($additional[0]);
            }
           $note->recording_link = (!empty($phone) && isset($recording_links[$phone])) ? $recording_links[$phone] : '';
        }
        unset($note);

        // Відображаємо повний шаблон
        $this->load->view('binotel_integration/all_notifications', $data);
    }

    /**
     Метод пошуку recording_link для списку номерів у заданій таблиці.
     * Повертає найсвіжіший recording_link по кожному номеру.
     *
     * @param array  $phones
     * @param string $type - 'leads', 'clients', 'staff'
     * @return array<string,string>
     */
    private function find_recording_links_in_table(array $phones, $type = 'leads') {
        $tables = [
            'leads'   => db_prefix().'binotel_call_statistics_leads',
            'clients' => db_prefix().'binotel_call_statistics_clients',
            'staff'   => db_prefix().'binotel_call_statistics_staff',
        ];
        if (!isset($tables[$type])) {
                return [];
        }

        $phones = array_values(array_unique(array_filter($phones)));
        if (empty($phones)) {
            return [];
        }
        $tableName = $tables[$type];
        $this->db->select('contact_name, recording_link');
        $this->db->where_in('contact_name', $phones);
        $this->db->where('recording_link !=', '');
        $this->db->order_by('call_time', 'DESC');
        $rows = $this->db->get($tableName)->result();

        $recordings_by_phone = [];
        foreach ($rows as $row) {
            if (!isset($recordings_by_phone[$row->contact_name])) {
                $recordings_by_phone[$row->contact_name] = $row->recording_link;
            }
        }

        return $recordings_by_phone;
    }

    /**
     * Пошук recording_link у пріоритеті: leads -> clients -> staff.
     *
     * @param array $phones
     * @return array<string,string>
     */
    private function find_recording_links_by_phones(array $phones) {
        $phones = array_values(array_unique(array_filter($phones)));
        if (empty($phones)) {
            return [];
        }

        $recordings = $this->find_recording_links_in_table($phones, 'leads');
        $missing = array_values(array_diff($phones, array_keys($recordings)));

        if (!empty($missing)) {
            $client_recordings = $this->find_recording_links_in_table($missing, 'clients');
            $recordings = $recordings + $client_recordings;
            $missing = array_values(array_diff($phones, array_keys($recordings)));
        }

        if (!empty($missing)) {
            $staff_recordings = $this->find_recording_links_in_table($missing, 'staff');
            $recordings = $recordings + $staff_recordings;
        }

        return $recordings;
    }

    public function get_binotel_calls_list() {
    $this->db->order_by('date', 'DESC');
    $this->db->limit(10);
    $calls = $this->db->get(db_prefix().'binotel_notifications')->result();

    $hasNewCalls = false;
    $newCallColor = '';
    $html = '';

    if ($calls) {
        foreach ($calls as $call) {
            if ($call->isread == 0) {
                $hasNewCalls = true;
                if (empty($newCallColor)) {
                    if (strpos($call->description, 'color:green') !== false) {
                        $newCallColor = 'green';
                    } elseif (strpos($call->description, 'color:blue') !== false) {
                        $newCallColor = 'blue';
                    } elseif (strpos($call->description, 'color:red') !== false) {
                        $newCallColor = 'red';
                    } elseif (strpos($call->description, 'color:orange') !== false) {
                        $newCallColor = 'orange';
                    }
                }
            }

            // Дістаємо номер телефону
            $additional = @unserialize($call->additional_data);
            $phone = (is_array($additional) && isset($additional[0])) ? $additional[0] : '';

            // Починаємо <li>
            $html .= '<li class="relative notification-wrapper" data-notification-id="'.$call->id.'">';
            $html .= '  <div class="tw-p-3 notification-box" style="display: flex; justify-content: space-between; align-items: flex-start;">';

            // Ліва частина: посилання на $call->link
            $html .= '    <a href="'.admin_url($call->link).'" class="notification-top notification-link" '
                  .'style="text-decoration: none; color: inherit; flex: 1; margin-right: 10px;">';
            $html .= '      <div class="media-body">';
            $html .= '        <span class="notification-title">'.$call->description.'</span><br>';
            $html .= '        <span class="notification-date">'.date("d.m.Y H:i", strtotime($call->date)).'</span>';
            $html .= '      </div>';
            $html .= '    </a>';

            // Права частина: кнопка виклику, якщо є номер
            if (!empty($phone)) {
                // Кнопка з атрибутом title="Телефонувати"
                $html .= '    <button class="call-button" data-phone="'.$phone.'" title="Телефонувати" '
                       .'style="background: #fff; border: 1px solid #000; border-radius: 50%; '
                       .'width: 30px; height: 30px; display: flex; align-items: center; '
                       .'justify-content: center; cursor: pointer;">'
                       .'<i class="fa fa-phone" style="color: #000; font-size: 16px;"></i>'
                       .'</button>';
            }

            $html .= '  </div>'; 
            $html .= '</li>';
        }
    } else {
        $html .= '<li><div class="tw-p-3">Нема дзвінків</div></li>';
    }

    // (Опційно) Кнопка «Набрати номер»
    $html .= '
        <li class="divider !tw-my-0"></li>
        <li class="relative notification-wrapper">
            <div class="tw-p-3 notification-box">
                <div class="media-body tw-text-center">
                    <button class="dialpad-floating-btn" onclick="openDialpad(); return false;" title="Набрати номер">
                        <i class="fa fa-th"></i>
                    </button>
                </div>
            </div>
        </li>
    ';

    // Кнопка "Подивитися всі дзвінки"
    $html .= '
        <li class="divider !tw-my-0"></li>
        <div class="tw-text-center tw-p-4 tw-bg-neutral-50">
            <a class="btn btn-default" href="'.admin_url('binotel_integration/binotel_admin/binotel_notifications').'">
                Подивитися всі дзвінки
            </a>
        </div>
    ';

    echo json_encode([
        'html'         => $html,
        'hasNew'       => $hasNewCalls,
        'newCallColor' => $newCallColor,
    ]);
}




public function mark_binotel_notifications_read() {
    $this->db->set('isread', 1);
    $this->db->update(db_prefix().'binotel_notifications');
    echo json_encode(['status' => 'success']);
}

    
}
