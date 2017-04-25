<?php

function pokemon_badges($search = NULL){
	$badges = [
		[
			'name' => 'Kanto',
			'name_en' => 'Kanto',
			'type' => 'BADGE_POKEDEX_ENTRIES',
			'desc' => 'Pokémon registrados en la Pokédex',
			'targets' => [5, 50, 100]
		],
		[
			'name' => 'Johto',
			'name_en' => 'Johto',
			'type' => 'BADGE_POKEDEX_JOHTO',
			'desc' => 'Registra x Pokémon originalmente de la región de Johto en la Pokédex.',
			'targets' => [5, 30, 70]
		],
		[
			'name' => 'Pokecolector',
			'name_en' => 'Collector',
			'type' => 'BADGE_CAPTURE_TOTAL',
			'desc' => 'Pokémon capturados',
			'desc_en' => 'Capture Pokemon',
			'targets' => [30, 500, 2000, 25000, 100000]
		],
		[
			'name' => 'Mochilero',
			'name_en' => 'Backpacker',
			'type' => 'BADGE_POKESTOPS_VISITED',
			'desc' => 'Visitas a Poképaradas',
			'desc_en' => 'Visit PokéStops',
			'targets' => [100, 1000, 2000, 25000, 100000]
		],
		[
			'name' => 'Corredor',
			'name_en' => 'Jogger',
			'type' => 'BADGE_TRAVEL_KM',
			'desc' => 'Km recorridos',
			'desc_en' => 'Walk km',
			'targets' => [10, 100, 1000, 3000, 12742]
		],
		[
			'name' => 'Cientifico',
			'name_en' => 'Scientist',
			'type' => 'BADGE_EVOLVED_TOTAL',
			'desc' => 'Pokémon evolucionados',
			'desc_en' => 'Evolve Pokémon',
			'targets' => [3, 20, 200, 2000, 20000]
		],
		[
			'name' => 'Luchadora',
			'name_en' => 'Battle Girl',
			'type' => 'BADGE_BATTLE_ATTACK_WON',
			'desc' => 'Combates de Gimnasio ganados',
			'desc_en' => 'Win Gym battles',
			'targets' => [10, 100, 1000, 10000, 100000]
		],
		[
			'name' => 'Criapokemon',
			'name_en' => 'Breeder',
			'type' => 'BADGE_HATCHED_TOTAL',
			'desc' => 'Huevos eclosionados',
			'desc_en' => 'Hatch Eggs',
			'targets' => [10, 100, 500, 3000, 25000]
		],
		[
			'name' => 'Pescador',
			'name_en' => 'Fisherman',
			'type' => 'BADGE_BIG_MAGIKARP',
			'desc' => 'Magikarp grandes conseguidos',
			'desc_en' => 'Catch big Magikarp',
			'targets' => [3, 50, 300, 2500, 7500]
		],
		[
			'name' => 'Brillante',
			'name_en' => 'Shiny',
			'type' => 'BADGE_SHINY',
			'custom' => TRUE,
			'desc' => 'Pokémon Shiny capturados',
			'targets' => [1, 5, 25]
		],
		[
			'name' => 'Entre. Guay',
			'name_en' => 'Ace Trainer',
			'type' => 'BADGE_BATTLE_TRAINING_WON',
			'desc' => 'Número de entrenamientos',
			'name_en' => 'Train times',
			'targets' => [10, 100, 1000, 10000, 100000]
		],
		[
			'name' => 'Joven',
			'name_en' => 'Youngster',
			'type' => 'BADGE_SMALL_RATTATA',
			'desc' => 'Rattata pequeños conseguidos',
			'desc_en' => 'Catch tiny Rattata',
			'targets' => [3, 50, 300, 2500, 7500]
		],
		[
			'name' => 'Fan de Pikachu',
			'name_en' => 'Pikachu Fan',
			'type' => 'BADGE_PIKACHU',
			'desc' => 'Pikachu conseguidos',
			'desc_en' => 'Catch Pikachu',
			'targets' => [3, 50, 300, 2500, 7500]
		],
		[
			'name' => 'Unown',
			'type' => 'BADGE_UNOWN',
			'desc' => 'Unown atrapados',
			'targets' => [3, 10, 26]
		],
		[
			'name' => 'Escolar',
			'name_en' => 'Schoolkid',
			'type' => 'BADGE_TYPE_NORMAL',
			'desc' => 'Pokémon de tipo Normal capturados.',
			'desc_en' => 'Catch Normal-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Ornitologo',
			'name_en' => 'Bird Keeper',
			'type' => 'BADGE_TYPE_FLY',
			'desc' => 'Pokémon de tipo Volador capturados.',
			'desc_en' => 'Catch Flying-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Chica Mala',
			'name_en' => 'Punk Girl',
			'type' => 'BADGE_TYPE_POISON',
			'desc' => 'Pokémon de tipo Veneno capturados.',
			'desc_en' => 'Catch Poison-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Cazabichos',
			'name_en' => 'Bug Catcher',
			'type' => 'BADGE_TYPE_BUG',
			'desc' => 'Pokémon de tipo Bicho capturados.',
			'desc_en' => 'Catch Bug-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Nadador',
			'name_en' => 'Swimmer',
			'type' => 'BADGE_TYPE_WATER',
			'desc' => 'Pokémon de tipo Agua capturados.',
			'desc_en' => 'Catch Water-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Jardinero',
			'name_en' => 'Gardener',
			'type' => 'BADGE_TYPE_GRASS',
			'desc' => 'Pokémon de tipo Planta capturados.',
			'desc_en' => 'Catch Grass-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Ruinamaniaco',
			'name_en' => 'Ruin Maniac',
			'type' => 'BADGE_TYPE_GROUND',
			'desc' => 'Pokémon de tipo Tierra capturados.',
			'desc_en' => 'Catch Ground-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Medium',
			'name_en' => 'Psychic',
			'type' => 'BADGE_TYPE_PSYCHIC',
			'desc' => 'Pokémon de tipo Psíquico capturados.',
			'desc_en' => 'Catch Psychic-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Niña Soñadora',
			'name_en' => 'Fairy Tale Girl',
			'type' => 'BADGE_TYPE_FAIRY',
			'desc' => 'Pokémon de tipo Hada capturados.',
			'desc_en' => 'Catch Fairy-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Karateka',
			'name_en' => 'Black Belt',
			'type' => 'BADGE_TYPE_FIGHTING',
			'desc' => 'Pokémon de tipo Lucha capturados.',
			'desc_en' => 'Catch Fighting-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Montañero',
			'name_en' => 'Hiker',
			'type' => 'BADGE_TYPE_ROCK',
			'desc' => 'Pokémon de tipo Roca capturados.',
			'desc_en' => 'Catch Rock-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Bruja',
			'name_en' => 'Hex Maniac',
			'type' => 'BADGE_TYPE_GHOST',
			'desc' => 'Pokémon de tipo Fantasma capturados.',
			'desc_en' => 'Catch Ghost-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Prendefuegos',
			'name_en' => 'Kindler',
			'type' => 'BADGE_TYPE_FIRE',
			'desc' => 'Pokémon de tipo Fuego capturados.',
			'desc_en' => 'Catch Fire-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Rockero',
			'name_en' => 'Rocker',
			'type' => 'BADGE_TYPE_ELECTRIC',
			'desc' => 'Pokémon de tipo Eléctrico capturados.',
			'desc_en' => 'Catch Electric-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Ferroviario',
			'name_en' => 'Depot Agent',
			'type' => 'BADGE_TYPE_STEEL',
			'desc' => 'Pokémon de tipo Acero capturados.',
			'desc_en' => 'Catch Steel-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Esquiador',
			'name_en' => 'Skier',
			'type' => 'BADGE_TYPE_ICE',
			'desc' => 'Pokémon de tipo Hielo capturados.',
			'desc_en' => 'Catch Ice-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Domadragon',
			'name_en' => 'Dragon Tamer',
			'type' => 'BADGE_TYPE_DRAGON',
			'desc' => 'Pokémon de tipo Dragón capturados.',
			'desc_en' => 'Catch Dragon-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
		[
			'name' => 'Macarra',
			'name_en' => 'Delinquent',
			'type' => 'BADGE_TYPE_DARK',
			'desc' => 'Pokémon de tipo Siniestro capturados.',
			'desc_en' => 'Catch Dark-type Pokémon.',
			'targets' => [10, 50, 200, 5000, 25000]
		],
	];
	if(empty($search)){ return $badges; }
	$search = str_replace(["é", "í", "ó"], ["e", "i", "o"], $search);
	foreach($badges as $badge){
		if(
			strtolower($search) == strtolower($badge['name']) or
			strtolower($search) == strtolower(@$badge['name_en']) or
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

function badges_last($user, $type, $full = FALSE){
	$CI =& get_instance();

	$query = $CI->db
		->select(['type', 'date'])
		->where('uid', $user)
		->where('type', $type)
		->where('date >=', 'NOW() - INTERVAL 26 HOUR', FALSE)
		->order_by('date', 'ASC')
		->limit(1)
	->get('user_badges');

	if($query->num_rows() == 0){ return NULL; }
	if($full){ return $query->row(); }
	return $query->row()->date;
}

function badges_max_ranking($badges = NULL){
	$CI =& get_instance();

	$inchat = NULL;
	// Ranking del grupo
	if(is_numeric($badges)){
		$inchat = $badges;
		$badges = NULL;
	}

	if(empty($badges)){
		$badges = array_column(pokemon_badges(), 'type');
	}elseif(!is_array($badges)){
		$badges = [$badges];
	}

	$CI->db
		->select(['user_badges.*', 'user.username'])
		->from('user_badges')
		->join('user', 'user_badges.uid = user.telegramid')
		->group_start();
	foreach($badges as $b){
		$sql = 'SELECT id FROM user_badges WHERE type = "'. $b .'" ';
		if($inchat){
			$sql .= 'AND uid IN (SELECT uid FROM user_inchat WHERE cid = "'. $inchat .'") ';
		}
		$sql .= 'ORDER BY value DESC, date ASC LIMIT 1';

		$CI->db->or_where('id', "($sql)", FALSE);
	}

	$CI->db->group_end();

	$query = $CI->db
		->order_by('type')
	->get();

	echo $CI->db->last_query();

	if($query->num_rows() == 0){ return array(); }
	$list = array();
	foreach($query->result_array() as $r){
		$list[$r['type']] = $r;
	}

	return $list;
}

// ---------------------------------------------

function achievements_last($user, $type){
	$CI =& get_instance();

	$query = $CI->db
		->where('uid', $user)
		->where('type', $type)
		->where('date >=', 'NOW() - INTERVAL 26 HOUR', FALSE)
		->order_by('date', 'ASC')
		->limit(1)
	->get('user_achievements');

	if($query->num_rows() == 0){ return FALSE; }
	return $query->row()->date;
}

function achievements_can_make_new($user, $type){
	$date = achievements_last($user, $type);
	if($date === FALSE){ return TRUE; }
	$date = strtotime("+26 hours", strtotime($date));
	return (time() >= $date);
}

function achievement_add($user, $type, $amount = NULL){
	$CI =& get_instance();

	$data = [
		'uid' => $user,
		'type' => $type,
		'amount' => $amount
	];

	$CI->db->insert('user_achievements', $data);
	return $CI->db->insert_id();
}

function achievements_get($user, $type = NULL){
	$CI =& get_instance();

	if(!empty($type)){ $CI->db->where('type', $type); }
	$query = $CI->db
		->select(['type', 'count(*) AS count'])
		->where('uid', $user)
		->group_by('type')
	->get('user_achievements');

	if($query->num_rows() == 0){
		return (!empty($type) ? 0 : array());
	}
	return (!empty($type) ? $query->row()->count : array_column($query->result_array(), 'count', 'type') );
}

// ---------------------------------------------

if($pokemon->step($telegram->user->id) == "BADGE" && !$this->telegram->is_chat_group()){
	if($this->telegram->text_has("Listo", TRUE)){
		$this->telegram->send
			->text("Guay! Puedes ver todas las medallas con /badges .")
		->send();
		$this->pokemon->settings($telegram->user->id, 'badge_type', "DELETE");
		$this->pokemon->step($telegram->user->id, NULL);
	}elseif($this->telegram->photo() && !$telegram->has_forward){
		// Hacer OCR

		if($this->pokemon->settings($telegram->user->id, 'badge_type')){ return -1; }

		// HACK FIXME Arreglar método de acceso a última foto
		$photos = $this->telegram->dump()['message']['photo'];
		$photo = array_pop($photos);
		$photo = $photo['file_id'];

		$url = $this->telegram->download($photo);

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
			$badge = pokemon_badges($t);
			if(!empty($badge)){ break; }
		}

		if(empty($badge)){
			$this->telegram->send
				->text($this->telegram->emoji(":warning: ") ."No he reconocido la medalla. Asegúrate de no recortar ni mover la pantalla.")
			->send();
			return -1;
		}

		// Hide previous Keyboard
		$this->telegram->send->keyboard()->hide(TRUE);

		// Set badge to settings.
		$this->pokemon->settings($telegram->user->id, 'badge_type', $badge['type']);
		$str = $this->telegram->emoji(":ok: ") ."Detecto la medalla <b>" .$badge['name'] ."</b>. ¿Cuántos puntos tienes?";
		if($badge['type'] == "BADGE_TRAVEL_KM"){
		    $str .= "\n" .$this->telegram->emoji(":red-exclamation:") ." <b>NOTA:</b> En esta medalla <b>no pongas decimales, y redondea hacia abajo</b>."
		            ."\n" ."<i>Si tienes 745.75 KM, pon 745.</i>";
		}
		$this->telegram->send
		    ->reply_to(TRUE)
		    ->force_reply(TRUE)
		    ->text($str, 'HTML')
		->send();

	}elseif(
		$this->telegram->text() &&
		$this->telegram->has_reply &&
		$this->telegram->reply_user->id == $this->config->item('telegram_bot_id') &&
		$this->telegram->words() == 1
	){
		$tx = $this->telegram->text_message();
		if(strpos($tx, "Detecto la medalla") === FALSE){ return -1; } // HACK

		$amount = (int) $this->telegram->last_word();
		if($amount < 1){ return -1; }

		$badgetype = $pokemon->settings($telegram->user->id, 'badge_type');
		if(empty($badgetype)){
			$this->pokemon->step($telegram->user->id, NULL);
			return -1;
		}
		$badge = pokemon_badges($badgetype);

		$utarget = $this->telegram->user->id;
		$points = badge_points($badge['type'], $utarget);

		$str = ":ok: No ha cambiado nada desde la última vez que la pusiste.";

		if($amount < $points){
			$this->telegram->send
				->text($this->telegram->emoji(":times: ¡No puedes poner menos puntos de los que ya tienes!"))
			->send();

			return -1;
		}elseif($amount != $points){
			$badgetype = $pokemon->settings($telegram->user->id, 'badge_type', "DELETE");

			// TODO algo falta en la función. Comprobar si se hacen varios envíos a lo largo del día, coger la última fecha.
			// Hay que diferenciar los puntos para llegar hasta ellos.

			$achievement = FALSE;

			// Cargar datos anteriores.
			$last = badges_last($telegram->user->id, $badgetype, TRUE);
			if($last && $points > 0){ // && time() < strtotime("+26 hours", strtotime($last->date))
				if(achievements_can_make_new($telegram->user->id, $badgetype)){
					// Calcular los puntos necesarios para hacer el logro.

					$achs = [
						// 'BADGE_CAPTURE_TOTAL' => 300,
						'BADGE_POKESTOPS_VISITED' => 300,
						'BADGE_BATTLE_ATTACK_WON' => 50,
						'BADGE_BATTLE_TRAINING_WON' => 50,
						'BADGE_TRAVEL_KM' => 15,
						'BADGE_HATCHED_TOTAL' => 5,
						'BADGE_PIKACHU' => 10,
						'BADGE_BIG_MAGIKARP' => 5,
						'BADGE_SMALL_RATTATA' => 5,
						'BADGE_EVOLVED_TOTAL' => 50,
					];

					if(isset($achs[$last->type]) && ($last->value + $achs[$last->type]) >= $amount){
						achievement_add($telegram->user->id, $badgetype);
						$achievement = TRUE;

						$this->telegram->send
							->notification(TRUE)
							->text(-184149677) // Grupo Medallas
							->text("LOGRO: " .$telegram->user->id . " - $bt: " .(abs($amount - $points)) )
						->send();
					}
				}
			}


			$q = badge_register($badge, $amount, $utarget);
			$str = ":warning: Error al guardar.";
			if($q){
				$str = ":ok: ¡Guardada! Ahora tienes <b>" .$amount ."</b> en <b>" .$badge['name'] ."</b>!";
				if($achievement){
					$str .= "\n" .":star: ¡Has conseguido un logro!";
				}

				$bt = str_replace("BADGE_", "", $badge['type']);
				$this->telegram->send
					->notification(FALSE)
					->chat(-184149677) // Grupo Medallas
					->text("BADGE: " .$telegram->user->id . " - $bt: " .($points == 0 ? $amount : "$points -> $amount (" .(abs($amount - $points)) .")" ) )
				->send();
			}
		}

		$str .= "\n" ."Puedes enviar otra medalla si tienes la captura.";

		$this->telegram->send
			->keyboard()
				->row_button("Listo")
			->show(TRUE, TRUE)
			->text($this->telegram->emoji($str), 'HTML')
		->send();
	}
	return -1;
}

if(
	$this->telegram->text_has(["registrar", "registro"], ["medalla", "medallas", "badge", "badges"]) &&
	$this->telegram->words() <= 5
){
	if($this->telegram->is_chat_group()){
		$str = $this->telegram->emoji(":times:") ." Dímelo por privado, por favor.";
		$this->telegram->send->keyboard()->hide(TRUE);
	}else{
		$this->telegram->send
		->keyboard()
			->row_button("Cancelar")
		->show(TRUE, TRUE);

		$str = "Puedes registrar tus medallas aquí para poder enseñarselas a tus amigos. En un futuro, hasta podrás competir con ellas!\n\n"
				."Tienes que subir (no reenviar) <b>DE UNA EN UNA</b> una captura de pantalla donde se vea el nombre de la medalla y los puntos que has conseguido.\n\n"
				."Procura no falsificar los puntos, o podrías acabar bloqueado.";

		$this->pokemon->settings($telegram->user->id, 'badge_type', "DELETE");
		$this->pokemon->step($telegram->user->id, 'BADGE');
	}

	$this->telegram->send
		->text($str, 'HTML')
	->send();

	return -1;
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
}elseif(
	($telegram->text_command("badgeocr") or $this->telegram->text_command("bocr") or $this->telegram->text_command("ocrb")) &&
	$this->telegram->user->id == $this->config->item('creator') && $this->telegram->has_reply
){

	if(!isset($this->telegram->reply->photo)){ return; }
	$photo = array_pop($this->telegram->reply->photo);
	$url = $this->telegram->download($photo['file_id']);

	$temp = tempnam("/tmp", "tgphoto");
	file_put_contents($temp, file_get_contents($url));

	require_once APPPATH .'third_party/tesseract-ocr-for-php/src/TesseractOCR.php';

	$ocr = new TesseractOCR($temp);
	$text = $ocr->lang('spa', 'eng')->run();

	$badge = NULL;
	foreach(explode("\n", $text) as $t){
		$t = trim($t);
		if(empty($t)){ continue; }
		if(($badge = pokemon_badges($t)) !== NULL){ break; }
	}

	exec("convert $temp -gravity Center -crop 16x4%+0+50 -scale 300% -posterize 2 $temp.2");
	$ocr = new TesseractOCR("$temp.2");
	$num = $ocr
		->lang('lato')
		->whitelist('1234567890,.')
		->psm(9)
	->run();

	unlink($temp);
	unlink("$temp.2");

	if(!empty($num)){
		$num = str_replace([".", ",", " "], "", $num); // HACK Revisar si es válido.
		$num = (float) trim($num);
		$num = round($num);
	}

	$amount = NULL;

	if($num > 0){ $amount = $num; }
	if($this->telegram->words() == 2){ $amount = (int) $this->telegram->last_word(); }

	$str = $this->telegram->emoji(":times:") ." Foto no reconocida.";

	if(!empty($badge)){
		$utarget = $this->telegram->reply_target('forward')->id;
		$points = badge_points($badge['type'], $utarget);

		if($amount < $points){
			$this->telegram->send
				->text($this->telegram->emoji(":times: ¡No puedes poner menos puntos de los que ya tienes! ($amount -> $points)"))
			->send();

			return -1;
		}elseif($amount == $points){
			$this->telegram->send
				->text($this->telegram->emoji(":ok: Ya estaba agregado."))
			->send();

			return -1;
		}

		$str = $this->telegram->emoji(":warning: Error al agregar " .$badge['name']. ".");
		$q = badge_register($badge, $amount, $utarget);
		if($q){
			$str = $this->telegram->emoji(":ok: Registro " .$badge['name'] ." a " .$amount ."!");
		}
	}

	$this->telegram->send
		->text($str)
	->send();

	return -1;
}elseif($telegram->text_command("badges") or $telegram->text_has("mis medallas")){
	$badges = pokemon_badges();
	$utarget = $this->telegram->user->id;
	if($this->telegram->user->id == $this->config->item('creator') && $this->telegram->has_reply){
		$utarget = $this->telegram->reply_target('forward')->id;
	}

	$user_badges = badges_list($utarget);

	$str = "No tienes medallas.";
	if(!empty($user_badges)){
		$str = "";

		$icons = ['\u2796', '\ud83e\udd49', '\ud83e\udd48', '\ud83e\udd47', '\ud83c\udf96', '\ud83d\udc8e'];
		$max = max(array_values($user_badges));
		$max = strlen($max);

		foreach($badges as $badge){
			$n = 0;
			$value = $user_badges[$badge['type']];
			if(empty($value)){ continue; }

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

elseif($telegram->text_has("ranking") && $telegram->text_has(["medallas", "badges"]) && $telegram->words() <= 7){
	if($telegram->text_has(["grupo", "grupal", "aqui", "canal"]) && $telegram->is_chat_group()){
		$ranking = badges_max_ranking($this->telegram->chat->id);
	}else{
		$ranking = badges_max_ranking();
	}
	$icons = ['\u2796', '\ud83e\udd49', '\ud83e\udd48', '\ud83e\udd47', '\ud83c\udf96', '\ud83d\udc8e'];
	$str = "";

	$maxlen = strlen(max(array_column($ranking, 'value')));

	foreach($ranking as $b){
		$n = 0;
		if(empty($b['value'])){ continue; }

		$badge = pokemon_badges($b['type']);

		foreach($badge['targets'] as $min){
			if($b['value'] >= $min){ $n++; }
		}

		$str .= $this->telegram->emoji($icons[$n])
			." <code>" .str_pad($b['value'], $maxlen, ' ', STR_PAD_LEFT) ." </code> "
			.$badge['name'] ." - "
			.$b['username']
			."\n";
	}

	$this->telegram->send
		->text($str, 'HTML')
	->send();

	return -1;
}


?>
