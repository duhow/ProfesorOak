<?php

class Group extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		if(!$this->chat->is_group()){ return; }
		if($this->user->step != NULL){ $this->step(); }
		parent::run();
	}

	protected function step(){
		$step = $this->user->step;
		if($step == "RULES" or $step == "WELCOME"){
			if(!$this->chat->is_admin($this->user->id)){
				$this->user->step = NULL;
				$this->end();
			}

			$text = $this->telegram->text_encoded();
			if(strlen($text) < 4){ $this->end(); }
			if(strlen($text) > 4000){
				$this->telegram->send
					->text("Buah, demasiado texto! Relájate un poco anda ;)")
				->send();
				$this->end();
			}
			// $this->analytics->event('Telegram', 'Set rules');
			// $this->analytics->event('Telegram', 'Set welcome');

			$this->chat->settings[strtolower($step)] = $text;
			$this->user->step = NULL;

			$this->telegram->send
				->text("Hecho!")
			->send();
			$this->end();
		}
	}

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
			( $this->telegram->text_regex($this->strings->get('command_admin_count')) and $this->telegram->words() <= 8 ) or
			( $this->telegram->text_command(["adminlist", "admins"]) )
		){
			$this->adminlist();
			$this->end();
		}

		elseif(
			$this->telegram->text_command("uv") or
		    (
				$this->telegram->text_regex($this->strings->get('command_user_count')) and
				$this->telegram->text_regex($this->strings->get('command_user_count_unverified'))
			)
		){
			$this->userlist_verified();
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
			$this->count(TRUE);
			$this->end();
		}

		elseif(
			$this->telegram->text_has(["grupo offtopic", "/offtopic"])
		){
			$this->offtopic();
			$this->end();
		}

		elseif(
			(
				$this->telegram->text_has(["reglas", "normas"], "del grupo") or
		        $this->telegram->text_has(['dime', 'ver'], ["las reglas", "las normas", "reglas", "normas"], TRUE) or
		        $this->telegram->text_has(["/rules", "/normas"], TRUE)
		    ) and
	    	!$this->telegram->text_has(["poner", "actualizar", "redactar", "escribir", "cambiar"])
		){
			$this->rules();
			$this->end();
		}

		elseif(
			$this->telegram->words() <= 6 &&
		    (
		        ( $this->telegram->text_has("está") and $this->telegram->text_has("aquí") ) and
		        ( !$this->telegram->text_has(["alguno", "alguien", "que"], ["es", "ha", "como", "está"]) ) and // Alguien está aquí? - Alguno es....
		        ( !$this->telegram->text_contains(["desde"]) ) // , "este"
		    )
		){

		}
	}

	public function count($chat = NULL, $say = FALSE){
		if(is_bool($chat)){ $say = $chat; $chat = NULL; }
		if(empty($chat)){ $chat = $this->chat->id; }

		$total = $this->telegram->send->get_members_count($chat);
		$query = $this->db
			->where('cid', $chat)
		->get('user_inchat');
		$members = $this->db->count;

		$sels = [
			"SUM(if(team = 'Y', 1, 0)) AS 'Y'",
			"SUM(if(team = 'R', 1, 0)) AS 'R'",
			"SUM(if(team = 'B', 1, 0)) AS 'B'",
			// "COUNT(team) AS 'Total'"
		];

		$users = $this->db
			->join("user_inchat", "user.telegramid = user_inchat.uid")
			->where('user_inchat.cid', $chat)
			->where('user.telegramid', ['NOT IN' => [$this->telegram->bot->id]])
		->get('user', implode(", ", $sels)); // TODO check

		if(!$say){
			return (object) [
				'team' => $users,
				'total' => $total,
				'count' => $members
			];
		}

		// if($pokemon->command_limit("count", $telegram->chat->id, $telegram->message, 10)){ return FALSE; }

	    $str = "Veo a $members ($total) y conozco " .array_sum($users) ." (" .round((array_sum($users) / $total) * 100)  ."%) :\n"
	            .":heart-yellow: " .$users["Y"] ." "
	            .":heart-red: " .$users["R"] ." "
	            .":heart-blue: " .$users["B"] ."\n"
	            ."Faltan: " .($total - array_sum($users));
	    $str = $this->telegram->emoji($str);

	    return $this->telegram->send
	        ->notification(FALSE)
	        ->text($str)
	    ->send();
	}

	public function autokick(){
		if($this->telegram->text_has("bomba de humo") and $this->user->id == CREATOR){
			$this->telegram->send->file('sticker', 'CAADBAADFQgAAjbFNAABxSRoqJmT9U8C');
		}
		global $Admin;
		return $Admin->kick($this->user->id);
	}

	public function adminlist($chat = NULL){
		// Get cache admin DB
		// If empty or expired, get from telegram
		if(empty($chat)){ $chat = $this->chat->id; }
		$admins = $this->telegram->send->get_admins($chat);
		$str = "No hay admins! :o";
		if(!empty($admins)){

		}
	}

	public function abandon(){
		$abandon = $this->chat->settings('abandon');
		if($abandon){
		    if(json_decode($abandon) != NULL){ $abandon = json_decode($abandon); }
		    $str = ($abandon == TRUE ? "Este chat ha sido abandonado." : $abandon);

		    $this->telegram->send
		        ->text($str)
		    ->send();

			$this->end();
		}
	}

	public function userlist_verified($chat = NULL){

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

	public function rules(){

	}

	public function welcome(){

	}

	public function offtopic(){

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
		if(!$commands or !empty($this->user->step)){ return FALSE; }
	    // $commands = unserialize($commands);
	    if(is_array($commands)){
	        foreach($commands as $word => $action){
	            if($this->telegram->text_has($word, TRUE)){
	                $content = current($action);
	                $action = key($action);
	                if($action == "text"){
	                    $this->telegram->send->text(json_decode($content))->send();
	                }else{
	                    $this->telegram->send->file($action, $content);
	                }
	                $this->end();
	            }
	        }
	    }
	}
}
