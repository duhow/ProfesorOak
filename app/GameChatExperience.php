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
			$amount = $this->telegram->words();
			if($amount == mt_rand(2, 9)){
				$timeout = $this->user->settings('expchat_timeout');
				if(empty($timeout) or time() >= $timeout){
					$points = $this->get_experience($this->user);

					$curlev = $this->get_level($points);
					$nextlev = $this->get_level($points + $amount);

					if($nextlev > $curlev && $nextlev > 0){
						$this->telegram->send
							->notification(FALSE)
							->text(
								$this->telegram->emoji(":arrow_up: ")
								.$this->strings->parse('game_exp_levelup', [$this->telegram->user->first_name, $nextlev])
							, 'HTML')
						->send();
					}
					// La recompensa será el número de palabras que haya tocado, para hacer el factor diferencial.
					// Puede favorecer a los spamers, así que cuidado.
					$this->add_experience($amount, $this->user);
				}
			}
		}

		if($this->telegram->text_has("mi experiencia")){
			$this->view_experience($this->user);
		}
	}

	public function view_experience($user){
		if($user instanceof \Telegram\User){
			$user = $user->id;
		}
		if(is_numeric($user)){
			$user = new User($user);
			$user->load();
		}
		$points = $this->get_experience($user);
		if(empty($points)){ $points = 0; }

		$level = $this->get_level($points);
		$this->telegram->send
			->text("<b>L$level</b> / $points EXP", 'HTML')
		->send();
	}

	public function add_experience($amount, $user = NULL){
		if(empty($user)){ $user = $this->user; }
		$user->settings('expchat_points', $user->settings('expchat_points') + $amount);
		$user->settings('expchat_timeout', (time() + 60));
		return $user->settings('expchat_points');
	}

	public function get_experience($user = NULL){
		if(empty($user)){ $user = $this->user; }
		return $user->settings('expchat_points');
	}

	public function get_level($points){
		for($i = 0; $i < count($this->levels); $i++){
			if($points > $this->levels[$i]){ continue; }
			return (max(0, $i - 1));
		}
	}
}
