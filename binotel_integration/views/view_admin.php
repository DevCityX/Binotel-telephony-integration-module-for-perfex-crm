<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">Binotel Integration</h4>
                        <p>Це модуль інтеграції телефонії Binotel з вашою Perfex CRM.</p>
                        <p>Для налаштування модуля перейдіть до 
                            <a href="<?php echo admin_url('settings?group=binotel_integration_settings'); ?>">налаштувань</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>