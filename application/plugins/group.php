<?php

if(!$telegram->is_chat_group()){ return; }

// el bot explusa al emisor del mensaje
if($telegram->text_command("autokick")){
    $this->analytics->event('Telegram', 'AutoKick');
    $res = $telegram->send->kick($telegram->user->id, $telegram->chat->id);
    if($res){ $pokemon->user_delgroup($telegram->user->id, $telegram->chat->id); }
    if(!$res){ $telegram->send->text("No puedo :(")->send(); }
    return;
}

// Lista de administradores
elseif(
    (
        ( $telegram->text_has("lista") and $telegram->text_has(["admins", "admin", "administradores"]) and $telegram->words() <= 8 ) or
        ( $telegram->text_command("adminlist") or $telegram->text_command("admins") )
    )
){
    $admins = $telegram->get_admins($telegram->chat->id, TRUE);
    $teams = ["Y" => "yellow", "B" => "blue", "R" => "red"];
    $str = "";

    if(empty($admins)){ $str = $telegram->emoji("No hay admin... :die:"); }

    foreach($admins as $k => $a){
        if($a['status'] == 'creator'){
            unset($admins[$k]);
            array_unshift($admins, $a);
        }elseif($a['user']['id'] == $this->config->item('telegram_bot_id')){
            unset($admins[$k]);
            array_push($admins, $a);
        }
    }
    foreach($admins as $k => $a){
        if($a['user']['id'] == $this->config->item('telegram_bot_id')){
            $str .= "Y yo, el Profesor Oak :)";
            continue;
        }
        $pk = $pokemon->user($a['user']['id']);
        $name = (!empty($a['user']['first_name']) ? $a['user']['first_name'] : "Desconocido");
        if(!empty($pk)){ $str .= $telegram->emoji(":heart-" .$teams[$pk->team] .":") ." L" .$pk->lvl ." @" .$pk->username ." - "; }
        $str .= $name ." ";
        if(isset($a['user']['username']) && (strtolower($a['user']['username']) != strtolower($pk->username)) ){ $str .= "( @" .$a['user']['username'] ." )"; }
        if($k == 0){ $str .= "\n"; } // - Creator
        $str .= "\n";
    }

    // Reply to private?
    // ->chat( $telegram->user->id )
    $this->analytics->event('Telegram', 'Admin List');
    $telegram->send
        ->notification(FALSE)
        ->text($str)
    ->send();
    return;
}

// Contar miembros de cada color
elseif($telegram->text_command("count")){
    $members = $pokemon->group_get_members($telegram->chat->id);
    $users = $pokemon->find_users($members);
    $count = $telegram->send->get_members_count();
    $teams = ['Y' => 0, 'R' => 0, 'B' => 0];
    foreach($users as $u){
        if($u['telegramid'] == $this->config->item('telegram_bot_id')){ continue; }
        $teams[$u['team']]++;
    }
    $str = "Veo a ". count($members) ." ($count) y conozco " .array_sum($teams) ." (" .round((array_sum($teams) / $count) * 100)  ."%) :\n"
            .":heart-yellow: " .$teams["Y"] ." "
            .":heart-red: " .$teams["R"] ." "
            .":heart-blue: " .$teams["B"] ."\n"
            ."Faltan: " .($count - array_sum($teams));
    $str = $telegram->emoji($str);

    $telegram->send
        ->notification(FALSE)
        ->text($str)
    ->send();
    return;
}

// Link del grupo offtopic_chat
elseif($telegram->text_has(["grupo offtopic", "/offtopic"])){
    $offtopic = $pokemon->settings($telegram->chat->id, 'offtopic_chat');
    $chatgroup = NULL;
    if(!empty($offtopic)){
        if($offtopic[0] != "@" and strlen($offtopic) == 22){
            $chatgroup = "https://telegram.me/joinchat/" .$offtopic;
        }else{
            $chatgroup = $offtopic;
        }
    }
    if(!empty($chatgroup)){
        $this->analytics->event('Telegram', 'Offtopic Link');
        $telegram->send
            ->notification(FALSE)
            ->text("Offtopic: $chatgroup")
        ->send();
    }
    return;
}

// Normas del grupo
elseif(
    (
        $telegram->text_has(["reglas", "normas"], "del grupo") or
        $telegram->text_has(['dime', 'ver'], ["las reglas", "las normas", "reglas", "normas"], TRUE) or
        $telegram->text_has(["/rules", "/normas"], TRUE)
    ) and
    !$telegram->text_has(["poner", "actualizar", "redactar", "escribir", "cambiar"]) and
    $telegram->is_chat_group()
){
    $this->analytics->event('Telegram', 'Rules', 'display');
    $rules = $pokemon->settings($telegram->chat->id, 'rules');

    $text = "No hay reglas escritas.";
    if(!empty($rules)){ $text = json_decode($rules); }
    $chat = $chat->id;
    if(strlen($rules) > 500){
        $chat = $user->id;
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text("Te las envío por privado, " .$user->first_name .".")
        ->send();
    }

    $telegram->send
        ->notification(FALSE)
        ->chat($chat)
        // ->disable_web_page_preview()
        ->text($text)
    ->send();
    return;
}

?>
