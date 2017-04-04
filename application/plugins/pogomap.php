<?php

if(
	$telegram->text_has("buscar", "pokemon") &&
	$telegram->has_reply &&
	isset($telegram->reply->location)
){
	$map = $pokemon->settings($telegram->chat->id, 'pogomap');
	if(!$map){ return; }

	$dist = 250; // metros

	$loc = [$telegram->reply->location['latitude'], $telegram->reply->location['longitude']];
	$locSW = $pokemon->location_add($loc, $dist, "SW");
	$locNE = $pokemon->location_add($loc, $dist, "NE");

	$data = [
		'pokestops' => 'false',
		'gyms' => 'false',
		'pokemon' => 'true',
		'lastpokemon' => 'true',
		'neLat' => $locNE[0],
		'neLng' => $locNE[1],
		'swLat' => $locSW[0],
		'swLng' => $locSW[1],
	];

	$url = $map ."map/raw_data?" .http_build_query($data);
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);


	$cookie = "pogomap." .abs($telegram->chat->id) .".txt";
	if(file_exists($cookie)){
		curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie);
	}

	$json = curl_exec($ch);
	curl_close($ch);


	$json = json_decode($json);
	$str = "No hay nada interesante.";

	if(!$json){
		$str = "Error al cargar.";
	}

	if($json && !empty($json->pokemons)){
		$str = "";
		foreach($json->pokemons as $poke){
			if(in_array($poke->pokemon_rarity, ["Common", "Uncommon"])){ continue; }
			$locpk = [$poke->latitude, $poke->longitude];
			$dist = $pokemon->location_distance($loc, $locpk);
			$str .= "- " .$poke->pokemon_name ." a $dist m.\n";
		}
	}

	$this->telegram->send
		->text($str)
	->send();
	return -1;
}

?>
