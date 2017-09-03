<?php

function user_warns($user, $chat = NULL){
	$CI =& get_instance();
	if(!empty($chat)){ $CI->db->where('chat', $chat); }

	$query =$CI->db
		->where('user', $user)
	->get('user_warns');

	if($query->num_rows() == 0){ return array(); }
	return $query->result_array();
}

if(!$telegram->is_chat_group()){ return; }
if(!$pokemon->is_group_admin($telegram->chat->id) and !in_array($telegram->user->id, telegram_admins(TRUE))){ return; }

$step = $pokemon->step($telegram->user->id);
// FIXME
if($step == "CUSTOM_COMMAND"){
	if(!$telegram->is_chat_group() or !in_array($telegram->user->id, telegram_admins(TRUE))){ return; }
	$command = $pokemon->settings($telegram->user->id, 'command_name');
	if(empty($command)){
		if($telegram->text()){
			$rm = ["^", "$", "'", "\"", "*"];
			$command = str_replace($rm, "", strtolower($telegram->text()));
			if($command == "crear comando"){
				$this->telegram->send
			        ->reply_to(TRUE)
			        ->text("Dime el comando / frase a crear.")
			    ->send();
			    return -1;
			}
			if($this->telegram->words() >= 5 or strlen($this->telegram->text()) >= 30){
				$this->telegram->send
					->text("¡Hala loco! No te pases. Prueba de nuevo.")
				->send();
				return -1;
			}
			$pokemon->settings($telegram->user->id, 'command_name', $command);
			$telegram->send
				->text("¡De acuerdo! Ahora envíame la respuesta que quieres enviar.")
			->send();
		}
		return -1; // HACK
	}
	$cmds = $pokemon->settings($telegram->chat->id, 'custom_commands');
	if($cmds){ $cmds = unserialize($cmds); }
	if(!is_array($cmds) or empty($cmds)){
		$pokemon->settings($telegram->chat->id, 'custom_commands', "DELETE");
		$cmds = array();
	}

	if(isset($cmds[$command])){ unset($cmds[$command]); }
	if($telegram->text()){
		if(strlen(trim($telegram->text())) < 4){ return; }
		$cmds[$command] = ["text" => $telegram->text_encoded()];
	}elseif($telegram->photo()){
		$cmds[$command] = ["photo" => $telegram->photo()];
	}elseif($telegram->voice()){
		$cmds[$command] = ["voice" => $telegram->voice()];
	}elseif($telegram->gif()){
		$cmds[$command] = ["document" => $telegram->gif()];
	}elseif($telegram->sticker()){
		$cmds[$command] = ["sticker" => $telegram->sticker()];
	}

	$cmds = serialize($cmds);
	$pokemon->settings($telegram->chat->id, 'custom_commands', $cmds);
	$pokemon->settings($telegram->user->id, 'command_name', "DELETE");
	$pokemon->step($telegram->user->id, NULL);
	$telegram->send
		->text("¡Comando creado correctamente!")
	->send();
	return -1;
}elseif($step == "RULES" or $step == "WELCOME"){
	$text = $telegram->text_encoded();
	if(strlen($text) < 4){ return -1; }
	if(strlen($text) > 4000){
		$this->telegram->send
			->text("Buah, demasiado texto! Relájate un poco anda ;)")
		->send();
		return -1;
	}

	// Ver si estamos en un grupo administrativo o no.
	$target = $telegram->chat->id;

	$group = $pokemon->is_group_admin($target);
	if($group and $group != $target){
		$target = $group; // editar en el grupo general, no en el admin.
	}

	if($step == "RULES"){
		$this->analytics->event('Telegram', 'Set rules');
		$pokemon->settings($target, 'rules', $text);
	}elseif($step == "WELCOME"){
		$this->analytics->event('Telegram', 'Set welcome');
		$pokemon->settings($target, 'welcome', $text);
	}

	$str = ":ok: ¡Hecho!";
	if($group){ $str .= " Cambiado en el grupo general."; }
	$telegram->send
		->text($this->telegram->emoji($str))
	->send();
	$pokemon->step($telegram->user->id, NULL);
	return -1;
}

// Echar usuario del grupo
if($telegram->text_command(["kick", "ban"])){
    $admins = $pokemon->telegram_admins(TRUE);
    $chat = $pokemon->is_group_admin($this->telegram->chat->id);
    if(empty($chat)){ $chat = $this->telegram->chat->id; }

    if($pokemon->is_group_admin($this->telegram->chat->id) or in_array($telegram->user->id, $admins)){ // Tiene que ser admin
        $kick = NULL;
        if($telegram->has_reply){
            $kick = $telegram->reply_user->id;
        }elseif($telegram->text_mention()){
            $kick = $telegram->text_mention(); // Solo el primero
            if(is_array($kick)){ $kick = key($kick); } // Get TelegramID
        }elseif($telegram->words() == 2){
            // Buscar usuario.
            $kick = $telegram->last_word();
            if(strlen($kick) < 4){ return; }
            // Buscar si no en PKGO user DB.
        }
		if($kick == $this->config->item('telegram_bot_id')){ return -1; }
        if(($telegram->user->id == $this->config->item('creator')) or !in_array($kick, $admins)){ // Si es creador o no hay target a admins
			$q = FALSE;
            if($telegram->text_contains("kick")){
                $this->analytics->event('Telegram', 'Kick');
                $q = $telegram->send->kick($kick, $chat);
            }elseif($telegram->text_contains("ban")){
                $this->analytics->event('Telegram', 'Ban');
                $q = $telegram->send->ban($kick, $chat);
            }
			if($q !== FALSE){
				$pokemon->user_delgroup($kick, $chat);
				$adminchat = $this->pokemon->settings($chat, 'admin_chat');
				if($adminchat){
					if($telegram->text_contains("kick")){
						$str = ":forbid: Usuario kickeado\n";
					}else{
						$str = ":banned: Usuario baneado\n";
					}
							// Autor
					$str .= ":guard: " .$telegram->user->id ." - " .$telegram->user->first_name . " @" .@$telegram->user->username ."\n";
					$str .= ":id: " .$kick;
					if($telegram->has_reply){
						$str .= " - " .$telegram->reply_user->first_name ." @" .@$telegram->reply_user->username;
					}
					$str .= "\n";
					// Motivo
					if($telegram->words() > 2){
						$i = ($telegram->has_reply ? 1 : 2);
						$str .= ":abc: " .ucfirst(strtolower($telegram->words($i, 10)));
					}

					$str = $this->telegram->emoji($str);

					$this->telegram->send
						->notification(TRUE)
						->chat($adminchat)
						->text($str)
					->send();
				}
			}
			return -1; // HACK
        }
    }
}

// Desbanear a usuario
elseif(($telegram->text_contains("desbanea", TRUE) or $telegram->text_command("unban")) && $telegram->words() <= 3){
    $target = NULL;
    if($telegram->has_reply){ $target = $telegram->reply_user->id; }
    elseif($telegram->text_mention()){
        $target = $telegram->text_mention();
        if(is_array($target)){ $target = key($target); }
    }else{
        $target = $telegram->last_word(TRUE);
		$user = $pokemon->user($target);
		if(!empty($user)){ $target = $user->telegramid; }
    }

	$chat = $telegram->chat->id;
	$adm = $pokemon->is_group_admin($telegram->chat->id);
	if($adm){ $chat = $adm; }

    if($target != NULL){
        $q = $telegram->send->unban($target, $chat);
		if($q !== FALSE){
			$telegram->send
				->text("Usuario $target desbaneado.")
			->send();
		}

        if($telegram->callback){
			$str = ":male: Desbaneado por " .$telegram->user->id ." - " .$telegram->user->first_name;
			$str = $telegram->emoji($str);
            $telegram->send
                ->message(TRUE)
                ->chat(TRUE)
                ->text($telegram->text_message() ."\n" .$str)
            ->edit('text');
        }
    }

    return -1;
}

// Limpiar antiflood
elseif($telegram->text_has(['/flood', '/antiflood'], TRUE)){
    $this->analytics->event('Telegram', 'Antiflood', 'Check');
    $antiflood = $pokemon->settings($telegram->chat->id, 'antiflood');
    if(!$antiflood){ return; }
    $group = $pokemon->group($telegram->chat->id);
    $admins = $pokemon->telegram_admins(TRUE);
    if(!in_array($telegram->user->id, $admins)){ return; }
    if($telegram->words() == 1){
        // ver estado y puntos.
        $telegram->send
            ->notification(FALSE)
            ->text($group->spam)
        ->send();
        return -1;
    }
    $word = $telegram->last_word(TRUE);
    if(strtolower($word) == "clear"){
        $telegram->send
            ->notification(FALSE)
            ->text("Reiniciado [" .$group->spam ."].")
        ->send();
        $pokemon->group_spamcount($telegram->chat->id, FALSE);
        return -1;
    }
}

elseif(
    $telegram->text_has(["oak", "profe"], "limpia") or
    $telegram->text_command("clean")
){
    // $admins = $pokemon->telegram_admins(TRUE);
    $admins = telegram_admins(TRUE);

    if(in_array($telegram->user->id, $admins)){
        $this->analytics->event('Telegram', 'Clean');
        $telegram->send
            ->notification(FALSE)
            ->text(".\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n.")
        ->send();
    }
    return;
}

// configurar el bot por defecto (solo creador/admin)
elseif(
    ( $telegram->text_has(["oak", "profe"], "configuración", TRUE) or $telegram->text_command("config") ) &&
    in_array($telegram->words(), [3,4])
){
    $admins = $pokemon->telegram_admins(TRUE);
    if(!in_array($telegram->user->id, $admins)){ return; }

    $config = strtolower($telegram->words(2, 2));
    $configs = [
        'actual' => [],
		'activa' => [],
		'actual?' => [],
		'activa?' => [],
        'inicial' => [
            'announce_welcome' => TRUE,
            'blacklist' => 'troll,spam,rager',
            'shutup' => TRUE,
            'jokes' => FALSE,
            'pole' => TRUE,
            'play_games' => FALSE,
			'pokegram' => TRUE,
        ],
        'divertida' => [
            'announce_welcome' => TRUE,
            'blacklist' => FALSE,
            'shutup' => FALSE,
            'jokes' => TRUE,
            'pole' => TRUE,
            'play_games' => TRUE,
			'pokegram' => TRUE,
        ],
        'silenciosa' => [
            'announce_welcome' => FALSE,
            'blacklist' => 'troll,spam,rager',
            'shutup' => TRUE,
            'jokes' => FALSE,
            'pole' => FALSE,
            'play_games' => FALSE,
			'pokegram' => FALSE,
        ],
		'exclusiva' => [
            'announce_welcome' => TRUE,
			'require_verified' => TRUE,
			'team_exclusive_kick' => TRUE,
            'blacklist' => 'troll,spam,rager,gps,hacks,fly,bot',
            'shutup' => TRUE,
            'jokes' => FALSE,
            'pole' => FALSE,
            'play_games' => FALSE,
			'pokegram' => FALSE,
        ]
    ];

    $this->analytics->event('Telegram', 'Set default config', $config);

    if(!isset($configs[$config])){ $config = "inicial"; }
    $icon = [":times:", ":green-check:", ":question-grey:", ":exclamation-grey:"];
    foreach($configs[$config] as $key => $val){
        $pokemon->settings($telegram->chat->id, $key, $val);
    }

    $str = "";
    $check = [
        'announce_welcome' => "Mostrar mensaje de bienvenida",
        'welcome' => "Mensaje personalizado",
        'rules' => "Reglas del grupo",
        'shutup' => "Modo silencioso",
        'jokes' => "Bromas",
        'blacklist' => "Blacklist",
        'play_games' => "Juegos",
        'pole' => "Pole",
        'location' => "Ubicación del grupo",
        'offtopic_chat' => "Grupo offtopic",
		'pokegram' => '¿Aparecen Pokémon?',
    ];
    foreach($check as $key => $txt){
        $set = $pokemon->settings($telegram->chat->id, $key);
        $seticon = ($set && $set != FALSE ? 1 : 0);
        $str .= $telegram->emoji( $icon[$seticon] ) ." $txt\n";
    }

    // Setting de link_chat
    $set = $pokemon->settings($telegram->chat->id, 'link_chat');
    if($set && $set[0] != "@"){ $set = "privado"; }
    elseif(!$set){
        // intentar sacar link del grupo publico.
        $chat = $telegram->send->get_chat();
        if(isset($chat['username'])){
            $pokemon->settings($telegram->chat->id, 'link_chat', "@" .$chat['username']);
            $set = $chat['username'];
        }
    }
    $seticon = ($set && $set != FALSE ? 1 : 0);
    $str .= $telegram->emoji( $icon[$seticon] ) ." Link del grupo $set\n";

    // Color del grupo
    $color = [
        'Y' => ['amarillo', 'instinto', 'instinct', 'sparky', ':heart-yellow:'],
        'R' => ['rojo', 'valor', 'candela', ':heart-red:'],
        'B' => ['azul', 'místico', 'mystic', 'blanche', ':heart-blue:'],
    ];
    $iconteam = ['Y' => ':heart-yellow:', 'R' => ':heart-red:', 'B' => ':heart-blue:'];
    $set = $pokemon->settings($telegram->chat->id, 'team_exclusive');
    if(!$set){
        $chat = $telegram->send->get_chat();
        $title = strtolower($telegram->emoji($chat['title'], TRUE));
        $teamsel = NULL;
		if(function_exists("color_parse")){
			$teamsel = color_parse($title);
		}else{
			foreach($color as $team => $words){
				foreach($words as $word){
					if(strpos($title, $word) !== FALSE){ $teamsel = $team; break; }
				}
				if($teamsel !== NULL){ break; }
			}
		}
        if($teamsel !== NULL){
            $pokemon->settings($telegram->chat->id, 'team_exclusive', $teamsel);
            $set = "detectado " .$telegram->emoji($iconteam[$teamsel]);
        }
    }else{
        $set = "es " .$telegram->emoji($iconteam[$set]);
    }
    $seticon = ($set && $set != FALSE && $set != NULL ? 1 : 0);
    $str .= $telegram->emoji( $icon[$seticon] ) ." Grupo de color $set\n";

    $telegram->send->text($str)->send();
    return;
}

// Echar al bot del grupo
elseif($telegram->text_has(["oak", "profe"], ["sal", "vete"], TRUE) && !$telegram->text_contains("salu") && $telegram->is_chat_group() && $telegram->words() < 4){
    $admins = $pokemon->telegram_admins(TRUE);

    if(in_array($telegram->user->id, $admins)){
        $this->analytics->event('Telegram', 'Leave group');
        $telegram->send
            ->notification(FALSE)
            ->text("Jo, pensaba que me queríais... :(\nBueno, si me necesitáis, ya sabéis donde estoy.")
        ->send();

        $pokemon->group_disable($telegram->chat->id);
        $telegram->send->leave_chat();
    }
}

// Investigar / contar usuarios topos de un grupo.
elseif($telegram->text_command("investigate")){
    $admins = $pokemon->telegram_admins(TRUE);

    if(!in_array($telegram->user->id, $admins)){ return; }

    $team = $pokemon->settings($telegram->chat->id, 'team_exclusive');
    if($team !== NULL){

        $run = $pokemon->settings($telegram->chat->id, 'investigation');
        if($run !== NULL){
            if(time() <= ($run + 3600)){ return; }
        }
        $run = $pokemon->settings($telegram->chat->id, 'investigation', time());

        $this->analytics->event('Telegram', 'Investigation', $team);
        $teams = ["Y", "B", "R"];
        unset( $teams[ array_search($team, $teams) ] );
        $users = $pokemon->get_users($teams);
        $c = 0;
        $dot = 0;
        $topos = array();

        $updates = $telegram->send
            ->notification(FALSE)
            ->text("*Progreso:* ", TRUE)
        ->send();
        foreach($users as $u){
            if(($c % 100 == 0) or ($c % 100 == 50) or ($c >= count($users))){
                $msg = "*Progreso:* " .floor(($c / count($users) * 100)) ."%";
                if($dot++ > 3){ $dot = 0; }
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
                $topos[] = $q;
                $telegram->send
                    ->notification(TRUE)
                    ->text("*TOPO!* " .$q['user']['first_name'] .(isset($q['user']['username']) ? " @" .$q['user']['username'] : "" ), TRUE)
                ->send();
            }
        }

        $str = "*Lista final:*\n";
        foreach($topos as $t){
            $str .= $t['user']['first_name'] .(isset($t['user']['username']) ? " @" .$t['user']['username'] : "" ) ."\n";
        }

        $telegram->send
            ->notification(FALSE)
            ->text($str . "\nFinalizado.", TRUE)
        ->send();
    }else{
        $telegram->send
            ->notification(FALSE)
            ->text("No es un grupo cerrado.")
        ->send();
    }
    return;
}

// Crear unión del grupo
elseif($telegram->text_has(["emparejamiento", "crear unión"], ["de grupo", "del grupo", "grupo", "grupal"]) && $telegram->words() <= 5){
    $admins = $pokemon->telegram_admins(TRUE);
    if(!in_array($telegram->user->id, $admins)){ return; }
    // Requiere team_exclusive

    $pokeuser = $pokemon->user($telegram->user->id);
    $team = $pokemon->settings($telegram->chat->id, 'team_exclusive');
    if(empty($team)){
        $telegram->send->text( $telegram->emoji(":times: Falta indicar exclusividad del grupo / color.") )->send();
        return;
    }
    // Requiere link del grupo
    $link = $pokemon->settings($telegram->chat->id, 'link_chat');
    if(empty($link)){
        $chat = $telegram->send->get_chat();
        if(isset($chat['username'])){
            $pokemon->settings($telegram->chat->id, 'link_chat', "@" .$chat['username']);
            // $this->_begin();
            return;
        }
        $telegram->send->text( $telegram->emoji(":times: Falta enlace del grupo.") )->send();
        return;
    }
    // El usuario tiene que estar validado.
    if(!$pokeuser->verified){
        $telegram->send->text( $telegram->emoji(":times: El usuario no está verificado.") )->send();
        return;
    }
    // El usuario tiene que ser del mismo color que el grupo
    if($pokeuser->team != $team && $telegram->user->id != $this->config->item('creator')){
        $telegram->send->text( $telegram->emoji(":times: El usuario no pertenece al mismo color del grupo.") )->send();
        return;
    }
    // Generar clave
    $minute = 5;
    if(date("i") % $minute == $minute - 1){
        $telegram->send->text("Espera un minuto antes de crear la clave.")->send();
        return;
    }
    $key = [
        strtoupper($team), // TEAM
        $telegram->chat->id, // ID CHAT
        (date("Ymd") .floor(date("i") / $minute)), // TIME
        mt_rand(1000,9999), // RAND
    ];
    $key = implode(":", $key);
    $pokemon->settings($telegram->chat->id, 'pair_key', sha1($key));
    $res = $telegram->send
        ->notification(TRUE)
        ->chat($telegram->user->id)
        ->text("Unir grupo <b>" .base64_encode($key) ."</b>", 'HTML')
    ->send();
    if(!$res){
        // Aviso al chat general.
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text("Por favor, inicia el chat conmigo para poder enviarte la clave, y una vez hecho vuelve a generarla.")
        ->send();
    }else{
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text("¡Hecho! Por favor *reenvía* la clave al *administrador* del grupo que quieres unir.", TRUE)
        ->send();
    }
}elseif($telegram->text_has("Unir grupo", TRUE) and $telegram->words() == 3){
    $key = $telegram->last_word();
    $admins = $pokemon->telegram_admins(TRUE);
    if(!in_array($telegram->user->id, $admins)){ return; }

    $pokeuser = $pokemon->user($telegram->user->id);

    $team = $pokemon->settings($telegram->chat->id, 'team_exclusive');
    if(!empty($team)){
        $telegram->send->text( $telegram->emoji(":times: El grupo no puede ser exclusivo para agregar otro.") )->send();
        return;
    }
    // Requiere link del grupo
    $link = $pokemon->settings($telegram->chat->id, 'link_chat');
    if(empty($link)){
        $chat = $telegram->send->get_chat();
        if(isset($chat['username'])){
            $pokemon->settings($telegram->chat->id, 'link_chat', "@" .$chat['username']);
            // $this->_begin();
            return;
        }
        $telegram->send->text( $telegram->emoji(":times: Falta enlace del grupo.") )->send();
        return;
    }
    // El usuario tiene que estar validado.
    if(!$pokeuser->verified){
        $telegram->send->text( $telegram->emoji(":times: El usuario no está verificado.") )->send();
        return;
    }

    $key = base64_decode($key);
    $keydec = explode(":", $key);

    $valid = TRUE;
    $minute = 5; // SYNC
    if($valid == TRUE && (!is_array($keydec) or count($keydec) != 4)){ $valid = -6; } // Decodificación incorrecta
    if($valid == TRUE && $keydec[1] == $telegram->chat->id){ $valid = -5; } // Si el grupo es el mismo, descartar.
    $this->pokemon->load_settings($keydec[1]); // Cargar datos del grupo.
    if($valid == TRUE && $keydec[0] != $pokemon->settings($keydec[1], 'team_exclusive')){ $valid = -4; } // Si el grupo no tiene el mismo color.
    if($valid == TRUE && $pokemon->settings($keydec[1], 'link_chat') == NULL){ $valid = -3; } // Si el grupo no tiene link.
    if($valid == TRUE && $keydec[2] != (date("Ymd") .floor(date("i") / $minute)) ){ $valid = -2; } // Si ha expirado la clave
    if($valid == TRUE && sha1($key) != $pokemon->settings($keydec[1], 'pair_key')){ $valid = -1; } // Si la clave por DB es distinta

    // Validar la clave.
    if($valid !== TRUE){
        $telegram->send->text( $telegram->emoji(":times: Clave inválida. [$valid]" ) )->send();
        return;
    }

    // Si el Oak no está en el grupo.
    if(!$telegram->user_in_chat($this->config->item('telegram_bot_id'), $keydec[1])){
        $telegram->send->text( $telegram->emoji(":times: No estoy en el grupo destino.") )->send();
        return;
    }

    $chatdst = $keydec[1];
    $pair = sha1($telegram->chat->id .":" .$chatdst);
    $team = $keydec[0];

    $pokemon->settings($telegram->chat->id, 'pair_team_' .$team, $pair);
    $pairs = $pokemon->settings($chatdst, 'pair_groups');
    if(!empty($pairs)){ $pairs = explode(",", $pairs); } // Decodifica Array
    $pairs[] = $pair; // Agrega grupo
    $pairs = array_unique($pairs); // Quitar duplicados
    $pairs = implode(",", $pairs);
    $pokemon->settings($chatdst, 'pair_groups', $pairs);
    $pokemon->settings($chatdst, 'pair_key', "DELETE");

    $telegram->send
        ->chat($telegram->chat->id)
        ->notification(TRUE)
        ->text("¡Grupo emparejado correctamente!")
    ->send();

    $telegram->send
        ->chat($chatdst)
        ->notification(TRUE)
        ->text("Se ha unido el grupo: " .$telegram->chat->title)
    ->send();

    $telegram->send
        ->chat($this->config->item('creator'))
        ->notification(TRUE)
        ->text("*PAIR:* " .$telegram->chat->id .":" .$chatdst, TRUE)
    ->send();
}

// Escribir las normas del grupo.
elseif(
    $telegram->words() <= 6 &&
    $telegram->text_has(["poner", "actualizar", "redactar", "escribir", "editar", "cambiar"], ["las normas", "las reglas"])
){
    $admins = $pokemon->telegram_admins(TRUE);
    if(in_array($telegram->user->id, $admins)){
		if($telegram->has_reply and isset($telegram->reply->text)){
			$text = json_encode($telegram->reply->text);
			if(strlen($text) > 4000 or strlen($text) < 10){
				$this->telegram->send
					->text("¿No crees que te pasas un poco?")
				->send();

				return -1;
			}

			// Ver si estamos en un grupo administrativo o no.
			$target = $telegram->chat->id;

			$group = $pokemon->is_group_admin($target);
			if($group and $group != $target){
				$target = $group; // editar en el grupo general, no en el admin.
			}

			$this->analytics->event('Telegram', 'Set rules');
			$pokemon->settings($target, 'rules', $text);

			$str = ":ok: ¡Hecho!";
			if($group){ $str .= " Cambiado en el grupo general."; }
			$telegram->send
				->text($this->telegram->emoji($str))
			->send();

			return -1;
		}

        $pokemon->step($telegram->user->id, 'RULES');
        $telegram->send
            ->reply_to(TRUE)
            ->text("De acuerdo, envíame el texto que quieres que ponga de normas.")
        ->send();
        return -1;
    }
}

// Escribir el mensaje de bienvenida
elseif(
    $telegram->words() <= 8 &&
    $telegram->text_has(["poner", "actualizar", "redactar", "escribir", "editar", "cambiar"]) &&
    $telegram->text_has(["mensaje", "anuncio"]) &&
    $telegram->text_has(["bienvenida", "entrada"])
){
    $admins = $pokemon->telegram_admins(TRUE);
    if(in_array($telegram->user->id, $admins)){
        $pokemon->step($telegram->user->id, 'WELCOME');
        $telegram->send
            ->reply_to(TRUE)
            ->text("De acuerdo, envíame el texto que quieres que ponga de bienvenida.")
        ->send();
        return;
    }
}

// Crear comandos personalizados
elseif(
    ( $telegram->text_has("crear", "comando", TRUE) or $telegram->text_command("command") )
){
	$can = $pokemon->settings($telegram->chat->id, 'custom_commands');
	if($can != NULL && $can == FALSE){
		$telegram->send
			->text("Nope.")
		->send();
		return -1;
	}
    $pokemon->settings($telegram->user->id, 'command_name', "DELETE");
    $pokemon->step($telegram->user->id, 'CUSTOM_COMMAND');
    $telegram->send
        ->reply_to(TRUE)
        ->text("Dime el comando / frase a crear.")
    ->send();
    return -1;
}

elseif(
	$this->telegram->text_has(["borra", "borrar"], "comando", TRUE)
){
	if($this->telegram->words() == 2){
		$this->telegram->send
			->text("Indica el comando que quieres borrar.")
		->send();

		return -1;
	}

	$command = trim(strtolower($this->telegram->words(2, 10)));
	$commands = $pokemon->settings($telegram->chat->id, 'custom_commands');

	$commands = unserialize($commands);
	if(empty($commands) or !is_array($commands)){ return -1; }

	$str = "No existe ese comando: $command";
	if(isset($commands[$command])){
		unset($commands[$command]);

		$commands = serialize($commands);
		$pokemon->settings($telegram->chat->id, 'custom_commands', $commands);

		$str = "¡Comando borrado correctamente!";
	}

	$this->telegram->send
		->text($str)
	->send();

	return -1;
}

elseif(
	$this->telegram->text_has(["borra", "borrar"], ["todos los comandos", "los comandos"], TRUE) and
	$this->telegram->words() <= 5
){
	$commands = $pokemon->settings($telegram->chat->id, 'custom_commands');

	$commands = unserialize($commands);
	if(empty($commands) or !is_array($commands)){ return -1; }

	$pokemon->settings($telegram->chat->id, 'custom_commands', "DELETE");

	$this->telegram->send
		->text_replace("%s comandos borrados.", count($commands))
	->send();

	return -1;
}

// Lista de comandos
elseif($this->telegram->text_has("lista de comandos") && $this->telegram->words() <= 5){
	$commands = $pokemon->settings($telegram->chat->id, 'custom_commands');
	$commands = unserialize($commands);
	$str = "No hay comandos.";
	if(is_array($commands)){
		$str = count($commands) ." comandos:\n";
		foreach($commands as $k => $v){
			$str .= "$k\n";
		}
	}

	$this->telegram->send
		->notification(FALSE)
		->text($str)
	->send();
	return -1;
}

elseif(
	(
		$this->telegram->text_command("warn") or
		$this->telegram->text_has(["primer", "último", "quedas"], ["aviso", "avisado"])
	) and $this->telegram->has_reply
	and $this->telegram->words() < 6
	and !in_array($this->telegram->reply_user->id, [
		$this->telegram->user->id, // No autowarn
		$this->config->item('telegram_bot_id'), // No warn al bot
		$this->config->item('creator') // No al creador
	])
){
	if($pokemon->user_flags($this->telegram->user->id, ['troll', 'troll_warn'])){ return -1; }
	$adminchat = $pokemon->settings($this->telegram->chat->id, 'admin_chat');

	$reasons = [
		'rager' => ['insulto', 'insultar', 'insultos', 'rager', 'rage', 'ragear'],
		'discuss' => ['discutir', 'movidas', 'discusion', 'discusiones', 'salseo', 'salsa'],
		'troll' => ['trol', 'trolear', 'troll'],
	];

	$reason = NULL;
	if($telegram->text_contains("por")){
		foreach($reasons as $r => $v){
			if($this->telegram->text_has("por", $v)){
				$reason = $r; break;
			}
		}
	}

	$data = [
		'user' => $this->telegram->reply_user->id,
		'chat' => $this->telegram->chat->id,
		'admin' => $this->telegram->user->id,
		'reason' => $reason
	];

	$this->db->insert('user_warns', $data);

	$frases = [
		"Compórtate o es posible que no dures mucho aquí...",
		"Vigila para la próxima vez.",
		"Procura no volver a repetirlo.",
		"Todos tenemos malos días, pero no tienes porqué pagarlo aquí.",
		"Va, demuestra que eres un adulto de verdad."
	];
	$n = mt_rand(0, count($frases) - 1);

	$warns = user_warns($this->telegram->reply_user->id, $this->telegram->chat->id);
	$str = $this->telegram->emoji(":warning:") ." Llevas ya <b>" .count($warns) ."</b> avisos.\n" .$frases[$n];

	$this->telegram->send
		->text($str, 'HTML')
	->send();

	if($adminchat){
		$str = ":warning: Usuario warneado\n"
				.":id: " .$this->telegram->reply_user->id ." - " .$this->telegram->reply_user->first_name ."\n"
				.":male: " .$this->telegram->user->id ." - " .$this->telegram->user->first_name ."\n"
				.":abc: " .($reason ?: "---");
		$str = $this->telegram->emoji($str);
		$this->telegram->send
			->notification(TRUE)
			->chat($adminchat)
			->text($str)
		->send();
	}

	return -1;
}

elseif($telegram->text_has("mutear contenido") and $telegram->words() <= 6){
	$settings = $this->pokemon->settings($telegram->chat->id, "mute_content");
	if($settings){ $settings = explode(",", $settings); }

	if($this->telegram->callback){
		$this->telegram->answer_if_callback("");
		$r = $this->telegram->last_word();
		if($r == "listo"){
			$this->telegram->send
				->text($this->telegram->emoji(":ok: ¡Listo!"))
				->message(TRUE)
				->chat(TRUE)
			->edit('text');

			sleep(1);
			$this->telegram->send->delete(TRUE);
			return -1;
		}

		if(in_array($r, $settings)){
			unset($settings[array_search($r, $settings)]);
		}else{
			$settings[] = $r;
		}

		$this->pokemon->settings($telegram->chat->id, "mute_content", implode(",", $settings));
	}

	$keys = [
		"voice" => "\ud83d\udd0a",
		"audio" => "\ud83c\udfb6",
		"photo" => "\ud83d\udcf8",
		"video" => "\ud83c\udfa5",
		"sticker" => "\ud83c\udf05",
		"url" => "\ud83c\udf0d",
		"gif" => "\ud83d\udd01",
		"game" => "\ud83d\udd79",
		"document" => "\ud83d\udcdd",
		"bot" => "\ud83e\udd16",
	];

	$display = array();

	foreach(array_keys($keys) as $key){
		if(in_array($key, $settings)){ $display[$key] = ":times:"; }
		else{ $display[$key] = ":ok:";  }
	}

	if(!$this->telegram->callback and !in_array($this->config->item('telegram_bot_id'), telegram_admins())){
		$this->telegram->send
			->text("No puedo controlar esto hasta que no sea administrador :(")
		->send();

		return -1;
	}

	$this->telegram->send
		->inline_keyboard()
			->row()
				->button($this->telegram->emoji($keys["voice"] ." " .$display["voice"]), "mutear contenido voice", "TEXT")
				->button($this->telegram->emoji($keys["audio"] ." " .$display["audio"]), "mutear contenido audio", "TEXT")
				->button($this->telegram->emoji($keys["photo"] ." " .$display["photo"]), "mutear contenido photo", "TEXT")
			->end_row()
			->row()
				->button($this->telegram->emoji($keys["video"] ." " .$display["video"]), "mutear contenido video", "TEXT")
				->button($this->telegram->emoji($keys["document"] ." " .$display["document"]), "mutear contenido document", "TEXT")
				->button($this->telegram->emoji($keys["sticker"] ." " .$display["sticker"]), "mutear contenido sticker", "TEXT")
			->end_row()
			->row()
				->button($this->telegram->emoji($keys["game"] ." " .$display["game"]), "mutear contenido game", "TEXT")
				->button($this->telegram->emoji($keys["url"] ." " .$display["url"]), "mutear contenido url", "TEXT")
				->button($this->telegram->emoji($keys["gif"] ." " .$display["gif"]), "mutear contenido gif", "TEXT")
			->end_row()
			->row()
				->button($this->telegram->emoji($keys["bot"] ." " .$display["bot"]), "mutear contenido bot", "TEXT")
			->end_row()
			->row_button($this->telegram->emoji("\ud83d\udcbe Guardar"), "mutear contenido listo", "TEXT")
		->show()
		->text("Selecciona el contenido a silenciar.");

	if($this->telegram->callback){
		$this->telegram->send
			->message(TRUE)
			->chat(TRUE)
		->edit('text');
	}else{
		$this->telegram->send->send();
	}

	return -1;
}

?>
