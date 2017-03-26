<?php

class GamePole extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public function run(){
		if(!$this->telegram->is_chat_group()){ return; }
		if($this->chat->settings('pole') == FALSE){ return; }
		if($this->user->settings('no_pole') == TRUE){ return; }
		parent::run();
	}

	public function pole(){
		return $this->polear(1);
	}

	public function subpole(){
		return $this->polear(2);
	}

	public function bronce(){
		return $this->polear(3);
	}

	private function polear($position){
		// $this->analytics->event("Telegram", "Pole");

		// Si está el Modo HARDCORE, la pole es cada hora. Si no, cada día.
		$timer = ($this->chat->settings('pole_hardcore') ? "H" : "d");

		if(!empty($pole)){
	        $pole = unserialize($pole);
	        if(
	            ( $position == 1 && is_numeric($pole[0]) && date($timer) == date($timer, $pole[0]) ) or
	            ( $position == 2 && is_numeric($pole[1]) && date($timer) == date($timer, $pole[1]) ) or
	            ( $position == 3 && is_numeric($pole[2]) && date($timer) == date($timer, $pole[2]) )
	        ){
	            return;  // Mismo dia? nope.
	        }
	    }
		$pole_user = unserialize($this->chat->settings('pole_user'));
        $timeuser = $this->user->settings('lastpole');
        if(empty($timeuser)){ $timeuser = 0; }

		if($position == 1){ // and date($timer) != date($timer, $pole[0])
	        $pole = [time(), NULL, NULL];
	        $pole_user = [$this->user->id, NULL, NULL];
	        $action = "la *pole*";
	    }elseif($position == 2 and date($timer) == date($timer, $pole[0]) and $pole_user[1] == NULL){
	        if(in_array($this->user->id, $pole_user)){ return; } // Si ya ha hecho pole, nope.
	        $pole[1] = time();
	        $pole_user[1] = $this->user->id;
	        $action = "la *subpole*";
	    }elseif($position == 3 and date($timer) == date($timer, $pole[0]) and $pole_user[1] != NULL and $pole_user[2] == NULL){
	        if(in_array($this->user->id, $pole_user)){ return; } // Si ya ha hecho sub/pole, nope.
	        $pole[2] = time();
	        $pole_user[2] = $this->user->id;
	        $action = "el *bronce*";
		}

		if($timer == "d"){
			if(date("d") != $timeuser){
				$this->user->pole += (4 - $position);
				$this->user->settings('lastpole', date("d"));
			}
		}

		$this->chat->settings('pole', serialize($pole));
		$this->chat->settings('pole_user', serialize($pole_user));
	    $this->telegram->send->text($this->telegram->user->first_name ." ha hecho $action!", TRUE)->send();
		$this->end(); // ?
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

	    $pole = unserialize($pole);
	    $poleuser = unserialize($poleuser);

	    $str = $this->telegram->emoji(":warning:") ." *Pole ";
	    $str .= ($this->chat->settings('pole_hardcore') ? "de las " .date("H", $pole[0]) ."h" : "del " .date("d", $pole[0])) ."*:\n\n";

	    foreach($poleuser as $n => $u){
	        $ut = $this->telegram->emoji(":question-red:");
	        $points = NULL;
	        if(!empty($u)){
				$user = new User($u, $this->db);
	            $ut = (!empty($user->username) ? $user->username : $user->telegramuser);
	            $points = $user->pole;
	        }

	        $str .= $this->telegram->emoji(":" .($n + 1) .": ") .$ut .($points ? " (*$points*)" : "") ."\n";
	    }

	    $this->telegram->send
	        ->text($str, TRUE)
	    ->send();
		$this->end();
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
