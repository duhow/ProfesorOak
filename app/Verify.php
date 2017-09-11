<?php

class Verify extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){
		// if($this->user->verified){ return; }

		if(
			!$this->telegram->is_chat_group() and
			!$this->telegram->has_forward and
			$this->user->step == "SCREENSHOT_VERIFY" and
			$this->telegram->photo()
		){
			return $this->verify_send();
		}
		if(
			(
				$this->telegram->text_command("start") and $this->telegram->text_has("verify")
			) or (
				$this->telegram->text_contains($this->strings->get('command_verify')[0]) and
				$this->telegram->text_contains($this->strings->get('command_verify')[1]) and
				$this->telegram->words() <= 7
			)
		){
			return $this->verify_run();
		}
	}

	/**
	 * Lista de votaciones realizadas por los helpers.
	 */
	public function verifylist(){

	}

	/**
	 * Iniciar votación para validar usuarios.
	 * @param integer $amount cantidad de votaciones que se van a hacer.
	 */
	public function verifyvote($amount = 10){

	}

	/**
	 * Validar usuario.
	 */
	public function verify_run(){
		if($this->telegram->is_chat_group()){
			// if($pokemon->command_limit("validar", $this->telegram->chat->id, $this->telegram->message, 7)){ return -1; }

			// Intentar dar un toque para hablarle por privado
			$res = $this->telegram->send
	            ->notification(TRUE)
	            ->chat($this->telegram->user->id)
	            ->text($this->strings->parse('verify_register_touch', $this->telegram->user->first_name))
	        ->send();

	        if(!$res){
	            $this->telegram->send
	                ->notification(FALSE)
	                // ->reply_to(TRUE)
	                ->text($this->telegram->emoji(":times: ") . $this->strings->get('verify_register_private') )
	                ->inline_keyboard()
	                    ->row_button($this->strings->get('verify_register_button'), "verify", TRUE)
	                ->show()
	            ->send();

				$this->end();
	        }
		}

		if($this->user->verified){
			$this->telegram->send
	            ->notification(TRUE)
	            ->chat($this->telegram->user->id)
	            ->text($this->telegram->emoji(":white_check_mark: ") . $this->strings->get('verify_already') )
	        ->send();

	        $this->end();
		}

		$str = implode("\n", $this->strings->get('verify_info'));

		$this->telegram->send
	        ->notification(TRUE)
	        ->chat($this->telegram->user->id)
	        ->text($this->telegram->emoji($str), "HTML")
	        ->keyboard()
	            ->row_button($this->strings->get("cancel"))
	        ->show(TRUE, TRUE)
	    ->send();

		$this->user->step = 'SCREENSHOT_VERIFY';
		$this->verify_check();

		$this->end(); // Kill process for STEP
	}

	public function is_disabled(){
		$value = $this->db
			->where('uid', CREATOR)
			->where('type', 'disable_verify')
		->getValue('settings', 'value');
		return (bool) $value;
	}

	private function verify_check(){
		// Desactivar validacioness en general
		if($this->is_disabled()){
			$this->telegram->send
				->chat($this->telegram->user->id)
				->text($this->telegram->emoji(":clock: ") .$this->strings->get('verify_disabled_too_many'))
			->send();

			$this->user->step = NULL;
			return FALSE;
		}

		// Si el usuario es nuevo
		$date = strtotime("+7 days", strtotime($this->user->register_date));
		if($date > time()){
			$timer = round(($date - time()) / 86400);
			$text = $this->telegram->emoji(":clock: "). $this->strings->parse('verify_disabled_newuser', $timer);
			$this->telegram->send
				->notification(TRUE)
				->chat($this->telegram->user->id)
				->text($this->telegram->emoji($text), 'HTML')
			->send();

			$this->user->step = NULL;
			return FALSE;
		}

		$notname = empty(strval($this->user->username));
		$notlvl = ($this->user->lvl == 1);

		if($notname or $notlvl){
			$text = $this->strings->get('verify_before_send') .' <b>';
			$add = array();
			if($notname){ $add[] = $this->strings->get('verify_before_send_username'); }
			if($notlvl){ $add[] = $this->strings->get('verify_before_send_level'); }
			$text .= implode($this->strings->get('verify_before_send_concat'), $add) ."</b>.\n";

			if($notname){
				$cmd = trim(str_replace('{name}', '', $this->strings->get("command_register_username")[0]));
				$text .= ":arrow_forward: <b>$cmd ...</b>\n";
			}
			if($notlvl){
				$cmd = trim(str_replace(['{level}', '{N:level}'], '', $this->strings->get("command_levelup")[0]));
				$text .= ":arrow_forward: <b>$cmd ...</b>\n";
			}

			$text .= $this->strings->get('verify_before_send_ready');

	        $this->telegram->send
	            ->notification(TRUE)
	            ->chat($this->telegram->user->id)
	            ->text($this->telegram->emoji($text), "HTML")
	            ->keyboard()->hide(TRUE)
	        ->send();

			return FALSE; // CHECK
			// $pokemon->step($this->telegram->user->id, NULL);
	    }
		return TRUE; // PASS
	}

	private function verify_send(){
		if(!$this->verify_check()){ $this->end(); }

		$str = NULL;
		$icon = NULL;

		if($this->is_disabled()){
			$this->user->step = NULL;

			$str = 'verify_disabled_too_many';
			$icon = ':clock:';
		}

		// Comprobar si ya me ha mandado la misma foto.
		$images = $this->user->settings('verify_images');
		if(!empty($images)){
			$images = unserialize($images);
			if(in_array($this->telegram->photo(), array_values($images))){
				$str = 'verify_disabled_repeat';
				$icon = ':times:';
			}
		}

		// Comprobar si ya hay otra imagen previamente en cola.
		$cooldown = $this->user->settings('verify_cooldown');
		if(!empty($cooldown) and $cooldown > time()){
			$this->user->step = NULL;

			$str = 'verify_disabled_cooldown';
			$icon = ':warning:';
		}

		if(!empty($str)){
			$this->telegram->send
				->chat($this->telegram->user->id)
				->text($this->telegram->emoji($icon) ." " . $this->strings->get($str))
			->send();

			$this->end();
		}

		if(!is_array($images)){ $images = array(); }
		$images[time()] = $this->telegram->photo();

		// Cooldown +18h
		$this->user->settings('verify_cooldown', (time() + 64800));
		$this->user->settings('verify_images', serialize($images));
		$this->user->settings('verify_id', $this->telegram->message_id);

		// Cola de validaciones
		// -----------------
		$data = [
			'photo' => $this->telegram->photo(),
			'telegramid' => $this->telegram->user->id,
			'username' => $this->user->username,
			'team' => $this->user->team,
		];

		$this->db->insert('user_verify', $data);

		$this->telegram->send
			->notification(TRUE)
			->chat($this->telegram->user->id)
			->keyboard()->hide(TRUE)
			->text($this->telegram->emoji(":ok: ") .$this->strings->get('verify_sent'))
		->send();

		$this->user->step = NULL;
		$this->end();
	}
}
