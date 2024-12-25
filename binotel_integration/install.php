<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Перевіряємо чи існує таблиця для зберігання даних для клієнтів
if (!$CI->db->table_exists(db_prefix() . 'binotel_call_statistics_clients')) {
    $CI->db->query('CREATE TABLE ' . db_prefix() . "binotel_call_statistics_clients (
      id int(11) NOT NULL AUTO_INCREMENT,
      client_id int(11) NOT NULL,
      call_type varchar(50) NOT NULL,
      call_time datetime NOT NULL,
      recording_link varchar(255) DEFAULT NULL,
      contact_name varchar(255) DEFAULT NULL,
      waiting_time time DEFAULT NULL,
      call_duration time DEFAULT NULL,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Перевіряємо чи існує таблиця для зберігання даних для лідів
if (!$CI->db->table_exists(db_prefix() . 'binotel_call_statistics_leads')) {
    $CI->db->query('CREATE TABLE ' . db_prefix() . "binotel_call_statistics_leads (
      id int(11) NOT NULL AUTO_INCREMENT,
      lead_id int(11) NOT NULL,
      call_type varchar(50) NOT NULL,
      call_time datetime NOT NULL,
      recording_link varchar(255) DEFAULT NULL,
      contact_name varchar(255) DEFAULT NULL,
      waiting_time time DEFAULT NULL,
      call_duration time DEFAULT NULL,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}
