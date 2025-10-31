<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <h4><?php echo html_escape($title); ?></h4>

        <!-- Блок з вибором дат -->
        <div class="row">
          <div class="col-md-4">
            <label>Дата від:</label>
            <input type="text" id="start_date" class="form-control datepicker" autocomplete="off" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
          </div>
          <div class="col-md-4">
            <label>Дата до:</label>
            <input type="text" id="end_date" class="form-control datepicker" autocomplete="off" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
          </div>
          <div class="col-md-4">
            <br>
            <button id="filter_btn" class="btn btn-primary">Фільтрувати</button>
            <button id="clear_btn" class="btn btn-default" style="margin-left:10px;">Очистити</button>
          </div>
        </div>

        <br>

        <!-- Віджет з кількістю нових лідів -->
        <div class="alert alert-info">
          <?php echo _l('Кількість нових лідів за період: ') . (!empty($new_leads_count) ? $new_leads_count : 0); ?>
        </div>

        <!-- Графіки (якщо є дані) -->
        <?php if (!empty($calls_per_lead) || !empty($calls_per_client)): ?>
          <div class="row">
            <div class="col-md-6">
              <canvas id="callsPerLeadChart" style="height: 400px;"></canvas>
            </div>
            <div class="col-md-6">
              <canvas id="callsPerClientChart" style="height: 400px;"></canvas>
            </div>
          </div>
        <?php endif; ?>

        <br>

        <!-- Таблиця дзвінків -->
        <div class="panel_s">
          <div class="panel-body">
            <?php if (!empty($notifications)): ?>
              <table class="table dt-table">
                <thead>
                  <tr>
                    <th>Дата</th>
                    <th>Опис</th>
                    <th>Статус</th>
                    <th>Дії</th>
                    <th>Розмови</th>
                  </tr>
                </thead>
                <tbody>
                   <?php foreach ($notifications as $note): 
                    // Формуємо посилання для рядка (якщо воно задане в $note->link)
                    $rowLink = admin_url($note->link);
                    ?>
                    <tr class="clickable-row" data-href="<?php echo $rowLink; ?>">
                      <td><?php echo _dt($note->date); ?></td>
                      <td><?php echo $note->description; ?></td>
                      <td><?php echo ($note->isread == 1) ? 'Прочитано' : 'Не прочитано'; ?></td>
                      <td>
                        <?php 
                          $additional = @unserialize($note->additional_data);
                          $phone = (is_array($additional) && !empty($additional[0])) ? trim($additional[0]) : '';
                          if (!empty($phone)):
                        ?>
                          <button class="btn btn-xs btn-success" onclick="makeBinotelCall('<?php echo $phone; ?>'); return false;" title="Подзвонити">
                            <i class="fa fa-phone"></i>
                          </button>
                        <?php else: ?>
                          Нема телефону
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($note->recording_link)): ?>
                          <button class="btn btn-xs btn-primary" onclick="openRecordingModal('<?php echo $note->recording_link; ?>'); return false;" title="Прослухати">
                            <i class="fa fa-play"></i>
                          </button>
                        <?php else: ?>
                          Нема запису
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p>Записів немає.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Кнопка "Подивитися всі дзвінки" -->
        <div class="tw-text-center tw-p-4 tw-bg-neutral-50">
          <a class="btn btn-default" href="<?php echo admin_url('binotel_integration/binotel_admin/binotel_notifications'); ?>">
            Подивитися всі дзвінки
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php init_tail(); ?>

<!-- Підключення бібліотек -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">

<!-- Модальне вікно для аудіопрогравача -->
<div class="modal fade" id="recordingModal" tabindex="-1" role="dialog" aria-labelledby="recordingModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="recordingModalLabel">Запис розмови</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Закрити">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <audio id="recordingAudio" controls style="width: 100%;">
          <source src="" type="audio/mpeg">
          Ваш браузер не підтримує аудіо.
        </audio>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  // Ініціалізація datepicker
  $('.datepicker').datetimepicker({
            format: 'Y-m-d',
            timepicker: false,
            scrollMonth: false,
            scrollInput: false,
        });

  // Фільтр: при натисканні кнопки "Фільтрувати" редірект
  $('#filter_btn').click(function(){
    var start_date = $('#start_date').val();
    var end_date = $('#end_date').val();
    if(start_date && end_date){
      window.location.href = "?start_date=" + start_date + "&end_date=" + end_date;
    } else {
      alert("Оберіть обидві дати!");
    }
  });

  // Кнопка "Очистити"
  $('#clear_btn').click(function(){
    window.location.href = "<?php echo admin_url('binotel_integration/binotel_admin/binotel_notifications'); ?>";
  });

  // Побудова графіка "Кількість дзвінків від кожного ліда"
  <?php if (!empty($calls_per_lead)): ?>
    var callsPerLeadLabels = [<?php foreach ($calls_per_lead as $lead){ echo '"' . addslashes(strip_tags($lead->description)) . '",'; } ?>];
    var callsPerLeadData = [<?php foreach ($calls_per_lead as $lead){ echo (int)$lead->count . ','; } ?>];
    var ctx1 = document.getElementById('callsPerLeadChart').getContext('2d');
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: callsPerLeadLabels,
        datasets: [{
          label: "Кількість дзвінків",
          data: callsPerLeadData,
          backgroundColor: "rgba(54, 162, 235, 0.5)",
          borderColor: "rgba(54, 162, 235, 1)",
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { ticks: { maxRotation: 45, minRotation: 45 } },
          y: { beginAtZero: true, ticks: { stepSize: 1 } }
        },
        onClick: function (event, elements) {
          if(elements.length > 0){
              var index = elements[0].index;
              var selectedLead = callsPerLeadLabels[index];
              var url = "<?php echo admin_url('leads/view/'); ?>" + encodeURIComponent(selectedLead);
              window.open(url, "_blank");
          }
        }
      }
    });
  <?php endif; ?>

  // Побудова графіка "Кількість дзвінків від кожного клієнта"
  <?php if (!empty($calls_per_client)): ?>
    var callsPerClientLabels = [<?php foreach ($calls_per_client as $client){ echo '"' . addslashes(strip_tags($client->description)) . '",'; } ?>];
    var callsPerClientData = [<?php foreach ($calls_per_client as $client){ echo (int)$client->count . ','; } ?>];
    var ctx2 = document.getElementById('callsPerClientChart').getContext('2d');
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: callsPerClientLabels,
        datasets: [{
          label: "Кількість дзвінків від клієнтів",
          data: callsPerClientData,
          backgroundColor: "rgba(255, 99, 132, 0.5)",
          borderColor: "rgba(255, 99, 132, 1)",
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { ticks: { maxRotation: 45, minRotation: 45 } },
          y: { beginAtZero: true, ticks: { stepSize: 1 } }
        },
        onClick: function (event, elements) {
          if(elements.length > 0){
              var index = elements[0].index;
              var selectedClient = callsPerClientLabels[index];
              var url = "<?php echo admin_url('clients/view/'); ?>" + encodeURIComponent(selectedClient);
              window.open(url, "_blank");
          }
        }
      }
    });
  <?php endif; ?>
});

// Функція виклику через Binotel
function makeBinotelCall(phone) {
  $.post("<?php echo admin_url('binotel_integration/make_call'); ?>", { phone: phone }, function(response) {
    if(response.status === 'success'){
      alert('Виклик здійснено для номера: ' + phone);
    } else {
      alert('Помилка виклику: ' + response.message);
    }
  }, 'json').fail(function(xhr, status, error){
    console.error("Error making call:", error);
  });
}

// Функція відкриття модального вікна з програвачем
function openRecordingModal(url) {
  $('#recordingAudio source').attr('src', url);
  $('#recordingAudio')[0].load();
  $('#recordingModal').modal('show');
}
</script>
