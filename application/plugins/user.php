<?php

function user_set_name($user, $name, $force = FALSE){
	$telegram = new Telegram();
	$pokemon = new Pokemon();
	$analytics = new Analytics();

	$pokeuser = $pokemon->user($user);
	if(empty($pokeuser)){ return; }
	if(!$force && !empty($pokeuser->username)){ return; }

	$accents = [
		'á' => 'a',
		'é' => 'e',
		'í' => 'i',
		'ó' => 'o',
		'ú' => 'u'
	];

	$name = str_replace(array_keys($accents), array_values($accents), $name);

	if($name[0] == "@"){ $name = substr($name, 1); }
	if(strlen($name) < 4 or strlen($name) > 18){ return; }

	// si el nombre ya existe
	if($pokemon->user_exists($name)){
		$telegram->send
			->reply_to(TRUE)
			->notification(FALSE)
			->text("No puede ser, ya hay alguien que se llama *@$name* :(\nNo haberte borrado el Telegram. Ale.", TRUE)
		->send();

		$str = $telegram->emoji(':warning: ') .'<a href="tg://user?id=' .$telegram->user->id .'">' .$telegram->user->id .'</a> se registra con nombre ocupado ' .$name;

		$telegram->send
			->notification(FALSE)
			->chat("-1001108551764")
			->text($str, 'HTML')
		->send();
		return FALSE;
	}
	// si no existe el nombre
	else{
		$analytics->event('Telegram', 'Register username');
		$pokemon->update_user_data($user, 'username', $name);

		if(function_exists('report_user_get')){
			$reps = report_user_get($name);
			if($reps and count($reps) > 0){
				$telegram->send
					->notification(TRUE)
					->chat("-246585563")
					->text($telegram->emoji(":warning: ") ."Se ha registrado el usuario $name.")
				->send();
			}
		}

		$str = "De acuerdo, *@$name*!\n";
		if($pokemon->settings($telegram->chat->id, 'require_verified')){
			// $str .= "Para estar en este grupo *debes estar validado.*";
		}else{
			// $str .= "¡Recuerda *validarte* para poder entrar en los grupos de colores!";
		}

		$telegram->send
			/* ->inline_keyboard()
				->row_button("Validar perfil", "quiero validarme", TRUE)
				->show() */
			->reply_to(TRUE)
			->notification(FALSE)
			->text($str, TRUE)
		->send();
	}
	return TRUE;
}

function user_reports($name, $retall = FALSE, $valid = TRUE){
	return NULL;
	$CI =& get_instance();
	if($valid){ $CI->db->where('valid', TRUE); }
	$query = $CI->db
		->where('reported', $name)
	->get('reports');

	if($query->num_rows() == 0){ return NULL; }
	if(!$retall){ return $query->num_rows(); }
	return $query->result_array();
}

function user_parser($text){
	$data = array();
	foreach($text as $w){
	    $w = trim($w);
	    if($w[0] == "/"){ continue; }
	    if(is_numeric($w) && $w >= 5 && $w <= POKEMON_GO_LEVEL_MAX){ $data['lvl'] = $w; }
	    if(in_array(strtoupper($w), ['R','B','Y'])){ $data['team'] = strtoupper($w); }
	    if($w[0] == "@" or strlen($w) >= 4){ $data['username'] = $w; }
	    if(strtolower($w) == "loc"){ $data['location'] = TRUE; }
	}
	if(!isset($data['username']) or !isset($data['team'])){ return FALSE; }
	if(!isset($data['lvl'])){ $data['lvl'] = 1; }
	$data['username'] = str_replace("@", "", $data['username']);
	return $data;
}

$step = $pokemon->step($telegram->user->id);
if($step == "SETNAME"){
	if($telegram->words() == 1 && !$telegram->text_command()){ user_set_name($telegram->user->id, $telegram->last_word(TRUE), TRUE); }
	$pokemon->step($telegram->user->id, NULL);
}elseif($step == "CHANGE_LEVEL" and $this->telegram->text()){
	$pokeuser = $pokemon->user($telegram->user->id);
	$pokemon->step($pokeuser->telegramid, NULL);

	if($this->telegram->words() > 4 or $this->telegram->has_forward){ return -1; }

	$level = filter_var($this->telegram->text(), FILTER_SANITIZE_NUMBER_INT);
	if(is_numeric($level)){
		if($level == $pokeuser->lvl){
			$telegram->send
				->text("Si, ya lo sé.")
			->send();
		}elseif($level > $pokeuser->lvl){
			if($level <= 35){
				$pokemon->update_user_data($pokeuser->telegramid, 'lvl', $level);
				$telegram->send
					->text("Ah, ya veo, así que $level ... Guay!")
				->send();
			}elseif($level > 35 && $level <= (POKEMON_GO_LEVEL_MAX - 1)) {
				$pokemon->step($pokeuser->telegramid, "LEVEL_SCREENSHOT");
				$pokemon->settings($pokeuser->telegramid, "levelup_new", $level);
				$telegram->send
					->text("En serio? Pues... Mándame captura para demostrarlo.")
				->send();
			}
		}
	}
	return -1;
}elseif($step == "LEVEL_SCREENSHOT"){
	if($telegram->photo() and !$telegram->has_forward){
		$pokeuser = $pokemon->user($telegram->user->id);
		$level = $pokemon->settings($telegram->user->id, "levelup_new");
		$pokemon->settings($telegram->user->id, "levelup_new", "DELETE");

		$str = ":ok: ¡Guay! La miro en un rato.";
		if(date("G") <= 1 or date("G") >= 23){
			$str = ":ok: ¡Guay! Te la miro cuando me despierte mañana...";
		}elseif(date("G") > 1 && date("G") <= 8){
			$str = ":ok: ¡Guay! Te la miro cuando esté despierto...";
		}

		$telegram->send
			->reply_to(TRUE)
			->text($telegram->emoji($str))
		->send();

		$str = "LEVELUP " .$telegram->emoji("|>") ." $level: @" .$pokeuser->username ." - " .$telegram->user->id;
		$telegram->send
			->chat("-211635275") // Niveles
			->text($str)
		->send();

		$telegram->send
			->message(TRUE)
			->chat(TRUE)
			->forward_to("-211635275") // Niveles
		->send();

		$pokemon->step($telegram->user->id, NULL);
		return -1;
	}
}

// -----------------

// guardar nombre de user
if($telegram->text_has(["Me llamo", "Mi nombre es", "Mi usuario es"], TRUE) && $telegram->words() <= 4 && $telegram->words() > 2){
	if($telegram->text_command()){ return -1; }
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
	$telegram->text_has(["Acabo de subir al", "He subido a nivel", "He subido al"]) &&
	!$telegram->text_has("No") and // Si, la gente es gilipollas.
	!$telegram->text_contains("?") // Anda que preguntar qué nivel eres de esta forma...
){
	if($this->telegram->has_forward){ return -1; }
	$level = filter_var($telegram->text(), FILTER_SANITIZE_NUMBER_INT);
	if(is_numeric($level)){
	    $pokeuser = $pokemon->user($telegram->user->id);
	    $command = $pokemon->settings($telegram->user->id, 'last_command');
	    if($level == $pokeuser->lvl){ // or $command == "LEVELUP"
	        $telegram->send
	            ->notification(FALSE)
	            ->text("Si, ya lo sé...")
	        ->send();
			return -1;
	    }
	    $this->analytics->event('Telegram', 'Change level', $level);
	    $pokemon->settings($telegram->user->id, 'last_command', 'LEVELUP');
	    if($level >= 5 && $level <= POKEMON_GO_LEVEL_MAX){
	        // if($level <= $pokeuser->lvl){ return; }
	        $pokemon->update_user_data($telegram->user->id, 'lvl', $level);
			$pokemon->update_user_data($telegram->user->id, 'exp', 0);
	        $pokemon->log($telegram->user->id, 'levelup', $level);
	        if($telegram->is_chat_group()){ // $command == "WHOIS" &&
				$editwho = $pokemon->settings($telegram->chat->id, 'whois_last');
				if(!empty($editwho)){
					// el mensaje será sobre self, imagino
					// pero puede ser que haga un quien es (no yo)
					// y responder yo, con lo que me invalida este comando.
					// FIXME solución: el quien es tendrá el UID de la persona
					// no del que lo escribe, si no sobre quien informa.
					$editwho = unserialize($editwho);
					$telegram->send
						->message($editwho[1])
						->chat(TRUE)
						->text("") // TODO
					->edit('text');

					$pokemon->settings($telegram->chat->id, 'whois_last', 'DELETE');
				}
	        }
		$telegram->send
	                ->notification(FALSE)
	                ->text("Así que has subido al nivel *$level*... Guay!", TRUE)
	                // Vale. Como me vuelvas a preguntar quien eres, te mando a la mierda. Que lo sepas.
		->send();

		/* if($level >= 35){
			$str = $pokeuser->username ." - " .$pokeuser->telegramid ." cambia de " .$pokeuser->lvl ." a " .$level ." (" .($level - $pokeuser->lvl) .") " .(($level - $pokeuser->lvl) >= 3 ? "\u{203c}\u{fe0f}" : "")  ;
	
			$telegram->send
				->chat("-211635275")
				->text($str)
				->notification( (($level - $pokeuser->lvl) >= 3) )
			->send();
		} */
	    }elseif($level > 35 && $level <= POKEMON_GO_LEVEL_MAX){
			if($level <= $pokeuser->lvl){ return; }
			if($pokeuser->lvl == 1){
				$telegram->send
					->text("Bueno... No sé si creermelo, pero...")
				->send();
				$pokemon->update_user_data($telegram->user->id, 'lvl', $level);
				$pokemon->update_user_data($telegram->user->id, 'exp', 0);
			}elseif($pokeuser->lvl < 35){
				// Control de que el usuario sea un nivel inferior al que dice ser para poder controlarlo.
				$telegram->send
					->text("Si, ya. Claro.")
				->send();
			// TODO Control de tiempo según log para validar esto.
			}elseif($pokeuser->lvl != $level - 1){
				$telegram->send
					->text("¿Tan rápido has subido? No me lo creo.")
				->send();
			}else{
				$pokemon->step($telegram->user->id, 'LEVEL_SCREENSHOT');
				$telegram->send
					->text("¡Guau! Pues... Mándame captura para confirmarlo, anda.")
				->send();
			}
		}

		if(
			$pokeuser->lvl == 1 &&
			!empty($pokeuser->username) &&
			!$pokeuser->verified
		){
			// $pokemon->step($telegram->user->id, 'SCREENSHOT_VERIFY');

			$str = "¡Genial! Ahora mándame una captura de pantalla de tu <b>perfil Pokémon GO</b> para validarte.\n"
					."¡Recuerda que tiene que ser <b>de ahora<b>, no una antigua!";
			$telegram->send
				->text($str, 'HTML')
			->send();
		}
	}
	return -1;
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

	$str .= 'eres *$team* $nivel';
	if($pokeuser->exp > 0){
		$str .= ' - $exp XP';
	}
	$str .= '. $valido';

	if($pokeuser->authorized){ $str .= $telegram->emoji(" :star: "); }

	// si el bot no conoce el nick del usuario
	if(empty($pokeuser->username)){ $str .= "\nPor cierto, ¿cómo te llamas *en el juego*? \n_Me llamo..._"; }

	// $chat = ($telegram->is_chat_group() && $this->is_shutup() && !in_array($telegram->user->id, $this->admins(TRUE)) ? $telegram->user->id : $telegram->chat->id);

	$repl = [
		'$nombre' => $telegram->user->first_name,
		'$apellidos' => $telegram->user->last_name,
		'$equipo' => $team[$pokeuser->team],
		'$team' => $team[$pokeuser->team],
		'$usuario' => $telegram->user->username,
		'$pokemon' => $pokeuser->username,
		'$nivel' => "L" .$pokeuser->lvl,
		'$exp' => number_format($pokeuser->exp, 0, ',', '.'),
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

	if(!empty($pokeuser->username)){
		$this->telegram->send
		->inline_keyboard()
			->row_button($telegram->emoji("\ud83d\udcdd Ver perfil"), "http://profoak.me/user/" .$pokeuser->username)
		->show();
	}

	$q = $telegram->send
		// ->chat($chat)
		// ->reply_to( ($chat == $telegram->chat->id) )
		->notification(FALSE)
		->text($telegram->emoji($str), TRUE)
	->send();

	if($telegram->is_chat_group()){
		$data = [$telegram->user->id, $q['message_id']];
		$pokemon->settings($telegram->chat->id, 'whois_last', serialize($data));
	}
	return -1;
}

elseif(
	( $telegram->text_has("quién", ["es", "eres"]) or
	$telegram->text_has("Conoces", "a") ) &&
	!$telegram->text_contains(["programa", "esta"]) &&
	$telegram->words() <= 5
){
	$str = "";
	$offline = FALSE;
	$teams = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];
	$user_search = NULL;
	// pregunta usando respuesta
	if($telegram->has_reply){
		$this->analytics->event('Telegram', 'Whois', 'Reply');

		$user_search = $telegram->reply_target('forward')->id;
	}else{
		$this->analytics->event('Telegram', 'Whois', 'User');
		if($telegram->text_mention()){
			$text = $telegram->text_mention();
			if(is_array($text)){ $text = key($text); }
		}
		elseif($telegram->words() == 4){ $text = $telegram->words(2); } // 2+1 = 3 palabra
		else{ $text = $telegram->last_word(); } // Si no hay mención, coger la última palabra
		$text = $telegram->clean('alphanumeric', $text);
		if(strlen($text) < 4){ return; }
		if(in_array($text, ["creado", "creador"])){ $text = "duhow"; } // Quien es tu creador?
		if(text_find(["quien", "quién", "quin", "este", "aqui", "aquí", "eres"], $text)){ return; } // Quien es quien?
		$pk = pokemon_parse($text);
		if(!empty($pk['pokemon'])){ /* $this->_pokedex($pk['pokemon']); */ return; } // TODO FIXME
		$user_search = $text;
	}

	$info = $pokemon->user($user_search);

	// si el usuario por el que se pregunta es el bot
	if($telegram->has_reply && $telegram->reply_user->id == $this->config->item("telegram_bot_id") && !$telegram->reply_is_forward){
		$str = "¡Pues ese soy yo mismo! ";
	// si es un bot
	}elseif(strtolower(substr($user_search, -3)) == "bot"){
		$str = "Es un bot."; // Yo no me hablo con los de mi especie.\nSi, queda muy raro, pero nos hicieron así...";
	// si no se conoce
	}elseif(empty($info)){
		$str = "No sé quien es $user_search.";
		// User offline
		$info = $pokemon->user_offline($user_search);
		if(!empty($info)){
			$offline = TRUE;
			$str = 'Es *$team* $nivel. :question-red:';
			$reps = user_reports($user_search, TRUE);
			if(!empty($reps)){
				$reptype = array_column($reps, 'type');
				$reptype = array_unique($reptype);
				$str .= "\n" .$this->telegram->emoji("\ud83d\udcdb ") ."*" .count($reps) ."* reportes";
				if(!empty($reptype)){ $str .= " por " .implode(", ", $reptype); }
				$str .= ".";
			}
			$ma = report_multiaccount_exists($user_search, TRUE);
			if(!empty($ma)){
				$str .= "\n" .$this->telegram->emoji("\ud83d\udc65 ") .count($ma['usernames']) ." cuentas agrupadas. #" .$ma['grouping'];
			}
		}

	}else{
		if(empty($info->username)){
			$str = "No sé como se llama, sólo sé que ";
		}else{
			$str = '$pokemon, ';
		}
		$str .= 'es *$team* $nivel. $valido' ."\n";

		if(!empty($info->username)){
			$reps = user_reports($info->username, TRUE);
			if(!empty($reps)){
				$reptype = array_column($reps, 'type');
				$reptype = array_unique($reptype);
				$str .= "\n" .$this->telegram->emoji("\ud83d\udcdb ") ."*" .count($reps) ."* reportes";
				if(!empty($reptype)){ $str .= " por " .implode(", ", $reptype); }
				$str .= ".";
			}
			$ma = report_multiaccount_exists($user_search, TRUE);
			if(!empty($ma)){
				$str .= "\n" .$this->telegram->emoji("\ud83d\udc65 ") .count($ma['usernames']) ." cuentas agrupadas. #" .$ma['grouping'];
			}
		}
	}

	if(!empty($info)){
		$flags = $pokemon->user_flags($info->telegramid);

		// añadir emoticonos basado en los flags del usuario REPETIDO
		// if($info->verified){ $str .= $telegram->emoji(":green-check: "); }
		// else{ $str .= $telegram->emoji(":warning: "); }
		// ----------------------
		if($info->blocked){ $str .= $telegram->emoji(":forbid: "); }
		if($info->authorized){ $str .= $telegram->emoji(":star: "); }
		if(!empty($flags)){
			if(in_array("ratkid", $flags)){ $str .= $telegram->emoji(":mouse: "); }
			if(in_array("multiaccount", $flags)){ $str .= $telegram->emoji(":multiuser: "); }
			if(in_array("gps", $flags)){ $str .= $telegram->emoji(":antenna: "); }
			if(in_array("bot", $flags)){ $str .= $telegram->emoji(":robot: "); }
			if(in_array("fly", $flags)){ $str .= $telegram->emoji("\ud83d\udd79 "); }
			if(in_array("rager", $flags)){ $str .= $telegram->emoji(":fire: "); }
			if(in_array("troll", $flags)){ $str .= $telegram->emoji(":joker: "); }
			if(in_array("spam", $flags)){ $str .= $telegram->emoji(":spam: "); }
			if(in_array("hacks", $flags)){ $str .= $telegram->emoji(":laptop: "); }
			if(in_array("enlightened", $flags)){ $str .= $telegram->emoji(":frog: "); }
			if(in_array("resistance", $flags)){ $str .= $telegram->emoji(":key: "); }
			if(in_array("donator", $flags)){ $str .= $telegram->emoji("\ud83d\udcb6 "); }
			if(in_array("helper", $flags)){ $str .= $telegram->emoji(":beginner: "); }
			if(in_array("gay", $flags)){ $str .= $telegram->emoji(":rainbow_flag: "); }
			if(in_array("spain", $flags)){ $str .= $telegram->emoji(":flag_es: "); }
		}
	}

	if(!empty($str)){
	// $chat = ($telegram->is_chat_group() && $this->is_shutup() && !in_array($telegram->user->id, $this->admins(TRUE)) ? $telegram->user->id : $telegram->chat->id);

	$validicon = ":green-check:";

	if(!$info->verified){
		$validicon = ":warning:";
		$query = $this->db
			->where('telegramid', $info->telegramid)
		->get('user_verify');
		if($query->num_rows() > 0){ $validicon .= " :clock:"; }
	}

	$repl = [
		// '$nombre' => $new->first_name,
		// '$apellidos' => $new->last_name,
		'$equipo' => $teams[$info->team],
		'$team' => $teams[$info->team],
		// '$usuario' => "@" .$new->username,
		'$pokemon' => $info->username,
		'$nivel' => "L" .$info->lvl,
		'$valido' => $validicon
	];

	$str = str_replace(array_keys($repl), array_values($repl), $str);

	if(!empty($info->username) && !$offline){
		$this->telegram->send
		->inline_keyboard()
			->row_button($telegram->emoji("\ud83d\udcdd Ver perfil"), "http://profoak.me/user/" .$info->username)
		->show();
	}
	// $this->last_command('WHOIS');
	$pokemon->settings($telegram->user->id, 'last_command', 'WHOIS');

	// $telegram->send->chat($this->config->item('creator'))->text($text->emoji($str))->send();

		$telegram->send
			// ->chat($chat)
			// ->reply_to( (($chat == $telegram->chat->id && $telegram->has_reply) ? $telegram->reply->message_id : NULL) )
			->notification(FALSE)
			->text($telegram->emoji($str), TRUE)
		->send();
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
	    if(strlen($touch) < 4 or $telegram->words() < 2){ return -1; }
	}
	$name = (isset($telegram->user->username) ? "@" .$telegram->user->username : $telegram->user->first_name);

	$usertouch = $pokemon->user($touch);
	$req = FALSE;

	if(!empty($usertouch)){
		$can = $pokemon->settings($usertouch->telegramid, 'touch');
		$req = FALSE;
		if($can == NULL or $can == TRUE){
			$req = $telegram->send
				->notification(TRUE)
				->chat($usertouch->telegramid)
				->text("$name te ha tocado.")
			->send();
		}
	}

	$text = ($req ? $telegram->emoji(":green-check:") : $telegram->emoji(":times:"));
	$telegram->send
	    ->chat($telegram->user->id)
	    ->notification(!$req)
	    ->text($text)
	->send();
	return -1;
}

// Responder el nivel de un entrenador.
elseif(
	$telegram->text_has("que") &&
	$telegram->text_has(["lvl", "level", "nivel"], ["eres", "es", "soy", "tiene", "tienes", "tengo"]) &&
	$telegram->words() <= 7
){
	$user = $telegram->user->id;
	if($telegram->text_has(["eres", "es", "tiene", "tienes"])){
	    if(!$telegram->has_reply){ return -1; }
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

	if($telegram->is_chat_group() && $pokemon->group_find_member($u->telegramid, $telegram->chat->id)){
		$pokemon->step($u->telegramid, 'CHANGE_LEVEL');
	}

	return -1;
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
	$data = user_parser($telegram->words(TRUE));
	if(!$data){ return -1; }
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

elseif($this->telegram->text_command("regcsv") && isset($this->telegram->reply->document)){
	$doc = (object) $this->telegram->reply->document;
	if($doc->mime_type != "text/csv"){
		$this->telegram->send
			->notification(FALSE)
			->text($this->telegram->emoji(":times: ") ."Archivo no reconocido. Quiero un CSV.")
		->send();

		return -1;
	}elseif(
		$doc->file_size > (5 * 1024) and
		$telegram->user->id != $this->config->item('creator')
	){
		$this->telegram->send
			->notification(FALSE)
			->text($this->telegram->emoji(":times: ") ."Esto pesa mucho.")
		->send();

		return -1;
	}

	$timeout = $pokemon->settings($telegram->chat->id, 'investigation');
	if($timeout > time()){ return -1; }

	$tmp = tempnam("/tmp", "ucsv");
	$r = $this->telegram->download($doc->file_id, $tmp);
	if(!$r){
		$this->telegram->send
			->notification(TRUE)
			->text($this->telegram->emoji(":warning: ") ."Error al descargar.")
		->send();

		return -1;
	}

	$csv = file_get_contents($tmp);
	$csv = str_replace([",", ";", "\t"], " ", $csv);
	$csv = str_replace("\r", "", $csv);
	$csv = explode("\n", $csv);
	unset($tmp);

	$str = $this->telegram->emoji(":clock: ") ."Cuento " .count($csv) ." usuarios.";
	$q = $this->telegram->send
		->text($str)
	->send();

	$pokemon->settings($telegram->chat->id, 'investigation', time() + 180);

	$car = 0; // Real user
	$cok = 0;
	$cup = 0; // Updated users
	foreach($csv as $r){
		$data = user_parser(explode(" ", $r));
		if(!$data){ continue; }
		if($pokemon->user($data['username'], FALSE)){ // Online
			$car++; continue;
		}
		$register = $pokemon->register_offline($data['username'], $data['team'], $telegram->user->id, $data['lvl']);
		if($register){
			$cok++;
		    $pokemon->log($telegram->user->id, 'register_offline', $register);
		}else{
		    $uoff = $pokemon->user_offline($data['username']);
		    if($uoff){
		        if($data['lvl'] > $uoff->lvl){
		            $pokemon->update_user_offline_data($uoff->id, 'lvl', $data['lvl']);
		            $pokemon->log($telegram->user->id, 'lvl_offline', $register);
					$cup++;
		        }
		    }
		}
	}

	$str .= "\n";
	if($cok > 0){ $str .= $this->telegram->emoji(":new: ") .$cok ." nuevos." ."\n"; }
	if($cup > 0){ $str .= $this->telegram->emoji(":male: ") .$cup ." actualizados." ."\n"; }
	if($car > 0){ $str .= $this->telegram->emoji(":ok: ") .$car ." ya registrados." ."\n"; }

	$this->telegram->send
		->message($q)
		->chat(TRUE)
		->text($str)
	->edit('text');
	return -1;
}

elseif(
	$this->telegram->text_command("exp") and
	$this->telegram->user->id == $this->config->item('creator') and
	$this->telegram->words() == 2 and
	$this->telegram->has_reply
){
	return -1;
	$target = $this->telegram->reply_target('forward')->id;
	$pkuser = $pokemon->user($target);

	$exp = $this->telegram->last_word(TRUE);

	$pokemon->update_user_data($target, 'exp', $exp);
	if(function_exists('badge_register')){ badge_register("TRAINER_XP", $exp, $target, TRUE); }

	$str = ":ok: ¡Experiencia registrada correctamente! - " .number_format($exp, 0, ',', '.') ." XP";
	$str = $this->telegram->emoji($str);
	$this->telegram->send
		->text($str)
	->send();

	$str = $target ." - @" .$pkuser->username ." " .number_format($exp, 0, ',', '.') ." MANUAL";

	$this->telegram->send
		->chat("-236154993") // Oak - Experiencia
		->text($str)
	->send();
}

elseif($this->telegram->text_command("exp") && $this->telegram->has_reply){
	return -1;
	if(isset($this->telegram->reply->photo)){
		$photo = array_pop($this->telegram->reply->photo);
		$photo = $photo['file_id'];
	}elseif(isset($this->telegram->reply->document)){
		$doc = $this->telegram->reply->document;
		if(strpos($doc['mime_type'], "image") === FALSE){
			$this->telegram->send
				->text($this->telegram->emoji(":warning: ") ."No es imagen. " .$doc['mime_type'])
			->send();
			return -1;
		}
		$photo = $doc['file_id'];
	}else{
		return -1;
	}

	$temp = tempnam("/tmp", "tgphoto");
	$res = $this->telegram->download($photo, $temp);

	// Error al descargar la foto.
	if(!$res){
		return -1;
	}

	// Reconocer la foto como perfil correspondiente
	// ----------

	$out = shell_exec("convert $temp +dither -posterize 2 -crop 20x20%+640+550 -define histogram:unique-colors=true -format %c histogram:info:-");

	$colors = ['Y' => 'yellow', 'R' => 'red', 'B' => 'cyan'];
	$csel = NULL;
	foreach($colors as $team => $color){
		if(strpos($out, $color) !== FALSE){
			$csel = $team; break;
		}
	}

	$pkuser = $pokemon->user($telegram->user->id);

	$error = FALSE;
	if(!$pkuser->verified){
		$error = ":times: Valídate antes de registrar la experiencia.";
	}elseif(empty($csel)){
		$error = ":times: La captura no parece válida.";
	}elseif($csel != $pkuser->team){
		$error = ":times: El equipo no corresponde.";
	}

	if($error){
		$error = $this->telegram->emoji($error);
		$this->telegram->send
			->text($error)
		->send();

		unlink($temp);
		return -1;
	}

	// OCR
	// ----------

	require_once APPPATH .'third_party/tesseract-ocr-for-php/src/TesseractOCR.php';

	$ocr = new TesseractOCR($temp);

	$str = $ocr->lang('spa', 'eng')->run();
	$str = trim($str);

	unlink($temp);

	$error = FALSE;
	if(empty($str)){
		$error = ":warning: No se ha reconocido la imagen.";
	}

	$str = strtoupper($str);

	if(strpos($str, "TOTAL XP") === FALSE and strpos($str, "TOTALXP") === FALSE){
		$error = ":warning: No se ha reconocido la experiencia.";
	}elseif(strpos($str, "DATE") === FALSE && strpos($str, "FECHA") === FALSE){
		$error = ":warning: La captura no parece válida.";
		$this->telegram->send
			->chat("-236154993") // Oak - Experiencia
			->caption($this->telegram->user->id)
		->file('photo', $photo);
	}

	if($error){
		$error = $this->telegram->emoji($error);
		$this->telegram->send
			->text($error)
		->send();

		return -1;
	}

	// Extraer experiencia y comparar
	// ----------

	$pos = strpos($str, "TOTAL");
	$exp = trim(substr($str, $pos, 20));
	$exp = filter_var($exp, FILTER_SANITIZE_NUMBER_INT);

	$error = FALSE;
	if($exp > 30000000 or empty($exp) or $exp <= 5000 or $exp < $pkuser->exp){
		$error = ":warning: Experiencia no reconocida.";
	}elseif($pkuser->exp != 0 and $exp > ($pkuser->exp * 1.8) ){
		$error = ":warning: Experiencia excede el límite. Contacta con @duhow.";
		$perc = round(($exp / $pkuser->exp) * 100, 2);

		$this->telegram->send
			->chat("-236154993") // Oak - Experiencia
			->caption($this->telegram->user->id . " excede: $exp VS " .$pkuser->exp . " ($perc %)")
		->file('photo', $photo);
	}elseif($pkuser->exp == $exp){
		$error = ":ok: Experiencia ya registrada.";
	}

	if($error){
		$error = $this->telegram->emoji($error);
		$this->telegram->send
			->text($error)
		->send();

		return -1;
	}

	$pokemon->update_user_data($telegram->user->id, 'exp', $exp);

	if(function_exists('badge_register')){
		badge_register("TRAINER_XP", $exp, $telegram->user->id, TRUE);

		$str = ":ok: ¡Experiencia registrada correctamente! - " .number_format($exp, 0, ',', '.') ." XP";
		$str = $this->telegram->emoji($str);

		$this->telegram->send
			->text($str)
		->send();

		$str = $this->telegram->user->id ." - @" .$pkuser->username ." " .number_format($exp, 0, ',', '.');

		$this->telegram->send
			->chat("-236154993") // Oak - Experiencia
			->text($str)
		->send();
	}
}

?>
