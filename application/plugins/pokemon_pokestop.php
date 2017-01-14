<?php


if($telegram->text_has(["busca", "buscar", "buscame"], ["pokeparada", "pokeparadas", "pkstop", "pkstops"])){

}

elseif($telegram->text_has(["busca", "buscar"], "pokeparada", TRUE) && $telegram->words() > 2){
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
	if(count($res) == 1){
		$telegram->send
			->location($res[0]['lat'], $res[0]['lng'])
			->venue($res[0]['title'], "")
		->send();
	}else{
		$telegram->send
			->text("He encontrado " .count($res) ." resultados.")
		->send();
	}

	return -1;
}


?>
