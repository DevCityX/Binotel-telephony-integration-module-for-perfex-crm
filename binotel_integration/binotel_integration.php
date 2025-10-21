<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Binotel Integration
Description: Integration with Binotel telephony for Perfex CRM.
Version: 1.0.0
Requires at least: 2.3.*
*/
define('BINOTEL_INTEGRATION_MODULE_NAME', 'binotel_integration');

hooks()->add_action('admin_init', 'binotel_integration_init_menu_items');
register_activation_hook('binotel_integration', 'binotel_integration_activation');
register_deactivation_hook('binotel_integration', 'binotel_integration_deactivation');
register_uninstall_hook('binotel_integration', 'binotel_integration_uninstall');

function binotel_integration_activation() {
   $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

function binotel_integration_deactivation() {
    // Код для деактивації модуля
}

function binotel_integration_uninstall() {
    // Код для видалення модуля
    $CI = &get_instance();
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'binotel_call_statistics_clients`');
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'binotel_call_statistics_leads`');
}

register_language_files(BINOTEL_INTEGRATION_MODULE_NAME, [BINOTEL_INTEGRATION_MODULE_NAME]);

function binotel_integration_init_menu_items() {
    $CI = &get_instance();

    $CI->app_menu->add_sidebar_menu_item('binotel-menu-item', [
        'name'     => 'Binotel Integration',
        'href'     => admin_url('binotel_integration/view'),
        'position' => 45,
        'icon'     => 'fa fa-phone',
    ]);
}

hooks()->add_action('admin_init', 'binotel_integration_add_customers_menu_items');

function binotel_integration_add_customers_menu_items() {
    $CI = &get_instance();

    if (has_permission('customers', '', 'view')) {
        $CI->app_tabs->add_customer_profile_tab('call_statistics', [
            'name'     => 'Статистика розмов',
            'icon'     => 'fa fa-phone',
            'view'     => 'binotel_integration/call_statistics',
            'position' => 20,
        ]);
    }
}

hooks()->add_action('after_lead_lead_tabs', 'binotel_integration_add_call_statistics_tab_first');

function binotel_integration_add_call_statistics_tab_first($lead)
{
    $CI = &get_instance();

    if (!is_object($lead) || empty($lead->id) || $CI->input->is_ajax_request()) {
        return; // Якщо об'єкт $lead не ініціалізований або це AJAX-запит, нічого не виводимо
    }

    // Додаємо вкладку "Статистика розмов" на початок
    echo '<li role="presentation">
        <a href="#tab_call_statistics" aria-controls="tab_call_statistics" role="tab" data-toggle="tab">
           <i class="fa-solid fa-mobile-retro"></i> '.  _l('call_statistics') . '
        </a>
    </li>';
}


hooks()->add_action('after_lead_tabs_content', 'binotel_integration_add_call_statistics_tab_content');

function binotel_integration_add_call_statistics_tab_content($lead)
{
    $CI = &get_instance();

    if (!is_object($lead) || empty($lead->id) || $CI->input->is_ajax_request()) {
        return; // Якщо об'єкт $lead не ініціалізований або це AJAX-запит, виходимо з функції
    }

    echo '<div role="tabpanel" class="tab-pane" id="tab_call_statistics">';
    $CI->load->view('binotel_integration/lead_call_statistics', ['lead' => $lead]);
    echo '</div>';
}
