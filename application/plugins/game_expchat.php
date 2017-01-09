<?php

if($telegram->is_chat_group()){
	if($telegram->words() == mt_rand(2, 9)){
		$timeout = $pokemon->settings($telegram->user->id, 'expchat_timeout');
		if(empty($timeout) or time() >= $timeout){
			$points = $pokemon->settings($telegram->user->id, 'expchat_points');
			// La recompensa será el número de palabras que haya tocado, para hacer el factor diferencial.
			// Puede favorecer a los spamers, así que cuidado.
			$pokemon->settings($telegram->user->id, 'expchat_points', $points + $telegram->words());
			$pokemon->settings($telegram->user->id, 'expchat_timeout', time() + 60);
		}
	}
}

if($telegram->text_has("mi experiencia")){
	$points = $pokemon->settings($telegram->user->id, 'expchat_points');
	if(empty($points)){ $points = 0; }

	$telegram->send
		->text("Tienes $points puntos.")
	->send();
	return -1;
}

?>
