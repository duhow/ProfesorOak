<?php

class GameNever extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){
		if(
			$this->telegram->text_has("Yo nunca", TRUE) or
			$this->telegram->text_command("yonunca")
		){ return $this->yonunca(FALSE); }

		elseif($this->telegram->callback == "yo nunca si"){
			return $this->yonunca(TRUE);
		}
	}

	public function frase($id = NULL){
		if($id !== NULL && is_numeric($id)){ $this->db->where('id', $id); }

		$query = $this->db
			->orderBy('rand()')
		->get('game_never', 1);

		if($this->db->count == 1){ return $query[0]['question']; }
		return NULL;
	}

	public function yonunca($edit = FALSE){
		$this->telegram->send
			->inline_keyboard()
				->row_button("Yo si", "yo nunca si")
			->show();

		if($edit){
			$text = $this->telegram->text_message();
			if(strpos($text, $this->telegram->user->first_name) !== FALSE){
				$this->telegram->answer_if_callback("Ya lo sabemos, tranquilo. " .$this->telegram->emoji("<3"), TRUE);
				$this->end();
			}

			$str = $this->telegram->text_message() ."\n" .$this->telegram->user->first_name ." lo ha hecho.";
			$this->telegram->send
				->message(TRUE)
				->chat(TRUE)
				->text($str)
			->edit('text');

			$this->telegram->answer_if_callback("");
			$this->end();
		}

		$this->telegram->send
			->text("Yo nunca " .$this->frase() .".")
		->send();
	}
}
