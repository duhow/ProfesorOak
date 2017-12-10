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
			$offset = 0;
			if($this->telegram->input->offset){ $offset = $this->telegram->input->offset; }
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
				$this->telegram->text_command(["rules", "normas"]) and
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
		if($this->telegram->text_has("bomba de humo") and $this->user->id == CREATOR){
			$this->telegram->send->file('sticker', 'CAADBAADFQgAAjbFNAABxSRoqJmT9U8C');
		}
		return $this->Admin->kick($this->user->id);
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

	public function user_count_verified($chat = NULL){

	}

	public function user_list($chat = NULL, $offset = 0, $retstr = FALSE){
		// TODO limitar repeticion - cooldown de boton
		if($offset < 0){ $offset = 0; }
		if($chat === TRUE){ $chat = $this->chat->id; }

		$this->db->pageLimit = 25;
		$users = $this->db
			->join("user_inchat c", "u.telegramid = c.uid")
			->where("c.cid", $chat)
			->where("u.anonymous", FALSE)
		->paginate('user u', $offset);
		$str = "";
		foreach($users as $user){
			$str .= ":heart-" .$user['team'] .": L" .$user['lvl'] ." - " .$this->telegram->userlink($user['telegramid'], $user['username']) ."\n";
		}
		$str = $this->telegram->emoji($str);
		if($retstr){ return $str; }

		// Anterior - final
		if($offset >= $this->db->totalPages){
			$this->telegram->send
				->inline_keyboard()
					->row_button('<<', 'userlist ' .($this->db->totalPages - 1))
				->show();
		// Anterior y siguiente - hay mas
		}elseif($offset > 0 and $offset < $this->db->totalPages){
			$this->telegram->send
				->inline_keyboard()
					->row()
						->button('<<', 'userlist ' .($offset - 1))
						->button('>>', 'userlist ' .($offset + 1))
					->end_row()
				->show();
		// Siguiente - principio y hay mas
		}elseif($offset == 0 and $this->db->totalPages > $offset){
			$this->telegram->send
				->inline_keyboard()
					->row_button('>>', 'userlist 1')
				->show();
		}

		$this->telegram->send->text($str, 'HTML');
		if($this->telegram->callback){
			$this->telegram->send->edit('text');
		}else{
			$this->telegram->send->send();
		}

		$this->end();
	}

	public function check_admin($user = NULL){
		if(empty($user)){ $user = $this->user->id; }
		//  or $user == $this->user->id)
	}

	public function check_user($search, $chat = NULL){

	}

	public function votekick(){

	}

	public function voteban(){

	}

	public function rules_agreement(){
		// Para pertener a este grupo, debes leer y aceptar las normas.
		// Se establece un tiempo minimo de lectura de 1 minuto
		// Variable en funcion de las palabras / letras que haya en las normas.
		// Tabla user_chat_agreements (uid, cid, type, OK 1/0/NULL)
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
		$this->telegram->send
			->notification(FALSE)
			->text($this->strings->parse('group_user_here_' .$here, $linkname))
		->send();

	}

	public function welcome(){
		if(!$this->chat->is_group()){ return NULL; }
		if($this->telegram->text_has($this->strings->get('command_rules_write'))){
			// Si es admin, cambiar las normas
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
			foreach(['text', 'photo', 'sticker', 'document', 'location', 'video'] as $elm){
				if($this->telegram->$elm()){
					$data = $this->telegram->$elm();
					if($elm == "text"){ $data = $this->telegram->text_encoded(); }
					elseif($elm == "location"){
						$data = implode(",", [$this->telegram->location()->latitude, $this->telegram->location()->longitude]);
					}
					$content = [$elm => $data];
					break;
				}
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
				$text = strtolower(trim($this->telegram->text(TRUE)));
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
}
