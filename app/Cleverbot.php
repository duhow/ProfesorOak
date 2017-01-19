<?php

class Cleverbot extends TelegramApp\Module {
	protected $runCommands = FALSE;
	private $chatter = NULL;

	public function __construct(){
		$file = 'libs/chatter-bot-api/php/chatterbotapi.php';
		if(file_exists($file)){
			require_once $file;
			$factory = new ChatterBotFactory();
			$bot1 = $factory->create(1); // Cleverbot
			$this->chatter = $bot1->createSession();
		}
	}

	public function think($text){
		if($this->chatter === NULL){ return FALSE; }
		return $this->telegram->send
			->notification(FALSE)
			->text($text)
		->send();
	}

	protected function hooks(){
		if($this->telegram->text_has("Oye", ["Oak", "profe"], TRUE) && $this->telegram->words() > 2){
			return $this->think($this->telegram->words(2, 20));
		}elseif($this->telegram->text_command("cleverbot") && $this->telegram->words() > 1){
			return $this->think($this->telegram->words(1, 20));
		}
	}
}
