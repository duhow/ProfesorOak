<?php

class GamePole extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public function run(){
		if(!$this->telegram->is_chat_group()){ return; }
		parent::run();
	}

	public function pole(){
		$this->polear(1);
		$this->end();
	}

	public function subpole(){
		$this->polear(2);
		$this->end();
	}

	public function bronce(){
		$this->polear(3);
		$this->end();
	}

	private function polear($position){
		// $this->analytics->event("Telegram", "Pole");
		if(
			$this->chat->settings('pole') === FALSE or
			$this->user->settings('no_pole') == TRUE or
			time() % 3600 <= 1
		){ $this->end(); }

		// $timer = "d"; // FIXME TEMP
		$action = ["",
			"la <b>pole</b>",
			"la <b>subpole</b>",
			"el <b>bronce</b>",
		];

		$select = [
			"uid" => $this->telegram->user->id,
			"cid" => $this->telegram->chat->id,
			"type" => $position,
			"date" => "'" .date("Y-m-d") ."'",
			"first" => 0 // TEMP HACK - No la primera.
		];

		/* $sq = $this->db->subQuery();
		$sq
			->join('user_inchat c', 'u.telegramid = c.uid')
			->where('u.telegramid', $this->telegram->user->id)
			->where('c.cid', $this->telegram->chat->id)
			->where('c.messages >=', 10)
		->get('user u', NULL, array_values($select)); */

		$query = $this->db
			->setQueryOption('IGNORE')
			->insert("pole", $select);

		// "Lo siento " .$telegram->user->first_name .", pero hoy la *pole* es mía! :D"
		if($query){
			$this->telegram->send
				->text($this->telegram->emoji(":medal-" .$position .": ") .$this->telegram->user->first_name ." ha hecho " .$action[$position] ."!", "HTML")
			->send();
		}

		return $query;
	}

	public function polerank(){
		$poleuser = $this->chat->settings('pole_user');
	    $pole = $this->chat->settings('pole');

	    if($pole == FALSE){ return; }
	    if($pole == NULL or ($pole === TRUE or $pole === 1)){
	        $this->telegram->send
	            ->text("Nadie ha hecho la *pole*.", TRUE)
	        ->send();
	        $this->end();
	    }

	    // $pole = unserialize($pole);
	    // $poleuser = unserialize($poleuser);

	    $str = $this->telegram->emoji(":warning:") ." *Pole ";
	    $str .= ($this->chat->settings('pole_hardcore') ? "de las " .date("H", $pole[0]) ."h" : "del " .date("d", $pole[0])) ."*:\n\n";

		$users = $this->points(array_values($poleuser), FALSE);
	    foreach($users as $n => $u){
	        $ut = $this->telegram->emoji(":question-red:");
	        $points = NULL;
	        if(!empty($u)){
	            $ut = (!empty($u->username) ? $u->username : $u->telegramuser);
	            $points = $u->pole;
	        }

	        $str .= $this->telegram->emoji(":" .($n + 1) .": ") .$ut .($points ? " (*$points*)" : "") ."\n";
	    }

	    $this->telegram->send
	        ->text($str, TRUE)
	    ->send();
		$this->end();
	}

	public function points($user, $onlypoints = TRUE){
		if($user instanceof User){ $user = $user->id; }
		$users = (is_array($user) ? $user : [$user]);

		$query = $this->db
			->where('telegramid', $users, 'IN')
			->orderBy('pole', 'desc')
		->get('user', NULL, ['telegramid', 'telegramuser', 'username', 'pole']);
		if($this->db->count > 0){
			if($onlypoints){
				if(!is_array($user)){ return $query[0]['pole']; }
				return array_column($query, 'pole', 'telegramid');
			}
			$final = array();
			// foreach($query as $u){ $final[$u['telegramid']] = (object) $u; }
			foreach($query as $u){
				$final[] = (object) [
					'id' => $u['telegramid'],
					'pole' => $u['pole'],
					'username' => $u['username'],
					'telegramuser' => $u['telegramuser']
				];
			}
			return $final;
		}
		// Return empty if not found.
		return (!is_array($user) ? 0 : array());
	}

	protected function hooks(){
		if($this->telegram->text_has(["pole", "!pole"], TRUE)){
			return $this->pole();
		}elseif($this->telegram->text_has(["subpole", "!subpole"], TRUE)){
			return $this->subpole();
		}elseif($this->telegram->text_has(["bronce", "!bronce"], TRUE)){
			return $this->bronce();
		}elseif($this->telegram->text_has(["!polerank", "!pole rank"], TRUE)){
			return $this->polerank();
		}
	}
}
