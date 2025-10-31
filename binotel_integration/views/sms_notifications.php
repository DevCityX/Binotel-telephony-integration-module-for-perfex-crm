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

        <!-- Таблиця SMS повідомлень -->
        <div class="panel_s">
          <div class="panel-body">
            <?php if (!empty($sms_messages)) : ?>
              <table class="table dt-table">
                <thead>
                  <tr>
                    <th>Дата</th>
                    <th>Номер телефону</th>
                    <th>SMS повідомлення</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sms_messages as $sms) : ?>
                    <tr>
                      <!-- Припустимо, що startTime вже відформатовано або використовується _dt() -->
                      <td><?php echo _dt($sms['startTime']); ?></td>
                      <td><?php echo $sms['externalNumber']; ?></td>
                      <td><?php echo $sms['smsContent']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p>Записів SMS немає.</p>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php init_tail(); ?>

<!-- Підключення бібліотек для datepicker -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>

<script>
$(document).ready(function(){
    // Ініціалізація datepicker
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true
    });
    
    // Фільтр за датою – редірект із GET-параметрами
    $('#filter_btn').click(function(){
        var start_date = $('#start_date').val();
        var end_date = $('#end_date').val();
        if(start_date && end_date){
            window.location.href = "?start_date=" + start_date + "&end_date=" + end_date;
        } else {
            alert("Оберіть обидві дати!");
        }
    });
    
    // Кнопка "Очистити" – скидання фільтра
    $('#clear_btn').click(function(){
        window.location.href = "<?php echo admin_url('binotel_integration/sms_notifications'); ?>";
    });
});
</script>
