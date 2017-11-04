<?php

class GroupBlackwords extends TelegramApp\Module {
	protected $runCommands = FALSE;
	public $words = array();

	public function run(){
		if(
			!$this->telegram->is_chat_group() or
			!$this->chat->settings('blackwords')
		){ return; }

		$this->words = explode(",", $this->chat->settings('blackwords'));
		parent::run();
	}

	protected function hooks(){
		if($this->chat->is_admin($this->user)){
			if($this->telegram->words() > 1){
				$txt = $this->telegram->words(1, 10);
				$txt = strtolower(trim($txt));

				if($this->telegram->text_command("bw")){
					$this->add($txt);
				}elseif($this->telegram->text_command("bwr")){
					$this->remove($txt);
				}
			}
			return;
		}

		if(!$this->telegram->text_contains($this->words)){ return; }

		$adminchat = $this->chat->settings('admin_chat');
		if(!empty($adminchat)){
			$this->telegram->send
				->message(TRUE)
				->chat(TRUE)
				->forward_to($adminchat)
			->send();

			/* $this->telegram->send
				->chat($adminchat)
				->text("Ha dicho algo malo :(")
			->send(); */
		}else{
			$this->telegram->send
				->text("Eh, te calmas.")
			->send();
		}

		$this->end();
	}

	public function add($word){
		// TODO
	}

	public function remove($word){
		$k = array_search($word, $this->words);
		if($k !== FALSE){
			unset($this->words[$k]);
			$this->chat->settings('blackwords', implode(",", $this->words));
		}
	}
}
