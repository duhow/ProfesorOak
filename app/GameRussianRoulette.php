<?php

class GameRussianRoulette extends TelegramApp\Module {
	function run(){
		// $can = $pokemon->settings($telegram->chat->id, 'play_games');
	    // if($can != NULL and $can == FALSE){ return; }

		parent::run();
	}

	public function hooks(){

	}

	function reload(){
		// return bool if reloaded
	}

	function shoot(){
		// return bool if dead.
	}

	/*
}elseif(($telegram->text_has("Recarga", TRUE) or $telegram->text_command("recarga")) && $telegram->words() <= 3){
	$can = $pokemon->settings($telegram->chat->id, 'play_games');
	if($can != NULL and $can == FALSE){ return; }

	$shot = $pokemon->settings($telegram->chat->id, 'russian_roulette');
	$text = NULL;
	if(empty($shot)){
		$this->analytics->event('Telegram', 'Games', 'Roulette Reload');
		$shot = mt_rand(1, 6);
		$pokemon->settings($telegram->chat->id, 'russian_roulette', $shot);
		$text = "Bala puesta.";
	}else{
		if($telegram->user->id == $this->config->item('creator')){
			$pokemon->settings($telegram->chat->id, 'russian_roulette', 'DELETE');
			$this->_begin(); // HACK vigilar
		}
		$text = "Ya hay una bala. ¡*Dispara* si te atreves!";
	}
	$telegram->send
		->notification(FALSE)
		->text($text, TRUE)
	->send();
	return;
}elseif($telegram->text_has(["Dispara", "Bang", "Disparo", "/dispara"], TRUE) && $telegram->words() <= 3){
	if($telegram->text_contains(["oak", "te"])){ return -1; }
	if($telegram->text_contains(" a ")){
		$telegram->send
			->notification(FALSE)
			->text("Cobarde, no sabes jugar a la ruleta...\nSi quieres disparar a alguien, que sea a ti mismo!")
		->send();
		return -1;
	}
	$can = $pokemon->settings($telegram->chat->id, 'play_games');
	if($can != NULL and $can == FALSE){ return; }

	$shot = $pokemon->settings($telegram->chat->id, 'russian_roulette');
	$text = NULL;
	$last = NULL; // Ultimo en disparar
	if(empty($shot)){
		$text = "No hay bala. *Recarga* antes de disparar.";
	}else{
		if($telegram->is_chat_group()){
			$last = $pokemon->settings($telegram->chat->id, 'russian_roulette_last');
			if($last == $telegram->user->id){
				$last = -1;
				$text = "Tu ya has disparado, ¡pásale el arma a otra persona!";
			}else{
				$pokemon->settings($telegram->chat->id, 'russian_roulette_last', $telegram->user->id);
			}
		}
		if($shot == 6 && $last != -1){
			$this->analytics->event('Telegram', 'Games', 'Roulette Shot');
			$pokemon->settings($telegram->chat->id, 'russian_roulette', 'DELETE');
			$text = ":die: :collision::gun:";
		}elseif($last != -1){
			$this->analytics->event('Telegram', 'Games', 'Roulette Shot');
			$pokemon->settings($telegram->chat->id, 'russian_roulette', $shot + 1);
			$faces = ["happy", "tongue", "smiley"];
			$r = mt_rand(0, count($faces) - 1);
			$text = ":" .$faces[$r] .": :cloud::gun:";
		}
		$telegram->send
			->notification(FALSE)
			->reply_to(TRUE)
			->text( $telegram->emoji($text) )
		->send();

		if($shot == 6 && $last != -1 && $telegram->is_chat_group()){
			if(!$pokemon->settings($telegram->chat->id, 'russian_roulette_easy')){
				$telegram->send->ban( $telegram->user->id );
			}
			// Implementar modo light o hard (ban)
			// Avisar al admin?
			$pokemon->settings($telegram->chat->id, 'russian_roulette_last', 'DELETE');
		}
	}
}
	*/
}
