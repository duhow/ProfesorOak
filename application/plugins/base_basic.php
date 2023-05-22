<?php

// pillando a los h4k0rs
if($telegram->text_contains(["fake GPS", "fake", "fakegps", "nox"]) and !$telegram->text_contains("me llamo", TRUE)){
    if(
		$telegram->user->id != $this->config->item("creator") and
		!in_array($this->pokemon->step($this->telegram->user->id), ["RULES", "WELCOME", "CUSTOM_COMMAND"])
	){
        $this->analytics->event('Telegram', 'Talk cheating');
        /* $telegram->send
            ->text("*(A)* *" .$telegram->chat->title ."* - " .$telegram->user->first_name ." @" .$telegram->user->username .":\n" .$telegram->text(), TRUE)
            ->chat("-226115807")
	    ->send(); */
        // $this->telegram->sendHTML("*OYE!* Si vas a empezar con esas, deberías dejar el juego. En serio, hacer trampas *NO MOLA*.");
		$bw = $pokemon->settings($telegram->chat->id, 'blackword');
		if(!$bw or stripos($bw, "fake") === FALSE){ return -1; }
    }
}

elseif($telegram->text_contains(["profe", "oak"]) && $telegram->text_has("dónde estás") && $telegram->words() <= 5){
	if($pokemon->user_flags($this->telegram->user->id, 'selfblock')){ return -1; }

    $telegram->send
        ->notification(FALSE)
        // ->reply_to(TRUE)
        ->text($telegram->emoji("Detrás de ti... :>"))
    ->send();
    return -1;
}

// comprobar estado del bot
elseif($telegram->text_contains(["profe", "oak"]) && $telegram->text_has(["ping", "pong", "me recibe", "estás", "estás ahí"]) && $telegram->words() <= 4){
	if($pokemon->command_limit("ping", $telegram->chat->id, $telegram->message, 5)){ return -1; }
	if($pokemon->settings($telegram->user->id, 'mute')){ return -1; }
	if($pokemon->user_flags($this->telegram->user->id, 'selfblock')){ return -1; }

    $this->analytics->event('Telegram', 'Ping');
    $q = $telegram->send->text("Pong! :D")->send();

	// sleep(4);
	// $r = $this->telegram->send->delete($q);
	// if($r !== FALSE){ $this->telegram->send->delete(TRUE); }

    return -1;
}

elseif($telegram->text_command("help")){
	if($pokemon->command_limit("help", $telegram->chat->id, $telegram->message, 5)){ return -1; }
	if($pokemon->settings($telegram->user->id, 'mute')){ return -1; }
	if($pokemon->user_flags($this->telegram->user->id, 'selfblock')){ return -1; }

    $telegram->send
        ->notification(FALSE)
        ->text('¡Aquí tienes la <a href="http://help.profoak.me">ayuda</a>!', 'HTML')
        // ->disable_web_page_preview(TRUE)
    ->send();
    return -1;
}

elseif($telegram->text_command(["donate", "donar"]) or
	($telegram->text_command("start") and $telegram->text_has("donate"))
){
	if($pokemon->command_limit("donate", $telegram->chat->id, $telegram->message, 7)){ return -1; }
	if($pokemon->settings($telegram->user->id, 'mute')){ return -1; }
	if($pokemon->user_flags($this->telegram->user->id, 'selfblock')){ return -1; }

	if($telegram->is_chat_group()){
		$str = "Si quieres ayudarme, puedes contribuir con la cantidad que quieras, aunque sea un 1€. Te prometo que merecerá la pena. <3";

		$this->telegram->send
			->inline_keyboard()
			->row()
				->button("Más info", "donate", "COMMAND")
				->button("Donar", "https://paypal.me/duhow")
			->end_row()
		->show();
	}else{
		$release = strtotime("2016-07-16 14:27");
	    $days = round((strtotime("now") - $release) / 3600 / 24);
		$str = "\ud83d\udcc6 He dedicado <b>más de $days dias</b> en ayudar a todos los entrenadores.\n"
			.":male: Cada día aparecen entre 20 y 50 entrenadores nuevos que exploran este mundo Pokémon.\n"
			."Y mientras tanto, yo estoy aquí estudiando en el laboratorio, nuevas herramientas para agregar al PokéNav de Telegram.\n"
			."Si llevas tiempo aquí, estoy seguro de que las conocerás de sobras. Incluso hay algunas que son secretas, y que son divertidas.\n\n"

			."Dedico tiempo a ésto porque me gusta, porque quiero ofrecer una herramienta útil y de calidad para todos los entrenadores.\n"
			."Pero lo cierto es que no recibo nada a cambio. Es más, vivo alimentándome de las bayas Pokémon que caen del árbol, y de los restos de Carameloraros que me da el <b>Profesor Willow</b>.\n"
			."No sé de donde los saca, pero saben a rayos. \ud83d\ude14 \n\n"

			."Pero tú tienes la oportunidad de ayudarme, si quieres, por supuesto... \ud83d\ude33 \n"
			."Si no tienes ningún problema, puedes donarme dinero para poder mantener el proyecto vivo, ya que tiene un coste mensual para mi y los usuarios.\n"
			."Aunque sea tan solo 1€, ya es una ayuda, créeme. \ud83d\udcb6 \n"
			."A cambio y para agradecertelo, recibirás una medalla y unos cuantos objetos. \ud83e\udd17"

			."\n\n\u203c\ufe0f <b>Asegúrate de escribir en la descripción tu usuario de Telegram para saber que se trata de tu donativo.</b>"

			."\n\n<i>Nota:</i> PayPal cobra tarifas por cargos con tarjeta. Asegúrate de enviar desde saldo PayPal o cuenta bancaria, y <b>para un amigo</b>.\n\n"
			."¡Muchísimas gracias, de verdad! <3";

		$this->telegram->send
			->inline_keyboard()
			->row_button("Donar", "https://paypal.me/duhow")
		->show();
	}

	$str = $this->telegram->emoji($str);
	$this->telegram->send
		->text($str, "HTML")
	->send();

	return -1;
}

if(
	strpos(strtolower(@$this->telegram->user->first_name), " oak") !== FALSE or
	strpos(strtolower(@$this->telegram->user->last_name), " oak") !== FALSE
){
	if($pokemon->user_flags($this->telegram->user->id, 'impersonate')){ return -1; }
	if($this->telegram->user->id == $this->config->item('telegram_bot_id')){ return -1; } // HACK FIX
	$str = ":warning: Suplantación de nombre\n"
				.":id: " .$this->telegram->user->id ." - " .$this->telegram->user->username ."\n"
				.":multiuser: " .$this->telegram->chat->id;
	$str = $this->telegram->emoji($str);
	$this->telegram->send
		->chat($this->config->item('creator'))
		->notification(TRUE)
		->text($str)
	->send();
}

?>
