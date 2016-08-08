<?php

class Transport extends CI_Model{

    function get_user_home($user){
        $query = $this->db
            ->select('zipcode')
            ->where('telegramid', $user)
            ->or_where('username', $user)
            ->or_where('email', $user)
        ->get('user');
        if($query->num_rows() == 1){ return $query->row()->zipcode; }
        return NULL;
    }

    function get($type, $loc, $limit = 1){
        $query = $this->db
            ->select(['*', 'SQRT(POW(' .$loc->latitude .' - latitude, 2) + POW(' .$loc->longitude .' - longitude, 2)) AS distance'])
            ->where('type', $type)
            ->order_by('distance', 'ASC')
            ->limit($limit)
        ->get('transport');
        if($query->num_rows() > 0){
            if($query->num_rows() == 1){ return $query->row(); }
            return $query->result_array();
        }
        return FALSE;
    }

    function get_zipcode($type, $zipcode = NULL){
        if($zipcode == NULL){ $zipcode = $this->get_user_home($this->telegram->user('id')); }
        $query = $this->db
            ->where('type', $type)
            ->where('cp', $zipcode)
            ->limit(1)
        ->get('transport');
        if($query->num_rows() == 1){
            return $query->row();
        }
    }

}
