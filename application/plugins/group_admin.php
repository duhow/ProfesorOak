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
if(!in_array($telegram->user->id, telegram_admins(TRUE))){ return; }

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
}

// Echar usuario del grupo
if($telegram->text_has(["/kick", "/ban"], TRUE)){
    $admins = $pokemon->telegram_admins(TRUE);

    if(in_array($telegram->user->id, $admins)){ // Tiene que ser admin
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
                $q = $telegram->send->kick($kick, $telegram->chat->id);
            }elseif($telegram->text_contains("ban")){
                $this->analytics->event('Telegram', 'Ban');
                $q = $telegram->send->ban($kick, $telegram->chat->id);
            }
			if($q !== FALSE){
				$pokemon->user_delgroup($kick, $telegram->chat->id);
				$adminchat = $this->pokemon->settings($this->telegram->chat->id, 'admin_chat');
				if($adminchat){
					if($telegram->text_contains("kick")){
						$str = ":forbid: Usuario kickeado\n";
					}else{
						$str = ":banned: Usuario baneado\n";
					}
							// Autor
					$str .= "\ud83d\udec2 " .$telegram->user->id ." - " .$telegram->user->first_name . " @" .@$telegram->user->username ."\n";
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

    if($target != NULL){
        $q = $telegram->send->unban($target, $telegram->chat->id);
		if($q !== FALSE){
			$telegram->send
				->text("Usuario $target desbaneado.")
			->send();
		}

        if($telegram->callback){
            $telegram->send
                ->message(TRUE)
                ->chat(TRUE)
                ->text($telegram->text_message() ."\n" ."Desbaneado.")
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
        return;
    }
    $word = $telegram->last_word(TRUE);
    if(strtolower($word) == "clear"){
        $telegram->send
            ->notification(FALSE)
            ->text("Reiniciado [" .$group->spam ."].")
        ->send();
        $pokemon->group_spamcount($telegram->chat->id, FALSE);
        return;
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
        'inicial' => [
            'announce_welcome' => TRUE,
            'blacklist' => 'troll,spam,rager',
            'shutup' => TRUE,
            'jokes' => FALSE,
            'pole' => TRUE,
            'play_games' => FALSE,
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
    $seticon = ($set && $set != FALSE ? 1 : 0);
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
}elseif($telegram->text_has(["unir", "unión"], ["de grupo", "del grupo", "grupo", "grupal"])){
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
        $pokemon->step($telegram->user->id, 'RULES');
        $telegram->send
            ->reply_to(TRUE)
            ->text("De acuerdo, envíame el texto que quieres que ponga de normas.")
        ->send();
        return;
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
){
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

	$warns = user_warns($this->telegram->reply_user->id);
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

?>
