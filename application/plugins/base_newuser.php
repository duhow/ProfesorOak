<?php

// Oak o otro usuario es añadido a una conversación
if($telegram->is_chat_group() && $telegram->data_received() == "new_chat_participant"){
    $set = $pokemon->settings($telegram->chat->id, 'announce_welcome');
	$adminchat = $pokemon->settings($telegram->chat->id, 'admin_chat');
    $new = $telegram->new_user;
    $user = $telegram->user;
    $chat = $telegram->chat;

	$count = 0;

	// A excepción de que lo agregue el creador
    if($new->id == $this->config->item("telegram_bot_id") && $telegram->user->id != $this->config->item('creator')){
        $count = $telegram->send->get_members_count();
        // Si el grupo tiene <= 5 usuarios, el bot abandona el grupo
        if(is_numeric($count) && $count <= 5){
            $this->analytics->event('Telegram', 'Join low group');
            $telegram->send->text("Nope.")->send();
            $telegram->send->leave_chat();
            return -1;
        }

		if($pokemon->settings($telegram->chat->id, 'die') == TRUE){
			$telegram->send->leave_chat();
            return -1;
		}

    // Bot agregado al grupo. Yo no saludo bots :(
    }elseif($new->id != $this->config->item('telegram_bot_id') && isset($new->username) and $telegram->is_bot($new->username)){
		if(in_array($this->telegram->user->id, telegram_admins(TRUE))){ return -1; } // Lo agrega un admin, no pasa na.
		$mute = $pokemon->settings($telegram->chat->id, 'mute_content');
		if(empty($mute)){ return -1; }
		$mute = explode(",", $mute);
		if(!in_array("bot", $mute)){ return -1; } // Se permite agregar bots

		$this->telegram->send->ban($this->telegram->user->id);
		$this->telegram->send->ban($new->id);

		if($adminchat){
			$str = ":warning: Poner bot en el grupo\n"
					.":id: @" .$new->username ." - " .$new->id ."\n"
					.":male: " .$this->telegram->user->first_name ." - " . $this->telegram->user->id;

			$str = $this->telegram->emoji($str);
			$this->telegram->send
				->chat($adminchat)
				->notification(TRUE)
				->text($str)
			->send();
		}

		return -1;
	}

	if($pokemon->settings($new->id, 'follow_join')){
		$str = ":warning: Join detectado\n"
				.":id: " .$new->id ." - " .$new->first_name ."\n"
				.":multiuser: " .$this->telegram->chat->id ." - " .$this->telegram->chat->title;
		$str = $telegram->emoji($str);
		$this->telegram->send
			->notification(TRUE)
			->chat($this->config->item('creator'))
			->text($str)
		->send();
	}

	$blacklist = $pokemon->settings($this->telegram->chat->id, 'blacklist');
	if(!empty($blacklist)){
		$blacklist = explode(",", $blacklist);
		$pknew_flags = $pokemon->user_flags($new->id);
		// TODO excepto si el que lo agrega es admin.
		foreach($blacklist as $b){
			if(in_array($b, $pknew_flags)){
				$this->analytics->event('Telegram', 'Join blacklist user', $b);
				$q = $this->telegram->send->ban($new->id, $this->telegram->chat->id);
				if($q){
					$pokemon->user_delgroup($new->id, $this->telegram->chat->id);
				}

				if($adminchat){
					$str = ":times: Usuario en blacklist - $b\n"
							.":id: " .$new->id ."\n"
							.":abc: " .$new->first_name ." - @" .$pknew->username;
					$str = $this->telegram->emoji($str);
					$this->telegram->send
						->notification(TRUE)
						->chat($adminchat)
						->text($str)
					->send();
				}
				return -1;
			}
		}
	}

    $pknew = $pokemon->user($new->id);
    // El usuario nuevo es creador
    if($new->id == $this->config->item('creator')){
        if($pokemon->settings($telegram->user->id, 'silent_join') == TRUE){ return -1; }
        $telegram->send
            ->notification(TRUE)
            ->reply_to(TRUE)
            ->text("Bienvenido, jefe @duhow! Un placer tenerte aquí! :D")
        ->send();
        return;
    }elseif(!empty($pknew)){
        // Si el grupo es exclusivo a un color y el usuario es de otro color
        $teamonly = $pokemon->settings($telegram->chat->id, 'team_exclusive');
        if(!empty($teamonly) && $teamonly != $pknew->team){
            $this->analytics->event('Telegram', 'Spy enter group');
            $telegram->send
                ->notification(TRUE)
                ->reply_to(TRUE)
                ->text("*¡SE CUELA UN TOPO!* @$pknew->username $pknew->team", TRUE)
            ->send();
			if($adminchat){
				$str = ":times: Topo detectado!\n"
						.":id: " .$new->id ."\n"
						.":abc: " .$new->first_name ." - @" .$pknew->username;
				$str = $telegram->emoji($str);
				$telegram->send
					->notification(TRUE)
					->chat($adminchat)
					->text($str)
				->send();
			}

            // Kickear (por defecto TRUE)
            $kick = $pokemon->settings($telegram->chat->id, 'team_exclusive_kick');
            // TODO excepto si el que lo agrega es admin.
            if($kick != FALSE){
                $telegram->send->ban($new->id, $telegram->chat->id);
                $pokemon->user_delgroup($new->id, $telegram->chat->id);
            }
            return -1;
        }
    }

    // Si el grupo no admite más usuarios...
    $nojoin = $pokemon->settings($telegram->chat->id, 'limit_join');
    // TODO excepto si el que lo agrega es admin.
    if($nojoin and !in_array($this->telegram->user->id, telegram_admins(TRUE))){
        $this->analytics->event('Telegram', 'Join limit users');
        $telegram->send->ban_until("+5 minutes", $new->id, $telegram->chat->id);
        $pokemon->user_delgroup($new->id, $telegram->chat->id);

		if($adminchat){
			$str = ":warning: Limit Join - bloqueo entrada.\n"
					.":id: " .$new->id ."\n"
					.":abc: " .$new->first_name ." - @" .$pknew->username;
			$str = $telegram->emoji($str);
			$telegram->send
				->inline_keyboard()
					->row_button("Desbanear", "desbanear " .$new->id, "TEXT")
				->show()
				->notification(TRUE)
				->chat($adminchat)
				->text($str)
			->send();
		}

        return -1;
    }

    // Si el grupo requiere validados
    if(
        $pokemon->settings($telegram->chat->id, 'require_verified') == TRUE &&
        $pokemon->settings($telegram->chat->id, 'require_verified_kick') == TRUE
    ){
        if(empty($pknew) or $pknew->verified != TRUE){
            $this->analytics->event('Telegram', 'Kick unverified user');
            $q = $telegram->send->ban_until("+2 minutes", $new->id, $telegram->chat->id);
			$str = "Usuario " . $new->first_name ." / " .$new->id ." no está verificado.";
			if($q !== FALSE){
				$pokemon->user_delgroup($new->id, $telegram->chat->id);
				$str = "Usuario " .$new->first_name ." / " .$new->id ." kickeado por no estar verificado.";
			}
			if($adminchat){
				$str = ":warning: Usuario no validado.\n"
						.":id: " .$new->id ."\n"
						.":abc: " .$new->first_name ." - @" .$pknew->username;
				$str = $telegram->emoji($str);
				$telegram->send
					->notification(TRUE)
					->chat($adminchat)
					->text($str)
				->send();
			}
			else{
				$telegram->send
				->text($str)
				->send();
			}
            return -1;
        }
    }

    // Si un usuario generico se une al grupo
    if($set != FALSE or $set === NULL){
        $custom = $pokemon->settings($telegram->chat->id, 'welcome');
        $text = 'Bienvenido al grupo, $nombre!' ."\n";
        if(!empty($custom)){ $text = json_decode($custom) ."\n"; }
		if($pokemon->settings($telegram->chat->id, 'require_avatar')){
			// TODO Get avatar
			// $text .= "Por favor, antes de nada, ponte una foto de avatar. La gente quiere ver caras familiares, no letras. :*" ."\n";
		}
        if(empty($pknew)){
			if($pokemon->settings($telegram->chat->id, 'force_welcome') != TRUE){
				$text .= "Oye, ¿podrías decirme el color de tu equipo?\n*Di: *_Soy ..._";
			}
        }else{
            $emoji = ["Y" => "yellow", "B" => "blue", "R" => "red"];
            $text .= '$pokemon $nivel $equipo $valido $ingress';

            if(!$pknew->verified && $pokemon->settings($telegram->chat->id, 'require_verified')){
                $text .= "\n" ."Para estar en este grupo *debes estar validado.*";

                $telegram->send
                    ->inline_keyboard()
                        ->row_button("Validar", "quiero validarme", "COMMAND")
                    ->show();
            }
        }

        if($new->id == $this->config->item("telegram_bot_id")){
			$text = ":new: ¡Grupo nuevo!\n"
					.":abc: " .$telegram->chat->title ."\n"
					.":id: " .$telegram->chat->id ."\n"
					.":guard: " .$count ."\n" // del principio de ejecución.
					.":man: " .$telegram->user->id ." - " .$telegram->user->first_name;
			$telegram->send
				->chat($this->config->item('creator'))
				->text($telegram->emoji($text))
			->send();
            $pkuser = $pokemon->user($telegram->user->id);
            if(
                ($pkuser && $pkuser->blocked) or
                $pokemon->user_flags($telegram->user->id, ['hacks', 'ratkid', 'poketelegram_cheat'])
            ){
                $telegram->send->leave_chat();
                return -1;
            }
            $text = "¡Buenas a todos, entrenadores!\n¡Un placer estar con todos vosotros! :D";

			$group = $pokemon->group($telegram->chat->id);
			if($group->messages == 0){
				$text .= "\nVeo que este grupo es nuevo, así que voy a buscar cuánta gente conozco.";
				// TODO si el Oak es nuevo en un grupo de más de X personas,
				// Realizar investigate sólo una vez.

				// Esto se puede hacer con el count de mensajes de un grupo, si es > 0.
				// Teniendo en cuenta que el grupo no se borre de la DB para que
				// no vuelva a ejecutarse este método.
			}
        }

        $pokemon->user_addgroup($new->id, $telegram->chat->id);
        $this->analytics->event('Telegram', 'Join user');

        $ingress = NULL;
        if($pokemon->settings($new->id, 'resistance')){ $ingress = ":key:"; }
        elseif($pokemon->settings($new->id, 'enlightened')){ $ingress = ":frog:"; }

        $repl = [
            '$nombre' => $new->first_name,
            '$apellidos' => $new->last_name,
            '$equipo' => ':heart-' .$emoji[$pknew->team] .':',
            '$team' => ':heart-' .$emoji[$pknew->team] .':',
            '$usuario' => "@" .$new->username,
            '$pokemon' => "@" .$pknew->username,
            '$nivel' => "L" .$pknew->lvl,
            '$valido' => $pknew->verified ? ':green-check:' : ':warning:',
            '$ingress' => $ingress
        ];
        $text = str_replace(array_keys($repl), array_values($repl), $text);
		$text = $telegram->emoji($text);
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text( $text , TRUE)
        ->send();

		if($new->id == $this->config->item('telegram_bot_id')){ return -1; } // HACK Stop processing.

		if($adminchat){
			$str = ":new: Entra al grupo\n"
					.":id: " .$new->id ."\n"
					.":abc: " .$new->first_name ." - @" .($pknew->username ?: $new->username);
			$str = $telegram->emoji($str);
			$telegram->send
				->notification(TRUE)
				->chat($adminchat)
				->text($str)
			->send();
		}

        if(!empty($pknew)){
            $team = $pknew->team;
            $key = $pokemon->settings($telegram->chat->id, 'pair_team_' .$team);
            if(!empty($key)){
                $teamchat = $pokemon->group_pair($telegram->chat->id, $team);
                if(!$teamchat){
                    $telegram->send
                        ->chat($this->config->item('creator'))
                        ->notification(TRUE)
                        ->text("Problema con pairing $team en " .$telegram->chat->id ." (" .substr($key, 0, 10) .")")
                    ->send();
                    return -1;
                }
                // Tengo chat, comprobar blacklist
                $black = explode(",", $pokemon->settings($teamchat, 'blacklist'));
                if($pokemon->user_flags($new->id, $black)){ return -1; }

                $link = $pokemon->settings($teamchat, 'link_chat');
                if(empty($link)){
                    $telegram->send
                        ->chat($this->config->item('creator'))
                        ->notification(TRUE)
                        ->text("Problema con pair link $team en " .$telegram->chat->id ." (" .substr($key, 0, 10) .")")
                    ->send();
                    return -1;
                }
                // Si es validado
                $color = ['Y' => 'Amarillo', 'R' => 'Rojo', 'B' => 'Azul'];
                $text = "Hola! Veo que eres *" .$color[$pknew->team] ."* y acabas de entrar al grupo " .$telegram->chat->title .".\n"
                        ."Hay un grupo de tu team asociado, pero no te puedo invitar porque no estás validado " .$telegram->emoji(":warning:") .".\n"
                        ."Si *quieres validarte*, puedes decirmelo. :)";
                if($pknew->verified){
                    $text = "Hola! Te invito al grupo *" .$color[$pknew->team] ."* asociado a " .$telegram->chat->title .". "
                            ."¡No le pases este enlace a nadie!\n"
                            .$telegram->grouplink($link);
                }

                if(!$telegram->user_in_chat($new->id, $teamchat)){
                    $telegram->send
                        ->notification(TRUE)
                        ->chat($new->id)
                        ->text($text, NULL) // TODO NO Markdown.
                    ->send();

                    if($pknew->verified){
                        $telegram->send
                            ->notification(TRUE)
                            ->chat($teamchat)
                            ->text("He invitado a @" .$pknew->username ." a este grupo.")
                        ->send();
                    }
                }
            }
        }
    }
    return -1;
}elseif($telegram->is_chat_group() && $telegram->data_received("left_chat_participant")){
	$left = $telegram->new_user; // HACK nombre confunde.
    $pokemon->user_delgroup($left->id, $telegram->chat->id);
	if($left->id == $this->config->item('telegram_bot_id')){
		// Limpieza general
		$this->db
			->where('chat', $telegram->chat->id)
		->delete('poleauth');

		$str = ":door: Me echan :(\n"
				.":id: " .$telegram->chat->id ."\n"
				.":abc: " .$telegram->chat->title ."\n"
				.":guard: " .$telegram->user->id ." - " .$telegram->user->first_name;

		$this->telegram->send
			->notification(TRUE)
			->chat($this->config->item('creator'))
			->text($telegram->emoji($str))
		->send();
	}
    return -1;
}

?>
