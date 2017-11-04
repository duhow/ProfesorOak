<?php

class GameGeneral extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public function run(){
		if($this->chat->settings('play_games') === FALSE){ return; }
		parent::run();
	}

	protected function hooks(){
		if(
			$this->telegram->text_has($this->strings->get_multi('command_game_rolldice', 0), $this->strings->get_multi('command_game_rolldice', 1)) or
			$this->telegram->text_command("roll")
		){
			$num = $this->telegram->last_word();
			if(!is_numeric($num)){ $num = 6; }
			$this->dado($num);
			$this->end();
		}elseif(
			( $this->telegram->text_has($this->strings->get_multi('command_game_rps', 0)) and // Piedra
			$this->telegram->text_has($this->strings->get_multi('command_game_rps', 1)) and   // Papel
			$this->telegram->text_contains($this->strings->get_multi('command_game_rps', 2)) ) or // Tijera
			$this->telegram->text_command(["rps", "rpsls"])
		){
			$this->rps();
			$this->end();
		}elseif(
			$this->telegram->text_has($this->strings->get('command_game_coin')) or
			$this->telegram->text_command(["coin", "flip"], FALSE)
		){
			$this->coin();
			$this->end();
		}elseif($this->telegram->text_regex($this->strings->get('command_game_roulette')) && $this->telegram->words() <= 5){
			$this->roulette($this->telegram->input->bet);
			$this->end();
		}
	}

	public function dado($num = 6, $display = TRUE){
		// $this->tracking->event('Telegram', 'Games', 'Dice');

		if(!is_numeric($num) or ($num < 0 or $num > 1000)){ $num = 6; } // default MAX
		$dice = mt_rand(1,$num);
		if($display){
			$this->telegram->send
				->text("*" .$dice ."*", TRUE)
			->send();
		}
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
		$flip = ["heads", "tails"];

		$coin = ($n % 2);
		$this->telegram->send
			->text("<b>" .$this->strings->get('game_coin_' .$flip[$coin]) ."!</b>", 'HTML')
		->send();
		return $coin;
	}

	public function roulette($action){
		$numbers = [2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35]; // black
		if(is_string($action)){ $action = trim(strtolower($action)); }

		$num = mt_rand(0, 36);
		$win = FALSE;

		if(in_array($action, $this->strings->get('game_roulette_red')) && $num != 0 && !in_array($num, $numbers)){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_black')) && in_array($num, $numbers)){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_even')) && ($num % 2) == 0){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_odd')) && ($num % 2) == 1){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_first')) && $num <= 12){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_second')) && $num > 12 && $num <= 24){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_third')) && $num > 24 && $num <= 36){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_beginning')) && $num <= 18){
			$win = TRUE;
		}elseif(in_array($action, $this->strings->get('game_roulette_ending')) && $num > 18){
			$win = TRUE;
		}elseif(is_numeric($action) && $action == $num){
			$win = TRUE;
		}

		$str = $num ." ";
		if($num == 0){ $str .= ":white_check_mark:"; } // zero
		elseif(in_array($num, $numbers)){ $str .= ":black_circle:"; } // black
		else{$str .= ":red_circle:"; } // red

		$str .= " - " .($win ? ":white_check_mark:" : ":times:");
		$this->telegram->send
			->notification(FALSE)
			->text($this->telegram->emoji($str))
		->send();
		return $win;
	}
}
