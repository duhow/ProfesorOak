<?php

class Admin extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		// get si Oak es admin.
		// get si el user es admin.
		// carga y guarda caché de admin.
		if(!$this->chat->is_group()){ return; }
		parent::run();
	}

	protected function hooks(){

	}

	public function antipalmera(){
		if($this->telegram->is_chat_group() && $this->telegram->sticker()){
			$palmeras = [
		        'BQADBAADzw0AAu7oRgABumTXtan23SUC',
		        'BQADBAAD0Q0AAu7oRgABXE1L0Qpaf_sC',
		        'BQADBAAD0w0AAu7oRgABq22PAgABeiCcAg',
		        // GATO
		        'BQADBAAD4wEAAqKYZgABGO27mNGhdSUC',
		        'BQADBAAD5QEAAqKYZgABe9jp1bTT8jcC',
		        'BQADBAAD5wEAAqKYZgABiX1O201m5X0C',
				// Puke Rainbow
				'BQADBAADjAADl4mhCRp2GRnaNZ2EAg',
				'BQADBAADpgADl4mhCfVfg6PMDlAyAg',
				'BQADBAADqAADl4mhCVMHew7buZpwAg',
		    ];
			if(in_array($this->telegram->sticker(), $palmeras)){
				if($this->user->is_admin() or $this->user->id == CREATOR){ return; }
				if(!$this->chat->is_admin($this->telegram->bot->id)){ return; }
		        // $admins = array();
		        /* if(function_exists('telegram_admins')){
		            $admins = telegram_admins(TRUE);
		            if(in_array($this->config->item('telegram_bot_id'), $admins)){
		                if(in_array($telegram->user->id, $admins)){ return; }
		                $telegram->send->text("¡¡PALMERAS NO!!")->send();
		                $telegram->send->kick($telegram->user->id, $telegram->chat->id);
		            }
		        } */
		        return TRUE;
		    }
		}
		return FALSE;
	}

	public function antiflood(){
		if($this->chat->is_admin($this->user->id)){ return; }
		$amount = NULL;
		if($this->telegram->text_command()){ $amount = 1; }
		elseif($this->telegram->photo()){ $amount = 0.8; }
		elseif($this->telegram->sticker()){
			$allowed = [
				"AAjbFNAAB" // + BQADBAAD - Oak Games
			];
			$amount = 1; // Default
			foreach($allowed as $s){
				if(strpos($this->telegram->sticker(), $s) === FALSE){
					$amount = 0;
					break;
				}
			}
		}
		// elseif($this->telegram->document()){ $amount = 1; }
		elseif($this->telegram->gif()){ $amount = 1; }
		elseif($this->telegram->text() && $this->telegram->words() >= 50){ $amount = 0.5; }
		elseif($this->telegram->text()){ $amount = -0.4; }
		// TODO Spam de text/segundo.
		// TODO Si se repite la última palabra.

		$countflood = 0;
		if($amount !== NULL){ $countflood = (float) $this->chat->settings('spam'); }

		if($countflood >= $flood){

			if($this->chat->settings('antiflood_ban') == TRUE){
				$res = $this->ban($this->user->id, $this->chat->id);

				if($this->chat->settings('antiflood_ban_hidebutton') != TRUE){
					$this->telegram->send
					->inline_keyboard()
						->row_button("Desbanear", "desbanear " .$this->telegram->user->id, "TEXT")
					->show();
				}
			}else{
				$res = $this->kick($this->user->id, $this->chat->id);
			}

			if($res){
				// $pokemon->group_spamcount($this->telegram->chat->id, -1.1); // Avoid another kick.
				// $pokemon->user_delgroup($this->telegram->user->id, $this->telegram->chat->id);
				$this->telegram->send
					->text("Usuario expulsado por flood. [" .$this->user->id .(isset($this->telegram->user->username) ? " @" .$this->telegram->user->username : "") ."]")
				->send();
				$adminchat = $this->chat->settings('admin_chat');
				if($adminchat){
					// TODO forward del mensaje afectado
					$this->telegram->send
						->chat($adminchat)
						->text("Usuario " .$this->telegram->user->id .(isset($this->telegram->user->username) ? " @" .$this->telegram->user->username : "") ." expulsado del grupo por flood.")
					->send();
				}
				$this->end(); // No realizar la acción ya que se ha explusado.
			}
			// Si tiene grupo admin asociado, avisar.
		}
	}

	public function antispam(){
	    if($this->user->messages <= 5 && $this->chat->settings('antispam') != FALSE){
	        if(
				!$this->telegram->text_contains(["http", "www", ".com", ".es", ".net"]) &&
	            !$this->telegram->text_contains("telegram.me") or
	            $this->telegram->text_contains(["PokéTrack", "PokeTrack"]) or
	            $this->telegram->text_contains(["maps.google", "google.com/maps"])
	        ){ return FALSE; } // HACK Falsos positivos.

	        // TODO mirar antiguedad del usuario y mensajes escritos. - RELACIÓN.
	        $this->telegram->send
	            ->message(TRUE)
	            ->chat(TRUE)
	            ->forward_to(CREATOR)
	        ->send();

	        $this->telegram->send
	            ->chat(CREATOR)
	            ->text("*SPAM* del grupo " .$this->chat->id .".", TRUE)
	            ->inline_keyboard()
	                ->row_button("No es spam", "/nospam " .$this->user->id ." " .$this->chat->id, "TEXT")
	            ->show()
	        ->send();

			$this->user->flags[] = 'spam';
			$this->user->update();

	        $this->telegram->send
	            ->text("¡*SPAM* detectado!", TRUE)
	        ->send();

	        $this->ban($this->user->id, $this->chat->id);
	    }
	}

	public function kick($user, $chat){
		if(is_array($user)){
			$c = 0;
			foreach($user as $u){
				$q = $this->kick($u, $chat);
				if($q !== FALSE){ $c++; }
			}
			return $c;
		}
		$this->ban($user, $chat);
		return $this->unban($user, $chat);
	}

	public function ban($user, $chat){
		return $this->telegram->send->ban($user, $chat);
	}

	public function unban($user, $chat){
		return $this->telegram->send->unban($user, $chat);
	}

	public function multikick($users){
		// TODO Quitar al Oak de la lista
		$c = $this->kick($users, $chat);
		$str = "No puedo echar a nadie :(";
		if($c > 0){ $str = "Vale, " .$c ." fuera!"; }

		$this->telegram->send
			->text($str)
		->send();
		return $c;
	}

	// Kick all users who didn't say anything during X days.
	public function kick_old($days = 30, $chat = NULL, $countonly = FALSE){
		$users = $this->db
			->where('cid', $chat)
			->where('(last_date <= ? OR last_date = ?)', [date("Y-m-d H:i:s", strtotime("-$days days")), "0000-00-00 00:00:00"])
		->get('user_inchat');
		if($this->db->count == 0 or $countonly){ return $this->db->count; }
		return $this->multikick(array_column($users, 'uid'));
	}

	// List all users in group and kick those who are unverified.
	public function kick_unverified($chat = NULL, $countonly = FALSE){
		// TODO Comprobar que los que no estén registrados, también los eche. LEFT/RIGHT ?
		$users = $this->db
			->join('user_inchat c', 'u.telegramid = c.uid')
			->where('c.cid', $chat)
			->where('u.verified', FALSE)
		->get("user u", null, "c.uid");
		if($this->db->count == 0 or $countonly){ return $this->db->count; }
		return $this->multikick(array_column($users, 'uid'));
	}

	// List all users in group and kick those who haven't send minimum messages.
	public function kick_messages($min = 6, $chat = NULL, $countonly = FALSE){
		$users = $this->db
			->where('cid', $chat)
			->where('messages >=', $min)
		->get('user_inchat');
		if($this->db->count == 0 or $countonly){ return $this->db->count; }
		return $this->multikick(array_column($users, 'uid'));
	}

	// Forward the current message to the groups set.
	public function forward_to_groups(){

	}
}
