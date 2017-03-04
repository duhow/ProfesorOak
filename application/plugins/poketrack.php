<?php

if($this->telegram->text_has(["I found a", "Encontré un"], TRUE) && $this->telegram->text_contains("PokéTrack")){
	$loc = array();
	preg_match("/([+-]?)(\d+.\d+)[,;]\s?([+-]?)(\d+.\d+)/", $telegram->text(), $loc);
	if(empty($loc)){ return -1; }

    $loc = $loc[0];
    if(strpos($loc, ";") !== FALSE){ $loc = explode(";", $loc); }
    elseif(strpos($loc, ",") !== FALSE){ $loc = explode(",", $loc); }

	$txt = explode("\n", $telegram->text());

	$poke = $this->telegram->words(2, TRUE);
	if($this->telegram->text_has("I found a")){ $poke = $this->telegram->words(3, TRUE); }
	$poke = str_replace("IV", "", $poke); // BUG Aparece como PokemonIV
	$poke = trim($poke);

	$poke = $pokemon->find($poke); // Load data
	if(empty($poke)){ return -1; }

	$title = $poke['name'] ." " .trim($txt[1]); // Name + IV
	$subtitle = trim($txt[4]); // Time left

	$this->telegram->send
		->location($loc[0], $loc[1])
		->venue($title, $subtitle)
	->send();

	return -1;
}

?>
