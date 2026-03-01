<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Binotel_integration_model extends CI_Model {
    public function __construct() {
        parent::__construct();
    }

    public function get_client_call_statistics($client_id, $start_date = null, $end_date = null) {
        $this->db->select('call_type, contact_name, waiting_time, call_duration, call_time, recording_link');
        $this->db->where('client_id', $client_id);
        
        if ($start_date) {
            $this->db->where('call_time >=', $start_date . ' 00:00:00');
        }
        
        if ($end_date) {
            $this->db->where('call_time <=', $end_date . ' 23:59:59');
        }
        
        $query = $this->db->get(db_prefix() . 'binotel_call_statistics_clients');
        return $query->result_array();
    }

   public function get_lead_call_statistics($lead_id, $start_date = null, $end_date = null) {
        $this->db->select('id, call_type, contact_name, waiting_time, call_duration, call_time, recording_link, transcription_text, transcription_status, transcribed_at');
    $this->db->where('lead_id', $lead_id);
    
    if ($start_date) {
        $this->db->where('call_time >=', $start_date . ' 00:00:00');
    }
    
    if ($end_date) {
        $this->db->where('call_time <=', $end_date . ' 23:59:59');
    }
    
    $query = $this->db->get(db_prefix() . 'binotel_call_statistics_leads');
    return $query->result_array();
}
public function get_staff_call_statistics($staff_id, $start_date = null, $end_date = null) {
   this->db->select('id, call_type, contact_name, waiting_time, call_duration, call_time, recording_link, transcription_text, transcription_status, transcribed_at');
    $this->db->where('staff_id', $staff_id);
    
    if ($start_date) {
        $this->db->where('call_time >=', $start_date . ' 00:00:00');
    }
    
    if ($end_date) {
        $this->db->where('call_time <=', $end_date . ' 23:59:59');
    }
    
    $query = $this->db->get(db_prefix() . 'binotel_call_statistics_staff');
    return $query->result_array(); 
}

 public function get_call_record_for_transcription($entity_type, $call_id) {
        $table = $this->resolve_statistics_table($entity_type);
        if (!$table) {
            return null;
        }

        return $this->db->where('id', $call_id)->get($table)->row_array();
    }

    public function update_transcription($entity_type, $call_id, $data) {
        $table = $this->resolve_statistics_table($entity_type);
        if (!$table) {
            return false;
        }

        return $this->db->where('id', $call_id)->update($table, $data);
    }

    private function resolve_statistics_table($entity_type) {
        $tables = [
            'lead' => db_prefix() . 'binotel_call_statistics_leads',
            'client' => db_prefix() . 'binotel_call_statistics_clients',
            'staff' => db_prefix() . 'binotel_call_statistics_staff',
        ];

        return $tables[$entity_type] ?? null;
    }



}

