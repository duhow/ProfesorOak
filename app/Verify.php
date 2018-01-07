<?php

class Verify extends TelegramApp\Module {
	protected $runCommands = FALSE;

	const VERIFY_OK	 = 1;
	const VERIFY_CHECK  = 2;
	const VERIFY_REJECT = 3;
	const VERIFY_REPORT = 4;

	public $icons = [
		self::VERIFY_OK     => ":ok:",
		self::VERIFY_CHECK  => ":warning:",
		self::VERIFY_REJECT => ":times:",
		self::VERIFY_REPORT => ":bangbang:",
	];

	const VERIFY_MIN_VOTE_AMOUNT = 5;
	const MAX_VERIFY_VOTE_AMOUNT = 200;

	protected function hooks(){
		// if($this->user->verified){ return; }

		if(
			!$this->telegram->is_chat_group() and
			!$this->telegram->has_forward and
			$this->user->step == "SCREENSHOT_VERIFY" and
			!$this->user->verified and
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
	 * Buscar votacion disponible
	 */
	public function get_random_submit($current_user = NULL, $return_id = FALSE){
		if(empty($current_user)){ $current_user = $this->user->id; }

		$user = $this->db
			->where('v.status IS NULL')
			->where("v.id", "(SELECT photo FROM user_verify_vote WHERE telegramid = $current_user)", "NOT IN")
			->where("v.id", "(SELECT photo FROM user_verify_vote GROUP BY photo HAVING count(*) >= " .VERIFY_MIN_VOTE_AMOUNT ." )", "NOT IN")
			->where("v.telegramid", "(SELECT telegramid FROM user WHERE verified = TRUE)", "NOT IN")
			->orderBy('RAND()')
		->getOne('user_verify v', 'v.*');

		if(!$user){ return FALSE; }
		return($return_id ? $user['id'] : $user);
	}

	/**
	 * Ver si el usuario ha enviado una foto para validarse
	 * o buscar el ID de validación
	 */
	public function user_has_submited($id, $time = "1 week"){
		return $this->db
			->where('id', $id)
			->orWhere('telegramid', $id)
			->where('date', date("Y-m-d", strtotime('-' .$time)), ">=")
			->orderBy('id DESC')
		->getOne('user_verify');
	}

	/**
	 * Ver el ID de Telegram segun el contenido
	 */
	public function get_user_telegram($search){
		return $this->db
			->where('id', $search)
			->orWhere('photo', $search)
		->getOne('user_verify', 'telegramid');
	}

	/**
	 * Contar los usuarios que faltan por votar
	 */
	public function count_user_vote_remaining($user = NULL){
		if($user === TRUE){ $user = $this->user->id; }

		if(!empty($user)){
			$this->db->where("id NOT IN (SELECT photo FROM user_verify_vote WHERE telegramid = $user)");
		}

		return $this->db
			->where('status IS NULL')
			->where('date_finish IS NULL')
		->getValue('user_verify', 'count(*)');
	}

	/**
	 * Lista de votaciones realizadas por los helpers.
	 * Principalmente para el creador (?)
	 */
	public function vote_list($id, $return = FALSE){
		$votes = $this->db
			->join('user u', 'u.telegramid = v.telegramid')
			->where('photo', $id)
		->get('user_verify_vote v', NULL, ['u.username', 'v.status']);

		if($return){ return $votes; }

		$str = "\ud83d\udcdd #$id\n";
		foreach($votes as $vote){
			$str .= $this->icons[$vote['status']] ." " .$vote['username'] ."\n";
		}

		$req = $this->telegram->send
			->text($this->telegram->emoji($str), "HTML")
		->send();

		$msgs = $this->user->settings('verify_messages');
		$msgs .= "," .$req['message_id'];
		$this->user->settings('verify_messages', $msgs);

		$this->telegram->answer_if_callback("");
	}

	/**
	 * Contar cuanta gente ha votado por esta foto
	 */
	public function vote_count($id, $full_array = FALSE, $retpercbase = FALSE){
		$votes = $this->db
			->where('photo', $id)
			->groupBy('status')
		->get('user_verify_vote', NULL, ['status', 'count(*) AS count']);
		if(!$full_array){ return array_sum(array_column($votes, 'count')); }

		$res = [
			self::VERIFY_OK => 0,
			self::VERIFY_CHECK => 0,
			self::VERIFY_REJECT => 0,
			self::VERIFY_REPORT => 0
		];

		foreach($votes as $row){
			$res[intval($row['status'])] = intval($row['count']);
		}

		if($retpercbase === FALSE){ return $res; }

		// Percentage base
		$total = array_sum(array_values($res));
		$totalvotes = array();

		foreach($res as $status => $count){
			$totalvotes[$status] = floor(($count / $retpercbase) * 100);
		}
		return $totalvotes;
	}

	/**
	 * Establecer el estado final de la foto de validacion
	 */
	public function vote_set($vote_id, $status, $force = FALSE){
		if(!$force){ $this->db->where('status IS NULL'); }

		$data = [
			'status' => $status,
			'date_finish' => $this->db->now()
		];

		return $this->db
			->where('id', $vote_id)
		->update('user_verify', $data);
	}

	private function verify_text_generate($verifydata, $userdata, $left = NULL){
		$str = "";

		// Old time de registro Telegram
		$old = (time() - strtotime($userdata->register_date));
		if($old <= (86400*7)){
			$str .= ":new: ";
		}
		// Old time de la captura
		$old = (time() - strtotime($verifydata->date_add));
		if($old >= (86400*2)){
			$old = round($old / 86400) ." d";
		}elseif($old >= 3600){
			$old = round($old / 3600) ." h";
		}elseif($old >= 60){
			$old = round($old / 60) ." m";
		}else{
			$old .= " s";
		}

		$teamico = [
			'Y' => ":thunder:",
			'R' => ":fire:",
			'B' => ":water:"
		];

		$str .= $old ."  ";
		if(!empty($left)){ $str .= "<code>		  </code>$left :triangle-left: "; }
		$str .= "<code>		  </code>" .date("H:i", strtotime($verifydata->date_add)) ." :clock:";

		$str .= "\n" .":arrow-up: <b>L" .$userdata->lvl ." </b>"
			.$teamico[$userdata->team] ."<code>		  </code>@" .$userdata->username;

		/* if(strtolower($userdata->username) == strtolower($userdata->telegramuser)){
			$str .= " :ok:";
		} */

		return $str;
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

		if(!$this->verify_check()){ $this->end(); }

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

		$this->end(); // Kill process for STEP
	}

	public function is_disabled(){
		$value = $this->db
			->where('uid', CREATOR)
			->where('type', 'disable_verify')
		->getValue('settings', 'value');
		return (bool) $value;
	}

	public function last_verify_date($user){
		if($user instanceof User){ $user = $user->id; }
		return $this->db
			->where('uid', $user)
			->where('status', self::VERIFY_OK)
			->where('date_finish', NULL, 'IS NOT')
			->orderBy('date_finish', 'DESC')
		->getValue('user_verify', 'date_finish', 1);
	}

	private function verify_check(){
		// Desactivar validacioness en general
		if($this->is_disabled() or $this->count_user_vote_remaining() > self::MAX_VERIFY_VOTE_AMOUNT)){
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
			$timer = max(round(($date - time()) / 86400), 1);
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

		if($this->is_disabled() or $this->count_user_vote_remaining() > self::MAX_VERIFY_VOTE_AMOUNT)){
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
