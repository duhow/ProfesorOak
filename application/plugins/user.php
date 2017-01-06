<?php

function user_set_name($user, $name, $force = FALSE){
	$telegram = new Telegram();
	$pokemon = new Pokemon();
	$analytics = new Analytics();

	$pokeuser = $pokemon->user($user);
	if(empty($pokeuser)){ return; }
	if(!$force && !empty($pokeuser->username)){ return; }
	if($name[0] == "@"){ $name = substr($name, 1); }
	if(strlen($name) < 4 or strlen($name) > 18){ return; }

	// si el nombre ya existe
	if($pokemon->user_exists($name)){
		$telegram->send
			->reply_to(TRUE)
			->notification(FALSE)
			->text("No puede ser, ya hay alguien que se llama *@$name* :(\nHabla con @duhow para arreglarlo.", TRUE)
		->send();
		return FALSE;
	}
	// si no existe el nombre
	else{
		$analytics->event('Telegram', 'Register username');
		$pokemon->update_user_data($user, 'username', $name);
		$str = "De acuerdo, *@$name*!\n"
				."¡Recuerda *validarte* para poder entrar en los grupos de colores!";
		$telegram->send
			->inline_keyboard()
				->row_button("Validar perfil", "quiero validarme", TRUE)
			->show()
			->reply_to(TRUE)
			->notification(FALSE)
			->text($str, TRUE)
		->send();
	}
	return TRUE;
}

if($pokemon->step($telegram->user->id) == "SETNAME"){
	if($telegram->words() == 1){ user_set_name($telegram->user->id, $telegram->last_word(TRUE), TRUE); }
	$pokemon->step($telegram->user->id, NULL);
}

// -----------------

// guardar nombre de user
if($telegram->text_has(["Me llamo", "Mi nombre es", "Mi usuario es"], TRUE) && $telegram->words() <= 4 && $telegram->words() > 2){
	$pokeuser = $pokemon->user($telegram->user->id);
	if(!empty($pokeuser->username)){ return -1; }
	$word = $telegram->last_word(TRUE);
	user_set_name($telegram->user->id, $word, FALSE);
	return -1;
}

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

// pedir info sobre uno mismo
if(
	$telegram->text_has(["Quién soy", "Cómo me llamo", "who am i"], TRUE) or
	($telegram->text_has(["profe", "oak"]) && $telegram->text_has("Quién soy") && $telegram->words() <= 5)
){
	$str = "";
	$team = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];

	$pokeuser = $pokemon->user($telegram->user->id);

	$this->analytics->event('Telegram', 'Whois', 'Me');
	if(empty($pokeuser->username)){ $str .= "No sé como te llamas, sólo sé que "; }
	else{ $str .= '$pokemon, '; }

	$str .= 'eres *$team* $nivel. $valido';

	// si el bot no conoce el nick del usuario
	if(empty($pokeuser->username)){ $str .= "\nPor cierto, ¿cómo te llamas *en el juego*? \n_Me llamo..._"; }

	// $chat = ($telegram->is_chat_group() && $this->is_shutup() && !in_array($telegram->user->id, $this->admins(TRUE)) ? $telegram->user->id : $telegram->chat->id);

	$repl = [
		'$nombre' => $telegram->user->first_name,
		'$apellidos' => $telegram->user->last_name,
		'$equipo' => $team[$pokeuser->team],
		'$team' => $team[$pokeuser->team],
		'$usuario' => "@" .$telegram->user->username,
		'$pokemon' => "@" .$pokeuser->username,
		'$nivel' => "L" .$pokeuser->lvl,
		'$valido' => ($pokeuser->verified ? ':green-check:' : ':warning:')
	];

	$str = str_replace(array_keys($repl), array_values($repl), $str);
	if($pokemon->settings($telegram->user->id, 'last_command') == "LEVELUP"){
		if($chat != $telegram->chat->id){
			/* $telegram->send
				->chat($this->config->item('creator'))
				->text("Me revelo contra " .$pokeuser->username ." " .$user->id ." en " .$telegram->chat->id)
			->send();

			$str = "¿Eres tonto o que? Ya te lo he dicho antes. ¿Puedes parar ya?"; */
		}
	}
	$pokemon->settings($telegram->user->id, 'last_command', 'WHOIS');

	$telegram->send
		// ->chat($chat)
		// ->reply_to( ($chat == $telegram->chat->id) )
		->notification(FALSE)
		->text($telegram->emoji($str), TRUE)
	->send();
	return -1;
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
elseif($telegram->text_has("que") && $telegram->text_has(["lvl", "level", "nivel"], ["eres", "es", "soy", "tiene", "tienes", "tengo"]) && $telegram->words() <= 7){
    $user = $telegram->user->id;
    if($telegram->text_has(["eres", "es", "tiene", "tienes"])){
        if(!$telegram->has_reply){ return; }
        $user = $telegram->reply_user->id;
    }

    $u = $pokemon->user($user);
    $text = NULL;
    if(!empty($u) && $u->lvl >= 5){
        $this->analytics->event('Telegram', 'Whois', 'Level');
        $text = ($telegram->text_has(["eres", "es", "tienes", "tiene"]) ? "Es" : "Eres") ." L" .$u->lvl .".";
    }else{
        $text = ($telegram->text_has("soy", "tengo") ? "No lo sé. ¿Y si me lo dices?" : "No lo sé. :(");
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

?>
