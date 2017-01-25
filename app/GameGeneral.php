<?php

class GameGeneral extends TelegramApp\Module {
	function run(){
		// $can = $pokemon->settings($telegram->chat->id, 'play_games');
	    // if($can != NULL and $can == FALSE){ return; }

		parent::run();
	}

	public function hooks(){
		if($this->telegram->text_has(["tira", "lanza", "tirar", "roll"], ["el dado", "los dados", "the dice"], TRUE) or $telegram->text_has("/dado", TRUE)){
			$num = $this->telegram->last_word();
			if(!is_numeric($num)){ $num = 6}
			$this->dado($num);
			$this->end();
		}elseif(
			( $this->telegram->text_has("piedra") and
		    $this->telegram->text_has("papel") and
		    $this->telegram->text_has(["tijera", "tijeras"]) ) or
		    $this->telegram->text_has(["/rps", "/rpsls"], TRUE)
		){
			$this->rps();
			$this->end();
		}elseif($this->telegram->text_has(["cara o cruz", "/coin", "/flip"])){
			$this->coin();
			$this->end();
		}
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

	function rpsls(){ return $this->rps(TRUE); }
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

	function flip(){ return $this->coin(); }
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
