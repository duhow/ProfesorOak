<?php

class Creator extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public function run(){
		if($this->telegram->user->id != CREATOR){ return; }
		if($this->user->step){
			$method = "step_" .strtolower($this->user->step);
			if(method_exists($this, $method) and is_callable([$this, $method])){
				$this->$method();
			}
		}
		parent::run();
	}

	protected function hooks(){
		if($this->telegram->text_has("mal") && $this->telegram->words() < 4 && $this->telegram->has_reply){
			$this->hide_message(TRUE, TRUE);
			$this->end();
		}

		if($this->telegram->has_reply && $this->telegram->text_has("Este") && $this->telegram->words() > 3){
			$reply = $this->telegram->reply_user;
			$word = $this->telegram->last_word();

			if($this->telegram->text_has("Este", "se llama")){

			}elseif($this->telegram->text_has("Este", "es nivel")){

			}elseif($this->telegram->text_has("Este", ["es color", "es del equipo"])){

			}
			$this->end();
		}

		// Salir de un grupo.
		if($this->telegram->text_regex("^salte de {chatid}$")){
			$id = $this->telegram->input->chatid;
		    $this->telegram->send->leave_chat($id);
			$chat = new Chat($id);
			$chat->disable();
			$this->end();
		}

		if(
			$this->chat->is_group() and
			$this->telegram->has_reply and
			$this->telegram->text_has(["te asciendo", "ascender"], ["a admin", "a administrador", "como admin"])
		){
			$this->addadmin();
		}

		if($this->telegram->text_command("f") and $this->telegram->words() >= 2){
			$this->setflag(TRUE);
		}

		if($this->telegram->text_command("r")){
			$this->register();
		}
	}

	private function get_user($onlyid = FALSE, $wordpos = 2){
		$user = NULL;
		if($this->telegram->has_reply){
	        $user = $this->telegram->reply_target('forward')->id;
	    }elseif($this->telegram->text_mention()){
	        $user = $this->telegram->text_mention();
	        if(is_array($user)){ $user = key($user); }
	    }elseif($this->telegram->words() == $wordpos){
	        $user = $this->telegram->last_word();
	        if(!is_numeric($user)){ return NULL; }
	    }

		if($onlyid){ return $user; }
		return new User($user);
	}

	public function cinfo($id){
		$this->telegram->send
			->text( $this->chat_info($id) )
		->send();
		$this->end();
	}

	public function ui(){ return $this->uinfo(); }
	public function uinfo(){
		$u = $this->get_user(FALSE);

		if(empty($u)){ $this->end(); }
		$chat = ($this->chat->is_group() ? $this->chat->id : $u);

		$this->telegram->send
			->notification(FALSE)
			->text( $this->user_info ($u, $chat) )
		->send();

		$this->end();
	}

	private function user_info($id, $chat){
		$find = $telegram->send->get_member_info($id, $chat);

	    $str = "Desconocido.";
	    if($find !== FALSE){
	        $str = $find['user']['id'] . " - " .$find['user']['first_name'] ." " .$find['user']['last_name'] ." ";
	        if(in_array($find['status'], ["administrator", "creator"])){ $str .= ":star:"; }
	        elseif(in_array($find['status'], ["left"])){ $str .= ":door:"; }
	        elseif(in_array($find['status'], ["kicked"])){ $str .= ":forbid:"; }
	        else{ $str .= ":multiuser:"; }

	        if(!$pk){ $str .= " :question-red:"; }
	        else{
	            $colors = ["Y" => "yellow", "R" => "red", "B" => "blue"];
	            $str .= " :heart-" .$colors[$pk->team] .":";
	        }

			// TODO
	        // $info = $this->chat->user_in_group($id, $chat);
			$info = FALSE;
	        if($info){
	            $str .= "\n:calendar: " .date("d/m/Y H:i", strtotime($info->register_date))
						."\n:date: " .date("d/m/Y H:i", strtotime($info->last_date))
						."\n:speech_balloon: " .$info->messages;
	        /* }elseif($this->telegram->user_in_chat($find['user']['id'])){
	            $pokemon->user_addgroup($find['user']['id'], $telegram->chat->id);
	        } */
			}

			$str = $telegram->emoji($str);
	    }
		return $str;
	}

	private function chat_info($id){
		$info = $this->telegram->send->get_chat($id);
	    $count = $this->telegram->send->get_members_count($id);
		$str = "Nope.";
		if($info != FALSE){
			$str = ":id: " .$info['id'] ."\n"
					.":abc: " .($info['title'] ?: $info['first_name']) ."\n"
					.":globe_with_meridians: " .($info['username'] ? "@" .$info['username'] : "---") ."\n"
					.":vibration_mode: " .$info['type'] ."\n";
			if($info['type'] != 'private'){
				$info = $this->telegram->send->get_member_info($this->telegram->bot->id, $id);
				$str .= ":restroom: " .$count ."\n"
						.":info: " .$info['status'];
			}

			$str = $this->telegram->emoji($str);
		}
		return $str;
	}

	public function addadmin($user = NULL, $chat = NULL){
		if(
			(!$this->chat->is_group() and empty($chat)) or
			(!$this->telegram->has_reply and empty($user))
		){ $this->end(); }

		// TODO
		// Ver si soy admin y puedo redelegar permisos de Telegram.
		// Si no, volver al método clásico - avisar, gestión via Oak.
		$admins = $this->chat->settings("admins");
		if(!empty($admins)){
			$admins = explode(",", $admins);
		}
		$orig = $admins;
		$admins[] = $this->telegram->reply_user->id;
		$admins = array_unique($admins);

		if($admins != $orig){
			$admins = implode(",", $admins);
			$this->chat->settings("admins", $admins);

			$this->telegram->send
				->notification(TRUE)
				->reply_to(FALSE)
				->caption("¡Has sido ascendido a Administrador!")
				->file('voice', "AwADBAADBQEAAsqrWFDJMI7qKiTnawI");
		}

		$this->end();
	}

	public function whereis($find = NULL){
		if(empty($find)){ $find = $this->get_user(FALSE); }
		$user = $find;

		if(!is_numeric($find)){
			$user = $this->db->subQuery();
			$user
				->orWhere('username', $find)
			->get('user', NULL, 'telegramid');
		}

		$groups = $this->db
			->join('chats c', 'c.id = u.cid')
			->where('u.uid', $user)
		->getValue('user_inchat u', 'c.title', NULL);
		if($this->db->count == 0){ $text = "No lo veo por ningún lado."; }
		else{
			$text = $find ."\n";
			$text .= implode("\n", $groups);
		}

		$this->telegram->send
			->text($text)
		->send();

		$this->end();
	}

	public function flags($user = NULL){
		if(!is_numeric($user) or empty($user)){ $user = $this->get_user(TRUE); }

		$str = "Eing?";
		if(!empty($user)){
			$str = "No tiene.";
			$flags = $this->db
				->where('user', $user)
			->getValue('user_flags', 'DISTINCT value');
			if(count($flags) > 0){ $str = implode(", ", $flags); }
		}

		$this->telegram->send
			->chat(CREATOR)
			->text($str)
		->send();

		$this->end();
	}

	public function setflag($flags, $user = NULL){
		if(empty($user)){ $user = $this->get_user(TRUE); }
		// Nueva short-tag, separar palabras.
		if($flags === TRUE){
			if(empty($user)){
				if(!is_numeric($this->telegram->last_word())){ $this->end(); }
				$user = $this->telegram->last_word();
			}

			$flags = str_replace(",", " ", $this->telegram->text());
			$flags = explode(" ", $flags);
			array_shift($flags); // remove first - command
		}

		if(is_string($flags)){ $flags = [$flags]; }

		$list = [
			'CF' => 'change_faction',
			'CN' => 'change_name',
			'CA' => 'change_account',
			'PM' => 'posible_multiaccount',
			'PF' => 'posible_fly',
			'PT' => 'posible_troll',
			'MA' => 'multiaccount',
			'TR' => 'troll',
			'PB' => 'permaban'
		];

		$flaglist = array();

		foreach($flags as $t){
			if(strlen($t) <= 1){ continue; }
			$tu = strtoupper($t);
			if(in_array($tu, array_keys($list))){
				$flaglist[] = ['user' => $user, 'value' => $list[$tu]];
			}elseif(strlen($t) > 2 && !is_numeric($t)){
				$flaglist[] = ['user' => $user, 'value' => $t];
			}
		}

		$ids = $this->db->insertMulti('user_flags', $flaglist);
		$str = "Flags no insertadas. :(";
		if($ids){
			$str = $this->telegram->emoji(":ok: ") ."Aplico flags " .implode(", ", array_column($flaglist, 'value')) .".";
		}

		$q = $this->telegram->send
				->text($str)
			->send();

		sleep(3);
		$this->telegram->send->delete($q);
	}

	public function mode($mode = NULL, $user = NULL){
		if(empty($user)){ $user = $this->get_user(TRUE); }
		if(empty($user)){ $this->end(); } // Si no se ha podido cargar...

		// GET MODE
		if(empty($mode)){
			$step = $this->db
				->where('telegramid', $user)
			->getValue('user', 'step');
			if(empty($step)){ $step = "NULL"; }

			$this->telegram->send
				->text($step)
			->send();

		// SET MODE
		}else{
			$mode = strtoupper($mode);
			if(in_array($mode, ["NULL", "DELETE", "OFF"])){ $mode = NULL; }
			$this->db
				->where('telegramid', $user)
			->update('user', ['step' => $mode]);

			$this->telegram->send
				->text("set!")
			->send();

		}
		$this->end();
	}

	public function ban(){
		/* $target = NULL;
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
		} */
	}

	public function unban(){
		$target = NULL;
		$target_chat = NULL;

		if($this->telegram->has_reply){
			$target = $this->telegram->reply_target('forward')->id;
			if($this->telegram->is_chat_group()){ $target_chat = $this->chat->id; }
			elseif($this->telegram->words() == 2){ $target_chat = $this->telegram->last_word(); }
		}elseif($this->telegram->words() == 3){
			$target = $this->telegram->words(1);
			$target_chat = $this->telegram->words(2);
		}

		if(!empty($target) && !empty($target_chat)){
			$q = $this->telegram->send->unban($target, $target_chat);
			$str = "No puedo :(";

			if($q !== FALSE){
				$str = "Usuario $target desbaneado" .($target_chat != $this->telegram->chat->id ? " de $target_chat" : "") .".";
			}

			$this->telegram->send
				->text($str)
			->send();
		}
	}

	public function nospam($user, $chat = NULL){
		// HACK text_has porque comandos no se parsean en INLINE_keyboard.
	    /* $target = NULL;
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
	    } */
	}

	private function hide_message($message = TRUE, $chat = TRUE){
		if($message === TRUE){ $message = $this->telegram->reply->message_id; }
		return $this->telegram->send
			->message($message)
			->chat($chat)
			->notification(FALSE)
			->text("Perdon :(")
		->edit('message');
	}

	public function block($user = NULL){ $this->block_user(TRUE, $user); $this->end(); }
	public function unblock($user = NULL){ $this->block_user(FALSE, $user); $this->end(); }
	private function block_user($value = TRUE, $user = NULL){
		if(empty($user)){
			if($this->telegram->has_reply){
				$user = $this->telegram->reply_target('forward')->id;
		    }elseif($this->telegram->words() == 2 && $this->telegram->text_mention()){
		        // $user = $this->telegram->text_mention(); // --> to UID.
		    }elseif($this->telegram->words() == 2){
				$user = $this->telegram->last_word(TRUE);
			}
		}

	    if(empty($user)){ $this->end(); }

		$ret = $this->db
			->where('telegramid', $user)
			->orWhere('username', $user)
		->update('user', ['blocked' => $value], 1);

		// Si se bloquea, especificar motivo.
		if($value){
			$this->user->step = "BLOCK_REASON";
			$this->user->settings('block_user_reason', $user);
			$this->telegram->send
				->chat($this->user->id)
				->keyboard()
					->button(":floppy_disk: Guardar")
				->show(TRUE, TRUE)
				->text(":question: ¿Motivo del block a $user?")
			->send();
		}

		return $ret;
	}

	public function speak($target = NULL){
		if($this->chat->is_group()){ $this->end(); }
		if(empty($target) and $this->telegram->has_reply){
			$target = $this->telegram->reply_target('forward')->id;
		}
		if(empty($target) or in_array(strtolower($target), ["stop", "off", "false"])){
			// Ver si estaba en modo de hablar.
			if($this->user->settings('speak')){
				$this->user->settings('speak_last', $this->user->settings('speak'));
				$this->user->settings('speak', "DELETE");
				$this->user->step = NULL;
		        $this->telegram->send
		            ->text($this->telegram->emoji(":forbid: Chat detenido."))
		        ->send();
			}elseif($this->user->settings('speak_last') and empty($target)){
				// Hablar con el último que estaba.
				return $this->speak($this->user->settings('speak_last'));
			}
		}

		// Vale, pues vamos a hablar!
		// Si es supergrupo pero le falta el negativo.
		if(is_numeric($target) and strlen($target) > 11 and $target < 0){
			$target = "-" .$target;
		}

		// Si no es número, vamos a ver qué es.
		if(!is_numeric($target)){
			// Usuario Pokemon?
			$find = $this->db
				->where('username', $target)
				->orWhere('telegramuser', $target)
				->orderBy('last_action', 'ASC')
			->getValue('user', 'telegramid', 1);

			// Nombre de grupo?
			if(!$find){
				$possible = [$find];
				if($find[0] != "@"){ $possible[] = "@" . $find; }
				$find = $this->db
					->where('type', ['name', 'link_chat'], 'IN')
					->where('value', $possible, 'IN')
					->orderBy('lastupdate', 'DESC')
				->getValue('settings', 'uid', 1);
			}

			if(!$find){
				$this->telegram->send
					->text($this->telegram->emoji(":forbid: No encuentro ese grupo o persona."))
				->send();
				$this->end();
			}

			$target = $find;
		}

		// Listo! Entonces... Estoy ahi?
		if(!$this->telegram->user_in_chat($this->telegram->bot->id, $target)){
	        $this->telegram->send
	            ->text($this->telegram->emoji(":times: No estoy :("))
	        ->send();
	        $this->end();
	    }

		// Parece que si. Voy a sacar la info.
		$info = $this->telegram->send->get_chat($target);
		$forward = FALSE;

		// Si es privado, o grupo y no estoy ahí, reenvia.
		if($info['type'] == "private" or !$this->telegram->user_in_chat($this->user->id, $target)){
			$targetchat = new Chat($target);
			$targetchat->settings('forward_interactive', TRUE);
	        $forward = TRUE;
	    }

		// Titulo del chat.
		$title = (isset($chat['title']) ? $chat['title'] : $chat['first_name'] ." " .$chat['last_name']);

		// Envia acción de escribiendo...
		$q = $this->telegram->send
			->chat($target)
			->chat_action("typing")
		->send();

		$str = $this->telegram->emoji(":times: No se puede conectar.");
		// Si la accion se envía sin problemas...
		if($q !== FALSE){
			$str = $this->telegram->emoji(":ok: ") .($forward ? "Forwarding activo. " : "") ."Hablando en " .$title;
			$this->user->settings('speak', $target);
			$this->user->step = 'SPEAK';
		}

	    $this->telegram->send
	        ->text($str)
	    ->send();
	    $this->end();
	}

	public function banall($user = NULL){ return $this->ban_user_all($user, FALSE); }
	public function kickall($user = NULL){ return $this->ban_user_all($user, TRUE); }
	private function ban_user_all($user = NULL, $kick = FALSE){
		if(empty($user)){
			if($this->telegram->has_reply){
				$user = $this->telegram->reply_target('forward')->id;
		    }elseif($this->telegram->words() == 2 && $this->telegram->text_mention()){
		        // $user = $this->telegram->text_mention(); // --> to UID.
		    }elseif($this->telegram->words() == 2){
				$user = $this->telegram->last_word(TRUE);
				$user = $this->db
					->where('username', $user)
					->orWhere('telegramuser', $user)
					->orderBy('last_action', 'ASC')
				->getValue('user', 'telegramid', 1);
			}
		}

	    if(empty($user)){ return -1; }

		// lista de grupos del usuario
		$groups = $this->db
			->where('uid', $user)
		->getValue('user_inchat', 'cid');

		$c = 0; // Contador
		foreach($groups as $g){
			$q = $this->telegram->send->ban($target, $g);
			if($kick){ $this->telegram->send->unban($target, $g); }
			if($q !== FALSE){ $c++; }
		}

		$this->telegram->send
			->text("Fuera $c de " .count($groups) ." grupos.")
		->send();

		return $c;
	}

	// Registro y modificacion de datos de un usuario.
	public function register(){
		if(!$this->telegram->has_reply or $this->telegram->words() < 2){ $this->end(); }

		$data['telegramid'] = $this->telegram->reply_target('forward')->id;
		$data['telegramuser'] = @$this->telegram->reply_target('forward')->username;

		foreach($telegram->words(TRUE) as $w){
	        $w = trim($w);
	        if($w[0] == "/"){ continue; }
	        if(is_numeric($w) && $w >= 5 && $w <= 40){ $data['lvl'] = $w; }
			if(is_numeric($w) && $w > 40){ $data['exp'] = (int) $w; }
	        if(in_array(strtoupper($w), ['R','B','Y'])){ $data['team'] = strtoupper($w); }
	        if($w[0] == "@" or strlen($w) >= 4 && !is_numeric($w)){ $data['username'] = $w; }
	        if(strtoupper($w) == "V"){ $data['verified'] = TRUE; }
	    }

		// No ha habido registro.
	    $register = FALSE;

		// Cargar info de usuario + nombre por si hay duplicado
		if(isset($data['username'])){
			$this->db->orWhere('username', $data['username']);
		}

		$check = $this->db
			->orWhere('telegramid', $data['telegramid'])
		->getValue('user', ['telegramid', 'username']);

		// Crear objeto del usuario.
		$newUser = new User($data['telegramid']);

		// Si el usuario no está registrado en DB, registrar.
		if(!in_array($data['telegramid'], array_column($check, 'telegramid'))){
			// Minimo tiene que haber team.
			if(!isset($data['team'])){
	            $this->telegram->send
	                ->notification(FALSE)
	                ->text($this->telegram->emoji(":times: Falta team."))
	            ->send();
	            $this->end();
	        }
			// Si el registro falla...
			if($newUser->register($team) === FALSE){
				$this->telegram->send
					->text($this->strings->get('error_register'))
				->send();
				$this->end();
			}

	        $register = TRUE;
		}

		// Cargar datos del usuario.
		$newUser->load();

		// Si hay que cambiar nombre, buscar si está duplicado
		if(isset($data['username'])){
			foreach($check as $user){
				if(
					($user['telegramid'] != $data['telegramid']) and // Si otra persona
					(strtolower($user['username']) == $data['username']) // tiene el mismo nombre
				){
					$str = ":times: Nombre duplicado por " .$user['telegramid'] .", cancelando.";
					$this->telegram->send
						->text($this->telegram->emoji($str))
					->send();
					$this->end();
				}
			}
		}

		// Actualizar datos
		$this->db
			->where('telegramid', $data['telegramid'])
		->update('user', $data);

		// Anunciar el registro
		$str = ":ok: Hecho" .(isset($data['verified']) ? " y validado!" : "!");
		// Si no se ha registrado, anunciar los cambios.
	    if($register === FALSE){
	        $changes = array();
	        if(isset($data['lvl']) && $newUser->lvl != $data['lvl'] ){ $changes[] = "nivel"; }
			if(isset($data['exp']) && $newUser->exp != $data['exp']){ $changes[] = "experiencia"; }
	        if(isset($data['team']) && $newUser->team != $data['team'] ){ $changes[] = "equipo"; }
	        if(isset($data['username']) && $newUser->username != $data['username']){ $changes[] = "nombre"; }
	        $str = ":ok: Cambio <b>" .implode(", ", $changes) .(isset($data['verified']) ? "</b> y <b>valido</b>!" : "</b>!");
	    }

	    $this->telegram->send
	        ->notification(FALSE)
	        ->text($this->telegram->emoji($str), TRUE)
	    ->send();
	    $this->end();
	}

	private function step_speak(){
		if(
			$this->telegram->is_chat_group() or
			$this->telegram->callback or
			($this->telegram->text() && substr($this->telegram->words(0), 0, 1) == "/")
		){ return; }
		$chattalk = $this->user->settings('speak');
		if($this->telegram->user->id != CREATOR or $chattalk == NULL){
			$this->user->step = NULL;
			return;
		}
		$this->telegram->send
			->notification(TRUE)
			->chat($chattalk);

		if($this->telegram->text()){
			$type = 'Markdown';
			if(strip_tags($this->telegram->text()) != $this->telegram->text()){ $type = 'HTML'; }
			$this->telegram->send->text( $this->telegram->text(), $type )->send();
		}elseif($this->telegram->photo()){
			$this->telegram->send->file('photo', $this->telegram->photo());
		}elseif($this->telegram->sticker()){
			$this->telegram->send->file('sticker', $this->telegram->sticker());
		}elseif($this->telegram->voice()){
			$this->telegram->send->file('voice', $this->telegram->voice());
		}elseif($this->telegram->gif()){
			$this->telegram->send->file('document', $this->telegram->gif());
		}elseif($this->telegram->video()){
			$this->telegram->send->file('video', $this->telegram->video());
		}
		$this->end();
	}

	private function step_block_reason(){
		if(
			$this->chat->is_group() or
			$this->telegram->text_command() or
			$this->telegram->callback
		){ return; }

		$blocked_user = $this->user->settings('block_user_reason');

		// ???
		if(
			!$blocked_user or
			($this->telegram->text_has("Guardar") and $this->telegram->words() <= 3)
		){
			$this->user->settings('block_user_reason', "DELETE");
			$this->user->step = NULL;
			if($this->telegram->text_has("Guardar")){
				$this->telegram->send
					->text($this->telegram->emoji(":floppy_disk:") ." Razones guardadas.")
				->send();
			}
			$this->end();
		}

		$key = "block_reason_" .substr(md5($this->telegram->id), 0, 8);
		$reason = "";
		if($this->telegram->photo()){
			$reason = "photo:" .$this->telegram->photo();
		}elseif($this->telegram->text()){
			$reason = $this->telegram->text();
		}

		if(empty($reason)){ return; }

		$data = [
			'uid' => $blocked_user,
			'type' => $key,
			'value' => $reason,
			'displaylist' => FALSE
		];

		$this->db->insert('settings', $data);
		$this->end();
	}

	public function broadcast(){}
	public function usercast(){}
}
