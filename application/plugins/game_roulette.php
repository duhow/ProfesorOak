<?php

$numbers = [2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35]; // black

if($telegram->text_has(["ruleta", "apuesto", "apostar"], "al") && $telegram->words() == 3){
	$can = $this->pokemon->settings($this->telegram->chat->id, 'play_games');
	if($can != NULL && $can == FALSE){ return -1; }

	$num = mt_rand(0, 36);
	$word = strtolower($telegram->last_word(TRUE));
	$win = FALSE;

	$str = $num ."! ";
	if($num == 0){ $str .= ":ok:"; } // zero
	elseif(in_array($num, $numbers)){ $str .= "\u26ab\ufe0f"; } // black
	else{$str .= "\ud83d\udd34"; } // red

	if(is_numeric($word)){
		$win = ($word == $num);
	}else{
		switch ($word) {
			case 'rojo':      $win = ($num != 0 && !in_array($num, $numbers)); break;
			case 'negro':     $win = (in_array($num, $numbers)); break;
			case 'par':       $win = (($num % 2) == 0); break;
			case 'impar':     $win = (($num % 2) == 1); break;
			case 'primero':   $win = ($num <= 12); break;
			case 'segundo':   $win = ($num > 12 && $num <= 24); break;
			case 'tercero':   $win = ($num > 24 && $num <= 36); break;
			case 'principio': $win = ($num <= 18); break;
			case 'final':     $win = ($num > 18); break;
			default:
				$win = FALSE;
			break;
		}
	}

	$str .= "\n" .($win ? "Acertaste!" : "Has perdido :(");
	$telegram->send
		->notification(FALSE)
		->text($telegram->emoji($str))
	->send();
	return -1;
}

?>
