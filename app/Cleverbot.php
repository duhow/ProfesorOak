<?php

class Cleverbot extends TelegramApp\Module {
	protected $runCommands = FALSE;
	private $chatter = NULL;

	public function __construct(){
		$file = 'libs/chatter-bot-api/php/chatterbotapi.php';
		if(file_exists($file)){ require_once $file; }
	}

	public function think($text, $ret = FALSE){
		if($this->chatter === NULL){
			$factory = new ChatterBotFactory();
			$bot1 = $factory->create(1); // Cleverbot
			$this->chatter = $bot1->createSession();
			if($this->chatter === NULL){ return FALSE; }
		}
		$this->telegram->send->chat_action("typing")->send();
		$response = $this->chatter->think($text);
		$q = $this->telegram->send
			->notification(FALSE)
			->text($response)
		->send();

		return ($ret ? $response : $q);
	}

	protected function hooks(){
		if(
			$this->telegram->words() > 2 &&
			(
				$this->telegram->text_regex("Oye (Oak|profe),? {S:question}") or
				$this->telegram->text_command("cleverbot")
			)
		){
			$lim = ($this->telegram->text_command("cleverbot") ? 1 : 2);
			// $txt = $this->telegram->input->question;
			$txt = $this->telegram->words($lim, 20);

			$this->think($txt);
			$this->end();
		}
	}
}
