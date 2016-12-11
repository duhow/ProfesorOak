<?php

// Configurar el bot (solo creador/admin/chat privado)
if(
    $telegram->text_command("set") &&
    $telegram->words() == 3 &&
    (
        ( $telegram->is_chat_group() && in_array($telegram->user->id, telegram_admins(TRUE)) ) or
        ( !$telegram->is_chat_group() )
    )
){
    $key = $telegram->words(1);
    $value = $telegram->words(2);

    $this->analytics->event('Telegram', 'Set config', $key);
    $set = $pokemon->settings($telegram->chat->id, $key, $value);
    $announce = $pokemon->settings($telegram->chat->id, 'announce_settings');
    $telegram->send
        ->chat( $this->config->item('creator') )
        ->text("CONFIG: $key " .json_encode($set) ." -> " .json_encode($value))
    ->send();

    if( ($set !== FALSE or $set > 0) && ($announce == TRUE) ){
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text("Configuración establecida: *$value*", TRUE)
        ->send();
    }
    return -1;
}

elseif(
    $telegram->text_command("get") &&
    in_array($telegram->words(), [2,3])
){
    $get = $telegram->chat->id;
    if($telegram->is_chat_group()){
        $admins = $pokemon->telegram_admins(TRUE);
        if(!in_array($telegram->user->id, $admins)){ return -1; }
        if($telegram->has_reply && $telegram->user->id == $this->config->item('creator')){ $get = $telegram->reply_user->id; }
    }

    $word = $telegram->words(1);
    $chat = $telegram->chat->id;
    if(strpos($word, "+private") !== FALSE){
        $chat = $telegram->user->id;
        $word = trim(str_replace("+private", "", $word));
    }
    if(strtolower($word) == "all"){ $word = "*" ; } // ['say_hello', 'say_hey', 'play_games', 'announce_welcome', 'announce_settings', 'shutup']; }
    if($telegram->words() == 3){
        if($telegram->user->id != $this->config->item('creator')){ return -1; }
        if(is_numeric($telegram->last_word())){ $get = $telegram->last_word(); }
        else{
            $get = $pokemon->group_find($telegram->last_word());
            if(!$get){
                $get = $pokemon->user($telegram->last_word());
                if(!$get){ return -1; }
                $get = $get->telegramid;
            }
        }
    }
    $value = $pokemon->settings($get, $word);
    $text = "";
    if(is_array($value)){
        foreach($value as $k => $v){
            $text .= "$k: $v\n";
        }
    }else{
        $text = "*" .json_encode($value) ."*";
    }
    $telegram->send
        ->chat($chat)
        ->notification( ($chat != $telegram->chat->id) )
        // ->reply_to( ($chat == $telegram->chat->id) )
        ->text($text, (!is_array($value)))
    ->send();
    return -1;
}

?>
