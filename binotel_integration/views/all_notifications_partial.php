<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
  <div class="col-md-12">
    <h4><?php echo html_escape($title); ?></h4>

    <!-- Форма фільтрації -->
    <div class="row">
      <div class="col-md-4">
        <label>Дата від:</label>
        <input type="text" id="start_date" class="form-control datepicker" autocomplete="off">
      </div>
      <div class="col-md-4">
        <label>Дата до:</label>
        <input type="text" id="end_date" class="form-control datepicker" autocomplete="off">
      </div>
      <div class="col-md-4">
        <br>
        <button id="filter_btn" class="btn btn-primary">Фільтрувати</button>
      </div>
    </div>

    <br>

    <!-- Віджет з кількістю нових лідів -->
    <div class="alert alert-info">
      <?php echo _l('Кількість нових лідів за період: ') . (!empty($new_leads_count) ? $new_leads_count : 0); ?>
    </div>

    <!-- Графіки (відображаються, якщо є дані) -->
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

    <!-- Таблиця сповіщень -->
    <div class="panel_s">
      <div class="panel-body">
        <?php if (!empty($notifications)) : ?>
          <table class="table dt-table">
            <thead>
              <tr>
                <th>Дата</th>
                <th>Опис</th>
                <th>Статус</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($notifications as $note): ?>
                <tr>
                  <td><?php echo _dt($note->date); ?></td>
                  <td><?php echo $note->description; ?></td>
                  <td><?php echo ($note->isread == 1) ? 'Прочитано' : 'Не прочитано'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>Записів немає.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>


<!-- Підключаємо бібліотеки для графіків та datepicker -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">

<script>
$(document).ready(function () {
  // Ініціалізація datepicker
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true
  });

  // Обробник кнопки "Фільтрувати" через AJAX
  $('#filter_btn').click(function () {
    var start_date = $('#start_date').val();
    var end_date = $('#end_date').val();
    if (start_date && end_date) {
      $.ajax({
        url: '<?php echo admin_url('binotel_integration/binotel_admin/binotel_notifications'); ?>',
        type: 'GET',
        data: { start_date: start_date, end_date: end_date },
        success: function(response){
          $('#ajax_notifications_content').html(response);
        },
        error: function() {
          alert("Помилка при отриманні даних.");
        }
      });
    } else {
      alert("Оберіть обидві дати!");
    }
  });

  // Побудова графіка "Кількість дзвінків від кожного ліда"
  <?php if (!empty($calls_per_lead)): ?>
    var callsPerLeadLabels = [<?php foreach ($calls_per_lead as $lead) { echo '"' . addslashes(strip_tags($lead->description)) . '",'; } ?>];
    var callsPerLeadData = [<?php foreach ($calls_per_lead as $lead) { echo $lead->count . ','; } ?>];
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
          x: {
            ticks: { maxRotation: 45, minRotation: 45 }
          },
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 }
          }
        },
        onClick: function (event, elements) {
          if (elements.length > 0) {
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
    var callsPerClientLabels = [<?php foreach ($calls_per_client as $client) { echo '"' . addslashes(strip_tags($client->description)) . '",'; } ?>];
    var callsPerClientData = [<?php foreach ($calls_per_client as $client) { echo $client->count . ','; } ?>];
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
          x: {
            ticks: { maxRotation: 45, minRotation: 45 }
          },
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 }
          }
        },
        onClick: function (event, elements) {
          if (elements.length > 0) {
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
</script>
