<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name:   <img src="https://perfex.vikna-city.com.ua/modules/binotel_integration/binotel.png" >   Binotel Integration   
Description: Integration with Binotel telephony for Perfex CRM   <i class="fa fa-phone"></i> .
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
        'href'     => admin_url('binotel_integration/binotel_admin'),
        'position' => 45,
        'icon'     => 'fa fa-phone',
    ]);
}

hooks()->add_action('admin_init', 'binotel_integration_hook_admin_init');

/**
 * Додавання пункту в налаштування
 */
function binotel_integration_hook_admin_init() {
    $CI = &get_instance();

    if (is_admin() || has_permission('settings', '', 'view')) {
        $perfex_version = (int)$CI->app->get_current_db_version();
        $slug = 'binotel_integration_settings';
        $tab = [
            'name'     => 'Binotel Integration',
            'view'     => 'binotel_integration/binotel_settings',
            'position' => 40, // Позиція у вкладках
            'icon'     => 'fa fa-phone', // Іконка для вкладки
        ];

        // Додавання вкладки до налаштувань
        if ($perfex_version >= 320) {
            $CI->app->add_settings_section_child('integrations', $slug, $tab);
        } else {
            $CI->app_tabs->add_settings_tab($slug, $tab);
        }
    }
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



// Для Лідів
hooks()->add_action('after_lead_lead_tabs', 'binotel_integration_add_call_statistics_tab_first_lead');

function binotel_integration_add_call_statistics_tab_first_lead($lead)
{
    if (!isset($lead->id)) {
        return; // Якщо об'єкт $lead не ініціалізований, виходимо з функції
    }

    // Додаємо вкладку "Статистика розмов" на початок
    echo '<li role="presentation">
        <a href="#tab_call_statistics" aria-controls="tab_call_statistics" role="tab" data-toggle="tab"> 
           <i class="fa-solid fa-mobile-retro"></i> '.  _l('call_statistics') . '
        </a>
    </li>';
}

hooks()->add_action('after_lead_tabs_content', 'binotel_integration_add_call_statistics_tab_content_lead');

function binotel_integration_add_call_statistics_tab_content_lead($lead)
{
    if (!isset($lead->id)) {
        return; // Якщо об'єкт $lead не ініціалізований, виходимо з функції
    }
    
    echo '<div role="tabpanel" class="tab-pane" id="tab_call_statistics">';
    $CI = &get_instance();
    $CI->load->view('binotel_integration/lead_call_statistics', ['lead' => $lead]);
    echo '</div>';
}

  // Додавання вкладки статистика ромов для співробітників
hooks()->add_action('app_admin_head', 'binotel_integration_add_staff_tab_js');

function binotel_integration_add_staff_tab_js() {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var tabsList = document.querySelector(".nav-tabs");

            if (tabsList) {
                // Отримання staff_id з прихованого поля або DOM
                var staffIdElement = document.querySelector(\'input[name="memberid"]\');
                if (!staffIdElement) {
                    console.error("ID співробітника не знайдено.");
                    return;
                }
                var staffId = staffIdElement.value;

                // Додати нову вкладку
                var newTab = document.createElement("li");
                newTab.innerHTML = `<a href="#tab_call_statistics" data-toggle="tab"><i class="fa fa-phone"></i> Статистика розмов</a>`;
                tabsList.appendChild(newTab);

                // Додати контейнер для вкладки
                var tabContent = document.querySelector(".tab-content");
                if (tabContent) {
                    var newTabContent = document.createElement("div");
                    newTabContent.className = "tab-pane";
                    newTabContent.id = "tab_call_statistics";
                    newTabContent.innerHTML = `<div id="call-statistics-content">Завантаження...</div>`;
                    tabContent.appendChild(newTabContent);

                    // Завантажити вміст через AJAX при переході на вкладку
                    document.querySelector("a[href=\'#tab_call_statistics\']").addEventListener("click", function () {
                        if (!newTabContent.dataset.loaded) {
                            fetch("' . admin_url('binotel_integration/load_staff_call_statistics') . '?staff_id=" + staffId)
                                .then(response => response.text())
                                .then(html => {
                                    document.getElementById("call-statistics-content").innerHTML = html;
                                    newTabContent.dataset.loaded = "true";
                                })
                                .catch(error => {
                                    console.error("Помилка завантаження статистики розмов:", error);
                                    document.getElementById("call-statistics-content").innerHTML = "Помилка завантаження вмісту.";
                                });
                        }
                    });
                }
            }
        });
    </script>';
}

hooks()->add_action('app_admin_head', 'binotel_integration_insert_floating_icon_view');
function binotel_integration_insert_floating_icon_view(){
    $CI = &get_instance();
    $CI->load->view('binotel_integration/floating_icon');
}

