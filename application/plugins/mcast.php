<?php

function mcast_add($text){
    $ci =& get_instance();
    if( $ci->db->set('message', $text)->insert('mcast_message') ){
        return $ci->db->insert_id();
    }
    return FALSE;
}

function mcast_view($id, $chat){
    
}

if($telegram->user->id !== $this->config->item('creator')){ return; }

$step = $pokemon->step($telegram->user->id);
if(!empty($step) && $step == "MCAST"){

}

if($telegram->text_command("/mcast")){
    if($telegram->words() == 1){
        $pokemon->step($telegram->user->id, 'MCAST');
        $telegram->send
            ->text("De acuerdo, envíame el mensaje que quieres que envíe a los grupos.")
        ->send();
    }
    return -1;
}


?>
