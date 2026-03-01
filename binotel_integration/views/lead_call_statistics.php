<?php
defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$CI->load->model('binotel_integration/Binotel_integration_model');

$lead_id = $lead->id;

// Отримання всіх дзвінків за замовчуванням
$call_statistics = $CI->Binotel_integration_model->get_lead_call_statistics($lead_id);

// Підключення CSS-файлу
echo '<link rel="stylesheet" type="text/css" href="' . base_url('modules/binotel_integration/assets/css/call_statistics.css') . '">';

// Отримання номера телефону ліда
$lead_phone = $lead->phonenumber;

// Розбити номери на масив, використовуючи регулярний вираз
$phone_numbers = preg_split('/[\s,\.]+/', $lead_phone);
?>

<h4 class="lead-profile-group-heading">Статистика розмов</h4>
<div class="panel_s">
    <div class="panel-body">
        <form id="filter-form" method="post" action="#">
            <input type="hidden" name="lead_id" value="<?php echo $lead_id; ?>">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="start_date">Початкова дата</label>
                        <input type="text" class="form-control datepicker" name="start_date" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="end_date">Кінцева дата</label>
                        <input type="text" class="form-control datepicker" name="end_date" autocomplete="off">
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary" onclick="filterCalls()">Фільтрувати</button>
        </form>
        <br>

        <!-- Перевірка на кількість номерів -->
        <div class="call-section">
            <?php if (count($phone_numbers) > 1): ?>
                <label for="phone-number-select">Виберіть номер для дзвінка:</label>
                <select id="phone-number-select" class="form-control">
                    <?php foreach ($phone_numbers as $number): ?>
                        <option value="<?php echo trim($number); ?>"><?php echo trim($number); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <br>
            <button id="call-button" class="btn btn-success" onclick="makeCall()">
                <i class="fas fa-phone"></i> Здійснити дзвінок
            </button>
        </div>

        <br>
        <div id="call-statistics">
            <?php if (!empty($call_statistics)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Тип дзвінка</th>
                                <th>Час дзвінка</th>
                                <th>Запис дзвінка</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($call_statistics as $call): ?>
                                <tr class="<?php echo $call['call_type'] == '1' ? ($call['recording_link'] ? 'outgoing-call' : 'missed-outgoing-call') : ($call['recording_link'] ? 'incoming-call' : 'missed-incoming-call'); ?>">
                                    <td>
                                        <div>
                                            <?php if ($call['call_type'] == '1'): ?>
                                                <i class="fas fa-long-arrow-alt-up" style="color: <?php echo $call['recording_link'] ? 'blue' : 'red'; ?>;"></i>
                                                <div class="arrow-text-separator"></div>
                                                <?php echo $call['recording_link'] ? 'Вихідний' : 'Вихідний неприйнятий'; ?>
                                            <?php else: ?>
                                                <i class="fas fa-long-arrow-alt-down" style="color: <?php echo $call['recording_link'] ? 'green' : 'red'; ?>;"></i>
                                                <div class="arrow-text-separator"></div>
                                                <?php echo $call['recording_link'] ? 'Вхідний' : 'Вхідний неприйнятий'; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $call['call_time']; ?></td>
                                    <td>
                                        <?php if ($call['recording_link']): ?>
                                            <audio controls style="margin: 0; padding: 0;">
                                                <source src="<?php echo $call['recording_link']; ?>" type="audio/mpeg">
                                                Ваш браузер не підтримує елемент <code>audio</code>.
                                            </audio>
                                        <?php else: ?>
                                            Немає запису
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Немає записів розмов для цього ліда.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    $(function() {
        $('.datepicker').datetimepicker({
            format: 'Y-m-d',
            timepicker: false,
            scrollMonth: false,
            scrollInput: false,
        });
    });

    function filterCalls() {
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

        var data = $('#filter-form').serializeArray();
        data.push({ name: csrfName, value: csrfHash });

        $.ajax({
            url: '<?php echo admin_url('binotel_integration/get_filtered_calls_for_lead'); ?>',
            type: 'POST',
            data: $.param(data),
            success: function(response) {
                $('#call-statistics').html(response);
            },
            error: function() {
                alert('Помилка при отриманні даних.');
            }
        });
    }

    function makeCall() {
        var selectedNumber;
        if (document.getElementById('phone-number-select')) {
            selectedNumber = document.getElementById('phone-number-select').value;
        } else {
            selectedNumber = '<?php echo $lead_phone; ?>';
        }

        if (selectedNumber) {
            var callButton = $('#call-button');
            var originalText = callButton.html();
            callButton.html('<i class="fas fa-spinner fa-spin"></i> Дзвонимо...');
            callButton.prop('disabled', true);

            $.post(admin_url + 'binotel_integration/make_call', { phone: selectedNumber }, function(response) {
                if (response.status === 'success') {
                    callButton.html('<i class="fas fa-check"></i> Додзвонились');
                    setTimeout(function() {
                        callButton.html(originalText);
                        callButton.prop('disabled', false);
                    }, 3000);
                } else {
                    callButton.html(originalText);
                    callButton.prop('disabled', false);
                    alert('Помилка: ' + response.message);
                }
            }, 'json');
        } else {
            alert('Номер телефону не знайдено.');
        }
    }
</script>
