<?php

class GameGeneral extends TelegramApp\Module {
	function run(){
		// $can = $pokemon->settings($telegram->chat->id, 'play_games');
	    // if($can != NULL and $can == FALSE){ return; }

		parent::run();
	}

	public function hooks(){

	}

	function dado($num = 6){
		// $this->analytics->event('Telegram', 'Games', 'Dice');

	    if(!is_numeric($num) or ($num < 0 or $num > 1000)){ $num = 6; } // default MAX
		$dice = mt_rand(1,$num);
		$this->telegram->send
			->text("*" .$dice ."*", TRUE)
		->send();
		return $dice;
	}

	function rps($ls = FALSE){
		// $this->analytics->event('Telegram', 'Games', 'RPS');

	    $rps = ["Piedra", "Papel", "Tijera"];
	    if($this->telegram->text_contains(["lagarto", "/rpsls"]) or $ls === TRUE){ $rps[] = "Lagarto"; }
	    if($this->telegram->text_contains(["spock", "/rpsls"]) or $ls === TRUE){ $rps[] = "Spock"; }
		$this->telegram->send
			->text("*" .$rps[mt_rand(0, count($rps) - 1)] ."!*")
		->send();
		// return choice?
	}

	function coin(){
		// $this->analytics->event('Telegram', 'Games', 'Coin');
	    $n = mt_rand(0, 99);
	    $flip = ["Cara!", "Cruz!"];

		$coin = ($n % 2);
		$this->telegram->send
			->text("*" .$flip[$coin] ."*", TRUE)
		->send();
		return $coin;
	}
}
