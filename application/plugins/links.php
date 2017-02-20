<?php

if(
	( $telegram->text_has(["link", "enlace"], ["del grupo", "de este grupo", "grupo"]) or
	$telegram->text_has(["/linkgroup", "/grouplink"], TRUE))
){
	$colores_full = [
		'Y' => ['amarillo', 'instinto', 'yellow', 'instinct'],
		'R' => ['rojo', 'valor', 'red'],
		'B' => ['azul', 'sabiduría', 'blue', 'mystic'],
	];

	if($telegram->text_url()){ return; }

	$team = NULL;
	foreach($colores_full as $k => $colores){
		if($telegram->text_has($colores)){ $team = $k; break; }
	}

	$pokeuser = $pokemon->user($telegram->user->id);

	if($team && ($pokeuser->team == $team or $telegram->user->id == $this->config->item('creator'))){
		$pairteam = $pokemon->settings($telegram->chat->id, 'pair_team_' .$team);
		if(!$pairteam){ return -1; }
		$teamchat = $pokemon->group_pair($telegram->chat->id, $team);
		if(!$teamchat or $pairteam != sha1($telegram->chat->id .":" .$teamchat)){ // HACK Algoritmo de verificación
			$telegram->send
				->chat($this->config->item('creator'))
				->text("MEEEEEC en " .$telegram->chat->id ." con $team")
			->send();
			return -1;
		}

		// Tengo chat, comprobar blacklist
		$black = explode(",", $pokemon->settings($teamchat, 'blacklist'));
		if($pokemon->user_flags($telegram->user->id, $black)){ return; }

		$teamlink = $pokemon->settings($teamchat, 'link_chat');

		// Si es validado
		$color = ['Y' => 'Amarillo', 'R' => 'Rojo', 'B' => 'Azul'];
		$text = "Hay un grupo de tu team *" .$color[$team] ."*, pero no te puedo invitar porque no estás validado " .$telegram->emoji(":warning:") .".\n"
				."Si *quieres validarte*, puedes decirmelo. :)";
		if($pokeuser->verified){
			$text = "Te invito al grupo *" .$color[$team] ."* asociado a " .$telegram->chat->title .". "
					."¡No le pases este enlace a nadie!\n"
					.$telegram->grouplink($teamlink);

			if($telegram->is_chat_group()){
				if(!$pokemon->command_limit("link_color", $telegram->chat->id, $telegram->message, 7)){
					$telegram->send
						->text($telegram->emoji("Ahora mismo! Te abro por privado :D"))
					->send();
				}
			}
		}

		$telegram->send
			->notification(TRUE)
			->chat($telegram->user->id)
			->text($text, NULL) // TODO NO Markdown.
		->send();

		if($pokeuser->verified){
			$telegram->send
				->notification(TRUE)
				->chat($teamchat)
				->text("He invitado a @" .$pokeuser->username ." a este grupo.")
			->send();
		}
		return -1;
	}

	$link = $pokemon->settings($telegram->chat->id, 'link_chat');
	$word = $telegram->last_word(TRUE);

	if(!$team && !is_numeric($word) and strlen($word) >= 4 and !$telegram->text_has("este")){ // XXX comprobar que no dé problemas
		$s = $pokemon->group_link($word);
		if(!empty($s)){ $link = $s; }
	}
	$chatgroup = NULL;
	if(!empty($link)){ $chatgroup = $telegram->grouplink($link); }
	if(!empty($chatgroup)){
		$this->analytics->event('Telegram', 'Group Link');
		$telegram->send
			->notification(FALSE)
			->disable_web_page_preview()
			->text("Link: $chatgroup")
		->send();
	}
	return -1;
}

elseif($telegram->text_has("Lista de", ["enlaces", "links"], TRUE)){
	$str = "";
	$links = $pokemon->link("ALL");
	$str = implode("\n- ", array_column($links, 'name'));
	$telegram->send
		->notification(FALSE)
		->text("- " .$str)
	->send();
	return -1;
}elseif(
	$telegram->text_has(["Enlace", "Link"], TRUE) or
	$telegram->text_has(["/enlace", "/link"], TRUE) and
	!$telegram->text_contains("http") // and
	// $telegram->words() < 6
){
	$text = $telegram->text();
	$text = explode(" ", $text);
	unset($text[0]);
	$command = trim(strtolower($telegram->last_word(TRUE)));

	if(in_array($command, ["aquí", "aqui"])){
		$chat = $telegram->chat->id;
		unset( $text[end(array_keys($text))] );
	}
	else{ $chat = $telegram->user->id; }

	$text = implode(" ", $text);
	$text = trim(strtolower($text));

	$link = $pokemon->link($text);
	if(!empty($link) && count($link) == 1){
		$telegram->send
			->chat($chat)
			->text($link)
		->send();
	}elseif(is_numeric($link) or count($link) > 1){
		$telegram->send
			->chat($chat)
			->text("Demasiadas coincidencias. Vuelve a probar.")
		->send();
	}

	return -1;
}

 ?>
