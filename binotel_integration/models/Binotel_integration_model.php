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
    $this->db->select('call_type, contact_name, waiting_time, call_duration, call_time, recording_link');
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

}
