<?php

// HACK para acelerar el procesamiento.
if($this->telegram->text_contains(["pole", "bronce"]) && !$this->telegram->is_chat_group()){ return -1; }

if(!$telegram->is_chat_group()){ return; }

if($telegram->text_has(["pole", "subpole", "bronce"], TRUE) or $telegram->text_command("pole") or $telegram->text_command("subpole")){
    $this->analytics->event("Telegram", "Pole");
    $pole = $pokemon->settings($telegram->chat->id, 'pole');
    if(
		time() % 3600 < 1 or // Tiene que haber pasado un segundo de la hora en punto.
		($pole != NULL && $pole == FALSE) or
		$pokemon->settings($telegram->user->id, 'no_pole') or
		$pokemon->group_users_active($telegram->chat->id, TRUE) < 6
	){ return -1; }

    // Si está el Modo HARDCORE, la pole es cada hora. Si no, cada día.
    $timer = ($pokemon->settings($telegram->chat->id, 'pole_hardcore') ? "H" : "d");

	$pole = $pokemon->settings($telegram->chat->id, 'pole_points');
    if(!empty($pole)){
        $pole = unserialize($pole);
        if(
            ( $telegram->text_has("pole", TRUE) &&    is_numeric($pole[0]) && date($timer) == date($timer, $pole[0]) ) or
            ( $telegram->text_has("subpole", TRUE) && is_numeric($pole[1]) && date($timer) == date($timer, $pole[1]) ) or
            ( $telegram->text_has("bronce", TRUE) &&  is_numeric($pole[2]) && date($timer) == date($timer, $pole[2]) )
        ){
            if(date("G") == 23){
                $lim = $pokemon->settings($telegram->user->id, 'pole_adelantado');
                if(empty($lim)){ $lim = 0; }
                $lim++; // +1

                if($lim == 1){
                    $str = 'La impaciencia con las poles te costará tu futuro. Ten cuidado.';
                }elseif($lim == 2){
                    $str = 'El que avisa no es traidor, a la próxima te quedas sin poles.';
                }elseif($lim >= 3){
                    $str = "Pues nada. Te quedas sin poles.";
                    $pokemon->settings($telegram->user->id, 'no_pole', TRUE);
                    // $pokemon->update_user_data($telegram->user->id, 'pole', 0);

                    // Avisar en el grupo.
                    $telegram->send
                        ->reply_to(TRUE)
                        ->notification(FALSE)
                        ->text($telegram->user->first_name ." se queda sin poles por adelantarse.")
                    ->send();
                }

                // Guardar
                $pokemon->settings($telegram->user->id, 'pole_adelantado', $lim);

                // Avisar por privado
                $telegram->send
                    ->notification(TRUE)
                    ->chat($telegram->user->id)
                    ->text($str)
                ->send();
            }
            return -1;  // Mismo dia? nope.
        }
    }
	// $this->db->query('LOCK TABLE settings WRITE, user WRITE, user_flags WRITE, user_inchat WRITE');
    $pole_user = unserialize($pokemon->settings($telegram->chat->id, 'pole_user'));
    $pkuser = $pokemon->user($telegram->user->id);
    if($pkuser){
        $timeuser = $pokemon->settings($pkuser->telegramid, 'lastpole');
        if(empty($timeuser)){ $timeuser = 0; }
    }

    if($telegram->text_has("pole", TRUE)){ // and date($timer) != date($timer, $pole[0])
        $pole = [time(), NULL, NULL];
        $pole_user = [$telegram->user->id, NULL, NULL];
        $action = "la *pole*";
        if($pkuser && $timer == "d"){
            if(date("d") != $timeuser){
                $pokemon->update_user_data($pkuser->telegramid, 'pole', ($pkuser->pole + 3));
                $pokemon->settings($pkuser->telegramid, 'lastpole', date("d"));
            }
        }
    }elseif($telegram->text_has("subpole", TRUE) and date($timer) == date($timer, $pole[0]) and $pole_user[1] == NULL){
        if(in_array($telegram->user->id, $pole_user)){ return -1; } // Si ya ha hecho pole, nope.
        $pole[1] = time();
        $pole_user[1] = $telegram->user->id;
        $action = "la *subpole*";
        if($pkuser && $timer == "d"){
            if(date("d") != $timeuser){
                $pokemon->update_user_data($pkuser->telegramid, 'pole', ($pkuser->pole + 2));
                $pokemon->settings($pkuser->telegramid, 'lastpole', date("d"));
            }
        }
    }elseif($telegram->text_has("bronce", TRUE) and date($timer) == date($timer, $pole[0]) and $pole_user[1] != NULL and $pole_user[2] == NULL){
        if(in_array($telegram->user->id, $pole_user)){ return -1; } // Si ya ha hecho sub/pole, nope.
        $pole[2] = time();
        $pole_user[2] = $telegram->user->id;
        $action = "el *bronce*";
        if($pkuser && $timer == "d"){
            if(date("d") != $timeuser){
                $pokemon->update_user_data($pkuser->telegramid, 'pole', ($pkuser->pole + 1));
                $pokemon->settings($pkuser->telegramid, 'lastpole', date("d"));
            }
        }
    }else{
        return -1;
    }

    $pokemon->settings($telegram->chat->id, 'pole_points', serialize($pole));
    $pokemon->settings($telegram->chat->id, 'pole_user', serialize($pole_user));
	// $this->db->query('UNLOCK TABLES');
    $telegram->send->text($telegram->user->first_name ." ha hecho $action!", TRUE)->send();
    // $telegram->send->text("Lo siento " .$telegram->user->first_name .", pero hoy la *pole* es mía! :D", TRUE)->send();
    return -1;
}

if($telegram->text_command("polerank") or $telegram->text_has("!polerank")){
    $poleuser = $pokemon->settings($telegram->chat->id, 'pole_user');
    $pole = $pokemon->settings($telegram->chat->id, 'pole');

    if($pole == FALSE){ return; }

    $pole = $pokemon->settings($telegram->chat->id, 'pole_points');
    if($pole == NULL or ($pole === TRUE or $pole === 1)){
        $telegram->send
            ->text("Nadie ha hecho la *pole*.", TRUE)
        ->send();
        return;
    }

    $pole = unserialize($pole);
    $poleuser = unserialize($poleuser);
    $hardcore = $pokemon->settings($telegram->chat->id, 'pole_hardcore');

    $str = $telegram->emoji(":warning:") ." *Pole ";
    $str .= ($hardcore ? "de las " .date("H", $pole[0]) ."h" : "del " .date("d", $pole[0])) ."*:\n\n";

    foreach($poleuser as $n => $u){
        $ut = $telegram->emoji(":question-red:");
        $points = NULL;
        if(!empty($u)){
            $user = $pokemon->user($u);
            $ut = (!empty($user->username) ? $user->username : $user->telegramuser);
            $points = $user->pole;
        }

        $str .= $telegram->emoji(":" .($n + 1) .": ") .$ut .($points ? " (*$points*)" : "") ."\n";
    }

    $telegram->send
        ->text($str, TRUE)
    ->send();
    return;
}

?>
