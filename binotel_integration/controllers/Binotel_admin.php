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

        // Фільтрація дзвінків за датою
        $this->db->order_by('date', 'DESC');
        if (!empty($start_date) && !empty($end_date)) {
            $this->db->where('date >=', $start_date . ' 00:00:00');
            $this->db->where('date <=', $end_date . ' 23:59:59');
        }
        $data['notifications'] = $this->db->get(db_prefix().'binotel_notifications')->result();

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

        // Збираємо унікальні номери телефонів з усіх сповіщень
        $phones = [];
        foreach ($data['notifications'] as $note) {
            $additional = @unserialize($note->additional_data);
            if (is_array($additional) && !empty($additional[0])) {
                $phones[] = trim($additional[0]);
            }
        }
        $phones = array_values(array_unique(array_filter($phones)));

        // Batch-запити: 3 запити замість N×3
        $recording_map = [];
        if (!empty($phones)) {
            $tables_batch = [
                'leads'   => db_prefix().'binotel_call_statistics_leads',
                'clients' => db_prefix().'binotel_call_statistics_clients',
                'staff'   => db_prefix().'binotel_call_statistics_staff',
            ];
            foreach ($tables_batch as $rows) {
                $result = $this->db
                    ->select('contact_name, recording_link')
                    ->where_in('contact_name', $phones)
                    ->where('recording_link !=', '')
                    ->order_by('call_time', 'DESC')
                    ->get($rows)->result();
                foreach ($result as $r) {
                    $p = $r->contact_name;
                    // Перший збіг (найновіший) зберігається; пріоритет: leads > clients > staff
                    if (!isset($recording_map[$p]) && !empty($r->recording_link)) {
                        $recording_map[$p] = $r->recording_link;
                    }
                }
            }
        }

        // Для кожного сповіщення підтягуємо recording_link із map (O(1))
        foreach ($data['notifications'] as &$note) {
            $phone = '';
            $additional = @unserialize($note->additional_data);
            if (is_array($additional) && !empty($additional[0])) {
                $phone = trim($additional[0]);
            }
            $note->recording_link = (!empty($phone) && isset($recording_map[$phone]))
                ? $recording_map[$phone] : '';
        }
        unset($note);

        // Відображаємо повний шаблон
        $this->load->view('binotel_integration/all_notifications', $data);
    }

    /**
     * Метод пошуку recording_link за номером телефону у заданій таблиці
     * @param string $phone
     * @param string $type - 'leads', 'clients', 'staff'
     * @return string|null
     */
    private function find_recording_link_by_phone($phone, $type = 'leads') {
        $tables = [
            'leads'   => db_prefix().'binotel_call_statistics_leads',
            'clients' => db_prefix().'binotel_call_statistics_clients',
            'staff'   => db_prefix().'binotel_call_statistics_staff',
        ];
        if (!isset($tables[$type])) {
            return null;
        }
        $tableName = $tables[$type];
        $this->db->select('recording_link');
        $this->db->where('contact_name', $phone);
        $this->db->order_by('call_time', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get($tableName)->row();
        return ($row && !empty($row->recording_link)) ? $row->recording_link : null;
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

/**
 * Транскрибація запису розмови через OpenAI Whisper API
 * POST параметри: call_id (int), call_type (leads|clients|staff)
 */
public function transcribe_call() {
    if (!$this->input->is_ajax_request()) {
        show_404();
    }

    header('Content-Type: application/json');

    $csrf = [
        'csrf_name' => $this->security->get_csrf_token_name(),
        'csrf_hash' => $this->security->get_csrf_hash(),
    ];

    $call_id   = (int) $this->input->post('call_id');
    $call_type = $this->input->post('call_type');

    $allowed_types = ['leads', 'clients', 'staff'];
    if (!$call_id || !in_array($call_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Невірні параметри'] + $csrf);
        return;
    }

    $openai_api_key = get_option('binotel_openai_api_key');
    if (empty($openai_api_key)) {
        echo json_encode(['success' => false, 'error' => 'OpenAI API ключ не налаштовано. Будь ласка, вкажіть його в налаштуваннях Binotel.'] + $csrf);
        return;
    }

    $tables = [
        'leads'   => db_prefix() . 'binotel_call_statistics_leads',
        'clients' => db_prefix() . 'binotel_call_statistics_clients',
        'staff'   => db_prefix() . 'binotel_call_statistics_staff',
    ];
    $table = $tables[$call_type];

    $row = $this->db->get_where($table, ['id' => $call_id])->row();
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Запис не знайдено'] + $csrf);
        return;
    }

    // Якщо транскрипція вже існує — повертаємо її
    if (!empty($row->transcription)) {
        echo json_encode(['success' => true, 'transcription' => $row->transcription] + $csrf);
        return;
    }

    if (empty($row->recording_link)) {
        echo json_encode(['success' => false, 'error' => 'Запис розмови відсутній'] + $csrf);
        return;
    }

    $api_key    = get_option('binotel_api_key');
    $api_secret = get_option('binotel_secret');

    // Визначаємо generalCallID: з БД → з fileName= параметра URL → кінець URL
    $general_call_id = $row->general_call_id ?? '';
    if (empty($general_call_id) && !empty($row->recording_link)) {
        // Пріоритет: fileName=5124429886.mp3 у URL Binotel-порталу
        if (preg_match('/[?&]fileName=(\d+)\.mp3/i', $row->recording_link, $m)) {
            $general_call_id = $m[1];
        } elseif (preg_match('/[\/=](\d{6,15})\/?(?:&|$)/', $row->recording_link, $m)) {
            $general_call_id = $m[1];
        }
    }
    $lookup_debug = '';
    if (empty($general_call_id) && !empty($api_key) && !empty($api_secret)) {
        $general_call_id = $this->_lookup_general_call_id(
            $row->contact_name ?? '',
            $row->call_time ?? '',
            $api_key,
            $api_secret,
            $lookup_debug
        );
        // Зберігаємо знайдений ID щоб не шукати знову
        if (!empty($general_call_id)) {
            $this->db->where('id', $call_id);
            $this->db->update($table, ['general_call_id' => $general_call_id]);
        }
    }

    $tmp_file   = tempnam(sys_get_temp_dir(), 'binotel_rec_') . '.mp3';
    $audio_data = false;

    // Єдиний спосіб отримати аудіо: файл завантажений з браузера (captureStream або вибір файлу)
    if (!empty($_FILES['audio_blob']['tmp_name']) && $_FILES['audio_blob']['size'] > 0) {
        $audio_data = file_get_contents($_FILES['audio_blob']['tmp_name']);
        if ($this->_is_html($audio_data)) {
            $audio_data = false;
        }
    }

    if ($audio_data === false) {
        echo json_encode(['success' => false, 'error' => 'Аудіофайл не отримано. Натисніть "Обрати файл" і виберіть запис вручну, або зачекайте поки аудіо відтвориться.'] + $csrf);
        return;
    }

    if ($this->_is_html($audio_data)) {
        echo json_encode(['success' => false, 'error' => 'Отримано HTML замість аудіо. (' . $debug . ')'] + $csrf);
        return;
    }

    file_put_contents($tmp_file, $audio_data);

    // Відправляємо на OpenAI Whisper
    $transcription = $this->_transcribe_with_whisper($tmp_file, $openai_api_key);
    @unlink($tmp_file);

    if ($transcription === false) {
        echo json_encode(['success' => false, 'error' => 'Помилка транскрибації. Перевірте OpenAI API ключ та спробуйте ще раз.'] + $csrf);
        return;
    }

    // Зберігаємо транскрипцію в БД
    $this->db->where('id', $call_id);
    $this->db->update($table, ['transcription' => $transcription]);

    echo json_encode(['success' => true, 'transcription' => $transcription] + $csrf);
}

/**
 * Шукає generalCallID через Binotel API get-calls-list.json за номером телефону та часом дзвінка.
 * Використовується як fallback коли webhook не надіслав generalCallID.
 *
 * Binotel повертає `calls` як асоціативний масив: { "generalCallID": { callData }, ... }
 *
 * @param string $phone        Номер телефону (зовнішній)
 * @param string $call_time    Datetime рядок дзвінка (Y-m-d H:i:s)
 * @param string $api_key
 * @param string $api_secret
 * @param string &$debug_info  Діагностична інформація (out)
 * @return string|null  generalCallID або null якщо не знайдено
 */
private function _lookup_general_call_id($phone, $call_time, $api_key, $api_secret, &$debug_info = '') {
    if (empty($phone) || empty($call_time)) {
        $debug_info = 'lookup: phone або call_time порожні';
        return null;
    }

    $ts          = strtotime($call_time);
    $date_from   = date('Y-m-d H:i:s', $ts - 300);
    $date_to     = date('Y-m-d H:i:s', $ts + 300);
    $phone_clean = preg_replace('/\D/', '', $phone);

    $body = json_encode([
        'key'      => $api_key,
        'secret'   => $api_secret,
        'dateFrom' => $date_from,
        'dateTo'   => $date_to,
    ]);

    $ch = curl_init('https://api.binotel.com/api/4.0/calls/get-calls-list.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $http_code !== 200) {
        $debug_info = 'lookup API HTTP ' . $http_code . ($response === false ? ' (curl error)' : '');
        return null;
    }

    $data = @json_decode($response, true);
    if (!is_array($data)) {
        $debug_info = 'lookup: не JSON, відповідь: ' . substr($response, 0, 120);
        return null;
    }

    // Binotel: { "status":"success", "calls": { "generalCallID": { callData }, ... } }
    // Також підтримуємо indexed-масив на випадок іншої версії API
    $calls = $data['calls'] ?? $data['data'] ?? [];
    if (empty($calls)) {
        $api_msg    = $data['message'] ?? $data['error'] ?? $data['description'] ?? '';
        $api_code   = $data['code']    ?? $data['status']                         ?? '';
        $debug_info = 'Binotel API: ' . ($api_code ? "[$api_code] " : '') . ($api_msg ?: 'calls порожній, ключі: ' . implode(',', array_keys($data)));
        return null;
    }

    $debug_info = 'lookup: знайдено ' . count($calls) . ' дзвінків, шукаємо ' . $phone_clean;

    foreach ($calls as $gid => $call) {
        if (!is_array($call)) {
            continue;
        }
        $ext = preg_replace('/\D/', '', $call['externalNumber'] ?? $call['phone'] ?? '');
        if (empty($ext)) {
            continue;
        }
        // Порівнюємо з урахуванням кодів країни (38050... vs 050...)
        if ($ext === $phone_clean
            || str_ends_with($ext, $phone_clean)
            || str_ends_with($phone_clean, $ext)
        ) {
            // Ключ масиву — і є generalCallID; також перевіряємо поле всередині
            $found_gid = $call['generalCallID'] ?? (is_string($gid) || is_int($gid) ? (string)$gid : null);
            if (!empty($found_gid)) {
                $debug_info = 'lookup: знайдено ID=' . $found_gid . ' для ' . $ext;
                return (string) $found_gid;
            }
        }
    }

    // Перший дзвінок у вікні — якщо номер не збігся, але дзвінок єдиний
    if (count($calls) === 1) {
        $gid = array_key_first($calls);
        $debug_info = 'lookup: єдиний дзвінок у вікні, використовуємо ID=' . $gid;
        return (string) $gid;
    }

    $debug_info = 'lookup: збіг за телефоном не знайдено серед ' . count($calls) . ' дзвінків';
    return null;
}

/**
 * Перевіряє чи є вміст HTML-документом
 */
private function _is_html($data) {
    if (empty($data)) return false;
    $start = strtolower(substr(ltrim($data), 0, 15));
    return strpos($start, '<html') !== false || strpos($start, '<!doc') !== false;
}

/**
 * Завантажує аудіозапис через Binotel API /calls/get-record.json
 * @param string  $general_call_id
 * @param string  $api_key
 * @param string  $api_secret
 * @param string &$api_debug  Діагностика: HTTP-код та скорочена відповідь (out)
 * @return string|false
 */
private function _download_via_binotel_api($general_call_id, $api_key, $api_secret, &$api_debug = '') {
    $url  = 'https://api.binotel.com/api/4.0/calls/get-record.json';
    $body = json_encode([
        'key'           => $api_key,
        'secret'        => $api_secret,
        'generalCallID' => $general_call_id,
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $data      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $api_debug = 'API_HTTP=' . $http_code . ' resp=' . substr(preg_replace('/\s+/', ' ', $data), 0, 120);

    if ($data === false || $http_code !== 200) {
        return false;
    }

    // API може повернути JSON з посиланням на файл або сам файл
    $decoded = @json_decode($data, true);
    if (is_array($decoded)) {
        // Шукаємо URL запису у відомих полях відповіді Binotel
        $record_url = $decoded['recordUrl']
            ?? $decoded['url']
            ?? $decoded['link']
            ?? $decoded['fileUrl']
            ?? $decoded['record']
            ?? $decoded['callRecord']
            ?? $decoded['recordLink']
            ?? $decoded['downloadUrl']
            ?? null;
        if ($record_url) {
            return $this->_download_file($record_url);
        }
        return false;
    }

    // Якщо відповідь — бінарні дані аудіо (перевіряємо що не HTML)
    if ($this->_is_html($data)) {
        return false;
    }

    return $data;
}

/**
 * Авторизується на my.binotel.ua та повертає рядок куки для подальших запитів.
 * Binotel API не дає доступу до аудіофайлів, тому потрібна портальна сесія.
 *
 * @param string  $email
 * @param string  $password
 * @param string &$debug
 * @return string|false  рядок виду "PHPSESSID=xxx; ..."
 */
private function _get_portal_cookie($email, $password, &$debug = '') {
    // Кешуємо куку у tmp файлі щоб не логінитись при кожному запиті
    $cache_file = sys_get_temp_dir() . '/binotel_portal_cookie_' . md5($email) . '.txt';
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
        $cached = file_get_contents($cache_file);
        if ($cached) {
            $debug = 'portal=cached_cookie';
            return $cached;
        }
    }

    // Крок 1: GET головної сторінки — отримуємо початкові куки і форму
    $ch = curl_init('https://my.binotel.ua/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER,         true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $resp1 = curl_exec($ch);
    curl_close($ch);

    // Збираємо Set-Cookie з першої відповіді
    $init_cookies = [];
    preg_match_all('/^Set-Cookie:\s*([^;\r\n]+)/mi', $resp1, $m);
    foreach ($m[1] as $c) { $init_cookies[] = trim($c); }

    // Крок 2: POST форми логіну
    $post_data = http_build_query([
        'email'    => $email,
        'password' => $password,
    ]);

    $ch = curl_init('https://my.binotel.ua/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER,         true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $post_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: https://my.binotel.ua/',
    ]);
    if ($init_cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $init_cookies));
    }
    $resp2     = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Збираємо всі куки з відповіді (об'єднуємо початкові + нові)
    preg_match_all('/^Set-Cookie:\s*([^;\r\n]+)/mi', $resp2, $m2);
    $all_cookies = $init_cookies;
    foreach ($m2[1] as $c) {
        $name = explode('=', trim($c), 2)[0];
        // Перезаписуємо куку з тим же ім'ям
        $all_cookies = array_filter($all_cookies, fn($x) => strpos($x, $name . '=') === false);
        $all_cookies[] = trim($c);
    }
    $cookie_str = implode('; ', $all_cookies);

    $debug = 'portal_login=HTTP' . $http_code;

    // Редирект після логіну (302) або вже на головній (200) — обидва означають успіх
    if (($http_code === 302 || $http_code === 200) && $cookie_str) {
        file_put_contents($cache_file, $cookie_str);
        return $cookie_str;
    }

    $debug .= ' FAIL';
    return false;
}

/**
 * Завантажує файл за URL використовуючи сесійну куку (портальна авторизація)
 * @param string $url
 * @param string $cookie  рядок куки виду "name=val; name2=val2"
 * @return string|false
 */
private function _download_file_with_cookie($url, $cookie) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_COOKIE,         $cookie);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Referer: https://my.binotel.ua/']);
    $data      = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $http_code !== 200) {
        return false;
    }
    return $data;
}

/**
 * Завантажує файл за URL та повертає його вміст
 * @param string $url
 * @return string|false
 */
private function _download_file($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($data === false || $http_code !== 200) {
        return false;
    }
    return $data;
}

/**
 * Транскрибує аудіофайл через OpenAI Whisper API
 * @param string $file_path - шлях до тимчасового аудіофайлу
 * @param string $api_key   - OpenAI API ключ
 * @return string|false     - текст транскрипції або false при помилці
 */
private function _transcribe_with_whisper($file_path, $api_key) {
    $post_fields = [
        'file'     => new CURLFile($file_path, 'audio/mpeg', 'recording.mp3'),
        'model'    => 'whisper-1',
        'language' => 'uk',
    ];

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $http_code !== 200) {
        return false;
    }

    $result = json_decode($response, true);
    if (!isset($result['text'])) {
        return false;
    }
    return $result['text'];
}


}
