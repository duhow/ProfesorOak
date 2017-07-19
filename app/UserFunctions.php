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
			$this->user->register_username($word, FALSE);
			$this->end();
		}
	}
}
