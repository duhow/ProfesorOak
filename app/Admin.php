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
				if($this->user->id == CREATOR or $this->chat->is_admin($this->user)){ return NULL; }
				if(!$this->chat->is_admin($this->telegram->bot->id)){ return NULL; }
				$this->telegram->send
					->text("¡¡PALMERAS NO!!")
				->send();
				return $this->kick($this->user);
			}
		}
		return FALSE;
	}

	public function antiflood(){
		if(empty($this->chat->settings('antiflood'))){ return; }

		if($this->chat->is_admin($this->user)){ return; }
		$amount = NULL;

		// Valoración de daños
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
		if($amount !== NULL){
			$countflood = (float) round(max($this->chat->settings('antiflood_count') + $amount, 0), 2);
		}

		if($countflood >= $this->chat->settings('antiflood')){
			if($this->chat->settings('antiflood_ban') == TRUE){
				$res = $this->ban($this->user->id, $this->chat->id);
				if($this->chat->settings('antiflood_ban_hidebutton') != TRUE){
					$this->telegram->send
					->inline_keyboard()
						->row_button($this->strings->get("admin_unban"), "desbanear " .$this->user->id, "TEXT")
					->show();
				}
			}else{
				$res = $this->kick($this->user->id, $this->chat->id);
			}

			if($res){
				$countflood = ($countflood - 1.1); // Avoid another kick.
				$this->chat->settings('antiflood_count', $countflood); // Save

				$str = $this->strings->get("admin_kicked_flood")
					." [" .$this->user->id .(isset($this->telegram->user->username) ? " @" .$this->telegram->user->username : "") ."]";

				$this->telegram->send
					->text($str)
				->send();

				// TODO forward del mensaje afectado
				$str = ":forbid: " .$this->strings->get('admin_kicked_reason_flood') ."\n"
					.":id: " .$this->user->id ."\n"
					.":abc: " .$this->telegram->user->first_name ." - @" .$this->user->username;
				$this->admin_chat_message($str);
				$this->end(); // No realizar la acción ya que se ha explusado.
			}
		}

		$this->chat->settings('antiflood_count', $countflood);
	}

	public function antispam(){
		if($this->user->messages > 5 or $this->chat->settings('antispam') === FALSE){ return FALSE; }

		if(
			!$this->telegram->text_contains(["http", "www", ".com", ".es", ".net"]) and
			!$this->telegram->text_contains(["telegram.me", "t.me"]) or
			$this->telegram->text_contains(["PokéTrack", "PokeTrack"]) or
			$this->telegram->text_contains(["maps.google", "google.com/maps", "goo.gl/maps"])
		){ return FALSE; } // HACK Falsos positivos.
		if(stripos($this->telegram->text_url(), "pokemon") !== FALSE){ return FALSE; } // HACK cosas de Pokemon oficiales u otros.

		// TODO mirar antiguedad del usuario y mensajes escritos. - RELACIÓN.
		if($this->user->is_admin()){ return FALSE; }

		$this->telegram->send
			->message(TRUE)
			->chat(TRUE)
			->forward_to(CREATOR)
		->send();

		$this->telegram->send
			->chat(CREATOR)
			->text("<b>SPAM</b> del grupo " .$this->chat->id .".", 'HTML')
			->inline_keyboard()
				->row_button("No es spam", "/nospam " .$this->user->id ." " .$this->chat->id, "TEXT")
			->show()
		->send();

		$this->user->flags[] = 'spam';
		$this->user->update();

		$this->telegram->send
			->text($this->strings->get('admin_spam_detected'), 'HTML')
		->send();

		$this->ban($this->user);
		$this->end();
	}

	public function antiafk(){
		$antiafk = $this->chat->settings('antiafk');
		if(!is_numeric($antiafk) or $antiafk <= 1){ $antiafk = 5; }
		$except = [$this->telegram->bot->id, $this->telegram->user->id];

		$query = $this->db
			// ->select(['uid', 'register_date'])
			->where('cid', $this->chat->id)
			->where('messages', 0)
			->where('register_date = last_date')
			->where('register_date IS NOT NULL')
			->where("DATE_ADD(register_date, INTERVAL $antiafk MINUTE) < NOW()")
			->where('uid', $except, 'NOT IN')
		->getOne('user_inchat');

		if(!empty($query)){
			$afk = (object) $query;

			$q = $this->kick($afk->uid);
			if($q !== FALSE){
				// $pokemon->user_delgroup($afk->uid, $this->telegram->chat->id);
				$str = ":warning: AntiAFK Newbie\n"
						.":id: " .$afk->uid ."\n"
						.":calendar_spiral: " .$afk->register_date;

				$str = $this->telegram->emoji($str);
				$this->admin_chat_message($str);
			}
		}
	}

	public function antinoavatar(){
		$query = $this->db
			// ->select('messages')
			->where('cid', $this->chat->id)
			->where('uid', $this->telegram->user->id)
		->getOne('user_inchat');

		if(!empty($query) and $query['messages'] == 5){
			// TODO Get avatar
			if(1 == 2){
				$q = $this->kick($this->telegram->user->id);

				if($q === FALSE){
					$str = ":warning: No foto de perfil\n"
							.":id: " .$telegram->user->id ."\n"
							.":male: " .$telegram->user->first_name;
				}else{
					// Si está kick, quitar del grupo.
					// $pokemon->user_delgroup($telegram->user->id, $this->telegram->chat->id);

					$str = ":forbid: Kick por no foto de perfil\n"
							.":id: " .$telegram->user->id ."\n"
							.":male: " .$telegram->user->first_name;
				}

				$str = $this->telegram->emoji($str);
				$this->admin_chat_message($str);

				$this->end();
			}
		}
	}

	public function mute_content(){
		$mute = explode(",", $this->chat->settings('mute_content'));
		if(
			(in_array("url", $mute) and $this->telegram->text_url()) or
			(in_array("command", $mute) and $this->telegram->text_command()) or
			(in_array("gif", $mute) and $this->telegram->gif()) or
			(in_array("photo", $mute) and $this->telegram->photo()) or
			(in_array("sticker", $mute) and $this->telegram->sticker()) or
			(in_array("voice", $mute) and $this->telegram->voice()) or
			(in_array("audio", $mute) and $this->telegram->audio()) or
			(in_array("video", $mute) and $this->telegram->video()) or
			(in_array("game", $mute) and $this->telegram->game()) or
			(in_array("document", $mute) and $this->telegram->document())
			// + bot on NewUser
		){
			$q = $this->telegram->send->delete(TRUE);
			if($q !== FALSE){ return -1; }
		}
	}

	public function kick($user, $chat = NULL){
		if(empty($chat)){ $chat = $this->chat->id; }
		if(is_array($user)){
			$c = 0;
			foreach($user as $u){
				$q = $this->kick($u, $chat);
				if($q !== FALSE){ $c++; }
			}
			return $c;
		}
		if($user instanceof User or $user instanceof \Telegram\User){ $user = $user->id; }
		if($user == $this->telegram->bot->id){ return FALSE; }

		$q = $this->telegram->send->ban_until("+30 seconds", $u, $chat);
		if($q !== FALSE){
			$this->db
				->where('uid', $u)
				->where('cid', $chat)
			->delete('user_inchat');
		}
		return $q;
	}

	public function ban($user, $chat = NULL){
		if(empty($chat)){ $chat = $this->chat->id; }
		// Evitar autoban del bot. Usar leave si es necesario.
		if($user == $this->telegram->bot->id){ return FALSE; }

		$q = $this->telegram->send->ban($user, $chat);
		if($q !== FALSE){
			$this->db
				->where('uid', $u)
				->where('cid', $chat)
			->delete('user_inchat');
		}
		return $q;
	}

	public function unban($user, $chat = NULL){
		if(empty($chat)){ $chat = $this->chat->id; }
		if($user instanceof User){ $user = $user->id; }
		return $this->telegram->send->unban($user, $chat);
	}

	public function multikick($users){
		$c = $this->kick($users, $chat);
		$str = "No puedo echar a nadie :(";
		if($c > 0){ $str = "Vale, " .$c ." fuera!"; }

		$this->telegram->send
			->text($str)
		->send();
		return $c;
	}

	// Kick all users who didn't say anything during X days.
	public function count_users_old($days = 30, $chat = NULL, $countonly = FALSE){
		$users = $this->db
			->where('cid', $chat)
			->where('(last_date <= ? OR last_date = ?)', [date("Y-m-d H:i:s", strtotime("-$days days")), "0000-00-00 00:00:00"])
		->getValue('user_inchat', "uid", NULL);
		if($this->db->count == 0 or $countonly){ return $this->db->count; }
		return $this->multikick(array_column($users, 'uid'));
	}

	// List all users in group and kick those who are unverified.
	public function count_users_unverified($chat = NULL, $countonly = FALSE){
		// TODO Comprobar que los que no estén registrados, también los eche. LEFT/RIGHT ?
		$users = $this->db
			->join('user_inchat c', 'u.telegramid = c.uid')
			->where('c.cid', $chat)
			->where('u.verified', FALSE)
		->get("user u", "c.uid", NULL);
		if($this->db->count == 0 or $countonly){ return $this->db->count; }
		return array_column($users, 'uid');
	}

	// List all users in group and kick those who haven't send minimum messages.
	public function count_users_messages($min = 3, $chat = NULL, $countonly = FALSE){
		$users = $this->db
			->where('cid', $chat)
			->where('messages <=', $min)
		->getValue('user_inchat', 'uid', NULL);
		if($this->db->count == 0 or $countonly){ return $this->db->count; }
		return array_column($users, 'uid');
	}

	public function count_users_team($team, $chat = NULL, $countonly = FALSE){
		$users = $this->db
			->join('user_inchat c', 'u.telegramid = c.uid')
			->where('c.cid', $chat)
			->where('u.team', $team)
		->getValue("user u", "c.uid", NULL);
		if($this->db->count == 0 or $countonly){ return $this->db->count; }
		return array_column($users, 'uid');
	}

	// Migrate settings from old chat to new chat.
	public function migrate_settings($to, $from = NULL){
		if(empty($from)){ $from = $this->chat->id; }

		return $this->db
			->where('uid', $from)
		->update('settings', ['uid' => $to]);
	}

	// Forward the current message to the groups set.
	public function forward_to_groups(){

	}

	public function admin_chat_message($message, $notify = TRUE){
		$adminchat = $this->chat->settings('admin_chat');
		if(!empty($adminchat)){
			return $this->telegram->send
				->chat($adminchat)
				->notification($notify)
				->text($message)
			->send();
		}
		return FALSE;
	}

	// WIP VERIFY
	public function cache_admin_list($group, $timeout = "+1 hour", $add = FALSE){
		if(is_array($add)){
			$addid = array();
			foreach($add as $u){
				if($u instanceof User){ $addid[] = $u->id; }
				elseif(is_numeric($u)){ $addid[] = $u; }
			}
			if(empty($addid)){ return FALSE; }
			$data = array();
			foreach($addid as $id){
				$data[] = [
					'timeout' => date("Y-m-d H:i:s", strtotime($timeout)),
					'gid' => $group,
					'uid' => $id
				];

				return $this->db->insertMulti($data);
			}
		}
		$admins = $this->db
			->where('gid', $group)
			->where('timeout >=', $this->db->now())
		->getValue('user_admins', 'DISTINCT uid', NULL);
		if(!empty($admins)){
			// !!!!!
			if($this->chat->id == $group){
				$this->chat->admins = $admins;
			}
			return $admins;
		}
		return $this->cache_admin_list($group, $timeout, $this->telegram->send->get_admin_list($group));
	}
}
