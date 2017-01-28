<?php

$numbers = [2,4,6,8,10,11,13,15,17,20,22,24,26,28,29,31,33,35]; // black

if($telegram->text_has(["ruleta", "apuesto", "apostar"], "al") && $telegram->words() == 3){
	$num = mt_rand(0, 36);
	$word = strtolower($telegram->last_word());
	$win = FALSE;

	$str = $num ."! ";
	if($num == 0){ $str .= ":ok:"; } // zero
	elseif(in_array($num, $numbers)){ $str .= "\u26ab\ufe0f"; } // black
	else{$str .= "\ud83d\udd34"; } // red

	if($word == 'rojo' && $num != 0 && !in_array($num, $numbers)){
		$win = TRUE;
	}elseif($word == 'negro' && in_array($num, $numbers)){
		$win = TRUE;
	}elseif($word == 'par' && ($num % 2) == 0){
		$win = TRUE;
	}elseif($word == 'impar' && ($num % 2) == 1){
		$win = TRUE;
	}elseif($word == 'primero' && $num <= 12){
		$win = TRUE;
	}elseif($word == 'segundo' && $num > 12 && $num <= 24){
		$win = TRUE;
	}elseif($word == 'tercero' && $num > 24 && $num <= 36){
		$win = TRUE;
	}elseif($word == 'principio' && $num <= 18){
		$win = TRUE;
	}elseif($word == 'final' && $num > 18){
		$win = TRUE;
	}elseif(is_numeric($word) && $word == $num){
		$win = TRUE;
	}

	$str .= "\n" .($win ? "Acertaste!" : "Has perdido :(");
	$telegram->send
		->notification(FALSE)
		->text($telegram->emoji($str))
	->send();
	return -1;
}

?>
