<?php

class Creator extends TelegramApp\Module {
	protected $runCommands = FALSE;

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
		}elseif(
			$this->telegram->text_command("block") or
			$this->telegram->text_command("unblock")
		){
			$this->end();
		}elseif($this->telegram->text_command("unban")){
			/* $target = NULL;
		    $target_chat = NULL;

		    if($telegram->has_reply){
		        $target = $telegram->reply_user->id;
		        if($telegram->is_chat_group()){ $target_chat = $telegram->chat->id; }
		    }elseif($telegram->words() == 3){
		        $target = $telegram->words(1);
		        $target_chat = $telegram->words(2);
		    }

		    if(!empty($target) && !empty($target_chat)){
		        $telegram->send->unban($target, $target_chat);
		        $telegram->send
		            ->text("Usuario $target desbaneado" .($target_chat != $telegram->chat->id ? " de $target_chat" : "") .".")
		        ->send();
		    } */
			$this->end();
		}elseif($this->telegram->text_command("ban") && !$this->telegram->is_chat_group() && $this->telegram->words() >= 3){

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
			$this->end();
		}
	}

	function chat_info($id){
		$info = $telegram->send->get_chat($id);
	    $count = $telegram->send->get_members_count($id);
		$str = "Nope.";
		if($info != FALSE){
			$str = "\ud83c\udd94 " .$info['id'] ."\n"
					."\ud83d\udd24 " .($info['title'] ?: $info['first_name']) ."\n"
					."\ud83c\udf10 " .($info['username'] ? "@" .$info['username'] : "---") ."\n"
					."\ud83d\udcf3 " .$info['type'] ."\n"
					."\ud83d\udebb " .$count ."\n";
			$info = $telegram->send->get_member_info($this->config->item('telegram_bot_id'), $id);
			$str .= "\u2139\ufe0f " .$info['status'];

			$str = $telegram->emoji($str);
		}
	}

	function nospam($user, $chat = NULL){
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

	function hide_message($message = TRUE, $chat = TRUE){
		if($message === TRUE){ $message = $this->telegram->reply->message_id; }
		return $this->telegram->send
			->message($message)
			->chat($chat)
			->notification(FALSE)
			->text("Perdon :(")
		->edit('message');
	}

	function block_user($user, $value = TRUE){
		// $user = NULL;
	    if($this->telegram->has_reply){
			$user = $this->telegram->reply_target('forward')->id;
	    }elseif($this->telegram->words() == 2 && $this->telegram->text_mention()){
	        // $user = $this->telegram->text_mention(); // --> to UID.
	    }
	    if(empty($user)){ return -1; }
	    $pokemon->update_user_data($user, 'blocked', $this->telegram->text_contains("/block"));
	}
}
