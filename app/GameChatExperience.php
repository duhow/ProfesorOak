<?php

class GameChatExperience extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public $levels = [
		0,
		200,
		500,
		1000,
		2000,
		4000,
		7500,
		12500,
		25000,
		50000,
	];

	protected function hooks(){
		if($this->telegram->is_chat_group()){
			e
		}

		if($this->telegram->text_has("mi experiencia")){
			$this->view_experience($this->telegram->user);
		}
	}

	public function view_experience($user){
		$points = $this->get_experience($user);
		$level = $this->get_level($points);
		$this->telegram->send
			->text("*L" .$level ."* / $points EXP", TRUE)
		->send();
	}

	public function add_experience($amount, $user = NULL){
		if(empty($user)){ $user = $this->telegram->user; }
	}

	public function get_experience($user = NULL){
		if(empty($user)){ $user = $this->telegram->user; }

	}

	public function get_level($points){
		for($i = 0; $i < count($this->levels); $i++){
			if($points > $this->levels[$i]){ continue; }
			return (max(0, $i - 1));
		}
	}
}
