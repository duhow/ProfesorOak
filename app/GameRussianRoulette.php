<?php

class GameRussianRoulette extends TelegramApp\Module {
	private $chat;

	function run(){
		$this->chat = new Chat($this->telegram->chat, $this->db)->load();
		if(isset($this->chat->settings['play_games']) && $this->chat->settings['play_games'] == FALSE){ return; }

		parent::run();
	}

	public function hooks(){
		if(
			($this->telegram->text_has("Recarga", TRUE) or $this->telegram->text_command("recarga")) &&
			$this->telegram->words() < 4
		){
			return $this->reload();
		}
		elseif(
			$this->telegram->text_has(["Dispara", "Bang", "Disparo", "/dispara"], TRUE) && $telegram->words() < 4
		){

		}
	}

	function reload(){
		// return bool if reloaded
		$shot = $this->chat->settings['russian_roulette'];
		$reload = FALSE;
		$text = NULL;
		if(empty($shot) or $this->telegram->user->id == CREATOR){
			// $this->analytics->event('Telegram', 'Games', 'Roulette Reload');
			$shot = mt_rand(1, 6);
			$this->chat->settings['russian_roulette'] = $shot;
			$reload = TRUE;
			$text = "Bala puesta.";
		}else{
			$text = "Ya hay una bala. ¡*Dispara* si te atreves!";
		}
		$this->telegram->send
			->notification(FALSE)
			->text($text, TRUE)
		->send();
		return $reload;
	}

	function shoot(){
		// return bool if dead.
		if($this->telegram->text_contains(["oak", "te"])){ return NULL; }
		if($this->telegram->text_contains(" a ")){
			$this->telegram->send
				->notification(FALSE)
				->text("Cobarde, no sabes jugar a la ruleta...\nSi quieres disparar a alguien, que sea a ti mismo!")
			->send();
			return NULL;
		}

		$shot = $this->chat->settings['russian_roulette'];
		$text = NULL;
		$last = NULL; // último en disparar
		if(empty($shot)){
			$text = "No hay bala. *Recarga* antes de disparar.";
		}else{
			if($this->telegram->is_chat_group()){
				$last = $this->chat->settings['russian_roulette_last'];
				if($last == $telegram->user->id){
					$last = -1;
					$text = "Tu ya has disparado, ¡pásale el arma a otra persona!";
				}else{
					$this->chat->settings['russian_roulette_last'] = $this->telegram->user->id;
				}
			}
			if($shot == 6 && $last != -1){
				// $this->analytics->event('Telegram', 'Games', 'Roulette Shot');
				$this->chat->settings['russian_roulette'] = NULL; // DELETE
				$text = ":die: :collision::gun:";
			}elseif($last != -1){
				// $this->analytics->event('Telegram', 'Games', 'Roulette Shot');
				$this->chat->settings['russian_roulette'] = ($shot + 1);
				$faces = ["happy", "tongue", "smiley"];
				$r = mt_rand(0, count($faces) - 1);
				$text = ":" .$faces[$r] .": :cloud::gun:";
			}
			$this->telegram->send
				->notification(FALSE)
				->reply_to(TRUE)
				->text( $this->telegram->emoji($text) )
			->send();

			if($shot == 6 && $last != -1 && $this->telegram->is_chat_group()){
				if($this->chat->settings['russian_roulette_easy'] !== TRUE){
					$this->telegram->send->ban( $this->telegram->user->id );
				}
				// Implementar modo light o hard (ban)
				// Avisar al admin?
				$this->chat->settings['russian_roulette_last'] = NULL;
			}
		}
	}
}
