<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Binotel_integration extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // Завантаження моделей, хелперів, бібліотек
        $this->load->model('leads_model');
        $this->load->model('clients_model');
        $this->load->model('staff_model');
        $this->load->model('binotel_integration/Binotel_integration_model');
        $this->load->helper('url');
    }

    public function receive_call() {
        // Перевірка методу запиту
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Only POST requests are allowed.';
            return;
        }
        
        // Дозволені IP (Binotel сервери)
        $allowed_ips = [
            '194.88.218.116', '194.88.218.114', '194.88.218.117', '194.88.218.118',
            '194.88.219.67', '194.88.219.78', '194.88.219.70', '194.88.219.71',
            '194.88.219.72', '194.88.219.79', '194.88.219.80', '194.88.219.81',
            '194.88.219.82', '194.88.219.83', '194.88.219.84', '194.88.219.85',
            '194.88.219.86', '194.88.219.87', '194.88.219.88', '194.88.219.89',
            '194.88.219.92', '194.88.218.119', '194.88.218.120', '185.100.66.145',
            '185.100.66.146', '45.91.130.82', '45.91.130.36', '45.91.129.203'
        ];
        if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
            http_response_code(403);
            echo 'Access denied: ' . $_SERVER['REMOTE_ADDR'];
            return;
        }
        
        // Читаємо вхідні дані (query string у тілі POST)
        $raw = $this->input->raw_input_stream;
        parse_str($raw, $data);
        if (empty($data)) {
            http_response_code(400);
            echo 'Invalid data received.';
            return;
        }
        
        // Якщо запит має requestType=apiCallSettings – це запит для передачі даних CRM до Binotel
        if (isset($data['requestType']) && $data['requestType'] === 'apiCallSettings') {
            $phone = $data['externalNumber'] ?? '';
            if (empty($phone)) {
                echo json_encode(['error' => 'Phone number not provided']);
                return;
            }
            // Шукаємо сутність за номером
            $client = $this->find_client_by_phone($phone);
            $lead   = $this->find_lead_by_phone($phone);
            $staff  = $this->find_staff_by_phone($phone);
            
            if ($client) {
                $customerName = !empty($client->company) ? $client->company : trim($client->firstname . ' ' . $client->lastname);
                $customerLink = admin_url('clients/client/' . $client->userid . '?group=call_statistics');
            } elseif ($lead) {
                $customerName = $lead->name;
                $customerLink = admin_url('leads/index/' . $lead->id);
            } elseif ($staff) {
                $customerName = trim($staff->firstname . ' ' . $staff->lastname);
                $customerLink = admin_url('staff/member/' . $staff->staffid . '?group=call_statistics');
            } else {
                $customerName = $phone;
                $customerLink = '';
            }
            echo json_encode([
                'customerData' => [
                    'name' => $customerName,
                    'linkToCrmUrl' => $customerLink
                ]
            ]);
            return;
        }
        
        // Обробка звичайного дзвінка (без requestType=apiCallSettings)
        $phone_number        = $data['callDetails']['externalNumber'] ?? '';
        $call_recording_link = $data['callDetails']['linkToCallRecordInMyBusiness'] ?? '';
        $call_type           = $data['callDetails']['callType'] ?? '';
        $disposition         = $data['callDetails']['disposition'] ?? '';
        $waiting_time        = $data['callDetails']['waitingTime'] ?? '';
        $call_duration       = $data['callDetails']['callDuration'] ?? '';
        $current_datetime    = date('Y-m-d H:i:s');
        
        if (empty($phone_number)) {
            echo json_encode(['status' => 'error', 'message' => 'Phone number not provided.']);
            return;
        }
        
        $client = $this->find_client_by_phone($phone_number);
        $lead   = $this->find_lead_by_phone($phone_number);
        $staff  = $this->find_staff_by_phone($phone_number);
        
        if ($client) {
            $this->insert_client_call_statistics($client->userid, $call_type, $current_datetime, $call_recording_link, $waiting_time, $call_duration, $phone_number);
            $this->create_notification($phone_number, $client, null, null, $call_type, $call_recording_link, $disposition);
        } elseif ($lead) {
            $this->update_lead_last_contact($lead->id, $current_datetime);
            $this->insert_lead_call_statistics($lead->id, $call_type, $current_datetime, $call_recording_link, $waiting_time, $call_duration, $phone_number);
            $this->create_notification($phone_number, null, $lead, null, $call_type, $call_recording_link, $disposition);
        } elseif ($staff) {
            $this->insert_staff_call_statistics($staff->staffid, $call_type, $current_datetime, $call_recording_link, $waiting_time, $call_duration, $phone_number);
            $this->create_notification($phone_number, null, null, $staff, $call_type, $call_recording_link, $disposition);
        } else {
            // Якщо номер новий – створюємо нового ліда
            $lead_data = [
                'name'        => $phone_number,
                'phonenumber' => $phone_number,
                'dateadded'   => $current_datetime,
                'status'      => 2,
                'source'      => 7,
                'assigned'    => 1,
                'addedfrom'   => 1,
                'lastcontact' => $current_datetime
            ];
            $this->db->insert(db_prefix() . 'leads', $lead_data);
            $new_lead_id = $this->db->insert_id();
            
            $this->insert_lead_call_statistics($new_lead_id, $call_type, $current_datetime, $call_recording_link, $waiting_time, $call_duration, $phone_number);
            $message = '<i class="fa fa-phone" style="color:green;"></i> Вхідний дзвінок від нового ліда ' . htmlspecialchars($phone_number);
            $notification_data = [
                'description'     => $message,
                'touserid'        => 1,
                'fromcompany'     => 0,
                'link'            => 'leads/index/' . $new_lead_id,
                'additional_data' => serialize([$phone_number]),
                'date'            => $current_datetime
            ];
            $this->db->insert(db_prefix() . 'binotel_notifications', $notification_data);
            // Для відповіді встановлюємо дані як для ліда
            $lead = (object)[
                'id'   => $new_lead_id,
                'name' => $phone_number
            ];
        }
        
        // Підготовка даних для відповіді, які передаються Бінотел
        if ($client) {
            $customerName = !empty($client->company) ? $client->company : trim($client->firstname . ' ' . $client->lastname);
            $customerLink = admin_url('clients/client/' . $client->userid . '?group=call_statistics');
        } elseif ($lead) {
            $customerName = $lead->name;
            $customerLink = admin_url('leads/index/' . $lead->id);
        } elseif ($staff) {
            $customerName = trim($staff->firstname . ' ' . $staff->lastname);
            $customerLink = admin_url('staff/member/' . $staff->staffid . '?group=call_statistics');
        } else {
            $customerName = $phone_number;
            $customerLink = '';
        }
        
        // Повертаємо відповідь у форматі JSON із статусом success
        echo json_encode([
            'customerData' => [
                'name' => $customerName,
                'linkToCrmUrl' => $customerLink
            ],
            'status' => 'success'
        ]);
    }
    
    /* Допоміжні методи */
    
    private function create_notification($phone_number, $client = null, $lead = null, $staff = null, $call_type = '1', $recording_link = null, $disposition = null) {
        $icons = [
            'accepted' => '<i class="fa fa-phone" style="color:green;"></i>',
            'missed' => '<i class="fas fa-phone-slash" style="color:red;"></i>',
            'outgoing' => '<i class="fa fa-phone" style="color:blue;"></i>',
            'missed_outgoing' => '<i class="fas fa-phone-slash" style="color:orange;"></i>'
        ];
    
        $type = ($call_type == '1') ? ($recording_link ? 'outgoing' : 'missed_outgoing') : ($recording_link ? 'accepted' : 'missed');
        $message = $icons[$type] . ' ';
        if ($call_type == '1') {
            $message .= $recording_link ? "Вихідний дзвінок до" : "Неприйнятий вихідний дзвінок до";
        } else {
            $message .= $recording_link ? "Вхідний дзвінок від" : "Неприйнятий дзвінок від";
        }
    
        if ($client) {
            $message .= " клієнта {$client->company}";
            $link = 'clients/client/' . $client->userid . '?group=call_statistics';
        } elseif ($lead) {
            $message .= " ліда {$lead->name}";
            $link = 'leads/index/' . $lead->id;
        } elseif ($staff) {
            $message .= " співробітника {$staff->firstname} {$staff->lastname}";
            $link = 'staff/member/' . $staff->staffid . '?group=call_statistics';
        } else {
            $message .= " номером {$phone_number}";
            $link = '';
        }
    
        $notification_data = [
            'description'     => $message,
            'touserid'        => 1,
            'fromcompany'     => 0,
            'link'            => $link,
            'additional_data' => serialize([$phone_number]),
            'date'            => date('Y-m-d H:i:s')
        ];
    
        $this->db->insert(db_prefix() . 'binotel_notifications', $notification_data);
    }
    
    private function find_client_by_phone($phone_number) {
        $this->db->like('phonenumber', $phone_number);
        $query = $this->db->get(db_prefix() . 'clients');
        return $query->row();
    }
    
    private function find_lead_by_phone($phone_number) {
        $this->db->like('phonenumber', $phone_number);
        $query = $this->db->get(db_prefix() . 'leads');
        return $query->row();
    }
    
    private function find_staff_by_phone($phone_number) {
        $this->db->like('phonenumber', $phone_number);
        $query = $this->db->get(db_prefix() . 'staff');
        return $query->row();
    }
    
    private function insert_client_call_statistics($client_id, $call_type, $call_time, $recording_link, $waiting_time, $call_duration, $external_number) {
        $data = [
            'client_id'      => $client_id,
            'call_type'      => $call_type,
            'call_time'      => $call_time,
            'recording_link' => $recording_link,
            'waiting_time'   => $waiting_time,
            'call_duration'  => $call_duration,
            'contact_name'   => $external_number
        ];
        $this->db->insert(db_prefix() . 'binotel_call_statistics_clients', $data);
    }
    
    private function insert_lead_call_statistics($lead_id, $call_type, $call_time, $recording_link, $waiting_time, $call_duration, $external_number) {
        $data = [
            'lead_id'        => $lead_id,
            'call_type'      => $call_type,
            'call_time'      => $call_time,
            'recording_link' => $recording_link,
            'waiting_time'   => $waiting_time,
            'call_duration'  => $call_duration,
            'contact_name'   => $external_number
        ];
        $this->db->insert(db_prefix() . 'binotel_call_statistics_leads', $data);
    }
    
    private function insert_staff_call_statistics($staff_id, $call_type, $call_time, $recording_link, $waiting_time, $call_duration, $external_number) {
        $data = [
            'staff_id'       => $staff_id,
            'call_type'      => $call_type,
            'call_time'      => $call_time,
            'recording_link' => $recording_link,
            'waiting_time'   => $waiting_time,
            'call_duration'  => $call_duration,
            'contact_name'   => $external_number
        ];
        $this->db->insert(db_prefix() . 'binotel_call_statistics_staff', $data);
    }
    
    private function update_lead_last_contact($lead_id, $datetime) {
        $this->db->where('id', $lead_id);
        $this->db->update(db_prefix() . 'leads', ['lastcontact' => $datetime]);
    }
    
    // Функція для виклику з CRM
    public function make_call() {
        $phone_number = $this->input->post('phone');

        if (empty($phone_number)) {
            echo json_encode(['status' => 'error', 'message' => 'Номер телефону не вказано.']);
            return;
        }

        $apiKey = get_option('binotel_api_key');

        $secret = get_option('binotel_secret');// Змініть на ваш секретний ключ

        $response = $this->make_binotel_call($phone_number, $apiKey, $secret);

        echo json_encode(['status' => 'success', 'message' => $response]);
    }
    
    

    private function make_binotel_call($phone, $apiKey, $secret) {
        $url = 'https://api.binotel.com/api/4.0/calls/internal-number-to-external-number.json';
        $internalNumber = get_option('binotel_internal_number'); // Змініть на ваш внутрішній номер у Binotel

        $data = [
            'internalNumber' => $internalNumber,
            'externalNumber' => $phone,
            'key' => $apiKey,
            'secret' => $secret,
            'playbackWaiting'  => false 
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
    
    
    
    
    
    // Функції фільтрації дзвінків (CRM)
    public function get_filtered_calls() {
        $lead_id = $this->input->post('lead_id');
        $start_date = $this->input->post('start_date');
        $end_date = $this->input->post('end_date');
        $this->load->model('binotel_integration/Binotel_integration_model');
        $call_statistics = $this->Binotel_integration_model->get_lead_call_statistics($lead_id, $start_date, $end_date);
        if (!empty($call_statistics)) {
            $this->load->view('call_statistics_partial_view', ['call_statistics' => $call_statistics]);
        } else {
            echo "<p>Записів розмов за цей період не знайдено</p>";
        }
    }
    
    public function get_filtered_calls_for_client() {
        $client_id = $this->input->post('client_id');
        $start_date = $this->input->post('start_date');
        $end_date = $this->input->post('end_date');
        $this->load->model('binotel_integration/Binotel_integration_model');
        $call_statistics = $this->Binotel_integration_model->get_client_call_statistics($client_id, $start_date, $end_date);
        if (!empty($call_statistics)) {
            $this->load->view('call_statistics_partial_view_clients', ['call_statistics' => $call_statistics]);
        } else {
            echo "<p>Записів розмов за цей період не знайдено.</p>";
        }
    }
    
    public function get_filtered_calls_for_staff() {
        $staff_id = $this->input->post('staff_id');
        $start_date = $this->input->post('start_date');
        $end_date = $this->input->post('end_date');
        if (empty($staff_id)) {
            echo json_encode(['status' => 'error', 'message' => 'ID співробітника не передано']);
            return;
        }
        $this->load->model('binotel_integration/Binotel_integration_model');
        $call_statistics = $this->Binotel_integration_model->get_staff_call_statistics($staff_id, $start_date, $end_date);
        $html = $this->load->view('call_statistics_partial_view_staff', ['call_statistics' => $call_statistics], true);
        echo json_encode(['status' => 'success', 'html' => $html]);
    }
    
    public function load_staff_call_statistics() {
        $staff_id = $this->input->get('staff_id');
        if (!$staff_id) {
            echo "ID співробітника не передано.";
            return;
        }
        $staff = $this->db->select('staffid, firstname, lastname, phonenumber')
                          ->where('staffid', $staff_id)
                          ->get(db_prefix() . 'staff')
                          ->row();
        if (!$staff) {
            echo "Співробітника не знайдено.";
            return;
        }
        $this->load->model('binotel_integration/Binotel_integration_model');
        $call_statistics = $this->Binotel_integration_model->get_staff_call_statistics($staff_id);
        $data = [
            'member' => $staff,
            'call_statistics' => $call_statistics
        ];
        $this->load->view('staff_call_statistics', $data);
    }
    
      public function transcribe_call() {
        if (function_exists('is_staff_logged_in') && !is_staff_logged_in()) {
            return $this->json_response(['status' => 'error', 'message' => 'Необхідна авторизація.'], 401);
        }

        $call_id = (int) $this->input->post('call_id');
        $entity_type = $this->input->post('entity_type');

        if (!$call_id || !in_array($entity_type, ['lead', 'client', 'staff'], true)) {
            return $this->json_response(['status' => 'error', 'message' => 'Некоректні параметри.'], 422);
        }

        $api_key = trim((string) get_option('binotel_openai_api_key'));
        $model = trim((string) get_option('binotel_openai_transcribe_model')) ?: 'gpt-4o-mini-transcribe';
        if (empty($api_key)) {
            return $this->json_response(['status' => 'error', 'message' => 'Не задано OpenAI API Key в налаштуваннях модуля.'], 422);
        }

        $call = $this->Binotel_integration_model->get_call_record_for_transcription($entity_type, $call_id);
        if (!$call || empty($call['recording_link'])) {
            return $this->json_response(['status' => 'error', 'message' => 'Не знайдено запис дзвінка для транскрибації.'], 404);
        }

        $this->Binotel_integration_model->update_transcription($entity_type, $call_id, [
            'transcription_status' => 'processing',
        ]);

        $audio_file = $this->download_audio_file($call['recording_link']);
      
        if (!empty($audio_file['error'])) {
            $this->Binotel_integration_model->update_transcription($entity_type, $call_id, [
                'transcription_status' => 'failed',
            ]);
            
            return $this->json_response(['status' => 'error', 'message' => $audio_file['error']], 500);
        }

        $transcription = $this->request_openai_transcription($audio_file['path'], $audio_file['mime'], $audio_file['filename'], $api_key, $model);

        if (!$transcription['success'] && $this->should_retry_transcription_with_mp3($transcription['message'])) {
            $converted_file = $this->convert_audio_to_mp3($audio_file['path']);
            if ($converted_file) {
                $transcription = $this->request_openai_transcription($converted_file['path'], $converted_file['mime'], $converted_file['filename'], $api_key, $model);
                @unlink($converted_file['path']);
            }
        }

        @unlink($audio_file['path']);

        if (!$transcription['success']) {
            $this->Binotel_integration_model->update_transcription($entity_type, $call_id, [
                'transcription_status' => 'failed',
            ]);
            return $this->json_response(['status' => 'error', 'message' => $transcription['message']], 500);
        }

        $this->Binotel_integration_model->update_transcription($entity_type, $call_id, [
            'transcription_status' => 'completed',
            'transcription_text' => $transcription['text'],
            'transcribed_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->json_response([
            'status' => 'success',
            'message' => 'Транскрибацію завершено.',
            'text' => $transcription['text'],
            'transcribed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function download_audio_file($url) {
        $tmp = tempnam(sys_get_temp_dir(), 'binotel_call_');
       

        $request = $this->execute_audio_download_request($url, true);
        $used_auth_fallback = false;

        if (($request['content'] === false || $request['http_code'] >= 400 || $this->looks_like_text_payload('application/octet-stream', $this->normalize_content_type($request['content_type']), (string) $request['content']))
            && $this->is_binotel_recording_url($url)) {
            $auth_url = $this->build_authorized_recording_url($url);
            $request = $this->execute_audio_download_request($auth_url, false);
            $used_auth_fallback = true;
        }

        if ($request['content'] === false || $request['http_code'] >= 400) {
            @unlink($tmp);
           
            return ['error' => $this->build_download_error_message($request, $used_auth_fallback)];
        }

        $normalized_header_mime = $this->normalize_content_type($request['content_type']);
        if ($this->looks_like_text_payload('application/octet-stream', $normalized_header_mime, $request['content'])) {
            $candidate_urls = $this->extract_audio_urls_from_html((string) $request['content'], $url);
            foreach ($candidate_urls as $candidate_url) {
                $candidate_request = $this->execute_audio_download_request($candidate_url, false);
                if ($candidate_request['content'] === false || $candidate_request['http_code'] >= 400) {
                    continue;
                }

                $candidate_mime = $this->normalize_content_type($candidate_request['content_type']);
                if ($this->looks_like_text_payload('application/octet-stream', $candidate_mime, $candidate_request['content'])) {
                    continue;
                }

                $request = $candidate_request;
                $url = $candidate_url;
                $normalized_header_mime = $candidate_mime;
                break;
            }
        }

        file_put_contents($tmp, $request['content']);

        $basename = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_BASENAME);
        $url_extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        $detected_mime = $this->detect_audio_mime_type($tmp);

        if ($this->looks_like_text_payload($detected_mime, $normalized_header_mime, $request['content'])) {
            @unlink($tmp);
            return ['error' => $this->build_download_error_message($request, $used_auth_fallback, 'отримано не аудіо, а HTML/текст')];
        }

        $extension = $this->normalize_audio_extension($url_extension, $detected_mime, $normalized_header_mime);
        $mime = $this->mime_from_extension($extension);

        if ($basename === '') {
            $filename = 'recording.' . $extension;
        } else {
            $name_without_extension = pathinfo($basename, PATHINFO_FILENAME);
            $safe_name = $name_without_extension !== '' ? $name_without_extension : 'recording';
            $filename = $safe_name . '.' . $extension;
        }

        return [
            'path' => $tmp,
            'mime' => $mime,
            'filename' => $filename,
        ];
    }

    private function execute_audio_download_request($url, $strict_ssl = true) {
        $ch = curl_init($url);
        $headers = [
            'Accept: audio/*,*/*;q=0.8',
        ];

        $api_key = trim((string) get_option('binotel_api_key'));
        $secret = trim((string) get_option('binotel_secret'));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'PerfexBinotelModule/1.0',
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($api_key !== '' && $secret !== '' && $this->is_binotel_recording_url($url)) {
            curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':' . $secret);
        }

        if (!$strict_ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $content = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($strict_ssl && $content === false && in_array($curl_errno, [35, 51, 58, 60], true)) {
            return $this->execute_audio_download_request($url, false);
        }

        return [
            'content' => $content,
            'http_code' => $http_code,
            'content_type' => $content_type,
            'curl_errno' => $curl_errno,
            'curl_error' => $curl_error,
        ];
    }

    private function is_binotel_recording_url($url) {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $host !== '' && strpos($host, 'binotel') !== false;
    }

    private function build_authorized_recording_url($url) {
        $api_key = trim((string) get_option('binotel_api_key'));
        $secret = trim((string) get_option('binotel_secret'));

        if ($api_key === '' || $secret === '') {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (!isset($query['apiKey'])) {
            $query['apiKey'] = $api_key;
        }
        if (!isset($query['key'])) {
            $query['key'] = $api_key;
        }

        if (!isset($query['secret'])) {
            $query['secret'] = $secret;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $path = $parts['path'] ?? '';
        $query_string = http_build_query($query);

        return $scheme . '://' . $parts['host'] . $path . ($query_string !== '' ? ('?' . $query_string) : '');
    }

    private function extract_audio_urls_from_html($html, $base_url) {
        if (!is_string($html) || trim($html) === '') {
            return [];
        }

        $urls = [];
        $patterns = [
            '/<(?:audio|source)[^>]+src=["\']([^"\']+)["\']/i',
            '/<(?:a|link)[^>]+href=["\']([^"\']+)["\']/i',
            '/data-(?:audio|src|url)=["\']([^"\']+)["\']/i',
            '/["\'](https?:\/\/[^"\'<>\s]+)["\']/i',
            '/["\'](\/[^"\'<>\s]+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches) && !empty($matches[1])) {
                foreach ($matches[1] as $match) {
                    $decoded = html_entity_decode((string) $match, ENT_QUOTES, 'UTF-8');
                    $decoded = str_replace('\\/', '/', $decoded);
                    $resolved = $this->resolve_relative_url($base_url, $decoded);
                    if ($resolved !== '') {
                        $urls[] = $resolved;
                    }
                }
            }
        }

        $urls = array_values(array_unique($urls));

       
        usort($urls, function ($a, $b) {
            $a_score = preg_match('/\.(mp3|wav|ogg|m4a|webm|flac)(\?|$)/i', $a) ? 1 : 0;
            $b_score = preg_match('/\.(mp3|wav|ogg|m4a|webm|flac)(\?|$)/i', $b) ? 1 : 0;
            return $b_score <=> $a_score;
        });

        return $urls;
    }

   
    private function resolve_relative_url($base_url, $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $candidate)) {
            return $candidate;
        }

        if (strpos($candidate, '//') === 0) {
            $scheme = parse_url($base_url, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $candidate;
        }

        $scheme = parse_url($base_url, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($base_url, PHP_URL_HOST);
        if (!$host) {
            return '';
        }

        $port = parse_url($base_url, PHP_URL_PORT);
        $host_part = $scheme . '://' . $host . ($port ? ':' . $port : '');

        if (strpos($candidate, '/') === 0) {
            return $host_part . $candidate;
        }

        $path = parse_url($base_url, PHP_URL_PATH) ?: '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $host_part . ($dir ? $dir : '') . '/' . ltrim($candidate, '/');
    }

    private function build_download_error_message($request, $used_auth_fallback = false, $reason = '') {
        $parts = ['Не вдалося завантажити аудіозапис'];

        if ($reason !== '') {
            $parts[] = $reason;
        }

        if (!empty($request['http_code']) && (int) $request['http_code'] >= 400) {
            $parts[] = 'HTTP ' . (int) $request['http_code'];
        }

        if (!empty($request['curl_error'])) {
            $parts[] = 'cURL: ' . $request['curl_error'];
        }

        if ($used_auth_fallback) {
            $parts[] = 'після повторної авторизованої спроби через Binotel';
        }

        $parts[] = 'Перевірте доступ до запису в кабінеті Binotel та коректність API key/secret у налаштуваннях модуля';

        return implode('. ', $parts) . '.';
    }

    private function request_openai_transcription($file_path, $mime, $filename, $api_key, $model) {
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');

        $post_fields = [
            'model' => $model,
            'file' => new CURLFile($file_path, $mime, $filename),
            'response_format' => 'json',
            'language' => 'uk',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
            ],
            CURLOPT_TIMEOUT => 90,
        ]);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => 'Помилка CURL: ' . $curl_error];
        }

        $decoded = json_decode($response, true);
        if ($http_code >= 400) {
            $message = $decoded['error']['message'] ?? ('OpenAI API error HTTP ' . $http_code);
            return ['success' => false, 'message' => $message];
        }

        $text = trim((string)($decoded['text'] ?? ''));
        if ($text === '') {
            return ['success' => false, 'message' => 'OpenAI не повернув текст транскрибації.'];
        }

        return ['success' => true, 'text' => $text];
    }

    private function detect_audio_mime_type($file_path) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $file_path);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return 'application/octet-stream';
    }

    private function normalize_audio_extension($url_extension, $detected_mime, $header_mime = '') {
        $supported_extensions = [
            'flac',
            'm4a',
            'mp3',
            'mp4',
            'mpeg',
            'mpga',
            'oga',
            'ogg',
            'wav',
            'webm',
        ];

        if (in_array($url_extension, $supported_extensions, true)) {
            return $url_extension;
        }

        $map = [
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'audio/mpga' => 'mpga',
            'audio/flac' => 'flac',
            'audio/x-flac' => 'flac',
        ];

        if (isset($map[$detected_mime])) {
            return $map[$detected_mime];
        }

        if ($header_mime !== '' && isset($map[$header_mime])) {
            return $map[$header_mime];
        }

        return 'mp3';
    }

    private function normalize_content_type($content_type) {
        $value = strtolower(trim((string) $content_type));
        if ($value === '') {
            return '';
        }

        $parts = explode(';', $value);
        return trim($parts[0]);
    }

    private function looks_like_text_payload($detected_mime, $header_mime, $content) {
        $text_mimes = [
            'text/html',
            'text/plain',
            'application/json',
            'application/xml',
            'text/xml',
        ];

        if (in_array($detected_mime, $text_mimes, true) || in_array($header_mime, $text_mimes, true)) {
            return true;
        }

        $sample = ltrim(substr((string) $content, 0, 256));
        return stripos($sample, '<!doctype html') === 0 || stripos($sample, '<html') === 0;
    }

    private function should_retry_transcription_with_mp3($message) {
        $msg = strtolower((string) $message);
        return strpos($msg, 'unsupported file format') !== false
            || strpos($msg, 'corrupted or unsupported') !== false
            || strpos($msg, 'invalid file format') !== false;
    }

    private function convert_audio_to_mp3($source_path) {
        $ffmpeg_bin = trim((string) @shell_exec('command -v ffmpeg'));
        if ($ffmpeg_bin === '') {
            return null;
        }

        $target_path = tempnam(sys_get_temp_dir(), 'binotel_call_mp3_');
        if ($target_path === false) {
            return null;
        }

        $target_mp3 = $target_path . '.mp3';
        @unlink($target_path);

        $command = escapeshellcmd($ffmpeg_bin)
            . ' -y -i ' . escapeshellarg($source_path)
            . ' -vn -ar 16000 -ac 1 -b:a 64k ' . escapeshellarg($target_mp3) . ' 2>&1';

        @exec($command, $output, $code);

        if ($code !== 0 || !file_exists($target_mp3) || filesize($target_mp3) === 0) {
            @unlink($target_mp3);
            return null;
        }

        return [
            'path' => $target_mp3,
            'mime' => 'audio/mpeg',
            'filename' => 'recording.mp3',
        ];
    }

    private function mime_from_extension($extension) {
        $map = [
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'mp4' => 'audio/mp4',
            'mpeg' => 'audio/mpeg',
            'mpga' => 'audio/mpeg',
            'oga' => 'audio/ogg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
        ];

        return $map[$extension] ?? 'audio/mpeg';
    }

    private function json_response($payload, $status_code = 200) {
        $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));

        return;
    }
}
