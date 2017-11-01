<?php

class Creator extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public function run(){
		if($this->telegram->user->id != CREATOR){ return; }
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

	public function addadmin(){
		if(!$this->chat->is_group() or !$this->telegram->has_reply){ $this->end(); }

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
		$user = $this->get_user();

		$str = "No tiene.";
		if(!empty($user)){
			$user->load();
			if(!empty($user->flags)){ $str = implode(", ", $user->flags); }
		}

		$this->telegram->send
			->chat(CREATOR)
			->text($str)
		->send();

		$this->end();
	}

	public function setflag($flag, $user = NULL){
		if(empty($user)){ $user = $this->get_user(TRUE); }
		if(is_numeric($user)){ $user = new User($user); }

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

	public function block(){ $this->block_user(TRUE); $this->end(); }
	public function unblock(){ $this->block_user(FALSE); $this->end(); }
	private function block_user($value = TRUE, $user = NULL){
		$user = NULL;
	    if($this->telegram->has_reply){
			$user = $this->telegram->reply_target('forward')->id;
	    }elseif($this->telegram->words() == 2 && $this->telegram->text_mention()){
	        // $user = $this->telegram->text_mention(); // --> to UID.
	    }
	    if(empty($user)){ return -1; }
		$ruser = new User($user);
		if(empty($ruser)){ return FALSE; }

		$ruser->blocked = $value;
		$ruser->update();

		return TRUE;
	}
}
