<?php

function pokemon_badges($search = NULL){
	$badges = [
		[
			'name' => 'Kanto',
			'type' => 'BADGE_POKEDEX_ENTRIES',
			'desc' => 'Pokémon registrados en la Pokédex',
			'targets' => [5, 50, 100]
		],
		[
			'name' => 'Johto',
			'type' => 'BADGE_POKEDEX_JOHTO',
			'desc' => 'Registra x Pokémon originalmente de la región de Johto en la Pokédex.',
			'targets' => [5, 30, 70]
		],
		[
			'name' => 'Pokécolector',
			'type' => 'BADGE_CAPTURE_TOTAL',
			'desc' => 'Pokémon capturados',
			'targets' => [30, 500, 2000]
		],
		[
			'name' => 'Mochilero',
			'type' => 'BADGE_POKESTOPS_VISITED',
			'desc' => 'Visitas a Poképaradas',
			'targets' => [100, 1000, 2000]
		],
		[
			'name' => 'Corredor',
			'type' => 'BADGE_TRAVEL_KM',
			'desc' => 'Km recorridos',
			'targets' => [10, 100, 1000]
		],
		[
			'name' => 'Científico',
			'type' => 'BADGE_EVOLVED_TOTAL',
			'desc' => 'Pokémon evolucionados',
			'targets' => [3, 20, 200]
		],
		[
			'name' => 'Luchadora',
			'type' => 'BADGE_BATTLE_ATTACK_WON',
			'desc' => 'Combates de Gimnasio ganados',
			'targets' => [10, 100, 1000]
		],
		[
			'name' => 'Criapokémon',
			'type' => 'BADGE_HATCHED_TOTAL',
			'desc' => 'Huevos eclosionados',
			'targets' => [10, 100, 500]
		],
		[
			'name' => 'Pescador',
			'type' => 'BADGE_BIG_MAGIKARP',
			'desc' => 'Magikarp grandes conseguidos',
			'targets' => [3, 50, 300]
		],
		[
			'name' => 'Entre. Guay',
			'type' => 'BADGE_BATTLE_TRAINING_WON',
			'desc' => 'Número de entrenamientos',
			'targets' => [10, 100, 1000]
		],
		[
			'name' => 'Joven',
			'type' => 'BADGE_SMALL_RATTATA',
			'desc' => 'Rattata pequeños conseguidos',
			'targets' => [3, 50, 300]
		],
		[
			'name' => 'Fan de Pikachu',
			'type' => 'BADGE_PIKACHU',
			'desc' => 'Pikachu conseguidos',
			'targets' => [3, 50, 300]
		],
		[
			'name' => 'Escolar',
			'type' => 'BADGE_TYPE_NORMAL',
			'desc' => 'Pokémon de tipo Normal capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Ornitólogo',
			'type' => 'BADGE_TYPE_FLY',
			'desc' => 'Pokémon de tipo Volador capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Chica Mala',
			'type' => 'BADGE_TYPE_POISON',
			'desc' => 'Pokémon de tipo Veneno capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Cazabichos',
			'type' => 'BADGE_TYPE_BUG',
			'desc' => 'Pokémon de tipo Bicho capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Nadador',
			'type' => 'BADGE_TYPE_WATER',
			'desc' => 'Pokémon de tipo Agua capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Jardinero',
			'type' => 'BADGE_TYPE_GRASS',
			'desc' => 'Pokémon de tipo Planta capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Ruinamaníaco',
			'type' => 'BADGE_TYPE_GROUND',
			'desc' => 'Pokémon de tipo Tierra capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Médium',
			'type' => 'BADGE_TYPE_PSYCHIC',
			'desc' => 'Pokémon de tipo Psíquico capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Niña Soñadora',
			'type' => 'BADGE_TYPE_FAIRY',
			'desc' => 'Pokémon de tipo Hada capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Karatek',
			'type' => 'BADGE_TYPE_FIGHTING',
			'desc' => 'Pokémon de tipo Lucha capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Montañero',
			'type' => 'BADGE_TYPE_ROCK',
			'desc' => 'Pokémon de tipo Roca capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Bruja',
			'type' => 'BADGE_TYPE_GHOST',
			'desc' => 'Pokémon de tipo Fantasma capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Prendefuegos',
			'type' => 'BADGE_TYPE_FIRE',
			'desc' => 'Pokémon de tipo Fuego capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Rockero',
			'type' => 'BADGE_TYPE_ELECTRIC',
			'desc' => 'Pokémon de tipo Eléctrico capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Ferroviario',
			'type' => 'BADGE_TYPE_STEEL',
			'desc' => 'Pokémon de tipo Acero capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Esquiador',
			'type' => 'BADGE_TYPE_ICE',
			'desc' => 'Pokémon de tipo Hielo capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Domadragón',
			'type' => 'BADGE_TYPE_DRAGON',
			'desc' => 'Pokémon de tipo Dragón capturados.',
			'targets' => [10, 50, 200]
		],
		[
			'name' => 'Macarra',
			'type' => 'BADGE_TYPE_DARK',
			'desc' => 'Pokémon de tipo Siniestro capturados.',
			'targets' => [10, 50, 200]
		],
	];
	if(empty($search)){ return $badges; }
	foreach($badges as $badge){
		if(
			strtolower($search) == strtolower($badge['name']) or
			strtoupper($search) == $badge['type'] or
			strtoupper("BADGE_TYPE_" .$search) == $badge['type'] or
			strtoupper("BADGE_" .$search) == $badge['type']
		){ return $badge; }
	}
	return NULL;
}

function badge_points($badge, $user, $update = FALSE){
	if(substr($badge, 6) != "BADGE_"){
		$badge = pokemon_badges($badge);
		if(empty($badge)){ return FALSE; }
		$badge = $badge['type'];
	}

	$CI =& get_instance();

	$query = $CI->db
		->where('uid', $user)
		->where('type', $badge)
		->order_by('date', 'DESC')
		->limit(1)
	->get('user_badges');

	if($query->num_rows() == 0){ return 0; }
	if(!$update){ return (int) $query->row()->value; }

	$data = [$query->row()->value, $query->row()->data];
	$data[1] = date("Y-m-d H:i:s", strtotime($data[1]));
	return $data;
}

function badge_register($badge, $amount, $user){
	$CI =& get_instance();
	if(is_string($badge)){
		$badge = pokemon_badges($badge);
		if(empty($badge)){ return FALSE; }
	}

	$data = [
		'uid' => $user,
		'type' => $badge['type'],
		'value' => $amount
	];

	return $CI->db->insert('user_badges', $data);
}

function badges_list($user){
	$CI =& get_instance();
	$user = (int) $user;

	$query = $CI->db
		->where_in('id', 'SELECT MAX(id) FROM user_badges WHERE uid = ' .$user .' GROUP BY type', FALSE)
		->order_by('date', 'DESC')
	->get('user_badges');

	if($query->num_rows() == 0){ return array(); }
	return array_column($query->result_array(), 'value', 'type');
}

if(
	($telegram->text_command("badge") or
	$telegram->text_command("medalla")) and
	$telegram->words() == 3
){
	$amount = (int) $telegram->last_word(TRUE);
	$badge = pokemon_badges($telegram->words(1));

	if(empty($badge)){
		$this->telegram->send
			->text($this->telegram->emoji(":warning: Esa medalla no existe."))
		->send();

		return -1;
	}

	$utarget = $this->telegram->user->id;

	if($this->telegram->user->id == $this->config->item('creator') && $this->telegram->has_reply){
		$utarget = $this->telegram->reply_target('forward')->id;
	}

	$points = badge_points($badge['type'], $utarget);

	if($amount < $points){
		$this->telegram->send
			->text($this->telegram->emoji(":times: ¡No puedes poner menos puntos de los que ya tienes!"))
		->send();

		return -1;
	}elseif($amount == $points){
		$this->telegram->send
			->text($this->telegram->emoji(":ok: Ya estaba agregado."))
		->send();

		return -1;
	}

	$q = badge_register($badge, $amount, $utarget);
	if($q){
		$this->telegram->send
			->text($this->telegram->emoji(":ok: Registrada!"))
		->send();
	}

	return -1;
}elseif($telegram->text_command("badgeocr") && $this->telegram->user->id == $this->config->item('creator') && $this->telegram->has_reply){

	if(!isset($this->telegram->reply->photo)){ return; }
	$photo = array_pop($this->telegram->reply->photo);
	$url = $this->telegram->download($photo['file_id']);

	$temp = tempnam("/tmp", "tgphoto");
	file_put_contents($temp, file_get_contents($url));

	require_once APPPATH .'third_party/tesseract-ocr-for-php/src/TesseractOCR.php';

	$ocr = new TesseractOCR($temp);
	$text = $ocr->lang('spa', 'eng')->run();
	unlink($temp);

	$badge = NULL;
	foreach(explode("\n", $text) as $t){
		$t = trim($t);
		if(empty($t)){ continue; }
		if(($badge = pokemon_badges($t)) !== NULL){ break; }
	}

	$str = $this->telegram->emoji(":times:") ." Foto no reconocida.";
	if(!empty($badge)){
		$str = $badge['type'];
	}

	$this->telegram->send
		->text($str)
	->send();

	return -1;
}elseif($telegram->text_command("badges")){
	$badges = pokemon_badges();
	$utarget = $this->telegram->user->id;
	if($this->telegram->user->id == $this->config->item('creator') && $this->telegram->has_reply){
		$utarget = $this->telegram->reply_target('forward')->id;
	}

	$user_badges = badges_list($utarget);

	$str = "No tienes medallas.";
	if(!empty($user_badges)){
		$str = "";
		foreach($badges as $badge){
			$n = 0;
			$value = $user_badges[$badge['type']];
			if(empty($value)){ continue; }

			$max = max(array_values($user_badges));
			$max = strlen($max);

			$icons = ['\u2796', '\ud83e\udd49', '\ud83e\udd48', '\ud83e\udd47'];

			foreach($badge['targets'] as $min){
				if($value >= $min){ $n++; }
			}

			$str .= $this->telegram->emoji($icons[$n]) ." <code>" .str_pad($value, $max, ' ', STR_PAD_LEFT) ." </code> " .$badge['name'] ."\n";
		}
	}

	$this->telegram->send
		->text($str, 'HTML')
	->send();

	return -1;
}


?>
