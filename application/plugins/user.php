<?php

// Guardar nivel del user
if(
    $telegram->text_has("Soy", ["lvl", "nivel", "L", "level"]) or
    $telegram->text_has("Soy L", TRUE) or // HACK L junta
    $telegram->text_has("Acabo de subir al")
){
    $level = filter_var($telegram->text(), FILTER_SANITIZE_NUMBER_INT);
    if(is_numeric($level)){
        $pokeuser = $pokemon->user($telegram->user->id);
        $command = $pokemon->settings($telegram->user->id, 'last_command');
        if($level == $pokeuser->lvl or $command == "LEVELUP"){
            /* $telegram->send
                ->notification(FALSE)
                ->text("Que ya lo sé, pesado...")
            ->send(); */
        }
        $this->analytics->event('Telegram', 'Change level', $level);
        $pokemon->settings($telegram->user->id, 'last_command', 'LEVELUP');
        if($level >= 5 && $level <= 35){
            if($level <= $pokeuser->lvl){ return; }
            $pokemon->update_user_data($telegram->user->id, 'lvl', $level);
            $pokemon->log($telegram->user->id, 'levelup', $level);
            if($command == "WHOIS" && $telegram->is_chat_group()){
                $telegram->send
                    ->notification(FALSE)
                    ->text("Así que has subido al nivel *$level*... Guay!", TRUE)
                    // Vale. Como me vuelvas a preguntar quien eres, te mando a la mierda. Que lo sepas.
                ->send();
            }
        }
    }
    return;
}


// Mención de usuarios
if($telegram->text_has(["toque", "tocar"]) && $telegram->words() <= 3){
    $touch = NULL;
    if($telegram->has_reply){
        $touch = $telegram->reply_user->id;
    }elseif($telegram->text_mention()){
        $touch = $telegram->text_mention();
        if(is_array($touch)){ $touch = key($touch); }
        else{ $touch = substr($touch, 1); }
    }else{
        $touch = $telegram->last_word(TRUE);
        if(strlen($touch) < 4 or $telegram->words() < 2){ return; }
    }
    $name = (isset($telegram->user->username) ? "@" .$telegram->user->username : $telegram->user->first_name);

    $usertouch = $pokemon->user($touch);
    $req = FALSE;

    if(!empty($usertouch)){
        $req = $telegram->send
            ->notification(TRUE)
            ->chat($usertouch->telegramid)
            ->text("$name te ha tocado.")
        ->send();
    }

    $text = ($req ? $telegram->emoji(":green-check:") : $telegram->emoji(":times:"));
    $telegram->send
        ->chat($telegram->user->id)
        ->notification(!$req)
        ->text($text)
    ->send();
    exit();
}

// Responder el nivel de un entrenador.
elseif($telegram->text_has("que") && $telegram->text_has(["lvl", "level", "nivel"], ["eres", "es", "soy"]) && $telegram->words() <= 7){
    $user = $telegram->user->id;
    if($telegram->text_has(["eres", "es"])){
        if(!$telegram->has_reply){ return; }
        $user = $telegram->reply_user->id;
    }

    $u = $pokemon->user($user);
    $text = NULL;
    if(!empty($u) && $u->lvl >= 5){
        $this->analytics->event('Telegram', 'Whois', 'Level');
        $text = ($telegram->text_has(["eres", "es"]) ? "Es" : "Eres") ." L" .$u->lvl .".";
    }else{
        $text = ($telegram->text_has("soy") ? "No lo sé. ¿Y si me lo dices?" : "No lo sé. :(");
    }
    $telegram->send
        ->notification(FALSE)
        // ->reply_to(TRUE)
        ->text($text)
    ->send();
    return;
}

// Ver estadísticas de los entrenadores registrados
elseif($telegram->text_command("stats")){
    $stats = $pokemon->count_teams();
    $text = "";
    $equipos = ["Y" => "yellow", "B" => "blue", "R" => "red"];
    foreach($stats as $s => $v){
        $text .= $telegram->emoji(":heart-" .$equipos[$s] .":") ." $v\n";
    }
    $text .= "*TOTAL:* " .array_sum($stats);
    $telegram->send
        ->notification(FALSE)
        // ->reply_to(TRUE)
        ->text($text, TRUE)
    ->send();
    return;
}

// Registro offline de users
elseif($telegram->text_command("regoff")){
    // $chat = ($telegram->is_chat_group() && $this->is_shutup(TRUE)) ? $telegram->user->id : $telegram->chat->id);
    $data = array();
    foreach($telegram->words(TRUE) as $w){
        $w = trim($w);
        if($w[0] == "/"){ continue; }
        if(is_numeric($w) && $w >= 5 && $w <= 40){ $data['lvl'] = $w; }
        if(in_array(strtoupper($w), ['R','B','Y'])){ $data['team'] = strtoupper($w); }
        if($w[0] == "@" or strlen($w) >= 4){ $data['username'] = $w; }
        if(strtolower($w) == "loc"){ $data['location'] = TRUE; }
    }
    if(!isset($data['username']) or !isset($data['team'])){ return; }
    if(!isset($data['lvl'])){ $data['lvl'] = 1; }
    $data['username'] = str_replace("@", "", $data['username']);
    if($pokemon->user($data['username'], FALSE)){ // Online
        $telegram->send
            ->notification(FALSE)
            ->text($telegram->emoji(":warning: Es usuario real."))
        ->send();
        return;
    }
    $register = $pokemon->register_offline($data['username'], $data['team'], $telegram->user->id, $data['lvl']);
    if($register){
        $icon = ['Y' => ':heart-yellow:', 'R' => ':heart-red:', 'B' => ':heart-blue:'];
        $icon_text = ['Y' => 'amarillo', 'R' => 'rojo', 'B' => 'azul'];
        $this->analytics->event("Telegram", "Register offline", $icon_text[$data['team']]);
        $text = ":ok: Registro a @" .$data['username'] ." " .$icon[$data['team']] ." L" .$data['lvl'];
        $pokemon->log($telegram->user->id, 'register_offline', $register);
    }else{
        $uoff = $pokemon->user_offline($data['username']);
        if($uoff){
            $text = ":banned: Usuario ya registrado.";
            if($data['lvl'] > $uoff->lvl){
                $text = ":ok: Ya registrado, subo nivel *$uoff->lvl -> " .$data['lvl'] ."*.";
                $pokemon->update_user_offline_data($uoff->id, 'lvl', $data['lvl']);
                $pokemon->log($telegram->user->id, 'lvl_offline', $register);
            }
        }else{
            $text = ":forbid: Error general.";
        }
    }
    $telegram->send
        ->notification(FALSE)
        ->text($telegram->emoji($text), TRUE)
    ->send();
    return;
}

if($telegram->text_command("prueba")){
    /* if(function_exists("time_parse")){
        $datos = time_parse($telegram->text());
        $telegram->send->text(json_encode($datos))->send();
        return;
    }*/

    $telegram->send->text($this->config->item('creator'))->send();
}

?>
