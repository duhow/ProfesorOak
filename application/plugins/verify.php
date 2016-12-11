<?php

if($telegram->text_command("register")){
    $pokeuser = $pokemon->user($telegram->user->id);
    if($pokeuser->verified){ return; }

    $telegram->send
        ->text($telegram->emoji(":warning:") ." ¿Entiendo que quieres *validarte*?", TRUE)
        ->inline_keyboard()
            ->row_button("Validar", "quiero validarme", TRUE)
        ->show()
    ->send();
    return;
}

if($telegram->text_has("Te valido", TRUE) && $telegram->words() <= 3){
    $pokeuser = $pokemon->user($telegram->user->id);
    if(!$pokeuser->authorized){ return; }
    $target = NULL;
    if($telegram->words() == 2 && $telegram->has_reply){
        $target = $telegram->reply_user->id;
        if($telegram->reply_is_forward && $telegram->reply_user->id != $telegram->reply->forward_from->id){
            $target = $telegram->reply->forward_from['id'];
        }
    }elseif($telegram->words() == 3){
        $target = $telegram->last_word(TRUE);
        if($target[0] == "@"){ $target = substr($target, 1); }
        $target = $pokemon->find_users($target);
        if($target == FALSE or count($target) > 1){ return; }
        $target = $target[0]['telegramid'];
    }

    if($pokemon->user_verified($target)){
        $telegram->answer_if_callback($telegram->emoji("¡Ya está validado! :ok:"), TRUE);
        $telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->text($telegram->text_message() .$telegram->emoji(" :ok:"))
        ->edit('text');
        return;
    }
    if($pokemon->verify_user($telegram->user->id, $target)){
        $telegram->send
            ->notification(FALSE)
            ->text( $telegram->emoji(":green-check:") )
        ->send();

        if($telegram->callback){
            $telegram->answer_if_callback("¡De acuerdo, validado!");
            $telegram->send
                ->message(TRUE)
                ->chat(TRUE)
                ->text($telegram->text_message() .$telegram->emoji(" :ok:"))
            ->edit('text');
        }

        $telegram->send
            ->notification(TRUE)
            ->chat($target)
            ->text("Enhorabuena, estás validado! " .$telegram->emoji(":green-check:"))
        ->send();

        if($pokemon->step($target) == "SCREENSHOT_VERIFY"){
            $pokemon->step($target, NULL);
        }
    }
}

// Validar usuario
elseif(
    $telegram->text_contains(["oak", "profe", "quiero", "como"]) &&
    $telegram->text_contains(["validame", "valida", "validarme", "validarse", "válido", "verificarme", "verifico"]) &&
    $telegram->words() <= 7
){
    if($telegram->is_chat_group()){
        $res = $telegram->send
            ->notification(TRUE)
            ->chat($telegram->user->id)
            ->text("Hola, " .$telegram->user->first_name ."!")
        ->send();

        if(!$res){
            $telegram->send
                ->notification(FALSE)
                // ->reply_to(TRUE)
                ->text($telegram->emoji(":times: Pídemelo por privado, por favor."))
                ->inline_keyboard()
                    ->row_button("Validar perfil", "quiero validarme", TRUE)
                ->show()
            ->send();
            return;
        }
    }

    $pokeuser = $pokemon->user($telegram->user->id);

    if($pokeuser->verified){
        $telegram->send
            ->notification(TRUE)
            ->chat($telegram->user->id)
            ->text("¡Ya estás verificado! " .$telegram->emoji(":green-check:"))
        ->send();
        return;
    }

    $text = "Para validarte, necesito que me envies una *captura de tu perfil Pokemon GO.* "
            ."La captura tiene que cumplir las siguientes condiciones:\n\n"
            .":triangle-right: Tiene que verse la hora de tu móvil, y tienes que enviarlo en un márgen de 5 minutos.\n"
            .":triangle-right: Tiene que aparecer tu nombre de entrenador y color.\n"
            .":triangle-right: Si te has cambiado de nombre, avisa a @duhow para tenerlo en cuenta.\n"
            .":triangle-right: Si no tienes nombre puesto, *cancela el comando* y dime cómo te llamas.\n"
            ."\nCuando haya confirmado la validación, te avisaré por aquí.\n\n"
            ."Tus datos son: ";

    $color = ['Y' => ':heart-yellow:', 'R' => ':heart-red:', 'B' => ':heart-blue:'];

    $text .= (empty($pokeuser->username) ? "Sin nombre" : "@" .$pokeuser->username) ." L" .$pokeuser->lvl ." " .$color[$pokeuser->team];

    $telegram->send
        ->notification(TRUE)
        ->chat($telegram->user->id)
        ->text($telegram->emoji($text), TRUE)
        ->keyboard()
            ->row_button("Cancelar")
        ->show(TRUE, TRUE)
    ->send();

    if(empty($pokeuser->username) or $pokeuser->lvl == 1){
        $text = "Antes de validarte, necesito saber tu *nombre y/o nivel actual* según lo que me falta por saber. Dime línea por línea (*no lo escribas todo junto, son DOS mensajes separados*):\n"
                .":triangle-right: *Me llamo ...*\n"
                .":triangle-right: *Soy nivel ...*\n"
                ."Una vez hecho, vuelve a preguntarme para validarte el perfil.";
        $telegram->send
            ->notification(TRUE)
            ->chat($telegram->user->id)
            ->text($telegram->emoji($text), TRUE)
            ->keyboard()->hide(TRUE)
        ->send();
        exit(); // Kill process for STEP
    }

    $pokemon->step($telegram->user->id, 'SCREENSHOT_VERIFY');
    return;
}


?>
