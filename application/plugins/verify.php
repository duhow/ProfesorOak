<?php

if($this->pokemon->step($telegram->user->id) == 'SCREENSHOT_VERIFY'){
	if(!$telegram->is_chat_group() && $telegram->photo() and !$telegram->has_forward){
		$userid = $this->telegram->user->id;
		$pokeuser = $pokemon->user($userid);
		if(empty($pokeuser->username) or $pokeuser->lvl == 1){
			$text = "Antes de validarte, necesito saber tu *";
			$add = array();
			if(empty($pokeuser->username)){ $add[] = "nombre"; }
			if($pokeuser->lvl == 1){ $add[] = "nivel actual"; }
			$text .= implode(" y ", $add) ."*.\n";

			if(empty($pokeuser->username)){ $text .= ":triangle-right: *Me llamo ...*\n"; }
			if($pokeuser->lvl == 1){ $text .= ":triangle-right: *Soy nivel ...*\n"; }

			$text .= "Cuando lo hayas dicho, vuelve a enviarme la captura de pantalla de tu *PERFIL de Pokémon GO*.";
			$telegram->send
				->notification(TRUE)
				->chat($userid)
				->text($telegram->emoji($text), TRUE)
				->keyboard()->hide(TRUE)
			->send();
			// $pokemon->step($userid, NULL);
			return -1;
		}

		if($pokemon->settings($this->config->item('creator'), 'disable_verify')){
			$this->telegram->send
				->chat($userid)
				->text($this->telegram->emoji(":clock: ") ."Lo siento, pero ahora mismo estoy muy saturado. Prueba en otro momento.\n¡Y recuerda mandarme una captura nueva del perfil donde se vea la hora!")
			->send();
			$pokemon->step($userid, NULL);
			return -1;
		}

		// Comprobar si ya hay otra imagen previamente en cola.
		$cooldown = $pokemon->settings($userid, 'verify_cooldown');
		if(!empty($cooldown) and $cooldown > time()){
			$this->telegram->send
				->chat($userid)
				->text($this->telegram->emoji(":warning: ") ."¡Para el carro! Ya me has mandado una foto. Esperate a que la compruebe, no me des más faena...")
			->send();
			$pokemon->step($userid, NULL);
			return -1;
		}

		// Comprobar si ya me ha mandado la misma foto.
		$images = $pokemon->settings($userid, 'verify_images');
		if(!empty($images)){
			$images = unserialize($images);
			if(in_array($this->telegram->photo(), array_values($images))){
				$this->telegram->send
					->chat($userid)
					->text($this->telegram->emoji(":times: ") ."¡Esta foto ya me la has mandado! Haz otra foto nueva, y asegúrate de que cumple los requisitos.")
				->send();
				return -1;
			}
		}

		if(!is_array($images)){ $images = array(); }
		$images[time()] = $this->telegram->photo();

		// Cooldown +18h
		$pokemon->settings($userid, 'verify_cooldown', (time() + 64800));
		$pokemon->settings($userid, 'verify_images', serialize($images));
		$pokemon->settings($userid, 'verify_id', $this->telegram->message_id);

		// Cola de validaciones
		// -----------------
		$data = [
			'photo' => $this->telegram->photo(),
			'telegramid' => $userid,
			'username' => $pokeuser->username,
			'team' => $pokeuser->team,
		];

		$this->db->insert('user_verify', $data);
		// -----------------

		/* $telegram->send
			->message(TRUE)
			->chat(TRUE)
			->forward_to("-197822813")
		->send();

		$telegram->send
			->notification(TRUE)
			->chat("-197822813")
			->text("Validar " .$userid ." @" .$pokeuser->username ." L" .$pokeuser->lvl ." " .$pokeuser->team)
			->inline_keyboard()
				->row()
					->button($telegram->emoji(":ok:"), "te valido " .$userid, "TEXT")
					->button($telegram->emoji(":times:"), "no te valido " .$userid, "TEXT")
				->end_row()
			->show()
		->send(); */

		$telegram->send
			->notification(TRUE)
			->chat($userid)
			->keyboard()->hide(TRUE)
			->text($this->telegram->emoji(":ok: ") ."¡Enviado correctamente! El proceso de validar puede tardar un tiempo. Ten paciencia, que últimamente se registra mucha gente y no doy abasto!")
		->send();

		$pokemon->step($userid, NULL);
		return -1;
	// Si la gente no sabe decir bien el nivel...
	}elseif(
		!$telegram->is_chat_group() and
		$telegram->text() and
		$telegram->words() == 1 and
		is_numeric($telegram->text())
	){
		$str = $telegram->text() . " que? Dime la frase bien.";
		$this->telegram->send
			->text($str)
		->send();
		return -1;
	}elseif(
		!$telegram->is_chat_group() and
		$telegram->text() and
		$telegram->words() == 2 and
		$telegram->text_has("Nivel", TRUE) and
		is_numeric($telegram->last_word())
	){
		$str = "¡Que me digas la frase bien!";
		$this->telegram->send
			->text($str)
		->send();
		return -1;
	}
}

if($telegram->text_command("register")){
	if($pokemon->command_limit("register", $telegram->chat->id, $telegram->message, 10)){ return -1; }

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

if($telegram->text_has(["Te valido", "No te valido"], TRUE) && $telegram->words() <= 4){
    $pokeuser = $pokemon->user($telegram->user->id);
    if(!$pokeuser->authorized){ return; }
    $target = NULL;
    if($telegram->words() == 2 && $telegram->has_reply){
        $target = $telegram->reply_user->id;
        if($telegram->reply_is_forward && $telegram->reply_user->id != $telegram->reply->forward_from->id){
            $target = $telegram->reply->forward_from['id'];
        }
    }elseif(in_array($telegram->words(), [3,4])){
        $target = $telegram->last_word(TRUE);
        if($target[0] == "@"){ $target = substr($target, 1); }
        $target = $pokemon->find_users($target);
        if($target == FALSE or count($target) > 1){ return; }
        $target = $target[0]['telegramid'];
    }

	$poketarget = $pokemon->user($target);
	$str = "Validar " .$poketarget->telegramid ." @" .$poketarget->username
			." L" .$poketarget->lvl ." " .$poketarget->team;
    if($pokemon->user_verified($target)){
        $telegram->answer_if_callback($telegram->emoji("¡Ya está validado! :ok:"), TRUE);
        $telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->text($str .$telegram->emoji(" :ok:"))
        ->edit('text');
        return;
    }

	$pokemon->settings($target, 'verify_cooldown', 'DELETE');

	if($telegram->text_has("no")){

		// Update en nueva tabla.
		$this->db
			->where('telegramid', $target)
			->where('status IS NULL')
			->set('status', 3) // REJECT
			->set('date_finish', date("Y-m-d H:i:s"))
		->update('user_verify');

		$telegram->send
            ->notification(TRUE)
            ->chat($target)
            ->text($telegram->emoji(":times: ") ."La validación no es correcta. Revisa la captura de pantalla, y envíala tal y como se pide.")
        ->send();

		if($telegram->callback){
            $telegram->answer_if_callback("");
            $telegram->send
                ->message(TRUE)
                ->chat(TRUE)
                ->text($str .$telegram->emoji(" :times:"))
            ->edit('text');
        }

		$pokemon->step($target, "SCREENSHOT_VERIFY");

		return -1;
	}

    if($pokemon->verify_user($telegram->user->id, $target)){
		// Update en nueva tabla.
		$this->db
			->where('telegramid', $target)
			->where('status IS NULL')
			->set('status', 1) // OK
			->set('date_finish', date("Y-m-d H:i:s"))
		->update('user_verify');

        $telegram->send
            ->notification(FALSE)
            ->text( $telegram->emoji(":green-check:") )
        ->send();

        if($telegram->callback){
            $telegram->answer_if_callback("¡De acuerdo, validado!");
            $telegram->send
                ->message(TRUE)
                ->chat(TRUE)
                ->text($str .$telegram->emoji(" :ok:"))
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
		if($pokemon->command_limit("validar", $telegram->chat->id, $telegram->message, 7)){ return -1; }

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
            return -1;
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

	/* $date = strtotime("+7 days", strtotime($pokeuser->register_date));
	if($date > time()){
		$timer = max(round(($date - time()) / 86400), 1);
		$text = ":clock: Debido a que bastantes personas acaban abandonando Telegram (por motivos que desconozco, porque es muy guay, pero bueno), tendrás que esperar <b>$timer días</b> para poder validarte."
				."\nTen paciencia. :)";
		$this->telegram->send
			->notification(TRUE)
			->chat($this->telegram->user->id)
			->text($this->telegram->emoji($text), 'HTML')
		->send();

		$this->pokemon->step($this->telegram->user->id, NULL);
		return -1;
	} */

    $text = "Para validarte, necesito que me envies <b>UNA captura de tu PERFIL Pokémon GO.</b> "
            ."La captura tiene que cumplir las siguientes condiciones:\n\n"
            .":clock: Tiene que verse la <b>HORA</b> de tu móvil, y tienes que enviarlo en un márgen de <b>6 minutos</b>. No valen capturas antiguas.\n"
            .":male: En tu <b>PERFIL</b> se tiene que ver el nombre de entrenador y color.\n"
			.":triangle-right: En tu <b>PERFIL</b> se tiene que ver que la <b>MASCOTA</b> se llame <b>Oak</b>. Luego puedes cambiarle el nombre.\n"
			.":triangle-up: Asegúrate de que el <b>NIVEL</b> es correcto. Se revisará igualmente, pero la validación se hace más rápida si el nivel está bien puesto.\n"
            // .":triangle-right: Si te has cambiado de nombre, avisa a @duhow para tenerlo en cuenta.\n"
            // .":triangle-right: Si no tienes nombre puesto, *cancela el comando* y dime cómo te llamas.\n"
            ."\nCuando haya confirmado la validación, te avisaré por aquí.\n\n"
            ."Tus datos son: ";

    $color = ['Y' => ':heart-yellow:', 'R' => ':heart-red:', 'B' => ':heart-blue:'];

    $text .= (strlen($pokeuser->username) < 4 ? "Sin nombre" : "@" .$pokeuser->username) ." L" .$pokeuser->lvl ." " .$color[$pokeuser->team];

    $telegram->send
        ->notification(TRUE)
        ->chat($telegram->user->id)
        ->text($telegram->emoji($text), "HTML")
        ->keyboard()
            ->row_button("Cancelar")
        ->show(TRUE, TRUE)
    ->send();

    if(empty($pokeuser->username) or $pokeuser->lvl == 1){
		$text = "Antes de validarte, necesito saber tu *";
		$add = array();
		if(empty($pokeuser->username)){ $add[] = "nombre"; }
		if($pokeuser->lvl == 1){ $add[] = "nivel actual"; }
		$text .= implode(" y ", $add) ."*.\n";

		if(empty($pokeuser->username)){ $text .= ":triangle-right: *Me llamo ...*\n"; }
		if($pokeuser->lvl == 1){ $text .= ":triangle-right: *Soy nivel ...*\n"; }

		$text .= "Cuando lo hayas dicho, enviame la captura de pantalla de tu *PERFIL de Pokémon GO*.";

        $telegram->send
            ->notification(TRUE)
            ->chat($telegram->user->id)
            ->text($telegram->emoji($text), TRUE)
            ->keyboard()->hide(TRUE)
        ->send();
		// $pokemon->step($telegram->user->id, NULL);
        return -1; // Kill process for STEP
    }

    $pokemon->step($telegram->user->id, 'SCREENSHOT_VERIFY');
    return;
}

elseif($telegram->text_command("ocrv") && $this->telegram->user->id == $this->config->item('creator') && $this->telegram->has_reply){
	if(!isset($this->telegram->reply->photo)){ return; }
	$photo = array_pop($this->telegram->reply->photo);
	$url = $this->telegram->download($photo['file_id']);

	$temp = tempnam("/tmp", "tgphoto");
	file_put_contents($temp, file_get_contents($url));

	$out = shell_exec("convert $temp +dither -posterize 2 -crop 20x20%+600+50 -define histogram:unique-colors=true -format %c histogram:info:-");

	$colors = ['Y' => 'yellow', 'R' => 'red', 'B' => 'cyan'];
	$csel = NULL;
	foreach($colors as $team => $color){
		if(strpos($out, $color) !== FALSE){
			$csel = $team; break;
		}
	}

	$str = ":warning: Color no detectado.";
	if(!empty($csel)){
		$u = $pokemon->user($this->telegram->reply_target('forward')->id);
		$str = "Color detectado $csel, equipo " .$u->team ." - " .($csel == $u->team ? ":ok:" : ":times:");
	}
	$str = $this->telegram->emoji($str);

	$this->telegram->send
		->text($str)
	->send();

	unlink($temp);

	return -1;
}

?>
