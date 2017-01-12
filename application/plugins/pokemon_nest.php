<?php

if(
    $telegram->text_has(["donde", "conocéis", "sabéis", "sabe", "cual", "listado", "lista"]) &&
    $telegram->text_contains(["visto", "encontra", "encuentro", "salen", "sale", "nido"]) && $telegram->text_contains("?") &&
    $telegram->words() <= 10 && $telegram->is_chat_group()
){
    $text = str_replace("?", "", $telegram->text());
    $pk = pokemon_parse($text);
    if(empty($pk['pokemon'])){ return; }

    $query = $this->db
        ->where('chat', $telegram->chat->id)
    ->get('pokemon_nests');

    if($query->num_rows() == 0){
		if(!empty($telegram->callback)){
			$telegram->answer_if_callback("No hay ningún nido registrado.", TRUE);
			return -1;
		}
        $telegram->send->text("No hay ningún nido registrado.")->send();
        return -1;
    }

    $query = $this->db
        ->where('chat', $telegram->chat->id)
        ->where('pokemon', $pk['pokemon'])
    ->get('pokemon_nests');

    if($query->num_rows() == 0){
        $telegram->send->text("Aún no han encontrado a ese Pokémon por aquí cerca...")->send();
        return -1;
    }elseif($query->num_rows() == 1){
        $res = $query->row();
        if(!empty($res->lat) && !empty($res->lng)){
            $telegram->send
                ->location($res->lat, $res->lng)
            ->send();
        }
        $telegram->send->text("Sólo lo encuentro en " .$res->location_string .".")->send();
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
    $telegram->text_has(["confirmo", "confirmar", "confirmado", "hay"], ["nido", "un nido", "el nido"], TRUE) &&
    $telegram->text_has("en") &&
    $telegram->is_chat_group()
){
    // TODO el usuario debe estar validado y tiempo mínimo de registro 2 semanas.
    $pkuser = $pokemon->user($telegram->user->id);
    if(!$pkuser->verified){ return; }
    if($pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'spam'])){ return; }

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
    ->insert('pokemon_nests');

    $telegram->send->text("¡Registrado! Muchas gracias!")->send();

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
    ->insert('pokemon_nests');

    $telegram->send->text("¡Registrado! Muchas gracias!")->send();
    return -1;
}

elseif($telegram->text_contains("lista") && $telegram->text_contains("nido") && $telegram->words() <= 8){
    if($pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'spam'])){ return; }

    $target = (is_numeric(str_replace("-", "", $telegram->last_word())) ? $telegram->last_word() : $telegram->chat->id);

    if($telegram->user->id == $this->config->item('creator')){
     //   $telegram->send->text($target)->send();
    }

    $groups = $pokemon->settings($telegram->chat->id, 'pair_groups');
    if($groups){
    	$groups = explode(",", $groups);
    	$query = $this->db
    		->group_start()
    			->like('type', 'pair_team_')
    			->where_in('value', $groups)
    		->group_end()
    		/* ->or_group_start()
    			->where('type', 'common');
    		->group_end() */
    	->get('settings');

    	if($query->num_rows() > 0){
            $target = [$target];
    		foreach($query->result_array() as $g){
    			$target[] = $g['uid'];
    		}
    	}
    }

    // $groups = [$target];
    // $groups[] = $target;
    // $groups = array_unique($groups);

    $query = $this->db
        ->where_in('chat', $target)
        ->order_by('pokemon', 'ASC')
    ->get('pokemon_nests');

    if($query->num_rows() == 0){
        $telegram->send->text("No hay ningún nido registrado.")->send();
        return -1;
    }

    $pokedex = $pokemon->pokedex();

    $str = "Lista de nidos:\n";
    foreach($query->result_array() as $nest){
        $str .= "- " .$pokedex[$nest['pokemon']]->name ." " .(empty($nest['location_string']) ? "en ubicación." : "en " .$nest['location_string'])  ."\n";
    }

    // Si hay más de 12, mandarlo por privado.
    if($query->num_rows() > 12 or $telegram->callback){
        $telegram->send->chat($telegram->user->id);
    }

    $telegram->send->text($str)->send();
    $telegram->answer_if_callback("");

    // Avisar por grupo.
    if($query->num_rows() > 12 && !$telegram->callback){
        $str = "He contado <b>" .$query->num_rows() ."</b> nidos. Te los mando por privado!\n"
                ."¿Alguien más los quiere ver?";
        $telegram->send
            ->inline_keyboard()
                ->row_button("Yo!", "lista de nidos", "TEXT")
            ->show()
            ->text($str, 'HTML')
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

		// Jugar con rangos 1-3 1,2,3 ?

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
