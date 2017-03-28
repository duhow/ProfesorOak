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

function badge_register($badge, $amount, $user){
	$CI =& get_instance();
	$badge = pokemon_badges($badge);
	if(empty($badge)){ return FALSE; }

	$data = [
		'uid' => $user,
		'type' => $badge['type'],
		'value' => $amount
	];

	return $CI->db->insert('user_badges', $data);
}

if(
	($telegram->text_command("badge") or
	$telegram->text_command("medalla")) and
	$telegram->words() == 3
){
	$amount = (int) $telegram->last_word(TRUE);
	$badge = $telegram->words(1);

	$q = badge_register($badge, $amount, $telegram->user->id);
	if($q){
		$this->telegram->send
			->text($this->telegram->emoji(":ok: Registrada!"))
		->send();
	}

	return -1;
}


?>
