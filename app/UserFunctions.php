<?php

class UserFunctions extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){
		// guardar nombre de user
		if(
			!$this->telegram->text_command() and
			$this->telegram->text_has(["Me llamo", "Mi nombre es", "Mi usuario es"], TRUE) and
			in_array($this->telegram->words(), [3,4])
		){
			// if(){ $this->end(); }
			// $pokeuser = $pokemon->user($this->telegram->user->id);
			// if(!empty($pokeuser->username)){ $this->end(); }
			$word = $this->telegram->last_word(TRUE);
			$this->register_username($this->telegram->user->id, $word, FALSE);
			$this->end();
		}
	}

	public function register_username($user, $name, $force = FALSE){
		$u = new User($user, $this->db);
		if($name[0] == "@"){ $name = substr($name, 1); }

		if(
			(!$u->load()) or
			(!$force && !empty($u->username)) or
			(strlen($name) < 4 or strlen($name) > 18)
		){ return FALSE; }

		// si el nombre ya existe
		if($pokemon->user_exists($name)){
			$this->telegram->send
				->reply_to(TRUE)
				->notification(FALSE)
				->text($this->strings->parse("register_error_duplicated_name", $name), "HTML")
			->send();
			return FALSE;
		}
		// si no existe el nombre
		else{
			$this->analytics->event('Telegram', 'Register username');
			$u->username = $name;
			$this->telegram->send
				->inline_keyboard()
					->row_button("Validar perfil", "quierovalidarme", TRUE)
				->show()
				->reply_to(TRUE)
				->notification(FALSE)
				->text($this->strings->parse("register_successful", $name), TRUE)
			->send();
		}
		return TRUE;
	}

	// -----------------

	private function setname($name, $user = NULL){
		if(empty($user)){ $user = $this->user; }
		if($user->step == "SETNAME"){ $user->step = NULL; }
		try {
			$user->username = $name;
		} catch (Exception $e) {
			$this->telegram->send
				->text("Ya hay alguien que se llama @$name. Habla con @duhow para arreglarlo.")
			->send();
			$this->end();
		}
		$str = "De acuerdo, @$name!\n"
				."¡Recuerda <b>validarte</b> para poder entrar en los grupos de colores!";
		$this->telegram->send
			->text($str, 'HTML')
		->send();
		return TRUE;
	}
}
