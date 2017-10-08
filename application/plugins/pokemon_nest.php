<?php

function get_paired_groups($groups){
    $CI =& get_instance();
	$groups = explode(",", $groups);
	$query = $CI->db
		->group_start()
			->like('type', 'pair_team_')
			->where_in('value', $groups)
		->group_end()
		/* ->or_group_start()
			->where('type', 'common');
		->group_end() */
	->get('settings');

    $target = array();
	if($query->num_rows() > 0){
		foreach($query->result_array() as $g){
			$target[] = $g['uid'];
		}
	}
    return $target;
}

function check_reset_nest(){
	$CI =& get_instance();
	$query = $CI->db
		->select('register_date')
		->where('register_date IS NOT NULL')
		->where('active', TRUE)
		->order_by('register_date', 'ASC')
		->limit(1)
	->get('pokemon_nests');
	if($query->num_rows() == 0){ return FALSE; }
	$date = date("Y-m-d", strtotime($query->row()->register_date));
	$date = strtotime($date); // Sacar las 00:00 del día.

	$ret = (
		date("N") == 4 and // Jueves
		time() >= strtotime("+9 days", $date)
	);

	if($ret){
		$CI->db
			->set('active', FALSE)
			->where('active', TRUE)
		->update('pokemon_nests');
	}

	return $ret;
}

// if(!$this->telegram->text_contains("nido")){ return; } // HACK TODO Cambiar frases para la gente?

if(
    (
		$telegram->text_has(["donde", "conocéis", "sabéis", "sabe", "cual"]) &&
	    $telegram->text_contains(["visto", "encontra", "encuentro", "está", "aparece", "hay", "salen", "sale", "nido"]) && $telegram->text_contains("?") &&
	    $telegram->words() <= 10
	) or (
		$telegram->text_has("Y", TRUE) and
		$telegram->text_contains("?") and
		$telegram->words() <= 3 and
		$pokemon->settings($telegram->user->id, 'last_command') == "POKEMON_FIND_LOCATION"
	)
){
	if(!$telegram->is_chat_group()){
		$this->telegram->send
			->text("Pregúntalo en un grupo.")
		->send();
		return -1;
	}

	$adminchat = $this->pokemon->settings($this->telegram->chat->id, 'admin_chat');
	if(
		$adminchat and
		!$this->pokemon->settings($this->telegram->chat->id, 'disable_nest_log')
	){
		$chatinfo = $this->db
			->where('uid', $this->telegram->user->id)
			->where('cid', $this->telegram->chat->id)
			->limit(1)
		->get('user_inchat');

		$chatinfo = $chatinfo->row();
		$user = $this->pokemon->user($this->telegram->user->id);

		if($chatinfo and $user){
			$verified = $this->telegram->emoji($user->verified ? ":green-check:" : ":warning:");
			$time = time() - strtotime($chatinfo->register_date);
			$i = 0;
			$timestr = "";
			if($time >= 86400){
				$i = floor($time / 86400);
				$timestr .= $i ."d ";
				$time = $time - ($i * 86400);
			}
			if($time >= 3600){
				$i = floor($time / 3600);
				$timestr .= $i ."h ";
			}
			$timestr = trim($timestr);
			$str = $verified ." " .$user->telegramid ." @" .$user->username ." pide nidos.\n"
					.$chatinfo->messages ." - " .$timestr;

			$this->telegram->send
				->notification(FALSE)
				->chat($adminchat)
				->text($str)
			->send();
		}
	}

    if($pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'gps', 'hacks', 'multiaccount', 'spam'])){ return -1; }
	$pkuser = $pokemon->user($telegram->user->id);
	if(!$pkuser->verified){ return -1; }
    $text = str_replace("?", "", $telegram->text());
    $pk = pokemon_parse($text);
    if(empty($pk['pokemon'])){ return; }

    $groups = $pokemon->settings($telegram->chat->id, 'pair_groups');
    $target = array();
    if($groups){
        $target = get_paired_groups($groups);
    }
    $target[] = $this->telegram->chat->id;
    $target = array_unique($target);

    $query = $this->db
        ->where_in('chat', $target)
		->where('active', TRUE)
    ->get('pokemon_nests');

    if($query->num_rows() == 0){
		if(!empty($telegram->callback)){
			$telegram->answer_if_callback("No hay ningún nido registrado.", TRUE);
			return -1;
		}
        $telegram->send->text("No hay ningún nido registrado.")->send();
        return -1;
    }

	$adminchat = $this->pokemon->settings($this->telegram->chat->id, 'admin_chat');
	if(
		$adminchat and
		!$this->pokemon->settings($this->telegram->chat->id, 'disable_nest_log')
	){
		$chatinfo = $this->db
			->where('uid', $this->telegram->user->id)
			->where('cid', $this->telegram->chat->id)
			->limit(1)
		->get('user_inchat');

		$chatinfo = $chatinfo->row();
		$user = $this->pokemon->user($this->telegram->user->id);

		if($chatinfo and $user){
			$verified = $this->telegram->emoji($user->verified ? ":green-check:" : ":warning:");
			$time = time() - strtotime($chatinfo->register_date);
			$i = 0;
			$timestr = "";
			if($time >= 86400){
				$i = floor($time / 86400);
				$timestr .= $i ."d ";
				$time = $time - ($i * 86400);
			}
			if($time >= 3600){
				$i = floor($time / 3600);
				$timestr .= $i ."h ";
			}
			$timestr = trim($timestr);
			$str = $verified ." " .$user->telegramid ." @" .$user->username ." pide nidos.\n"
					.$chatinfo->messages ." - " .$timestr;

			$this->telegram->send
				->notification(FALSE)
				->chat($adminchat)
				->text($str)
			->send();
		}
	}

	// Registrar el último comando, independientemente de su resultado.
	$pokemon->settings($telegram->user->id, 'last_command', 'POKEMON_FIND_LOCATION');

	$uinfo = $pokemon->user_in_group($telegram->user->id, $telegram->chat->id);
	$utime = strtotime($uinfo->register_date);

	if($uinfo->messages <= 7 or strtotime("+4 days", $utime) > time() && $this->telegram->user->id != $this->config->item('creator')){
		$str = "¿Acabas de llegar y ya estás pidiendo nidos? Te calmas.";
		if($telegram->callback){
			$telegram->answer_if_callback($str, TRUE);
		}else{
			$telegram->send
				->text($str)
			->send();
		}
		return -1;
	}

    $query = $this->db
        ->where_in('chat', $target)
        ->where('pokemon', $pk['pokemon'])
		->where('active', TRUE)
    ->get('pokemon_nests');

    if($query->num_rows() == 0){
		$frases = [
			'Aún no han encontrado a ese Pokémon por aquí cerca...',
			'No lo sé.',
			'Pues no tengo ni idea.',
			'No soy adivino, si no lo sabéis ni vosotros... Yo no me voy a poner a buscarlo.',
			'A saber.',
			'Sinceramente, ni lo sé, ni me preocupa.',
			'Ese Pokémon? Aún no lo sé.'
		];

		if($telegram->text_has("Y", TRUE)){
			$frases = [
				'Tampoco lo sé.',
				'Nope.',
				'Ni idea.',
				'Meh.',
				'No estoy seguro.'
			];
		}

		$n = mt_rand(0, count($frases) - 1);
        $telegram->send->text($frases[$n])->send();
        return -1;
    }elseif($query->num_rows() == 1){
        $res = $query->row();
        if(!empty($res->lat) && !empty($res->lng)){
            $telegram->send
                ->location($res->lat, $res->lng)
            ->send();
        }
        $frases = [
            'Sólo lo he visto en',
            'Prueba a buscar en',
            'Mira a ver si te sale en',
            'Lo he visto en',
            'Tal vez lo encuentres en'
        ];
        $n = mt_rand(0, count($frases) - 1);
        $telegram->send->text($frases[$n] ." " .$res->location_string .".")->send();
    }elseif($query->num_rows() > 1){
        $str = "Está en varios lugares:\n";
        foreach($query->result_array() as $res){
            $str .= "- " .$res['location_string'] ."\n";
        }
        $telegram->send->text($str)->send();
    }

    // $telegram->send->text("El #" .$pk['pokemon'] ." dices? Aún no lo sé.")->send();
    return -1;
}

elseif(
	$telegram->is_chat_group() &&
	$telegram->text_has(["sale", "sale algo", "que hay", "que nidos hay", "algun nido", "hay nidos", "hay nido"], "en") &&
	$telegram->text_contains("?") &&
	$telegram->words() >= 4
){
    if($pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'gps', 'hacks', 'multiaccount', 'spam'])){ return; }
	$pkuser = $pokemon->user($telegram->user->id);
	if(!$pkuser->verified){ return -1; }
	$txt = $telegram->text(TRUE);
	$txt = substr($txt, strpos($txt, " en ") + strlen(" en "));
	$txt = trim($txt);
	if(in_array(substr($txt, 0, 2), ['el', 'la'])){
		$txt = substr($txt, 3);
		$txt = trim($txt);
	}
	if(strlen($txt) < 4){ return; }

    $groups = $pokemon->settings($telegram->chat->id, 'pair_groups');
    $target = array();
    if($groups){
        $target = get_paired_groups($groups);
    }
    $target[] = $this->telegram->chat->id;
    $target = array_unique($target);

	$query = $this->db
		->where_in('chat', $target)
		->like('location_string', $txt)
		->where('active', TRUE)
	->get('pokemon_nests');

	$frases = [
		'Pues no he visto nada por ahí.',
		'No lo sé.',
		'Creo que no.',
		'No estoy muy seguro...'
	];
	$n = mt_rand(0, count($frases) - 1);
	$str = $frases[$n];

	if($query->num_rows() > 0){
		$pokedex = $pokemon->pokedex();
		if($query->num_rows() == 1){
			$str = "He visto un " .$pokedex[$query->row()->pokemon]->name ." en " .$query->row()->location_string .".";
		}else{
			$str = "Hay varios Pokémon por ahí:\n";
			foreach($query->result_array() as $nest){
				$str .= "- " .$pokedex[$nest['pokemon']]->name ." en " .$nest['location_string'] ."\n";
			}
		}
	}

	$telegram->send->text($str, 'HTML')->send();
	return -1;
}

elseif(
    $telegram->text_has(["confirmo", "confirmar", "confirmado", "hay"], ["nido", "un nido", "el nido"], TRUE) &&
    $telegram->text_has(["en", "entre", "delante", "enfrente", "frente"]) &&
    $telegram->is_chat_group()
){
    $pkuser = $pokemon->user($telegram->user->id);

	$uinfo = $pokemon->user_in_group($telegram->user->id, $telegram->chat->id);
	$utime = strtotime($uinfo->register_date);
    if(
		// Si no es admin
		(!in_array($telegram->user->id, telegram_admins(TRUE)) ) and (
			// El usuario debe estar validado,
			!$pkuser->verified or
			// tiempo mínimo de registro 1 semana,
			(strtotime("+7 days", strtotime($pkuser->register_date)) > time()) or
			// tiempo mínimo de grupo 7 mensajes y 4 días,
			($uinfo->messages <= 7 or strtotime("+4 days", $utime) > time()) or
			// y no hacer trampas.
			$pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'troll_nest', 'spam'])
		)
	){ return -1; }

    $text = $telegram->text();
    $pk = pokemon_parse($text);

    if(empty($pk['pokemon'])){ return; }

    $pos = strpos($text, " en ") + strlen(" en ");
    $loc = trim(substr($text, $pos));
    if(strlen($loc) < 4 or strlen($loc) > 200){ return; }

    $this->db
        ->set('user', $telegram->user->id)
        ->set('chat', $telegram->chat->id)
        ->set('pokemon', $pk['pokemon'])
        ->set('location_string', $loc)
        ->set('register_date', date("Y-m-d H:i:s"))
		->set('active', TRUE)
    ->insert('pokemon_nests');

    $telegram->send->text($telegram->emoji(":ok:") . " ¡Registrado!")->send();
	return -1;
    // $telegram->send->text("Recibo #" .$pk['pokemon'] . " en " . substr($text, $pos))->send();
}

elseif(
    $telegram->has_reply &&
    $telegram->text_has("ahí hay") && $telegram->text_has("nido") &&
    $telegram->is_chat_group()

){
    if(!isset($telegram->reply->location)){ return; }

    // TODO el usuario debe estar validado y tiempo mínimo de registro 2 semanas.
    $pkuser = $pokemon->user($telegram->user->id);
    if(!$pkuser->verified){ return; }
    if($pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'spam'])){ return; }

    $text = $telegram->text();
    $pk = pokemon_parse($text);

    if(empty($pk['pokemon'])){ return; }

    $loc = $telegram->reply->location;

    $this->db
        ->set('user', $telegram->user->id)
        ->set('chat', $telegram->chat->id)
        ->set('pokemon', $pk['pokemon'])
        ->set('lat', $loc['latitude'])
        ->set('lng', $loc['longitude'])
        ->set('register_date', date("Y-m-d H:i:s"))
		->set('active', TRUE)
    ->insert('pokemon_nests');

    $telegram->send->text($telegram->emoji(":ok:") . " ¡Registrado!")->send();
    return -1;
}

elseif($telegram->text_contains("lista") && $telegram->text_contains("nido") && $telegram->words() <= 8){
    if($pokemon->user_flags($telegram->user->id, ['ratkid', 'no_nest', 'troll', 'gps', 'hacks', 'multiaccount', 'spam'])){ return -1; }
	if(!$telegram->callback && !$telegram->is_chat_group()){
		$telegram->send
			->text("Pídemelos por el grupo. Si no, no sé qué nidos quieres.")
		->send();
		return -1;
	}
	$uinfo = $pokemon->user_in_group($telegram->user->id, $telegram->chat->id);
	$utime = strtotime($uinfo->register_date);

	$adminchat = $this->pokemon->settings($this->telegram->chat->id, 'admin_chat');
	if(
		$adminchat and
		!$this->pokemon->settings($this->telegram->chat->id, 'disable_nest_log')
	){
		$chatinfo = $this->db
			->where('uid', $this->telegram->user->id)
			->where('cid', $this->telegram->chat->id)
			->limit(1)
		->get('user_inchat');

		$chatinfo = $chatinfo->row();
		$user = $this->pokemon->user($this->telegram->user->id);

		if($chatinfo and $user){
			$verified = $this->telegram->emoji($user->verified ? ":green-check:" : ":warning:");
			$time = time() - strtotime($chatinfo->register_date);
			$i = 0;
			$timestr = "";
			if($time >= 86400){
				$i = floor($time / 86400);
				$timestr .= $i ."d ";
				$time = $time - ($i * 86400);
			}
			if($time >= 3600){
				$i = floor($time / 3600);
				$timestr .= $i ."h ";
			}
			$timestr = trim($timestr);
			$str = $verified ." " .$user->telegramid ." @" .$user->username ." pide nidos.\n"
					.$chatinfo->messages ." - " .$timestr;

			$this->telegram->send
				->notification(FALSE)
				->chat($adminchat)
				->text($str)
			->send();
		}
	}

	if($uinfo->messages <= 7 or strtotime("+4 days", $utime) > time() && $this->telegram->user->id != $this->config->item('creator')){
		$str = "¿Acabas de llegar y ya estás pidiendo nidos? Te calmas.";
		if($telegram->callback){
			$telegram->answer_if_callback($str, TRUE);
		}else{
			$telegram->send
				->text($str)
			->send();
		}
		return -1;
	}

    $target = (is_numeric(str_replace("-", "", $telegram->last_word())) ? $telegram->last_word() : $telegram->chat->id);

    if($telegram->user->id == $this->config->item('creator')){
     //   $telegram->send->text($target)->send();
    }

    $groups = $pokemon->settings($telegram->chat->id, 'pair_groups');
    if($groups){
        $target = get_paired_groups($groups);
        $target[] = $this->telegram->chat->id;
        $target = array_unique($target);
    }

    $query = $this->db
        ->where_in('chat', $target)
		->where('active', TRUE)
        ->order_by('pokemon', 'ASC')
    ->get('pokemon_nests');

    if($query->num_rows() == 0){
		$str = "No hay ningún nido registrado.";
		if($telegram->callback){
			$telegram->answer_if_callback($str, TRUE);
		}else{
			$telegram->send->text($str)->send();
		}
        return -1;
    }

    $pokedex = $pokemon->pokedex();

	if($this->telegram->text_has(["completa", "entera", "full"])){
		if(strtotime("+14 days", $utime) > time() && $this->telegram->user->id != $this->config->item('creator')){
			$frases = [
				'Lo siento granujilla, pero esto es para los que llevan tiempo.',
				'Aún no te conocen por aquí. Avaricioso.',
				'Lo siento. No confío en los nuevos.',
				'Esperate unos días que me lo piense.'
			];
			$str = $frases[mt_rand(0, count($frases) - 1)];
			if($telegram->callback){
				$telegram->answer_if_callback($str, TRUE);
			}else{
				$telegram->send
					->text($str)
				->send();
			}
			return -1;
		}

		$str = "Lista de nidos:\n";
	    foreach($query->result_array() as $nest){
	        $str .= "- " .$pokedex[$nest['pokemon']]->name ." " .(empty($nest['location_string']) ? "en ubicación." : "en " .$nest['location_string'])  ."\n";
	    }

		// Si hay más de 12, mandarlo por privado.
	    if($query->num_rows() > 12 or $telegram->callback){
	        $telegram->send->chat($telegram->user->id);
	    }

		$q = $telegram->send->text($str, 'HTML')->send();

		$str = ($q === FALSE ? "Ábreme por privado primero." : "");
	    $telegram->answer_if_callback($str, TRUE);

	    // Avisar por grupo.
	    if($query->num_rows() > 12 && !$telegram->callback){
	        $str = "He contado <b>" .$query->num_rows() ."</b> nidos. Te los mando por privado!\n"
	                ."¿Alguien más los quiere ver?";
	        $telegram->send
	            ->inline_keyboard()
	                ->row_button("Yo!", "lista de nidos completa", "TEXT")
	            ->show()
	            ->text($str, 'HTML')
	        ->send();
	    }
	}else{
		$str = "Hay <b>" .$query->num_rows() ."</b> nidos de:\n";
		$pokenames = array();
	    foreach($query->result_array() as $nest){
			$pokenames[] = $pokedex[$nest['pokemon']]->name;
	    }
		$pokenames = array_unique($pokenames);
		$str .= implode(", ", $pokenames) .".";

		$q = $telegram->send->text($str, 'HTML')->send();
	}

	if(check_reset_nest()){
		$str = ":ok: ¡Lista de nidos reiniciada!\n¡A por todos!";
		$this->telegram->send
			->notification(TRUE)
			->chat("-1001089222378") // Canal @ProfesorOakNews
			->text($this->telegram->emoji($str))
		->send();
	}

    return -1;
}

elseif($telegram->text_contains("nido") && $telegram->text_has(["borra", "borrar"])){
	if($telegram->text_has("mis nidos")){
		$this->db
			->where('chat', $telegram->chat->id)
			->where('user', $telegram->user->id);
	}elseif($telegram->text_has("nido de") && $telegram->text_has("en")){
		$pk = pokemon_parse($telegram->text());
		if(empty($pk['pokemon'])){
			$telegram->send
				->text($telegram->emoji(":times: ¿De qué Pokémon dices?"))
			->send();
			return -1;
		}
		$where = substr($telegram->text(), strpos($telegram->text(), " en ") + strlen(" en "));
		$this->db
			->where('chat', $telegram->chat->id)
			->where('pokemon', $pk['pokemon'])
			->like('location_string', $where);
	}elseif($telegram->text_has(["nido", "nidos"], "de")){
		$allow = [$this->config->item('creator')];
		if(!in_array($telegram->user->id, $allow)){
			$telegram->send
				->text("Buen intento. -.-")
			->send();
			return -1;
		}

		$pk = pokemon_parse($telegram->text());
		if(!empty($pk['pokemon'])){
			$this->db
				->where('chat', $telegram->chat->id)
				->where('pokemon', $pk['pokemon']);
		}
		// TODO si no, de usuario
	}elseif($telegram->text_has("sus nidos") && $telegram->has_reply){
		$allow = [$this->config->item('creator')];
		if(!in_array($telegram->user->id, $allow)){
			$telegram->send
				->text("Buen intento. -.-")
			->send();
			return -1;
		}

		if(isset($telegram->reply->forward_from)){
			$target = $telegram->reply->forward_from['id'];
		}else{
			$target = $telegram->reply_user->id;
		}

		$this->db
			->where('chat', $telegram->chat->id) // HACK todos los chats o sólo el activo?
			->where('user', $target);
	}elseif($telegram->text_has(["toda la lista", "todos los nidos"])){
		$allow = [$this->config->item('creator')];
		if(!in_array($telegram->user->id, $allow)){
			$telegram->send
				->text("Buen intento. -.-")
			->send();
			return -1;
		}
		$this->db
			->where('chat', $telegram->chat->id);
	}elseif(is_numeric($telegram->words(2))){
		$allow = [$this->config->item('creator')];
		if(!in_array($telegram->user->id, $allow)){
			$telegram->send
				->text("Buen intento. -.-")
			->send();
			return -1;
		}

		// TODO Jugar con rangos 1-3 1,2,3 ?

		$this->db->where('id', $telegram->words(2));
	}else{
		$telegram->send
			->text("No te entiendo.")
		->send();
		return -1;
	}

	$query = $this->db
		->select('id')
	->get('pokemon_nests');

	if($query->num_rows() >= 100){
		$telegram->send
			->text($telegram->emoji(":times: ¡Son demasiados!"))
		->send();
		return -1;
	}

	$ids = array_column($query->result_array(), 'id');
	if(empty($ids)){
		$telegram->send
			->text("No encuentro coincidencias.")
		->send();
		return -1;
	}

	$query = $this->db
		->where_in('id', $ids)
	->delete('pokemon_nests');

	$str = ":times: Error general.";

	if($query){
		$str = ":ok: *" .count($ids) ."* nidos borrados.";
	}

	$telegram->send
		->text($telegram->emoji($str), TRUE)
	->send();

	return -1;
}

?>
