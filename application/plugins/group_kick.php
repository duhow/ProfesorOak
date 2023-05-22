<?php

function get_user_kick_filter($chat, $action, $filter = NULL){
	$CI =& get_instance();
	$query = NULL;

	if($action == "old"){
		if(empty($filter)){ $filter = 15; } // dias sin hablar
		$query = $CI->db
			->select('uid')
			->where('cid', $chat)
			->group_start()
				->where('last_date <=', date("Y-m-d H:i:s", strtotime("-" .$filter ." days")))
				->or_where('last_date IS NULL')
			->group_end()
		->get('user_inchat');
	}elseif($action == "message"){
		if(empty($filter)){ $filter = 10; } // mensajes
		$query = $CI->db
			->select('uid')
			->where('cid', $chat)
			->where('messages <=', $filter)
		->get('user_inchat');
	}elseif($action == "verified"){
		$query = $CI->db
			->select('uid')
			->from('user_inchat')
			->join('user', 'user.telegramid = user_inchat.uid', 'LEFT')
			->where('cid', $chat)
			->group_start()
				->where('verified', FALSE)
				->or_where('verified IS NULL', NULL, FALSE)
			->group_end()
			->get();
	}elseif($action == "noregister"){
		$query = $CI->db
			->select('uid')
			->from('user_inchat')
			->where('cid', $chat)
			->where_not_in('uid', '(SELECT telegramid FROM user WHERE NOT anonymous)', FALSE)
			->get();
	}elseif($action == "team"){
		if(empty($filter)){ return FALSE; }
		$query = $CI->db
			->select('uid')
			->from('user_inchat')
			->join('user', 'user.telegramid = user_inchat.uid', 'LEFT')
			->where('cid', $chat)
			->where('team', $filter)
		->get();
	}elseif($action == "blacklist"){
		if(empty($filter)){
			$query = $CI->db
				->select('value')
				->from('settings')
				->where('uid', $chat)
				->where('type', 'blacklist')
			->get();
			if($query->num_rows() != 1){ return FALSE; }
			$filter = $query->row()->value;
		}
		if(is_string($filter)){ $filter = explode(",", $filter); }

		$query = $CI->db
			->select('uid')
			->from('user_inchat')
			->join('user_flags', 'user_flags.user = user_inchat.uid', 'LEFT')
			->where('cid', $chat)
			->where_in('value', $filter)
		->get();
	}

	if(empty($query) or $query->num_rows() == 0){ return FALSE; }
	$users = array_column($query->result_array(), 'uid');
	// Cleanup
	foreach([201760961, 3458358] as $user){
		$ks = array_search($user, $users);
		if($ks){ unset($users[$ks]); }
	}
	return array_values($users);
}

if(!$this->telegram->is_chat_group()){ return; }
if(!$pokemon->is_group_admin($this->telegram->chat->id) and !in_array($this->telegram->user->id, telegram_admins(TRUE))){ return; }

$timeout = $pokemon->settings($this->telegram->chat->id, 'investigation');
if($timeout and $timeout >= time()){ return; }

if($this->telegram->text_command([
	"kickold",
	"kickmsg",
	"kickuv",
	"kickunr",
	"kickteam",
	"kickblack"
])){
	$chat = $pokemon->is_group_admin($this->telegram->chat->id);
	if(empty($chat)){ $chat = $this->telegram->chat->id; }

	if(!in_array($this->config->item('telegram_bot_id'), telegram_admins())){
		$this->telegram->send
			->text($this->telegram->emoji(":times: ") ."Dadme admin y esperad una hora para que funcione.")
		->send();
		return -1;
	}

	$filter = 0;
	$action = NULL;

	if($this->telegram->text_command("kickold")){
		$action = "old";
		if($this->telegram->words() >= 2 and is_numeric($this->telegram->words(1))){
			$filter = (int) $this->telegram->words(1);
		}else{
			$filter = 15; // dias sin hablar
		}
	}elseif($this->telegram->text_command("kickmsg")){
		$action = "message";
		if($this->telegram->words() >= 2 and is_numeric($this->telegram->words(1))){
			$filter = (int) $this->telegram->words(1);
		}else{
			$filter = 10; // mensajes
		}
	}elseif($this->telegram->text_command("kickteam")){
		$action = "team";
		if($this->telegram->words() >= 2 and strlen($this->telegram->words(1)) == 1){
			$filter = $this->telegram->words(1);
		}else{
			// $filter = $this->pokemon->settings($this->telegram->chat->id, 'team_exclusive');
			// TODO seleccionar inversos, no echar a los del PROPIO color.
		}

		$filter = strtoupper($filter);
		if(!in_array($filter, ["R", "B", "Y"])){
			$str = ":times: El equipo especificado no existe o no está definido por defecto en el grupo.";
			$this->telegram->send
				->text($this->telegram->emoji($str))
			->send();
			return -1;
		}
	}elseif($this->telegram->text_command("kickuv")){
		$action = "verified";
	}elseif($this->telegram->text_command("kickunr")){
		$action = "noregister";
	}elseif($this->telegram->text_command("kickblack")){
		$action = "blacklist";
		if($this->telegram->words() == 1){
			$filter = $this->pokemon->settings($chat, 'blacklist');
		}elseif($this->telegram->words() == 2){
			$filter = $this->telegram->last_word();
		}
	}

	if(empty($action)){ return -1; } // HACK

	$users = get_user_kick_filter($chat, $action, $filter);

	$str = ":ok: No hay usuarios que echar.";
	if(!empty($users)){
		$str = ":warning: Se procesarán " .count($users) . " usuarios.\nSolicitando permiso...";
	}

	$this->telegram->send
		->notification(FALSE)
		->text($this->telegram->emoji($str))
	->send();

	if(empty($users)){ return -1; } // Si no hay que echar a nadie, fuera.

	$count = $this->telegram->send->get_members_count($chat);
	$final = $count - count($users);

	$str = ":id: " .$this->telegram->user->id . " - " . $this->telegram->user->first_name ."\n"
			.":abc: " .$this->telegram->chat->title ."\n"
			.":i: $count :triangle-right: $final (" .count($users) .")\n"
			.":forbid: $action $filter";

	$this->telegram->send
		->notification(TRUE)
		->chat("-224436942") // Kicks
		->inline_keyboard()
			->row()
				->button($this->telegram->emoji(":ok:"), "kickf $chat $action $filter", "TEXT")
				->button($this->telegram->emoji(":times:"), "kickf $chat nope 0", "TEXT")
			->end_row()
		->show()
		->text($this->telegram->emoji($str))
	->send();

	return -1;
}

if(
	$this->telegram->callback and
	$this->telegram->text_has("kickf", TRUE) and
	$this->telegram->user->id == $this->config->item('creator')
){
	$chat = $this->telegram->words(1);
	$action = $this->telegram->words(2);
	$filter = $this->telegram->words(3);

	if($action == "nope"){
		$str = ":times: Operación denegada.";
		$this->telegram->send
			->notification(TRUE)
			->chat($chat)
			->text($this->telegram->emoji($str))
		->send();

		$this->telegram->answer_if_callback("");

		$this->telegram->send
			->message(TRUE)
			->chat(TRUE)
			->text($this->telegram->text_message() ."\nDENEGADO.")
		->edit('text');

		return -1;
	}

	$investigation = $this->pokemon->settings($this->telegram->chat->id, 'investigation');
	if($investigation and $investigation >= time()){ return -1; }
	$this->pokemon->settings($this->telegram->chat->id, 'investigation', time() + 150);

	$str = ":ok: Operación autorizada.\n";
	$users = get_user_kick_filter($chat, $action, $filter);
	$str .= ":i: Echando a " .count($users) ." usuarios...";

	$not = $this->telegram->send
		->chat($chat)
		->notification(TRUE)
		->text($this->telegram->emoji($str))
	->send();

	// Quitar botones por si acaso
	$this->telegram->send
		->message(TRUE)
		->chat(TRUE)
		->text($this->telegram->text_message())
	->edit('text');

	$this->telegram->answer_if_callback("");

	// --------

	$c = 0;

	foreach($users as $user){
		/* if(in_array($user, [
			$this->config->item('creator'),
			$this->config->item('telegram_bot_id')]
		)){ continue; } */

		$q = $this->telegram->send->ban_until("+1 minute", $user, $chat);
		// $q = $this->telegram->send->kick($user, $chat);
		if($q !== FALSE){
			$this->pokemon->user_delgroup($user, $chat);
			$c++;
		}
		usleep(300000);
	}

	$str = ":ok: $c usuarios expulsados.";

	$this->telegram->send
		->notification(TRUE)
		->chat($chat)
		->text($this->telegram->emoji($str))
	->send();

	$str = $this->telegram->text_message() ."\n"
			.$this->telegram->emoji(":ok:") ." $c expulsados.";

	$this->telegram->send
		->message(TRUE)
		->chat(TRUE)
		->text($str)
	->edit('text');

	$this->pokemon->settings($this->telegram->chat->id, 'investigation', 'DELETE');

	return -1;
}
 ?>
