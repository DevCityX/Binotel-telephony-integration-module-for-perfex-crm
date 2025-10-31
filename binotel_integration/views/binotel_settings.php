<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="form-group">
    <label for="binotel_api_key"><?php echo _l('Ключ API (API Key)'); ?></label>
    <input type="text" id="binotel_api_key" name="settings[binotel_api_key]" class="form-control"
           value="<?php echo get_option('binotel_api_key'); ?>">
</div>
<div class="form-group">
    <label for="binotel_secret"><?php echo _l('Секретний ключ (Secret)'); ?></label>
    <input type="text" id="binotel_secret" name="settings[binotel_secret]" class="form-control"
           value="<?php echo get_option('binotel_secret'); ?>">
</div>
<div class="form-group">
    <label for="binotel_internal_number"><?php echo _l('Внутрішній номер (Internal Number)'); ?></label>
    <input type="text" id="binotel_internal_number" name="settings[binotel_internal_number]" class="form-control"
           value="<?php echo get_option('binotel_internal_number'); ?>">
</div>

<div class="alert alert-info">
    <strong><?php echo _l('Інструкція з налаштування вебхука'); ?>:</strong>
    <p>
        <?php echo _l('Ось посилання на вебхук для приймання вхідних та вихідних дзвінків з Binotel та запису їх в CRM'); ?>: 
        <strong id="webhook-url">
            <a href="<?php echo admin_url('binotel_integration/receive_call'); ?>" target="_blank">
                <?php echo admin_url('binotel_integration/receive_call'); ?>
            </a>
        </strong>
        <button class="btn btn-light btn-sm" onclick="copyWebhookURL()" title="<?php echo _l('Скопіювати посилання'); ?>">
            <i class="fa fa-copy"></i>
        </button>
    </p>
    <p>
        <?php echo _l('Скопіюйте його та зареєструйте в Binotel. Для цього зв\'яжіться з їхньою службою підтримки Binotel Support.'); ?>
    </p>
</div>

<script>
    function copyWebhookURL() {
        const webhookURL = '<?php echo admin_url('binotel_integration/receive_call'); ?>';
        navigator.clipboard.writeText(webhookURL).then(function () {
            alert('<?php echo _l('Посилання скопійовано до буфера обміну!'); ?>');
        }, function (err) {
            alert('<?php echo _l('Не вдалося скопіювати посилання. Спробуйте ще раз.'); ?>');
        });
    }
</script>



