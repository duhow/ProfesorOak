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

?>
