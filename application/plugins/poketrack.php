<?php

if($this->telegram->text_has(["I found a", "Encontré un"], TRUE) && $this->telegram->text_contains("PokéTrack")){
	$loc = array();
	preg_match("/([+-]?)(\d+.\d+)[,;]\s?([+-]?)(\d+.\d+)/", $telegram->text(), $loc);
	if(empty($loc)){ return -1; }

    $loc = $loc[0];
    if(strpos($loc, ";") !== FALSE){ $loc = explode(";", $loc); }
    elseif(strpos($loc, ",") !== FALSE){ $loc = explode(",", $loc); }

	$poke = pokemon_parse($this->telegram->text());
	if(empty($poke)){ return -1; }
	$poke = $pokemon->find($poke['pokemon']);

	$txt = explode("\n", $telegram->text());

	$title = $poke->name ." " .trim($txt[1]);
	$subtitle = trim($txt[4]);

	$this->telegram->send
		->location($loc[0], $loc[1])
		->venue($title, $subtitle)
	->send();

	return -1;
}

?>
