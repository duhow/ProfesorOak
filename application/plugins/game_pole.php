<?php

function pole_can_type($user, $group, $pole_type = 1){
    $CI =& get_instance();

    $query = $CI->db
        ->select('type', 'uid')
        ->where('cid', $group)
        ->where('date', date("Y-m-d"))
    ->get('pole');

    // Si nadie ha hecho nada, solo puede hadcer la pole
    if($query->num_rows() == 0){ return ($pole_type == 1); }

    $can = FALSE;
    $types = array_column($query->result_array(), 'type');
    $users = array_column($query->result_array(), 'uid');

    // Si alguien ha hecho la pole
    // solo puede hacer la subpole.
    if(
        $query->num_rows() == 1 and
        in_array(1, $types) and
        $pole_type == 2
    ){
        $can = TRUE;

    // Si alguien ha hecho la subpole
    // solo puede hacer el bronce.
    }elseif(
        $query->num_rows() == 2 and
        in_array(2, $types) and
        $pole_type == 3
    ){
        $can = TRUE;
    }

    // Si el usuario ya ha hecho algo,
    // No puede volver a hacer otra cosa.
    if($can and in_array($user, $users)){ $can = FALSE; }
    return $can;
}

function pole_can_group($group, $register = FALSE){
    $CI =& get_instance();
    if($register === FALSE){
        $query = $CI->db
            ->where('chat', $group)
            ->where('date', date("Y-m-d"))
        ->get('poleauth');
        return ($query->num_rows() == 1);
    }
    if(is_bool($register)){ $register = date("Y-m-d", strtotime("+1 day")); } // Para el día siguiente
    elseif(is_string($register)){ $register = date("Y-m-d", strtotime($register)); }

    $data = ['chat' => $group, 'date' => $register];
    return $CI->db->insert('poleauth', $data);
}

function pole_group_clean($group){
    $CI =& get_instance();

    // TRUE = ALL
    if($group !== TRUE){
        if(!is_array($group)){ $group = [$group]; }
        return $CI->db
            ->where_in('chat', $group)
        ->delete('poleauth');
    }

    return $CI->db->query("TRUNCATE TABLE poleauth");
}

function pole_add($user, $group, $pole_type){
    $CI =& get_instance();
    $data = [
        'cid' => $group,
        'uid' => $user,
        'type' => $pole_type,
        'date' => date("Y-m-d"),
    ];

    return $CI->db->insert('pole', $data);
}

function pole_lock($action = TRUE){
    $CI =& get_instance();
    if($action){
        return $CI->db->query("LOCK TABLES pole WRITE;");
    }
    return $CI->db->query("UNLOCK TABLES");
}


// HACK para acelerar el procesamiento.
if($this->telegram->text_contains(["pole", "bronce"]) && !$this->telegram->is_chat_group()){ return -1; }

if(!$telegram->is_chat_group()){ return; }

if($telegram->text_has(["pole", "subpole", "bronce"], TRUE) or $telegram->text_command("pole") or $telegram->text_command("subpole")){
    // $this->analytics->event("Telegram", "Pole"); // HACK TEMP
    if(
        !pole_can_group($telegram->chat->id) or // El grupo tiene que estar en la lista para poder hacer poles.
		time() % 3600 < 1 or // Tiene que haber pasado un segundo de la hora en punto.
		$pokemon->settings($telegram->user->id, 'no_pole')
	){ return -1; }

    // Si está el Modo HARDCORE, la pole es cada hora. Si no, cada día.
    // $timer = ($pokemon->settings($telegram->chat->id, 'pole_hardcore') ? "H" : "d");
    $timer = "d"; // DEBUG TEMP

    if($telegram->text_has("pole", TRUE)){
        $pole = 1;
        $action = "la *pole*";
    }
    elseif($telegram->text_has("subpole", TRUE)){
        $pole = 2;
        $action = "la *subpole*";
    }
    elseif($telegram->text_has("bronce", TRUE)){
        $pole = 3;
        $action = "el *bronce*";
    }

    pole_lock(TRUE);

    if(!pole_can_type($telegram->user->id, $telegram->chat->id, $pole)){ return -1; }
    pole_add($telegram->user->id, $telegram->chat->id, $pole);

    pole_lock(FALSE);

    $timeuser = $pokemon->settings($pkuser->telegramid, 'lastpole');
    if(empty($timeuser)){ $timeuser = 0; }

    if(date("d") != $timeuser){
        $points = (4 - $pole);
        $pokemon->update_user_data($telegram->user->id, 'pole', ($pkuser->pole + $points));
        $pokemon->settings($telegram->user->id, 'lastpole', date("d"));
    }

    $telegram->send->text($telegram->user->first_name ." ha hecho $action!", TRUE)->send();
    // $telegram->send->text("Lo siento " .$telegram->user->first_name .", pero hoy la *pole* es mía! :D", TRUE)->send();
    return -1;
}

if($telegram->text_command("polerank") or $telegram->text_has("!polerank")){
	if($pokemon->command_limit("polerank", $telegram->chat->id, $telegram->message, 7)){ return -1; }

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

elseif($telegram->text_command("poleauth") and $telegram->user->id == $this->config->item('creator')){
    $query = $this->db
        ->select('id')
        ->where_in('type', ['group', 'supergroup'])
        ->where('active', TRUE)
    ->get('chats');

    $chats = array_column($query->result_array(), 'id');

    $q = $this->telegram->send
        ->notification(TRUE)
        ->text("Hay " .count($chats) ." grupos.\n" .$this->telegram->emoji(":clock: ") ."Procesando...")
    ->send();

    pole_group_clean(TRUE);

    $i = 0;
    // Hacer las comprobaciones para permitir la pole.
    // TODO comprobar si hay más de un bot polero.
    foreach($chats as $c){
        $pole = $pokemon->settings($c, 'pole');
        $members = $pokemon->group_users_active($c, TRUE);

        if(
            ($pole != NULL && $pole == FALSE) or
            ($members < 6)
        ){ continue; }

        pole_can_group($c, TRUE);
        $i++;
    }

    $this->telegram->send
        ->notification(TRUE)
        ->message($q['message_id'])
        ->chat(TRUE)
        ->text("Hay " .count($chats) ." grupos.\n" .$this->telegram->emoji(":ok: ") ."Activado en $i.")
    ->edit('text');

    return -1;
}

?>
