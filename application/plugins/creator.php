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
    return;
}elseif($telegram->text_command("usercast")){
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

    return;
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
    if(empty($user)){ return; }
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
    return;
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
    return;
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
    return;
}

// Ver flags de usuarios
elseif($telegram->text_command("flags")){
    $uflag = NULL;
    if($telegram->has_reply){ $uflag = $telegram->reply_user->id; }
    elseif($telegram->text_mention()){
        $uflag = $telegram->text_mention();
        if(is_array($uflag)){ $uflag = key($uflag); }
    }elseif($telegram->words() == 2){
        $uflag = $telegram->last_word();
        if(!is_numeric($uflag)){ return; }
    }
    if(empty($uflag)){ return; }
    if(!is_numeric($uflag)){
        $find = $pokemon->user_find($uflag);
        if(!$find){ return; }
        $uflag = $find->telegramid;
    }
    $flags = $pokemon->user_flags($uflag);
    $flags = (empty($flags) ? "No tiene." : implode(", ", $flags));
    $telegram->send
        ->chat($this->config->item('creator'))
        ->text($flags)
    ->send();
    return;
}

// Poner flag a un usuario
elseif(
    $telegram->text_command("setflag") &&
    (in_array($telegram->words(), [2,3]))
){
    if($telegram->words() == 2 and $telegram->has_reply){
        $f_user = $telegram->reply_user->id;
    }elseif($telegram->words() == 3){
        $search = $telegram->words(1); // Penúltima
        if($telegram->text_mention()){
            $search = $telegram->text_mention();
            if(is_array($search)){ $search = key($search); }
            $serach = str_replace("@", "", $search);
        }
        $f_user = $pokemon->user($search);
        if(empty($f_user)){ return; }
        $f_user = $f_user->telegramid;
    }
    $flag = $telegram->last_word();
    $pokemon->user_flags($f_user, $flag, TRUE);
    return;
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
    return;
}

elseif($telegram->text_command("countonline") && $telegram->is_chat_group()){

    $run = $pokemon->settings($telegram->chat->id, 'investigation');
    if($run !== NULL){
        if(time() <= ($run + 3600)){ return; }
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
    if($pkuser){
        $telegram->send
            ->notification(FALSE)
            ->text("Ya está registrado.")
        ->send();
        return;
    }
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
    if(!isset($data['team'])){
        $telegram->send
            ->notification(FALSE)
            ->text($telegram->emoji(":times: Falta team."))
        ->send();
        return;
    }
    if($pokemon->register($data['telegramid'], $data['team']) !== FALSE){
        foreach($data as $k => $v){
            if(in_array($k, ['telegramid', 'team'])){ continue; }
            $pokemon->update_user_data($data['telegramid'], $k, $v);
        }
    }
    $telegram->send
        ->notification(FALSE)
        ->text($telegram->emoji(":ok: Hecho" .(isset($data['verified']) ? " y verificado!" : "!") ))
    ->send();
    return;
}
?>
