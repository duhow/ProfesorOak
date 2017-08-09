<?php

if($this->telegram->text_has(["I found a", "Encontré un"], TRUE)){
	//  && $this->telegram->text_contains("PokéTrack")
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

	$this->telegram->send->delete(TRUE);

	return -1;
}elseif($this->telegram->text_url() and $this->telegram->text_contains("m.poketrack.xyz")){
	$url = $this->telegram->text_url();
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HEADER, TRUE); //include headers in http data
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE); //don't follow redirects
	$http_data = curl_exec($ch); //hit the $url
	$curl_info = curl_getinfo($ch);
	curl_close($ch);

	if(!isset($curl_info['redirect_url'])){ return; }

	$query = parse_url($curl_info['redirect_url'], PHP_URL_QUERY);
	$query = explode("&", $query);
	$data = array();
	foreach($query as $q){
		$t = explode("=", $q);
		$data[$t[0]] = $t[1];
	}

	if(isset($data['lat']) and isset($data['lon'])){
		$poke = "¡Pokémon visto!";
		if(isset($data['name'])){ $poke = $data['name']; }

		$this->telegram->send
			->location($data['lat'], $data['lon'])
			->venue($poke, "")
		->send();

		$this->telegram->send->delete(TRUE);
	}
}

?>
