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

// Перевіряємо чи існує таблиця для зберігання даних для співробітників
if (!$CI->db->table_exists(db_prefix() . 'binotel_call_statistics_staff')) {
    $CI->db->query('CREATE TABLE ' . db_prefix() . "binotel_call_statistics_staff (
      id int(11) NOT NULL AUTO_INCREMENT,
      staff_id int(11) NOT NULL,
      call_type varchar(50) NOT NULL,
      call_time datetime NOT NULL,
      recording_link varchar(255) DEFAULT NULL,
      contact_name varchar(255) DEFAULT NULL,
      waiting_time time DEFAULT NULL,
      call_duration time DEFAULT NULL,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Новий: Таблиця для сповіщень дзвінків Binotel
if (!$CI->db->table_exists(db_prefix() . 'binotel_notifications')) {
    $CI->db->query('CREATE TABLE ' . db_prefix() . "binotel_notifications (
      id int(11) NOT NULL AUTO_INCREMENT,
      isread tinyint(1) NOT NULL DEFAULT 0,
      isread_inline tinyint(1) NOT NULL DEFAULT 0,
      date datetime NOT NULL,
      fromclientid int(11) DEFAULT NULL,
      description text NOT NULL,
      fromuserid int(11) DEFAULT NULL,
      from_fullname varchar(255) DEFAULT NULL,
      touserid int(11) NOT NULL,
      fromcompany tinyint(1) NOT NULL DEFAULT 0,
      link varchar(255) DEFAULT NULL,
      additional_data text,
      PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}