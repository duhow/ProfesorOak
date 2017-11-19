<?php

class Ingress extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public $teams = [
		"RES" => ["resistencia", "resistance", "res"],
		"ENL" => ["iluminados", "enlightened", "enl"],
	];

	protected function hooks(){
		$teams = array_values($this->teams["RES"]) + array_values($this->teams["ENL"]);
		if($this->telegram->text_has(["soy", "soy de la"], array_values($teams))){
			if(in_array(['resistance', 'enlightened'], $this->user->flags)){ $this->end(); }

			$team = NULL;
			if($this->telegram->text_has($this->teams["RES"])){ $team = 'resistance'; }
			elseif($this->telegram->text_has($this->teams["ENL"])){ $team = 'enlightened'; }

			$this->register($team);
			$this->end();
		}
	}

	private function register($team, $user = NULL){
		if(empty($user)){ $user = $this->user; }

		$text = $this->strings->get('ingress_welcome_agent') ." ";
		if($team == 'resistance'){
			$this->user->flags[] = 'resistance';
			$text .= $this->telegram->emoji(":key:");
		}elseif($team == 'enlightened'){
			$this->user->flags[] = 'enlightened';
			$text .= $this->telegram->emoji(":frog:");
		}

		return $this->telegram->send
			->chat($this->user->id)
			->text($text)
		->send();
	}

	public function cingress(){
		if(!$this->telegram->is_chat_group()){ return FALSE; }
		$ingress = ['resistance' => 0, 'enlightened' => 0];
		/* $users = $pokemon->group_get_members($telegram->chat->id);
		foreach($users as $u){
			foreach(array_keys($ingress) as $team){
				if($pokemon->user_flags($u, $team)){ $ingress[$team]++; }
			}
		} */

		$str = ":key: " .$ingress['resistance'] ." "
				.":frog: " .$ingress['enlightened'];

		$this->telegram->send
			->notification(FALSE)
			->text($this->telegram->emoji($str))
		->send();
		return $ingress;
	}

	public function recingress(){
		return $this->telegram->send
			->inline_keyboard()
				->row()
					->button($this->telegram->emoji(":heart-blue:"), "soy res", "TEXT")
					->button($this->telegram->emoji(":heart-green:"), "soy enl", "TEXT")
				->end_row()
			->show()
			->text($this->strings->get('ingress_register'))
		->send();
	}
}
