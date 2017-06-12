<?php

if($this->telegram->text_has("Hora", TRUE) && in_array($telegram->words(), [2,3]) && $this->telegram->key == "message"){
	$zonas = ['PDT' => -7, 'PST' => -8, 'CET' => 1, 'UTC' => 0, 'GMT' => 0];
	$sel = NULL;
	foreach($zonas as $z => $t){
		if($this->telegram->text_contains($z)){ $sel = $z; break; }
	}
	if($this->telegram->text_has("Pokémon")){ $sel = 'PST'; }

	if(empty($sel)){
		$this->telegram->send
		->text("Zona horaria no reconocida.")
		->send();
		return -1;
	}

	$sum = 0;
	$word = strtoupper($this->telegram->words(1));

	if(strpos($word, $zonas[$sel]) === 0){
		if(strlen($word) > strlen($zonas[$sel]) && in_array(substr($word, 3, 1), ["-", "+"])){
			$sum = substr($word, 3);
			$sum = intval($sum);
		}
	}

	// TODO Daylight. Debería cambiar automáticamente.
	$zdiff = 7200;
	$time = time() - $zdiff; // Madrid - Rome

	if($this->telegram->words() == 3){
		$timefrom = NULL;
		if(function_exists('time_parse')){
			$timefrom = time_parse($this->telegram->words(2));
			if(!empty($timefrom) && isset($timefrom['hour'])){

				$time = strtotime($timefrom['hour']);
				$local = $time - (3600 * $zonas[$sel]) - (3600*$sum) + $zdiff ; // TODO Check
			}
		}
	}else{
		$time = $time + (3600 * $zonas[$sel]) + (3600 * $sum);
		$local = time(); // Current
	}

	$icon_world = ":world:";
	if(in_array($sel, ['PST', 'PDT'])){ $icon_world = ":world-usa:"; }
	$str = ":clock: " .date("H:i:s", $local) ."\n"
	."$icon_world " .date("H:i:s", $time);

	$str = $this->telegram->emoji($str);
	$this->telegram->send
	->text($str)
	->send();

	return -1;
}

?>
