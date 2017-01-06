<?php

function meeting_text($meeting){
    $pokemon = new Pokemon();

    $text = "Invitación al evento del *" .date("d/m", strtotime($meeting->date_event))  ."* a las *" .date("H:i", strtotime($meeting->date_event)) ."*.\n";
    $text .= "Organizado por @" .$pokemon->user($meeting->creator)->username ."\n";
    if(substr($meeting->location, 0, 1) == '"'){
        $text .= "Ubicación: " .json_decode($meeting->location);
    }else{
        $text .= "Ubicación por posición enviada.";
    }
    $count = $pokemon->meeting_members_count($meeting->id);
    $text .= "\nVan a ir *$count* personas.\n";
    $text .= "¿Te apuntas?";
    return $text;
}

// -----------------
if($pokemon->step($telegram->user->id) == "MEETING_LOCATION"){
	$loc = NULL;
	// if(!$telegram->has_reply or $telegram->reply_user->id != $this->config->item('telegram_bot_id')){ return; }
	if($telegram->location()){
		$loc = $telegram->location()->latitude ."," .$telegram->location()->longitude;
	}elseif($telegram->text()){
		$loc = $telegram->text_encoded();
	}
	if($loc != NULL){
		$date = $pokemon->settings($telegram->user->id, 'meeting_date');
		$private = $pokemon->settings($telegram->user->id, 'meeting_private');
		if($date == NULL or $private === NULL){
			$telegram->send->text("Error general, cancelando acción.")->send();
		}else{
			$date = unserialize($date);
			$date = date("Y-m-d H:i:s", strtotime($date['date'] ." " .$date['hour']));
			$meeting = $pokemon->meeting_create($telegram->user->id, $date, $loc, $private);
			if($meeting){
				$chat = ($private ? $telegram->user->id : $telegram->chat->id);
				$text = "Para invitar a la gente envíales la siguiente clave:";
				if($private){ $text = "Te envío la clave por privado."; }
				$telegram->send
					->notification(TRUE)
					->text("De acuerdo, ¡quedada creada!\n$text")
				->send();
				$telegram->send
					->notification(TRUE)
					->chat($chat)
					->text("Quedada *$meeting*", TRUE)
				->send();
			}
		}

		$pokemon->settings($telegram->user->id, 'meeting_date', "DELETE");
		$pokemon->settings($telegram->user->id, 'meeting_private', "DELETE");
		$pokemon->step($telegram->user->id, NULL);
		return -1;
	}
}

// --------------

if($telegram->text_has("quedada", TRUE) && $telegram->words() == 2){
    $meeting = $pokemon->meeting($telegram->last_word('alphanumeric'));
    if(!$meeting){
        $telegram->send
            ->notification(FALSE)
            ->text($telegram->emoji(":times: Invitación no válida."))
        ->send();
        return;
    }
    // Enviar location.
    if(substr($meeting->location, 0, 1) != '"'){
        $loc = explode(",", $meeting->location);
        $telegram->send
            ->location($loc)
        ->send();
    }
    $telegram->send
        ->text(meeting_text($meeting), TRUE)
        ->inline_keyboard()
            ->row()
                ->button($telegram->emoji(":ok:"), "Asistiré a " .$meeting->joinkey, "TEXT")
                ->button($telegram->emoji(":times:"), "No asistiré a " .$meeting->joinkey, "TEXT")
                ->button($telegram->emoji(":question-grey:"), "Ver si asistiré a " .$meeting->joinkey, "TEXT")
            ->end_row()
        ->show()
    ->send();
    return;
}

// Marcar asistencia a la quedada
elseif($telegram->text_has("asistiré a") && $telegram->words() <= 5){
    // Inline Button Text
    $join = !($telegram->text_has("no asistiré"));
    $meeting = $pokemon->meeting($telegram->last_word(TRUE));
    if(!$meeting){
        if($telegram->answer_if_callback($telegram->emoji(":times: Invitación no válida."), TRUE)){ return; }
        $telegram->send
            ->notification(FALSE)
            ->text($telegram->emoji(":times: Invitación no válida."))
        ->send();
        return;
    }

    $str = "Bueno, una pena... ¡Siempre estás a tiempo de volver a apuntarte!";
    if($join){ $str = "¡Genial! ¡Te espero allí! ^^"; }

    if(!$telegram->text_has("Ver si asistiré")){
        $pokemon->meeting_join($telegram->user->id, $meeting->id, $join);
    }else{
        $meet = $pokemon->meeting_member($meeting->id, $telegram->user->id);
        if($meet === NULL){ $str = "No has dicho si vas a venir o no."; }
        elseif($meet == TRUE){ $str = "Has dicho que vas a venir. :ok:"; }
        elseif($meet == FALSE){ $str = "Has dicho que NO vas a venir. :times:"; }
    }

    $amount = $pokemon->meeting_members_total($meeting->id);

    $telegram->send
        ->message(TRUE)
        ->chat(TRUE)
        ->text(meeting_text($meeting), TRUE)
        ->inline_keyboard()
            ->row()
                ->button($telegram->emoji(":ok: $amount[1]"), "Asistiré a " .$meeting->joinkey, "TEXT")
                ->button($telegram->emoji(":times: $amount[0]"), "No asistiré a " .$meeting->joinkey, "TEXT")
                ->button($telegram->emoji(":question-grey:"), "Ver si asistiré a " .$meeting->joinkey, "TEXT")
            ->end_row()
        ->show()
    ->edit('text');

    // Editar mensaje con el contador nuevo.
    if($telegram->answer_if_callback($telegram->emoji($str), TRUE)){ return; }

    return;
}


elseif($telegram->text_has("Asistentes") && $telegram->words() <= 3){
    // TODO Asistentes a la quedada
}

elseif($telegram->text_has(["crear", "organizar", "hacer una", "hacemos una"], ["quedada", "reunión"])){
	$date = time_parse($telegram->text());
	if(isset($date['date'])){ // && $date['left_hours'] > 1
		$this->analytics->event("Telegram", "Create meeting");
		$private = ($telegram->text_has(["privada", "no pública"]));
		$rand = [
			"¡Me parece genial!",
			"¡Estupendo!",
		];
		$n = mt_rand(0, count($rand) - 1);
		$str = $rand[$n] ." Quedada ";
		$str .= ($private ? "*privada*" : "*pública*") ." ";
		if($date['left_hours'] <= 10 && $date['left_hours'] > 0){ $str .= "dentro de *" .$date['left_hours'] ." horas*.\n"; }
		elseif($date['left_minutes'] <= 60){ $str .= "dentro de *" .$date['left_minutes'] ." minutos*.\n"; }
		else{ $str .= "el *" .date("d/m", strtotime($date['date'])) ."* a las *" .date("H:i", strtotime($date['hour'])) ."*.\n"; }
		$str .= "Dime en qué lugar váis a quedar (escrito o ubicación).";

		$telegram->send
			->force_reply(TRUE)
			->text($str, TRUE)
		->send();

		$pokemon->settings($telegram->user->id, 'meeting_private', $private);
		$pokemon->settings($telegram->user->id, 'meeting_date', serialize($date));
		$pokemon->step($telegram->user->id, 'MEETING_LOCATION');
		return -1;
	}
}

?>
