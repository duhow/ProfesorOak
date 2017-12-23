<?php

class Group extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		if(!$this->chat->is_group()){ return; }
		if($this->user->step){
			$method = "step_" .strtolower($this->user->step);
			if(method_exists($this, $method) and is_callable([$this, $method])){
				$this->$method();
			}
		}
		parent::run();
	}

	// ALIAS
	private function step_rules(){ return $this->step_welcome(); }
	private function step_welcome(){
		if(!$this->chat->is_admin($this->user)){
			$this->user->step = NULL;
			$this->end();
		}

		$text = $this->telegram->text_encoded();
		if(strlen($text) < 4){ $this->end(); }
		if(strlen($text) > 4000){
			$this->telegram->send
				->text($this->strings->get('group_text_too_much'))
			->send();
			$this->end();
		}
		// $this->analytics->event('Telegram', 'Set rules');
		// $this->analytics->event('Telegram', 'Set welcome');

		$this->chat->settings(strtolower($this->user->step), $text);
		$this->user->step = NULL;

		$this->telegram->send
			->text($this->strings->get('group_text_done'))
		->send();
		$this->end();
	}
	private function step_custom_command(){ return $this->custom_command_create(); }

	protected function hooks(){
		if(
			$this->telegram->text_command("autokick") or
			$this->telegram->text_command("adios") or
			$this->telegram->text_has("bomba de humo")
		){
			$this->autokick();
			$this->end();
		}

		elseif(
			( $this->telegram->text_regex($this->strings->get('command_admin_list')) and $this->telegram->words() <= 8 ) or
			( $this->telegram->text_command(["adminlist", "admins"]) )
		){
			$this->adminlist();
			$this->end();
		}

		elseif(
			$this->telegram->text_command("uv") or
			(
				$this->telegram->text_regex($this->strings->get('command_user_list')) and
				$this->telegram->text_regex($this->strings->get('command_user_list_unverified'))
			)
		){
			$this->user_list_verified();
			$this->end();
		}

		elseif(
			$this->telegram->text_command("ul") or
			(
				$this->telegram->text_regex($this->strings->get('command_user_list')) and
				$this->telegram->words() <= $this->strings->get('command_user_list_limit')
			) or (
				$this->telegram->callback and
				$this->telegram->text_regex('userlist {N:offset}')
			) and $this->chat->is_admin($this->user)
		){
			$offset = (isset($this->telegram->input->offset) ? $this->telegram->input->offset : 1);
			$this->user_list(TRUE, $offset);
			$this->end();
		}

		elseif(
			$this->telegram->text_regex($this->strings->get('command_is_admin')) &&
			$this->telegram->words() <= 5
		){
			$target = ($this->telegram->has_reply ? $this->telegram->reply_target('forward') : $this->user->id);
			$this->check_admin($target);
			$this->end();
		}

		elseif(
			$this->telegram->text_command("count") or
			$this->telegram->text_regex($this->strings->get('command_user_count'))
		){
			$this->user_count(TRUE);
			$this->end();
		}

		elseif(
			// WIP
			// $this->telegram->text_has("offtopic") or
			$this->telegram->text_command("offtopic", FALSE)
		){
			$this->offtopic();
			$this->end();
		}

		elseif(
			(
				$this->telegram->text_regex($this->strings->get('command_rules')) or
				$this->telegram->text_command($this->strings->get('command_rules_slash')) and
				$this->telegram->words() <= $this->strings->get('command_rules_limit')
			)
		){
			$this->rules();
			$this->end();
		}

		elseif(
			(
				$this->telegram->text_has($this->strings->get('command_rules_write')) and
				$this->telegram->text_regex($this->strings->get('command_welcome_message')) and
				$this->telegram->words() <= $this->strings->get('command_rules_limit')
			)
		){
			$this->welcome();
			$this->end();
		}

		elseif(
			$this->telegram->text_regex($this->strings->get('command_is_here')) and
			$this->telegram->words() <= $this->strings->get('command_is_here_limit') and
			!in_array($this->telegram->input->username, $this->strings->get('command_is_here_black'))
		){
			$this->ishere($this->telegram->input->username);
			$this->end();
		}

		elseif(
			$this->telegram->text_regex($this->strings->get('command_custom_command_create')) and
			$this->telegram->words() <= $this->strings->get('command_custom_command_create_limit') and
			!$this->user->step and $this->chat->is_admin($this->user)
		){
			$this->user->settings('command_name', "DELETE");
			$this->custom_command_create();
			$this->end();
		}

		elseif(
			$this->telegram->text_regex($this->strings->get('command_custom_command_delete')) and
			!$this->user->step and $this->chat->is_admin($this->user)
		){
			$this->custom_command_delete($this->telegram->input->command);
			$this->end();
		}

		elseif(
			$this->telegram->text_regex($this->strings->get('command_custom_command_list')) and
			$this->telegram->words() <= ($this->strings->get('command_custom_command_create_limit') + 1) and
			!$this->user->step
		){
			if(
				!$this->chat->settings('custom_command_list_user') and
				!$this->chat->is_admin($this->user)
			){ $this->end(); }
			$this->custom_command_list();
			$this->end();
		}
	}

	public function user_count($chat = NULL, $say = FALSE){
		if(is_bool($chat)){ $say = $chat; $chat = NULL; }
		if(empty($chat)){ $chat = $this->chat->id; }

		$total = $this->telegram->send->get_members_count($chat);
		$members = $this->db
			->where('cid', $chat)
		->getValue('user_inchat', 'count(*)');

		$sels = array();
		foreach(['Y', 'B', 'R'] as $team){
			$sels[] = "SUM(IF(team = '$team', 1, 0)) AS '$team'";
		}

		$users = $this->db
			->join("user_inchat c", "u.telegramid = c.uid")
			->where('c.cid', $chat)
			->where('u.telegramid', [$this->telegram->bot->id], 'NOT IN')
		->get('user u', NULL, $sels);
		$users = current($users); // Seleccionar primera row [0].

		if(!$say){
			return (object) [
				'team' => $users,
				'total' => $total,
				'count' => $members
			];
		}

		// if($pokemon->command_limit("count", $telegram->chat->id, $telegram->message, 10)){ return FALSE; }

		$str = $this->strings->parse('group_count_result', [$members, $total, array_sum($users), round((array_sum($users) / $total) * 100)]) ."\n"
				.":heart-yellow: " .$users["Y"] ." "
				.":heart-red: "    .$users["R"] ." "
				.":heart-blue: "   .$users["B"] ."\n"
				.$this->strings->parse('group_count_result_left', ($total - array_sum($users)));

		return $this->telegram->send
			->notification(FALSE)
			->text($this->telegram->emoji($str))
		->send();
	}

	public function autokick(){
		$ret = $this->Admin->kick($this->user->id);
		if(
			$this->telegram->text_has("bomba de humo") and
			$this->user->id == CREATOR and
			$ret
		){
			$this->telegram->send->file('sticker', 'CAADBAADFQgAAjbFNAABxSRoqJmT9U8C');
		}
		return $ret;
	}

	public function adminlist($chat = NULL){
		// Get cache admin DB
		// If empty or expired, get from telegram
		if(empty($chat)){ $chat = $this->chat->id; }
		$admins = $this->telegram->send->get_admins($chat);
		if(empty($admins)){
			$str = "No hay admins! :o";
			$this->telegram->send
				->text($this->strings->get('group_no_admin'))
			->send();
			$this->end();
		}

		$creator = NULL;
		$self = FALSE;
		$adminlist = array();
		$uidlist = array();
		foreach($admins as $user){
			if($user['status'] == "creator"){
				$creator = $user['user'];
				$uidlist[] = $user['user']->id;
			}elseif($user['user']->id == $this->telegram->bot->id){
				$self = TRUE;
			}else{
				$adminlist[] = $user['user'];
				$uidlist[] = $user['user']->id;
			}
		}

		$users = $this->db
			->where('telegramid', $uidlist, 'IN')
			->where('anonymous', FALSE)
		->get('user');

		$str = "";
		foreach($adminlist as $user){
			$pokeuser = array_search($user->id, array_column($users, 'telegramid'));
			$str .= $users[$pokeuser]['team']
				.' L' .$users[$pokeuser]['lvl']
				.' ' .$this->telegram->userlink($user->id, $users[$pokeuser]['username'])
				.' - ' .strval($user) ."\n";
		}

		$this->telegram->send
			->text($str, 'HTML')
		->send();
	}

	public function abandon(){
		$abandon = $this->chat->settings('abandon');
		if($abandon){
			if(json_decode($abandon) != NULL){ $abandon = json_decode($abandon); }
			$str = ($abandon == TRUE ? $this->strings->get('error_chat_abandoned') : $abandon);

			$this->telegram->send
				->text($str)
			->send();

			$this->end();
		}
	}

	public function user_count_verified($chat = NULL, $verified = TRUE, $offset = 1, $retstr = FALSE){
		if($offset < 1){ $offset = 1; }
		if($chat === TRUE){ $chat = $this->chat->id; }

		$this->db->pageLimit = 20;
		$users = $this->db
			->join("user_inchat c", "u.telegramid = c.uid")
			->where("c.cid", $chat)
			->where("u.anonymous", FALSE)
			->where('u.verified', $verified)
		->paginate('user u', $offset);
		$str = "";

		$icons = [
			'B' => $this->telegram->emoji(':blue_heart:'),
			'R' => $this->telegram->emoji(':heart:'),
			'Y' => $this->telegram->emoji(':yellow_heart:'),
		];

		foreach($users as $user){
			$str .= $icons[$user['team']] .' L' .$user['lvl'] .' ' .$this->telegram->userlink($user['telegramid'], ($user['username'] ?: '-------')) ."\n";
		}
		$str = $this->telegram->emoji($str);
		if($retstr){ return $str; }

		$this->send_user_list_common($str, $offset, 'userveri');
		$this->telegram->answer_if_callback("");
		$this->end();
	}

	public function user_list_verified(){

	}

	public function user_list($chat = NULL, $offset = 1, $retstr = FALSE){
		if($offset < 1){ $offset = 1; }
		if($chat === TRUE){ $chat = $this->chat->id; }

		$this->db->pageLimit = 20;
		$users = $this->db
			->join("user_inchat c", "u.telegramid = c.uid")
			->where("c.cid", $chat)
			->where("u.anonymous", FALSE)
			->orderBy('u.team', 'ASC')
			->orderBy('u.lvl', 'DESC')
		->paginate('user u', $offset);
		$str = "";

		$icons = [
			'B' => $this->telegram->emoji(':blue_heart:'),
			'R' => $this->telegram->emoji(':heart:'),
			'Y' => $this->telegram->emoji(':yellow_heart:'),
		];

		foreach($users as $user){
			$str .= $icons[$user['team']] .' L' .$user['lvl'] .' ' .$this->telegram->userlink($user['telegramid'], ($user['username'] ?: '-------')) ."\n";
		}
		$str = $this->telegram->emoji($str);
		if($retstr){ return $str; }

		$this->send_user_list_common($str, $offset, 'userlist');
		$this->telegram->answer_if_callback("");
		$this->end();
	}

	private function send_user_list_common($str, $offset, $action){
		// REVIEW: Si es primera pagina y somos pocos, no pongas boton.
		if(!($offset == 1 and count(explode("\n", $str)) <= 19)){
			$this->paginator_data($offset, $action);
		}

		$this->telegram->send->convert_emoji = FALSE;
		$this->telegram->send->text($str, 'HTML');
		if($this->telegram->callback){
			return $this->telegram->send
				->chat(TRUE)
				->message(TRUE)
			->edit('text');
		}else{
			return $this->telegram->send
				->notification(FALSE)
			->send();
		}
	}

	// Works only with current DB query, as totalPages is needed.
	private function paginator_data($offset, $action){
		// Anterior - final
		if($offset >= $this->db->totalPages){
			$this->telegram->send
				->inline_keyboard()
					->row_button('<<', "$action " .($this->db->totalPages - 1), 'TEXT')
				->show();
		// Siguiente - principio y hay mas
		}elseif($offset == 1 and $this->db->totalPages > $offset){
			$this->telegram->send
				->inline_keyboard()
					->row_button('>>', "$action 2", 'TEXT')
				->show();
		// Anterior y siguiente - hay mas
		}elseif($offset > 1 and $offset < $this->db->totalPages){
			$this->telegram->send
				->inline_keyboard()
					->row()
						->button('<<', "$action " .($offset - 1), 'TEXT')
						->button($offset, "$action $offset", 'TEXT')
						->button('>>', "$action " .($offset + 1), 'TEXT')
					->end_row()
				->show();
		}
	}

	public function check_admin($user = NULL, $chat = NULL, $forceCheck = FALSE){
		if(empty($user)){ $user = $this->user->id; }
		if(empty($chat)){ $chat = $this->chat->id; }
		if($user instanceof User){ $user = $user->id; }
		if($chat instanceof Chat){ $chat = $chat->id; }

		// GET Telegram
		if($forceCheck){
			$admins = $this->telegram->get_admins($chat);
			return in_array($user, $admins);
		}

		$this->db
			->where('gid', $chat)
			->where('uid', $user)
			->where('expires >= NOW()')
		->getOne('user_admins');
		return ($this->db->count == 1);
	}

	public function check_user($search, $chat = NULL, $forceQuery = FALSE){
		if($search instanceof User){ $search = $search->id; }
		if(empty($chat)){ $chat = $this->chat->id; }
		if($chat instanceof Chat){ $chat = $chat->id; }
		if(!is_numeric($search)){
			$query = $this->db
				->where("(username = '$search' OR telegramuser = '$search')")
				->where('anonymous', FALSE)
			->getOne('user', 'telegramid');
			// TODO CHECK
		}
		if($forceQuery){
			// TODO Add to inchat DB?
			return $this->telegram->user_in_chat($serach, $chat);
		}
		$this->db
			->where('gid', $chat)
			->where('uid', $search)
		->get('user_inchat');
		return ($this->db->count == 1);
	}

	public function votekick(){
		// Create vote object [v] [x]
		// Trigger function each vote and depending on count users
		// Count users include "active today"
		// Minimal votes are needed or not (5)
		//
	}

	public function voteban(){

	}

	public function rules_agreement(){
		// Para pertener a este grupo, debes leer y aceptar las normas.
		// Se establece un tiempo minimo de lectura de 1 minuto
		// Variable en funcion de las palabras / letras que haya en las normas.
		// Tabla user_chat_agreements (uid, cid, type, OK 1/0/NULL)
	}

	// TODO move to admin
	public function warn($reason = NULL, $user = NULL, $chat = NULL){
		$message = NULL;

		// Si hay reply...
		if($this->telegram->has_reply){
			if(empty($user)){ $user = $this->telegram->reply_user->id; }

			if($this->telegram->text()){
				$message = $this->telegram->text_encoded();
			}elseif($this->telegram->photo()){
				$message = "PHOTO:" .$this->telegram->photo();
			}
		}

		if(empty($user)){ return FALSE; }

		if(empty($chat)){ $chat = $this->chat->id; }
		if($chat instanceof Chat){ $chat = $chat->id; }
		if($user instanceof User){ $user = $user->id; }

		$data = [
			'message' => $message,
			'cid' => $chat,
			'uid' => $user, // User warned
			'aid' => $this->user->id, // Who warns
			'reason' => $reason,
			'active' => TRUE,
			'date' => $this->db->now(),
		];

		$this->db->insert('user_warns', $data);
	}

	public function warn_list($user, $chat){
		// Get if current chat is admin
		// Get assoc chat
		if($chat){ $this->db->where('cid', $chat); }

		$warns = $this->db
			->join('user u', 'user_warns.aid = user.telegramid')
			->where('uid', $user)
			->where('active', TRUE)
		->get('user_warns w', NULL, 'u.username AS admin, w.date, w.cid');

		if($this->db->count == 0){
			$this->telegram->send
				->notification(FALSE)
				->text($this->strings->get('warn_empty'))
			->send();
			return FALSE;
		}

		$str = $this->strings->parse('warn_list_count', count($warns)) ."\n";
		foreach($warns as $warn){
			$str .= $this->strings->parse('warn_list_row', [
				Tools::DateParser($warn['date'], "dh"),
				$warn['admin']
			]);
			if(!$chat){ $str .= " @ " .$warn['cid']; }
			$str .= "\n";
		}

		$this->telegram->send
			->notification(FALSE)
			->text($str)
		->send();
		return TRUE;
	}

	public function rules(){
		if($this->telegram->text_has($this->strings->get('command_rules_write'))){
			// Si es admin, cambiar las normas
			if($this->chat->is_admin($this->user)){
				$this->user->step = "RULES";
				$this->telegram->send
					->reply_to(TRUE)
					->text($this->strings->get('group_rules_please_send'))
				->send();
			}
		}else{
			$rules = $this->chat->settings('rules');
			$str = ($rules ? json_decode($rules) : $this->strings->get('group_rules_empty'));
			// TODO: "Además de cumplir el TCC, este grupo tiene las siguientes normas:"
			if(strlen($str) >= 1000 or count(explode(" ", $str)) >= 100){
				$this->telegram->send
					->notification(FALSE)
					->text($this->strings->get('group_rules_private'))
				->send();
				// Para enviar las normas por privado.
				$this->telegram->send->chat($this->user->id);
			}
			$this->telegram->send
				->text($str)
			->send();
		}
		$this->end();
	}

	public function ishere($user = NULL){
		if(!$this->chat->is_group()){ return NULL; }
		if(in_array($user, $this->strings->get('command_is_here_black'))){ return NULL; }

		if(is_string($user)){
			$user = str_replace("@", "", $user);
			$username = $this->db
				->where('username', $user)
			->getValue('user', 'telegramid');
			if(!$username){
				$this->telegram->send
					->notification(FALSE)
					->text($this->strings->parse('group_user_here_unknown', $user))
				->send();
				$this->end();
			}
			$user = $username;
		}

		$look = $this->telegram->user_in_chat($user, $this->chat->id, TRUE);
		$here = ($look ? "yes" : "no");
		$linkname = ($here ? '<a href="tg://user?id=' .$look->user->id .'">' .strval($look->user) .'</a>' : "");
		$r = $this->telegram->send
			->notification(FALSE)
			->text($this->strings->parse('group_user_here_' .$here, $linkname))
		->send();
		if($here){
			$this->Main->message_assign_set($r, $look->user->id);
		}
	}

	public function welcome(){
		if(!$this->chat->is_group()){ return NULL; }
		if($this->telegram->text_has($this->strings->get('command_rules_write'))){
			// Si es admin, cambiar la bienvenida
			if($this->chat->is_admin($this->user)){
				$this->user->step = "WELCOME";
				$this->telegram->send
					->reply_to(TRUE)
					->text($this->strings->get('group_welcome_please_send'))
				->send();
			}
		}
		$this->end();
	}

	public function offtopic(){
		// TODO Limitar repeticion de comando.
		$offtopic = $this->chat->settings('offtopic_chat');
		if($offtopic){
			$this->telegram->send
				->text($this->telegram->grouplink($offtopic, TRUE))
			->send();
		}
	}

	// Dar name y devolver ID
	public function search_by_name($data){
		$query = $this->db
			->where('type', 'name')
			->where('value', $data)
		->getOne('settings', 'uid');
		return (!empty($query) ? $query['uid'] : NULL);
	}

	public function custom_commands(){
		if(
			$this->user->blocked or
			in_array($this->user->flags, ['ratkid', 'troll', 'rager', 'spamkid'])
		){ $this->end(); }

		$commands = $this->chat->settings('custom_commands');
		if(!$commands or ($this->user->step)){ return FALSE; }
		// $commands = unserialize($commands);
		if(is_array($commands)){
			foreach($commands as $word => $action){
				// FIX Regex
				$word = str_replace(['.', '?'], ['\.', '\?'], $word);
				if($this->telegram->text_has($word, TRUE)){
					$content = current($action);
					$action = key($action);
					if($action == "text"){
						$this->telegram->send->text(json_decode($content))->send();
					}elseif($action == "location"){
						$this->telegram->send->location($content)->send();
					}else{
						$this->telegram->send->file($action, $content);
					}
					$this->end();
				}
			}
		}
	}

	public function custom_command_create(){
		$this->user->step = 'CUSTOM_COMMAND';
		if($this->user->settings('command_name')){
			// Ver el contenido que ha enviado, y guardarlo en DB.
			$commands = $this->chat->settings('custom_commands');
			$cname = $this->user->settings('command_name');

			$content = array();
			if($this->telegram->text()){
				if(strlen(trim($this->telegram->text())) < 4){ $this->end(); }
				$content = ["text" => $this->telegram->text_encoded()];
			}elseif($this->telegram->photo()){
				$content = ["photo" => $this->telegram->photo()];
			}elseif($this->telegram->video()){
				$content = ["video" => $this->telegram->video()];
			}elseif($this->telegram->voice()){
				$content = ["voice" => $this->telegram->voice()];
			}elseif($this->telegram->gif()){
				$content = ["document" => $this->telegram->gif()];
			}elseif($this->telegram->sticker()){
				$content = ["sticker" => $this->telegram->sticker()];
			}elseif($this->telegram->location()){
				$content = ["location" => implode(",", [$this->telegram->location()->latitude, $this->telegram->location()->longitude])];
			}

			if(empty($content)){
				$this->telegram->send
					->text($this->strings->get_random('custom_command_unknown_content'))
				->send();
				$this->end();
			}

			$commands[$cname] = $content;
			$this->chat->settings('custom_commands', $commands);

			$this->user->step = NULL;
			$this->telegram->send
				->text($this->strings->get('custom_command_created'))
			->send();
			$this->end();
		}else{
			if($this->telegram->text_regex($this->strings->get('command_custom_command_create'))){
				$this->telegram->send
					->text($this->strings->get('custom_command_create'))
				->send();
				$this->end();
			}elseif($this->telegram->text()){
				$text = strtolower(trim($this->telegram->text()));
				if(
					strlen($text) <= 2 or
					strlen($text) >= 30 or
					$this->telegram->text_has_emoji or
					$this->telegram->words() > 6
				){
					$str = "";
					$this->end();
				}else{
					$this->user->settings('command_name', $text);
					$str = $this->strings->get('custom_command_answer');
				}
				$this->telegram->send
					->text($str)
				->send();
				$this->end();
			}
		}
	}

	public function custom_command_delete($command){
		$commands = $this->chat->settings('custom_commands');
		if(!$commands){
			$this->telegram->send
				->notification(FALSE)
				->text($this->strings->get('config_custom_commands_off'))
			->send();
			return FALSE;
		}

		if(array_key_exists($command, $commands)){
			unset($commands[$command]);
			$this->chat->settings('custom_commands', $commands);

			$this->telegram->send
				->text($this->strings->parse('custom_command_deleted', $command))
			->send();
		}

		return TRUE;
	}

	public function custom_command_list(){
		$commands = $this->chat->settings('custom_commands');
		if(!$commands){
			$this->telegram->send
				->notification(FALSE)
				->text($this->strings->get('config_custom_commands_off'))
			->send();
			return FALSE;
		}

		$str = $this->strings->parse('config_custom_commands_on', count($commands)) ."\n";
		foreach($commands as $command => $data){
			$str .= $command ."\n";
		}

		$this->telegram->send
			->notification(FALSE)
			->text($str)
		->send();

		return TRUE;
	}
}
