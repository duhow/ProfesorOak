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
	if($pokemon->command_limit("adminlist", $telegram->chat->id, $telegram->message, 7)){ return -1; }

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

elseif(
    $telegram->text_command("uv") or
    ($telegram->text_has("lista") && $telegram->text_has(["usuarios", "entrenadores"]) && $telegram->text_has(["sin", "no"], ["validar", "validados"]))
){
    $users = $pokemon->group_get_members($telegram->chat->id);
    $teams_total = ['Y' => 0, 'R' => 0, 'B' => 0];
    $teams_verified = ['Y' => 0, 'R' => 0, 'B' => 0];
    $users_left = array();
    foreach($users as $u){
        $ul = $pokemon->user($u);
        if(empty($ul)){ continue; }

        $teams_total[$ul->team]++;
        if($ul->verified){ $teams_verified[$ul->team]++; }
        else{ $users_left[] = $ul->username; }
    }

    $colors = ['Y' => ':heart-yellow:', 'R' => ':heart-red:', 'B' => ':heart-blue:'];
    $str = "";
    foreach($teams_total as $t => $v){
        $str .= $telegram->emoji($colors[$t]) ." " .$v ." (" .$teams_verified[$t] .") ";
    }
    if(!empty($users_left)){
        $str .= "\n" ."Faltan: " .implode(", ", $users_left);
    }
    $telegram->send
        ->text($str)
    ->send();
    return;
}

// Preguntar si el usuario es administrador
elseif($telegram->text_has(["soy", "es", "eres"], ["admin", "administrador"], TRUE) && $telegram->words() <= 5){
    $admin = NULL;
    if($telegram->text_has("soy")){ $admin = $telegram->user->id; }
    elseif($telegram->text_has(["es", "eres"]) && $telegram->has_reply){ $admin = $telegram->reply_user->id; }
    else{ return; }

    $admins = $pokemon->telegram_admins(FALSE);
    $text = "Nop.";
    if(in_array($admin, $admins)){
        $text = "Sip, es admin.";
    }
    $this->analytics->event('Telegram', 'Ask for admin');
    $telegram->send
        ->notification(FALSE)
        // ->reply_to($telegram->reply_user->id)
        ->text($text)
    ->send();
    return;
}

// Votar kick de usuarios.
elseif(
    ($telegram->text_command("votekick") or $telegram->text_command("voteban") && $telegram->words() > 1)
){
    // Si el usuario que convoca el comando es troll o tiene flags, no puede votar ni usarlo.
    // TODO si hay un votekick activo, no se puede hacer otro hasta que expire.
    // TODO anotar Message ID en settings del grupo para volver a editar el mensaje cuando toque.
    // TODO limite de tiempo de 5m.
    if($pokemon->user_flags($telegram->user->id, ['troll', 'bot', 'hacks', 'spam', 'rager', 'ratkid'])){ return; }
    $kickuser = NULL;
    $name = NULL;
    if($telegram->has_reply){
        if(
            $telegram->reply_user->id == $this->config->item('telegram_bot_id') or
            in_array($telegram->reply_user->id, $pokemon->telegram_admins(TRUE))
        ){ return; }
        $kickuser = $telegram->reply_user->id;
        $name = $telegram->reply_user->first_name;
    }

    if(empty($kickuser)){ return; }
    $action = ($telegram->text_command("voteban") ? "ban" : "kick");
    $text = trim(substr($telegram->text(), strpos($telegram->text(), " "))) ;

    $str = "Votación para <b>" .$action ."ear</b> al usuario $name ($kickuser).\n";
    $str .= "<b>Motivo:</b> $text";
    $telegram->send
        ->inline_keyboard()
            ->row()
                ->button($telegram->emoji(":ok:"), "voto el $action $kickuser")
                ->button($telegram->emoji(":times:"), "no voto el $action $kickuser")
            ->end_row()
        ->show()
        ->text($str, 'HTML')
    ->send();

    return -1;
}

elseif($telegram->text_has("voto el", ["kick", "ban"])){
    // ser callback
    if($pokemon->user_flags($telegram->user->id, ['troll', 'bot', 'hacks', 'spam', 'rager', 'ratkid'])){ return; }
}

// Contar miembros de cada color
elseif($telegram->text_command("count")){
	if($pokemon->command_limit("count", $telegram->chat->id, $telegram->message, 10)){ return -1; }

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
	if($pokemon->command_limit("offtopic", $telegram->chat->id, $telegram->message, 7)){ return -1; }

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
    $chat = $telegram->chat->id;
    if(strlen($rules) > 500){
        $chat = $telegram->user->id;
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text("Te las envío por privado, " .$telegram->user->first_name .".")
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

// Ver si un usuario está en un grupo.
elseif(
    $telegram->words() <= 6 &&
    (
        ( $telegram->text_has("está") and $telegram->text_has("aquí") ) and
        ( !$telegram->text_has(["alguno", "alguien", "que"], ["es", "ha", "como", "está"]) ) and // Alguien está aquí? - Alguno es....
        ( !$telegram->text_contains("desde") )
    )
){
    $pos = [['esta', 'está'], ['aqui', 'aquí']];
    if(
        // está aqui X
        in_array(strtolower($telegram->words(0)), $pos[0]) &&
        in_array(strtolower($telegram->words(1)), $pos[1])
    ){
        $find = $telegram->words(2, TRUE);
    }elseif(
        // X está aqui
        in_array(strtolower($telegram->words(1)), $pos[0]) &&
        in_array(strtolower($telegram->words(2)), $pos[1])
    ){
        $find = $telegram->words(0, TRUE);
    }elseif(
        // está X aqui
        in_array(strtolower($telegram->words(0)), $pos[0]) &&
        in_array(strtolower($telegram->words(2)), $pos[1])
    ){
        $find = $telegram->words(1, TRUE);
    }

    // TODO buscar si hay una @mencion

    $str = "";
    $find = str_replace(["@", "?"], "", $find);
    if(empty($find) or strlen($find) < 4){ return; }
    if(strpos($find, "est") !== FALSE or strpos($find, "aqu") !== FALSE){ return; }
    $this->analytics->event('Telegram', 'Search User', $find);
    $data = $pokemon->user($find);
    if(empty($data)){
        $str = "No sé quien es. ($find)";
    }else{
        $find = $telegram->user_in_chat($data->telegramid);
        if(!$find){
            $str = "No, no está.";
        }else{
            $str = "Si, " .$find['user']['first_name'] ." está aquí.";
        }
    }

    if(!empty($str)){
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text($str)
        ->send();
    }

    return;
}

?>
