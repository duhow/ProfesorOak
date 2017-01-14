<?php

if($telegram->text_has(["busca", "buscar"], "pokeparada", TRUE) && $telegram->words() >= 3){
	$text = $telegram->words(2, 10);
	$loc = NULL;

	if($telegram->text_has("cerca", "de")){
		$look = substr($telegram->text(), strpos($telegram->text(), "cerca de ") + strlen('cerca de '));
		$look = trim($look);
		$poscoords = explode(",", $look);

		if(count($poscoords) == 2 && is_numeric($poscoords[0])){
			$loc = $poscoords;
		}else{
			if(!function_exists('map_search')){
				$telegram->send
					->text($telegram->emoji(":times:") ." No puedo cargar ubicación aun :(")
				->send();
				return -1;
			}
			$loc = map_search($look, TRUE);
		}

		$text = substr($text, 0, strpos($text, "cerca de"));
	}

	$text = trim($text);

	$res = $pokemon->pokestops_search($text, $loc);
	if(count($res) > 0 && count($res) <= 5){
		foreach($res as $stop){
			$telegram->send
				->location($stop['lat'], $stop['lng'])
				->venue($stop['title'], "")
			->send();
			usleep(300*1000);
		}
	}elseif(count($res) == 0){
		$telegram->send
			->text($telegram->emoji(":times:") ." No encuentro nada de $text.")
		->send();
	}else{
		$telegram->send
			->text("He encontrado " .count($res) ." resultados de $text en " .json_encode($loc))
		->send();
	}

	return -1;
}

elseif(
	$telegram->words() <= 4 &&
	$telegram->text_has(["busca", "buscar", "buscame"], ["pokeparadas", "pkstop", "pkstops"])
){
	$pk = pokemon_parse($telegram->text());
	$distance = (isset($pk['distance']) ? $pk['distance'] : 160);
	$loc = NULL;
	if($telegram->has_reply && isset($telegram->reply->location)){
		$loc = [$telegram->reply->location['latitude'], $telegram->reply->location['longitude']];
	}elseif($telegram->is_chat_group()){
		$loc = explode(",", $pokemon->settings($telegram->chat->id, 'location'));
		if(!empty($loc)){
			$dist2 = $pokemon->settings($telegram->chat->id, 'location_radius');
			if(!empty($dist2)){ $distance = $dist2; }
		}
	}
	if($loc === NULL){
		$loc = explode(",", $pokemon->settings($telegram->chat->id, 'location'));
		if(empty($loc) or count($loc) != 2){
			$telegram->send
				->notification(FALSE)
				->text("No tengo ninguna ubicación. ¿Me la mandas?")
				->keyboard()
					->row_button($telegram->emoji(":pin: Enviar ubicación"), FALSE)
				->show(TRUE, TRUE)
			->send();
			return;
		}
	}
	$stops = $pokemon->pokestops($loc, $distance, 500);
	$text = "No hay PokéParadas registradas ahí.";
	$found = FALSE;
	if(!empty($stops)){
		$found = TRUE;
		$text = (count($stops) >= 499 ? "¡Hay más de <b>500</b> PokéParadas ahi!" : "Hay unas <b>" .count($stops) ."</b> PokéParadas aproximadamente. Seguramente menos.") ."\n";
		$text .= "Las más cercanas son:\n";
		$lim = (count($stops) < 10 ? count($stops) : 10);
		for($i = 0; $i < $lim; $i++){
			$text .= "A <b>" .floor($stops[$i]['distance']) ."m</b> tienes " .$stops[$i]['title'] .".\n";
		}

		$pkuser = $pokemon->user($telegram->user->id);

		if($pkuser->team == 'Y'){ $color = "0xffee00"; }
		elseif($pkuser->team == 'B'){ $color = "0x0000aa"; }
		else{ $color = "0xff0000"; } // Red

		$zoom = ($stops[count($stops) - 1]['distance'] < 400 ? 17 : 16);

		$url = "http://maps.googleapis.com/maps/api/staticmap?center=" .$loc[0] ."," .$loc[1] ."&zoom=$zoom&scale=2&size=500x400&maptype=terrain&format=png&visual_refresh=true";
		$url .= "&markers=size:mid%7Ccolor:" .$color ."%7Clabel:P%7C" .$loc[0] ."," .$loc[1];
		for($i = 0; $i < $lim; $i++){
			$url .= "&markers=size:mid%7Ccolor:0xdd40ff%7Clabel:" .($i+1) ."%7C" .$stops[$i]['lat'] ."," .$stops[$i]['lng'];
		}
		$text .= '<a href="' .$url .'">IMG</a>';
	}
	$chat = ($telegram->is_chat_group() && $this->is_shutup() ? $telegram->user->id : $telegram->chat->id);
	$telegram->send
		->chat($chat)
		->keyboard()->hide(TRUE)
		->text($text, 'HTML')
	->send();
	if(!$found && $loc){
		$telegram->send
			->chat($this->config->item('creator'))
			->text("*!!* Busca pokeparadas en *" .implode(",", $loc) ."*", TRUE)
		->send();
	}

	return -1;
}



?>
