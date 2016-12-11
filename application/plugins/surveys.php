<?php

function survey($id, $force = FALSE){
    $CI =& get_instance();

    if(!$force){ $CI->db->where('active', TRUE); }
    $query = $CI->db
        ->where('id', $id)
    ->get('survey_question');
    if($query->num_rows() == 0){ return FALSE; }
    return $query->row();
}

if($telegram->is_chat_group()){ return; }

if($telegram->has_reply && $telegram->reply_user->id == $this->config->item('telegram_bot_id')){
    $text = $telegram->reply->text;
    $pos = strpos($text, "Encuesta ");
    if($pos === FALSE){ return; }
    $pos = $pos + strlen("Encuesta ");
    $last = strpos($text, " ", $pos);
    $key = trim(substr($text, $pos, $last - $pos));

    $telegram->send
        ->keyboard()
        ->hide()
    ->send();

    return -1;
}

if($telegram->text_has("encuesta") && in_array($telegram->words(), [2,3])){
    $key = "c1c5ba39";
    $survey = survey($key);
    if($survey === FALSE){
        $telegram->send
            ->keyboard()
            ->hide()
            ->text($telegram->emoji(":warning:") ." Esa encuesta no existe.")
        ->send();
        return -1;
    }
    $str = "Encuesta <i>$key</i> -\n\n" .$survey->question;
    $telegram->send
        // ->force_reply()
        ->text($str, 'HTML');

    if(!empty($survey->keyboard)){
        $keyboard = unserialize($survey->keyboard);
        $telegram->send
            ->keyboard()
                ->selective(TRUE)
                ->push($keyboard)
            ->show(TRUE, TRUE)
        ->send();
    }else{
        $telegram->send->send();
    }
    return -1;
}

?>
