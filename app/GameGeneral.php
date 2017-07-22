<?php

class GameGeneral extends TelegramApp\Module {
	public function run(){
		if(isset($this->chat->settings['play_games']) && $this->chat->settings['play_games'] == FALSE){ return; }
		parent::run();
	}

	protected function hooks(){
		if(
			$this->telegram->text_has(["tira", "lanza", "tirar", "roll"], ["el dado", "los dados", "the dice"]) or
			$this->telegram->text_has("/dado", TRUE)
		){
			$num = $this->telegram->last_word();
			if(!is_numeric($num)){ $num = 6; }
			$this->dado($num);
			$this->end();
		}elseif(
			( $this->telegram->text_has("piedra") and
		    $this->telegram->text_has("papel") and
		    $this->telegram->text_contains("tijera") ) or
		    $this->telegram->text_has(["/rps", "/rpsls"], TRUE)
		){
			$this->rps();
			$this->end();
		}elseif($this->telegram->text_has(["cara o cruz", "/coin", "/flip"])){
			$this->coin();
			$this->end();
		}elseif($this->telegram->text_has(["apuesto", "apostar", "ruleta"], "al") && $this->telegram->words() == 3){
			$this->roulette($this->telegram->last_word());
			$this->end();
		}
	}

	public function dado($num = 6){
		// $this->tracking->event('Telegram', 'Games', 'Dice');

	    if(!is_numeric($num) or ($num < 0 or $num > 1000)){ $num = 6; } // default MAX
		$dice = mt_rand(1,$num);
		$this->telegram->send
			->text("*" .$dice ."*", TRUE)
		->send();
		return $dice;
	}

	public function rpsls(){ return $this->rps(TRUE); }
	public function rps($ls = FALSE){
		// $this->tracking->event('Telegram', 'Games', 'RPS');

	    $rps = ["Piedra", "Papel", "Tijera"];
	    if($this->telegram->text_contains(["lagarto", "/rpsls"]) or $ls === TRUE){ $rps[] = "Lagarto"; }
	    if($this->telegram->text_contains(["spock", "/rpsls"]) or $ls === TRUE){ $rps[] = "Spock"; }
		$this->telegram->send
			->text("*" .$rps[mt_rand(0, count($rps) - 1)] ."!*")
		->send();
		// return choice?
	}

	public function flip(){ return $this->coin(); }
	public function coin(){
		// $this->tracking->event('Telegram', 'Games', 'Coin');

	    $n = mt_rand(0, 99);
	    $flip = ["Cara!", "Cruz!"];

		$coin = ($n % 2);
		$this->telegram->send
			->text("*" .$flip[$coin] ."*", TRUE)
		->send();
		return $coin;
	}

	public function roulette($action){
		$numbers = [2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35]; // black
		if(is_string($action)){ $action = trim(strtolower($action)); }

		$num = mt_rand(0, 36);
		$win = FALSE;

		if($action == 'rojo' && $num != 0 && !in_array($num, $numbers)){
			$win = TRUE;
		}elseif($action == 'negro' && in_array($num, $numbers)){
			$win = TRUE;
		}elseif($action == 'par' && ($num % 2) == 0){
			$win = TRUE;
		}elseif($action == 'impar' && ($num % 2) == 1){
			$win = TRUE;
		}elseif($action == 'primero' && $num <= 12){
			$win = TRUE;
		}elseif($action == 'segundo' && $num > 12 && $num <= 24){
			$win = TRUE;
		}elseif($action == 'tercero' && $num > 24 && $num <= 36){
			$win = TRUE;
		}elseif($action == 'principio' && $num <= 18){
			$win = TRUE;
		}elseif($action == 'final' && $num > 18){
			$win = TRUE;
		}elseif(is_numeric($action) && $action == $num){
			$win = TRUE;
		}

		$str = $num ." ";
		if($num == 0){ $str .= ":ok:"; } // zero
		elseif(in_array($num, $numbers)){ $str .= "\u26ab\ufe0f"; } // black
		else{$str .= "\ud83d\udd34"; } // red

		$str .= " - " .($win ? ":ok:" : ":times:");
		$this->telegram->send
			->notification(FALSE)
			->text($this->telegram->emoji($str))
		->send();
		return $win;
	}
}
