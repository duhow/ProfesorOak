<?php

if($telegram->text_has(["cambiar de nombre", "cambiarme de nombre"], TRUE) and $telegram->words() <= 5){
	if($pokemon->settings($telegram->user->id, 'change_name')){
		$telegram->send
			->text("Ya te has cambiado de nombre, no puedes volver a hacerlo.")
		->send();

		return -1;
	}

	$pokemon->settings($telegram->user->id, 'change_name', TRUE);
	$pokemon->update_user_data($telegram->user->id, 'username', NULL);
	$pokemon->step($telegram->user->id, 'SETNAME');

	$telegram->send
		->text("De acuerdo, entonces como te llamas?")
	->send();

	return -1;
}
