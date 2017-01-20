<?php

class GameNever extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function hooks(){
		if(
			$this->telegram->text_has("Yo nunca", TRUE) or
			$this->telegram->text_command("yonunca")
		){ return $this->yonunca(FALSE); }

		elseif($this->telegram->callback == "yo nunca si"){
			return $this->yonunca(TRUE);
		}
	}

	function frase($id = NULL){
		if($id !== NULL && is_numeric($id)){ $this->db->where('id', $id); }

		$query = $this->db
			->orderBy('rand()')
		->get('game_never', 1);

		if($this->db->count == 1){ return $query[0]['question']; }
		return NULL;
	}

	function yonunca($edit = FALSE){
		$text = $this->frase();

		if($edit){
			$text = $this->telegram->text_message();
			if(strpos($text, $this->telegram->user->first_name) !== FALSE){
				$this->telegram->answer_if_callback("Ya lo sabemos, tranquilo. " .$telegram->emoji("<3"), TRUE);
			}
		}
	}
}
