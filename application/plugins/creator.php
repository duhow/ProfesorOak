<?php

if($telegram->user->id != $this->config->item('creator')){ return; }

// enviar broadcast a todos los grupos (solo creador)
if($telegram->text_command("broadcast")){
    exit();
    $text = substr($text, strlen("/broadcast "));
    foreach($pokemon->get_groups() as $g){
        $res = $telegram->send
            ->chat($g)
            ->notification(TRUE)
            ->text($text, TRUE)
        ->send();
        var_dump($res);
    }
    return -1;
}

elseif($telegram->text_command("usercast")){
    exit(); // TODO temporal
    $text = substr($text, strlen("/usercast "));
    // Cada 100 usuarios, enviar un mensaje de confirmación del progreso.
    $users = $pokemon->get_users(TRUE);
    $c = 0;
    foreach($users as $u){
        if($c % 100 == 0){
            $telegram->send
                ->chat( $this->config->item('creator') )
                ->notification(FALSE)
                ->text("Enviados $c de " .count($users) ." (" .floor(($c / count($users)) * 100) .")")
            ->send();
        }
        $telegram->send
            ->chat($u)
            ->notification(TRUE)
            ->text($text, TRUE)
        ->send();
        $c++;
    }
}

// Marcar otro usuario (solo creador)
elseif($telegram->text_has("Éste", TRUE) && $telegram->has_reply){
    $reply = $telegram->reply_user;
    $word = $telegram->last_word();

    // marcar de un color
    if(in_array(strtolower($word), ["rojo", "azul", "amarillo"])){
        if( $pokemon->register( $reply->id, $word ) !== FALSE){
            $name = trim("$reply->first_name $reply->last_name");
            $telegram->send
                ->notification(FALSE)
                ->text("Vale jefe, marco a $name como *$word*!", TRUE)
            ->send();
            $pokemon->update_user_data($reply->id, 'fullname', $name);
        }elseif($pokemon->user_exists( $reply->id )){
            $telegram->send
                ->notification(FALSE)
                ->text("Con que un topo, eh? ¬¬ Bueno, ahora es *$word*.\n_Cuidadín, que te estaré vigilando..._", TRUE)
            ->send();
            $pokemon->update_user_data($reply->id, 'team', $pokemon->team_text($word));
        }
    }

    // guardar nombre del user
    elseif($telegram->text_has("se llama")){

        if($pokemon->user_exists($word)){
            $telegram->send
                ->notification(FALSE)
                ->reply_to(TRUE)
                ->text("Oye jefe, que ya hay alguien que se llama así :(")
            ->send();
        }else{
            $pokemon->update_user_data($reply->id, 'username', $word);
            $this->analytics->event('Telegram', 'Register username');
            $str = "De acuerdo, *@$word*!\n"
                    ."¡Recuerda *validarte* para poder entrar en los grupos de colores!";
            $telegram->send
                ->notification(FALSE)
                ->text($str, TRUE)
            ->send();
        }
    }

    // guardar nivel del user
    elseif($telegram->text_has("es nivel")){
        if(is_numeric($word) && $word >= 5 && $word <= 40){
            $this->analytics->event('Telegram', 'Change level', $word);
            $pokemon->update_user_data($reply->id, 'lvl', $word);
        }
    }

    return -1;
}

// Bloquear usuarios del Oak
elseif($telegram->text_contains(["/block", "/unblock"], TRUE)){
    $user = NULL;
    if($telegram->has_reply){
        // if($telegram->reply_is_forward)
        $user = $telegram->reply_user->id;
    }elseif($telegram->words() == 2 && $telegram->text_mention()){
        // $user = $telegram->text_mention(); // --> to UID.
    }
    if(empty($user)){ return -1; }
    $pokemon->update_user_data($user, 'blocked', $telegram->text_contains("/block"));
}

// Desbanear usuarios de un grupo
elseif($telegram->text_command("unban")){
    // si es group y
    // in_array($telegram->user->id, $this->admins(TRUE)

    $target = NULL;
    $target_chat = NULL;

    if($telegram->has_reply){
        $target = $telegram->reply_user->id;
        if($telegram->is_chat_group()){ $target_chat = $telegram->chat->id; }
    }elseif($telegram->words() == 3){
        $target = $telegram->words(1);
        $target_chat = $telegram->words(2);
    }

    if(!empty($target) && !empty($target_chat)){
        $telegram->send->unban($target, $target_chat);
        $telegram->send
            ->text("Usuario $target desbaneado" .($target_chat != $telegram->chat->id ? " de $target_chat" : "") .".")
        ->send();
    }
    return -1;
}

elseif($telegram->text_command("ban") && !$telegram->is_chat_group() && $telegram->words() >= 3){
    $target = NULL;
    $chat = NULL;
    if($telegram->text_mention()){
        $target = $telegram->text_mention();
        if(is_array($target)){ $target = key($target); }
    }elseif(is_string($telegram->words(1))){
        $target = $pokemon->user($telegram->words(1));
        if($target){
            $target = $target->telegramid;
        }
    }elseif(is_numeric($telegram->words(1))){
        $target = $telegram->words(1);
    }

    if(!$target){ return TRUE; } // TODO exit.

    $chat = $telegram->last_word();
    if(!is_numeric($chat) && is_string($chat)){
        // Resolver name group.
    }

    if(!$telegram->user_in_chat($target, $chat)){
        $telegram->send
            ->chat($this->config->item('creator'))
            ->text($telegram->emoji(":warning:") ." Usuario no está en chat.")
        ->send();
    }

    $q = $telegram->send->ban($target, $chat);
    if($q){
        $telegram->send
            ->chat($this->config->item('creator'))
            ->text("Usuario $target baneado de $chat .")
        ->send();
    }

    return TRUE;
}

// Quitar tag de SPAM
elseif($telegram->text_has("/nospam", TRUE) && $telegram->words() <= 3){
    // HACK text_has porque comandos no se parsean en INLINE_keyboard.
    $target = NULL;
    $target_chat = NULL;
    if($telegram->has_reply){
        // si reply forward
        $target = $telegram->reply_user->id;
        if($telegram->is_chat_group()){ $target_chat = $telegram->chat->id; }
    }elseif($telegram->words() >= 2){
        $target = $telegram->words(1);
        $target_chat = $telegram->words(2);
    }

    if($target != NULL){
        $pokemon->user_flags($target, 'spam', FALSE);

        if($telegram->callback){
            $telegram->send
                ->chat(TRUE)
                ->message(TRUE)
                ->text("Flag *SPAM* quitado del grupo $target_chat.", TRUE)
            ->edit('text');
        }elseif($telegram->is_chat_group()){
            $telegram->send
                ->text("Flag *SPAM* de $target quitado.", TRUE)
            ->send();
        }
    }
    return -1;
}

// Ver información de un grupo
elseif($telegram->text_command("cinfo")){
    $id = $telegram->last_word();
    if(empty($id) or $id == "/cinfo"){ $id = $telegram->chat->id; }
    $info = $telegram->send->get_chat($id);
    $count = $telegram->send->get_members_count($id);
    $telegram->send->text( json_encode($info) ."\n$count" )->send();
    $info = $telegram->send->get_member_info($this->config->item('telegram_bot_id'), $id);
    $telegram->send->text( json_encode($info) )->send();
    exit();
}

// Ver información de un usuario
elseif($telegram->text_command("uinfo") or $telegram->text_command("ui")){
    $u = NULL;
    if($telegram->has_reply){
        $u = ($telegram->reply_is_forward ? $telegram->reply->forward_from['id'] : $telegram->reply_user->id);
        // $u = $telegram->reply_user->id;
    }elseif($telegram->text_mention()){
        $u = $telegram->text_mention();
        if(is_array($u)){ $u = key($u); }
    }elseif($telegram->words() == 2){
        $u = $telegram->last_word(TRUE);
    }

    if(empty($u)){ return -1; }
    $chat = ($telegram->is_chat_group() ? $telegram->chat->id : $u);
    $pk = $pokemon->user($u);
    if($pk){ $u = $pk->telegramid; }
    $find = $telegram->send->get_member_info($u, $chat);

    $str = "Desconocido.";
    if($find !== FALSE){
        $str = $find['user']['id'] . " - " .$find['user']['first_name'] ." " .$find['user']['last_name'] ." ";
        if(in_array($find['status'], ["administrator", "creator"])){ $str .= $telegram->emoji(":star:"); }
        elseif(in_array($find['status'], ["left"])){ $str .= $telegram->emoji(":door:"); }
        elseif(in_array($find['status'], ["kicked"])){ $str .= $telegram->emoji(":forbid:"); }
        else{ $str .= $telegram->emoji(":multiuser:"); }

        if(!$pk){ $str .= $telegram->emoji(" :question-red:"); }
        else{
            $colors = ["Y" => "yellow", "R" => "red", "B" => "blue"];
            $str .= $telegram->emoji(" :heart-" .$colors[$pk->team] .":");
        }

        $info = $pokemon->user_in_group($u, $chat);
        if($info){
            $str .= "\n";
            $str .= "$info->messages msj, último el " .date("d/m/Y H:i", strtotime($info->last_date));
        }elseif($telegram->user_in_chat($find['user']['id'])){
            $pokemon->user_addgroup($find['user']['id'], $telegram->chat->id);
        }

    }


    $telegram->send
        ->notification(FALSE)
        ->text($str)
    ->send();
    return -1;
}

// Salir de un grupo.
elseif($telegram->text_has("salte de", TRUE) && $telegram->words() == 3){
    $id = $telegram->last_word();
    $telegram->send->leave_chat($id);
    exit();
}

// Buscar usuario por grupos
elseif($telegram->text_has(["/whereis", "dónde está"], TRUE) && !$telegram->is_chat_group() && $telegram->words() <= 3){
    $find = $telegram->last_word(TRUE);
    $pkfind = $pokemon->user($find);
    if($pkfind && !is_numeric($find)){ $find = $pkfind->telegramid; }
    // @Pablo mencion sin @alias tambien debería valer.
    $text = "No sé quién es.";
    if(is_numeric($find)){
        $groups = $pokemon->group_find_member($find, TRUE);
        if(!$groups){ $text = "No lo veo por ningún lado."; }
        else{
            $text = $find;
            $text .= ($pkfind ? " @" .$pkfind->telegramuser ." - " .$pkfind->username ." " .$pkfind->team ."\n" : "\n");
            foreach($groups as $g => $d){
                $info = $pokemon->group($g);
                $text .= $info->title ."\n";
            }
        }
    }
    $telegram->send
        ->text($text)
    ->send();
    return -1;
}

// Ver flags de usuarios
elseif($telegram->text_command("flags")){
    $uflag = NULL;
    if($telegram->has_reply){
        $uflag = ($telegram->reply_is_forward ? $telegram->reply->forward_from['id'] : $telegram->reply_user->id);
    }elseif($telegram->text_mention()){
        $uflag = $telegram->text_mention();
        if(is_array($uflag)){ $uflag = key($uflag); }
    }elseif($telegram->words() == 2){
        $uflag = $telegram->last_word();
        if(!is_numeric($uflag)){ return -1; }
    }
    if(empty($uflag)){ return -1; }
    if(!is_numeric($uflag)){
        $find = $pokemon->user($uflag);
        if(!$find){ return -1; }
        $uflag = $find->telegramid;
    }
    $flags = $pokemon->user_flags($uflag);
    $flags = (empty($flags) ? "No tiene." : implode(", ", $flags));
    $telegram->send
        ->chat($this->config->item('creator'))
        ->text($flags)
    ->send();
    return -1;
}

// Poner flag a un usuario
elseif(
    $telegram->text_command("setflag") &&
    (in_array($telegram->words(), [2,3]))
){
    if($telegram->words() == 2 and $telegram->has_reply){
        $f_user = ($telegram->reply_is_forward ? $telegram->reply->forward_from['id'] : $telegram->reply_user->id);
    }elseif($telegram->words() == 3){
        $search = $telegram->words(1); // Penúltima
        if($telegram->text_mention()){
            $search = $telegram->text_mention();
            if(is_array($search)){ $search = key($search); }
            $serach = str_replace("@", "", $search);
        }
        $f_user = $pokemon->user($search);
        if(empty($f_user)){ return -1; }
        $f_user = $f_user->telegramid;
    }
    $flag = $telegram->last_word();
    $flag = explode(",", $flag);
    foreach($flag as $f){
        $pokemon->user_flags($f_user, $f, TRUE);
    }
    return -1;
}

// Ver o poner STEP a un usuario.
elseif($telegram->text_command("mode")){
    $user = ($telegram->has_reply ? $telegram->reply_user->id : $telegram->user->id);
    if($telegram->words() == 1){
        $step = $pokemon->step($user);
        if(empty($step)){ $step = NULL; }
        $telegram->send->text("*" .json_encode($step) ."*", TRUE)->send();
    }elseif($telegram->words() == 2){
        $step = $pokemon->step($user, $telegram->last_word());
        $telegram->send->text("set!")->send();
    }
    return -1;
}

// Conversación grupal
elseif($telegram->text_command("speak") && $telegram->words() == 2 && !$telegram->is_chat_group()){
    $chattalk = $telegram->last_word();
    if(in_array(strtolower($chattalk), ["stop", "off", "false"])){
        $chattalk = $pokemon->settings($telegram->user->id, 'speak');
        if(!$chattalk){ return; }
        $pokemon->settings($chattalk, 'forward_interactive', "DELETE");
        $pokemon->settings($telegram->user->id, 'speak', "DELETE");
        $pokemon->step($telegram->user->id, NULL);
        $telegram->send
            ->text($telegram->emoji(":forbid: Chat detenido."))
        ->send();
        return -1;
    }
    $isuser = FALSE;
    if($chattalk[0] != "-"){
        $pkuser = $pokemon->user($chattalk);
        if(!$pkuser){
            $new = $pokemon->group_find($chattalk);
            if(empty($new)){ return -1; }
            $chattalk = $new;
        }else{
            $chattalk = $pkuser->telegramid;
        }
    }
    if(!$telegram->user_in_chat($telegram->config->item('telegram_bot_id'), $chattalk)){
        $telegram->send
            ->text($telegram->emoji(":times: No estoy :("))
        ->send();
        return -1;
    }
    $chat = $telegram->send->get_chat($chattalk);
    // $telegram->send->text(json_encode($chat))->chat($this->config->item('creator'))->send();
    $forward = FALSE;
    if($chat['type'] == "private" or !$telegram->user_in_chat($telegram->config->item('creator'), $chattalk)){
        // No mirror.
        $pokemon->settings($chattalk, 'forward_interactive', TRUE);
        $forward = TRUE;
    }

    $pokemon->settings($telegram->user->id, 'speak', $chattalk);
    $pokemon->step($telegram->user->id, 'SPEAK');
    $title = (isset($chat['title']) ? $chat['title'] : $chat['first_name'] ." " .$chat['last_name']);

    $telegram->send
        ->text($telegram->emoji(":ok: ") .($forward ? "Forwarding activo. " : "") ."Hablando en " .$title)
    ->send();
    return -1;
}

elseif($telegram->text_command("countonline") && $telegram->is_chat_group()){

    $run = $pokemon->settings($telegram->chat->id, 'investigation');
    if($run !== NULL){
        if(time() <= ($run + 3600)){ return -1; }
    }
    $run = $pokemon->settings($telegram->chat->id, 'investigation', time());

    $teams = ["Y", "B", "R"];
    // unset( $teams[ array_search($team, $teams) ] );
    $users = $pokemon->get_users($teams);
    $c = 0;
    $dot = 0;
    $pks = array();
    $current_chat = $telegram->send->get_members_count();

    $updates = $telegram->send
        ->notification(FALSE)
        ->text("*Progreso:* ", TRUE)
    ->send();
    foreach($users as $u){
        if(($c % 100 == 0) or ($c % 100 == 50) or ($c >= count($users))){
            $msg = "*Progreso:* " .floor(($c / count($users) * 100)) ."%";
            $msg .= " (" .count($pks["Y"]) ." / " .count($pks["R"]) ." / " .count($pks["B"]) .") ";
            $msg .= "de " .$current_chat;
            if($dot++ >= 3){ $dot = 0; }
            for($i = 0; $i < $dot; $i++){ $msg .= "."; }
            $msg .= " ($c)";

            $run = $pokemon->settings($telegram->chat->id, 'investigation');
            if($run === NULL){ $msg = "Cancelado. $c comprobados."; }

            $telegram->send
                ->message($updates['message_id'])
                ->text($msg, TRUE)
            ->edit('text');

            if($run === NULL){ die(); }
        }
        $c++;

        $q = $telegram->user_in_chat($u);

        if($q){
            $pk = $pokemon->user($u);
            if(!empty($pk)){
                $pokemon->user_addgroup($u, $telegram->chat->id);
                $pks[$pk->team][] = $u;
            }
        }
    }

    $str = "Lista final:\n";
    $str .= ":heart-yellow: " .count($pks["Y"]) ."\n";
    $str .= ":heart-red: " .count($pks["R"]) ."\n";
    $str .= ":heart-blue: " .count($pks["B"]) ."\n";
    $str .= "Faltan: " .($current_chat - count($pks["Y"]) - count($pks["R"]) - count($pks["B"]));
    $str = $telegram->emoji($str);

    $telegram->send
        ->notification(FALSE)
        ->text($str)
    ->send();
}

// Registro manual - creador.
elseif($telegram->text_command("register") && $telegram->has_reply){
    $pkuser = $pokemon->user($telegram->reply_user->id);

    $data['telegramid'] = $telegram->reply_user->id;
    $data['telegramuser'] = @$telegram->reply_user->username;
    foreach($telegram->words(TRUE) as $w){
        $w = trim($w);
        if($w[0] == "/"){ continue; }
        if(is_numeric($w) && $w >= 5 && $w <= 40){ $data['lvl'] = $w; }
        if(in_array(strtoupper($w), ['R','B','Y'])){ $data['team'] = strtoupper($w); }
        if($w[0] == "@" or strlen($w) >= 4){ $data['username'] = $w; }
        if(strtoupper($w) == "V"){ $data['verified'] = TRUE; }
    }
    $register = FALSE;
    if($pkuser == FALSE or $pkuser == NULL){
        if(!isset($data['team'])){
            $telegram->send
                ->notification(FALSE)
                ->text($telegram->emoji(":times: Falta team."))
            ->send();
            return -1;
        }
        if($pokemon->register($data['telegramid'], $data['team']) === FALSE){
            $telegram->send
                ->notification(TRUE)
                ->text($telegram->emoji(":times: Error general."))
            ->send();
            return -1;
        }
        $register = TRUE;
        $pkuser = $pokemon->user($telegram->reply_user->id);
    }

    foreach($data as $k => $v){
        if(in_array($k, ['telegramid', 'team'])){ continue; }
        $pokemon->update_user_data($data['telegramid'], $k, $v);
    }

    $str = ":ok: Hecho" .(isset($data['verified']) ? " y validado!" : "!");
    if($register === FALSE){
        $changes = array();
        if(isset($data['lvl']) && $pkuser->lvl != $data['lvl'] ){ $changes[] = "nivel"; }
        if(isset($data['team']) && $pkuser->team != $data['team'] ){ $changes[] = "equipo"; }
        if(isset($data['username']) && $pkuser->username != $data['username']){ $changes[] = "nombre"; }
        $str = ":ok: Cambio *" .implode(", ", $changes) .(isset($data['verified']) ? "* y *valido*!" : "*!");
    }

    $telegram->send
        ->notification(FALSE)
        ->text($telegram->emoji($str), TRUE)
    ->send();
    return -1;
}
?>
