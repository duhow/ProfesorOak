<?php

class Ticket extends TelegramApp\Module {
	protected $runCommands = FALSE;

	const TICKET_WRITING   = 0;
	const TICKET_NEW       = 1;
	const TICKET_PROGRESS  = 2;
	const TICKET_WAITING   = 3;
	const TICKET_DELAYED   = 4;
	const TICKET_REJECTED  = 5;
	const TICKET_COMPLETED = 6;
	const TICKET_ARCHIVED  = 7;

	const CHAT_TICKETS = "-301843220";

	public function run(){
		if($this->user->step){
			$method = "step_" .strtolower($this->user->step);
			if(method_exists($this, $method) and is_callable([$this, $method])){
				$this->$method();
			}
		}
		parent::run();
	}

	protected function hooks(){
		$ticketId = $this->user->settings('ticket_writing');
		if($this->telegram->text_regex("ticket {action} {N:ticket}$")){
			$ticketId = $this->telegram->input->ticket;
		}

		if($this->telegram->text_command("ticket", "new")){
			$this->ticket_new();
			$this->end();
		}elseif(
			$this->telegram->text_command("ticket", "show") and
			!in_array("ticketer", $this->user->flags) and // for User
			$ticketId
		){
			$ticket = $this->get($ticketId);
			if(!$ticket or $this->user->id != $ticket->uid){
				$this->telegram->send
					->text($this->telegram->emoji(":x: ") .$this->strings->get('ticket_not_belong_user'), 'HTML')
				->send();
				$this->end();
			}

			$this->telegram->send
				->text($this->ticket_text_info($ticket, TRUE), 'HTML')
			->send();
			// --------
			$msg = $this->messages($ticket, NULL, TRUE);
			$this->send_messages($msg, $this->user->id);
			// --------
			$this->end();
		}elseif($this->telegram->callback and $this->telegram->input->action == "close" and $ticketId){
			$ticket = $this->get($ticketId);
			$this->telegram->answer_if_callback("");
			if($this->user->id == $ticket->uid){
				$this->status($ticketId, self::TICKET_ARCHIVED, $this->user->id);

				// Remove buttons
				$this->telegram->send
					->message(TRUE)
					->chat(TRUE)
					->text($this->telegram->text_message())
				->edit('text');

				// Avisar al usuario
				$this->telegram->send
					->text($this->strings->get('ticket_status_closed'), 'HTML')
				->send();

				$str = $this->strings->parse('ticket_status_change', [
					$ticket->id,
					$this->strings->get_multi('ticket_status', self::TICKET_ARCHIVED)
				]);

				// Avisar al asignado
				if($ticket->assigned){
					$this->telegram->send
						->notification(TRUE)
						->chat($ticket->assigned)
						->text($str, 'HTML')
					->send();
				}

				// Chat de avisos
				$this->telegram->send
					->notification(FALSE)
					->chat(self::CHAT_TICKETS)
					->text($str, 'HTML')
				->send();
			}
		}elseif($this->telegram->callback and $this->telegram->input->action == "reply" and $ticketId){
			$ticket = $this->get($ticketId);
			$this->telegram->answer_if_callback("");
			if($this->user->id == $ticket->uid){
				$this->user->settings('ticket_writing', $ticketId);
				// Sustituir y poner el boton de enviar en vez de responder
				$this->telegram->send
					->chat(TRUE)
					->message(TRUE)
					->text($this->telegram->text_message())
					->inline_keyboard()
						->row_button($this->telegram->emoji(":arrow_forward: ") .$this->strings->get('ticket_action_send'), "ticket send $ticketId", "TEXT")
					->show()
				->send();
				// Enviar mensaje normal.
				$this->telegram->send
					->keyboard()
						->row_button($this->telegram->emoji(":arrow_forward: ") .$this->strings->get('ticket_action_send'))
					->show(TRUE, TRUE)
					->text($this->strings->get('ticket_writing_answer_user'))
				->send();
				$this->user->step = "TICKET_WRITING";
				$this->end();
			}
		}elseif($this->telegram->callback and $this->telegram->input->action == "send" and $ticketId){
			$ticket = $this->get($ticketId);
			$this->telegram->answer_if_callback("");
			// TODO Comprobar que se haya enviado mensaje, poner step y demas.
			if($this->user->id == $ticket->uid and !$this->is_closed($ticket->status)){
				if($this->count_last_messages($ticketId, $this->user->id) == 0){
					$this->telegram->send
						->text($this->strings->get('ticket_writing_nothing'))
					->send();
					$this->end();
				}
				// Quitar boton
				$this->telegram->send
					->chat(TRUE)
					->message(TRUE)
					->text($this->telegram->text_message())
				->edit('text');

				$this->ticket_writing_finish();
			}
		}elseif(
			$this->telegram->callback and
			$this->telegram->input->action == "list" and
			$ticketId and
			in_array($this->user->flags, "ticketer")
		){
			// Siguiente pagina, TODO
		}elseif(
			$this->telegram->callback and
			$this->telegram->input->action == "show" and
			$ticketId and
			in_array($this->user->flags, "ticketer")
		){
			$ticket = $this->get($ticketId);
			if(!$ticket){
				$this->telegram->send
					->text($this->telegram->emoji(":x: ") .$this->strings->get('ticket_not_belong_user'), 'HTML')
				->send();
				$this->end();
			}

			$this->telegram->send
				->text($this->ticket_text_info($ticket, TRUE), 'HTML')
			->send();
			// --------
			$msg = $this->messages($ticket, NULL, TRUE);
			$this->send_messages_advanced($msg, $ticket, $this->user->id);
		}
		if(in_array('ticketer', $this->user->flags)){
			if($this->telegram->text_command("ticket", "list")){
				if($this->telegram->text_contains("lock")){
					$tickets = $this->list(TRUE);
				}else{
					$tickets = $this->list_all();
				}
				if(!$tickets){
					$this->telegram->send
						->text($this->strings->get('ticket_notify_no_tickets'))
					->send();
				}else{
					$this->telegram->send
						->text($this->list_display($tickets), 'HTML')
					->send();
				}
				$this->end();
			}elseif($this->telegram->text_command("ticket", "show")){
				$ticket = $this->get($ticketId);
				if(!$ticket){
					$this->telegram->send
						->text($this->telegram->emoji(":x: ") .$this->strings->get('ticket_not_exists'))
					->send();
					$this->end();
				}
				$this->user->settings('ticket_writing', $ticketId);
				$this->telegram->send
					->text($this->ticket_text_info($ticket, FALSE), 'HTML')
				->send();
				// ------
				$msg = $this->messages($ticket, NULL, TRUE);
				$this->send_messages_advanced($msg, $ticket, $this->user->id);
				// ------
				$this->end();
			}elseif($this->telegram->text_command("ticket", "reply")){
				$ticket = $this->precheck($ticketId);
				$this->user->settings('ticket_writing', $ticketId);
				$this->telegram->send
					->keyboard()
						->row_button($this->telegram->emoji(":arrow_forward: ") .$this->strings->get('ticket_action_send'))
					->show(TRUE, TRUE)
					->text($this->strings->get('ticket_writing_answer'))
				->send();
				$this->user->step = "TICKET_WRITING";
				$this->end();
			}elseif($this->telegram->text_command("ticket", "note")){
				$ticket = $this->precheck($ticketId, FALSE);
				$this->user->settings('ticket_writing', $ticketId);
				$this->user->settings('ticket_private', TRUE);
				$this->telegram->send
					->keyboard()
						->row_button($this->telegram->emoji(":arrow_forward: ") .$this->strings->get('ticket_action_send'))
					->show(TRUE, TRUE)
					->text($this->strings->get('ticket_writing_note'))
				->send();
				$this->user->step = "TICKET_WRITING";
				$this->end();
			}elseif($this->telegram->text_command("ticket", "status")){
				$ticket = $this->precheck($ticketId);
				if($this->is_closed($ticket->status)){
					$this->telegram->send
						->text($this->telegram->emoji(":x: ") .$this->strings->get('ticket_status_closed_admin'))
					->send();
					$this->user->settings('ticket_writing', 'DELETE');
					$this->end();
				}
				$this->user->settings('ticket_writing', $ticketId);
				$this->ticket_status_keyboard();
				$this->user->step = "TICKET_SETSTATUS";
				$this->end();
			}elseif($this->telegram->text_command("ticket", "lock")){
				$ticket = $this->precheck($ticketId);
				$this->lock($ticketId, TRUE, $this->user->id);
				$this->user->settings('ticket_writing', $ticketId);
				$this->end();
			}elseif($this->telegram->text_command("ticket", "unlock")){
				$ticket = $this->precheck($ticketId);
				$this->lock($ticketId, FALSE, $this->user->id);
				$this->telegram->send
					->text($this->telegram->emoji(':unlock: ') .$this->strings->get('ticket_unlocked_now'))
				->send();
				$this->user->settings('ticket_writing', 'DELETE');
				$this->end();
			}elseif($this->telegram->text_command("ticket", "assign")){
				$ticket = $this->precheck($ticketId);
				$this->user->settings('ticket_writing', $ticketId);
			}
		}


		if($this->telegram->text_command("ticket") and $this->telegram->words() == 1){
			// TODO limit en chat.
			$this->telegram->send
				->notification(FALSE)
				->text($this->strings->get('ticket_command_help', 'HTML'))
			->send();
			$this->end();
		}
	}

	private function ticket_text_info($ticket, $forUser = TRUE){
		$date = Tools::DateParser($ticket->date_create, "dh");
		$str = "Ticket <b>#" .$ticket->id ."</b>\n"
			.$this->telegram->emoji(":calendar: ") .$this->strings->parse('ticket_show_created', $date) ."\n"
			.$this->status_icon($ticket->status) .$this->strings->parse('ticket_show_status', "<b>" .$this->strings->get_multi('ticket_status', $ticket->status) ."</b>") ."\n";

		if($forUser){ return $str; }
		$str .= $this->telegram->emoji(":man_frowning: ") .$this->strings->parse('ticket_show_created_by', $this->telegram->userlink($ticket->uid, $ticket->uid)) ."\n";
		$assigned = $ticket->assigned;
		if($assigned){
			// $assigned = new User($assigned);
			// $assigned->load();
			// $assigned = strval($assigned);
			$assigned = $this->telegram->userlink($assigned, $assigned);
		}else{
			$assigned = $this->strings->get('ticket_assigned_nobody');
		}
		$str .= $this->telegram->emoji(":man_office_worker: ") .$this->strings->parse('ticket_show_assigned', $assigned) ."\n";
		if(strtotime($ticket->locked) >= time()){
			$str .= $this->telegram->emoji(":lock: ") .$this->strings->parse('ticket_show_locked_until', [Tools::DateParser($ticket->locked, "m"), date("H:i", strtotime($ticket->locked))]) ."\n";
		}else{
			$str .= $this->telegram->emoji(":unlock: ") . $this->strings->get('ticket_show_locked_not') ."\n";
		}

		return $str;
	}

	private function precheck($ticketId, $lock = TRUE){
		if(!$ticketId){ $this->end(); }
		$ticket = $this->get($ticketId);
		if(!$ticket){
			$this->telegram->send
				->text($this->telegram->emoji(":x: ") .$this->strings->get('ticket_not_exists'))
			->send();
			$this->end();
		}
		if(!$lock){ return $ticket; }
		if(strtotime($ticket->locked) >= time()){
			if(!in_array($this->user->id, [CREATOR, $ticket->assigned])){
				$this->telegram->send
					->text($this->telegram->emoji(":lock: ") .$this->strings->get('ticket_locked_cant_modify'))
				->send();
				$this->end();
			}
		}else{
			$this->assign($ticketId);
			$this->telegram->send
				->text($this->telegram->emoji(":lock: ") .$this->strings->get('ticket_locked_now'))
			->send();
		}
		return $ticket;
	}

	public function get($ticketId, $user = NULL){
		if($user instanceof User){ $user = $user->id; }
		if($user){ $this->db->where('uid', $user); }
		$ticket = $this->db
			->where('id', $ticketId)
		->getOne('ticket');

		if(!$ticket){ return FALSE; }
		return (object) $ticket;
	}

	public function list($locked = TRUE, $closed = FALSE, $page = 1, $amount = 20, $user = NULL){
		if(empty($user)){ $user = $this->user; }
		if($user instanceof User){ $user = $user->id; }

		if($locked){ $this->db->where('locked', date("Y-m-d H:i:s"), ">="); }
		$this->db->where('(assigned = "' .$user .'" OR assigned IS NULL)');

		// JUMP a las mismas funciones.
		return $this->list_all($closed, $page, $amount);
	}

	public function list_all($closed = FALSE, $page = 1, $amount = 20){
		if(!$closed){
			$this->db->where('status', $this->is_closed(TRUE), 'NOT IN');
		}
		$this->db->pageLimit = $amount;
		return $this->db
			->orderBy('date_create', 'DESC')
		->paginate('ticket', $page);
	}

	private function list_display($tickets){
		$str = "";
		foreach($tickets as $ticket){
			$ticket = (object) $ticket;
			$lock = ($ticket->locked >= time() ? ":lock:" : ":unlock:");
			$status = $this->status_icon($ticket->status, FALSE);

			$str .= "$status $lock <b>#$ticket->id</b> - " .$this->telegram->userlink($ticket->uid, $ticket->uid) ." (" .Tools::DateParser($ticket->date_create, "dh") .")\n";
		}
		return $this->telegram->emoji($str);
	}

	public function messages($ticketId, $filter = NULL, $private = FALSE){
		if(is_object($ticketId)){ $ticketId = $ticketId->id; }
		if(!in_array($filter, [NULL, TRUE, "any", "*"])){
			if(is_numeric($filter)){ $filter = [$filter]; }
			if(is_array($filter)){
				$this->db->where('uid', $filter, 'IN');
			}
		}
		if(!$private){ $this->db->where('private', FALSE); }
		$messages = $this->db
			->where('tid', $ticketId)
		->get('ticket_messages');
		if(!$messages){ return FALSE; }
		return $messages;
	}

	private function send_messages($messages, $toUser, $sleep = 100){
		$rets = array();
		foreach($messages as $msg){
			$this->telegram->send
				->chat($toUser)
				->notification(FALSE);

			if($msg['type'] == 'text'){
				$this->telegram->send
					->text($msg['message'])
				->send();
			}elseif(in_array($msg['type'], ['photo', 'document'])){
				$rets[] = $this->telegram->send->file($msg['type'], $msg['message']);
			}
			if($sleep){ usleep($sleep * 100); }
		}
		return $rets;
	}

	private function resolve_usernames($users){
		if($users instanceof User){ $users = $users->id; }
		if(is_numeric($users) or is_string($users)){ $users = [$users]; }

		$query = $this->db
			->where('(telegramid IN ("' .implode('", "', $users) .'") OR username IN ("' .implode('", "', $users) .'") )')
			->where('username IS NOT NULL')
			->where('anonymous', FALSE)
		->get('user', NULL, 'telegramid, username');
		if(!$query){ return FALSE; }
		return array_column($query, 'username', 'telegramid');
	}

	private function send_messages_advanced($messages, $ticket, $toUser, $sleep = 100){
		$rets = array();
		$str = "";
		if(is_numeric($ticket)){ $ticket = $this->get($ticket); }
		$userids = array();
		foreach($messages as $msg){
			$userids[] = $msg['uid'];
			if(!empty($msg['ref'])){ $userids[] = $msg['ref']; }
		}
		$users = $this->resolve_usernames(array_unique($userids));
		foreach($messages as $msg){
			if($msg['type'] != "text"){
				if(!empty($str)){
					$str = $this->telegram->emoji($str);
					$rets[] = $this->telegram->send
						->chat($toUser)
						->text($str, 'HTML')
					->send();
					usleep($sleep * 100);
				}
				$str = "";
				// Agregar caption de quien lo envia
				$arrow = ($msg['ref'] ? ':fast_forward:' : ':arrow_right:');
				$uicon = ($msg['uid'] == $ticket->uid ?
				       	":man_frowning: $arrow :man_in_tuxedo:" : // U > H
				       	":man_in_tuxedo: $arrow :man_frowning:"); // H > U
				$str .= "$uicon - " .$msg['uid'];
				if(in_array($msg['uid'], $users)){ error_log("entra"); $str .= ' ' .$users[$msg['uid']]; }
				// TODO si el usuario no habla con Oak, no se puede linkar
				// Asi que poner sólo ID numerico.
				if($msg['ref']){ $str .= ' // ' .$msg['ref']; }
				if($msg['private']){ $str .= ' :eyes:'; }
				$str = $this->telegram->emoji($str);

				$rets[] = $this->telegram->send
					->caption($str)
					->chat($toUser)
				->file($msg['type'], $msg['message']);

				$str = "";
				usleep($sleep * 100);
				continue;
			}else{
				if(strlen($str) >= 3500){
					$str = $this->telegram->emoji($str);
					$rets[] = $this->telegram->send
						->chat($toUser)
						->text($str, 'HTML')
					->send();
					usleep($sleep * 100);
					$str = "";
				}
				$arrow = ($msg['ref'] ? ':fast_forward:' : ':arrow_right:');
				$uicon = ($msg['uid'] == $ticket->uid ?
						":man_frowning: $arrow :man_in_tuxedo:" : // U > H
						":man_in_tuxedo: $arrow :man_frowning:"); // H > U
				$str .= "$uicon - <code>" .$msg['uid'] .'</code>';
				if(in_array($msg['uid'], $users)){  error_log("entra"); $str .= ' ' .$this->telegram->userlink($msg['uid'], $users[$msg['uid']]); }
				if($msg['ref']){
					$str .= ' // <code>' .$msg['ref'] .'</code>';
					if(in_array($msg['ref'], $users)){
						// $str .= ' '. $this->telegram->userlink($msg['ref'], $users[$msg['ref']]);
						$str .= ' '. $users[$msg['ref']];
					}
				}
				if($msg['private']){ $str .= ' :eyes:'; }

				$str .= "\n" .Tools::DateParser($msg['date'], "dh") . " (" .date("d/m H:i", strtotime($msg['date'])) .")\n";
				$str .= $msg['message'] ."\n\n";
			}
		}
		if(!empty($str)){
			$str = $this->telegram->emoji($str);
			$rets[] = $this->telegram->send
				->chat($toUser)
				->text($str, 'HTML')
			->send();
			usleep($sleep * 100);
		}
		return $rets;
	}

	public function assign($ticketId, $toUser = NULL, $lock = TRUE){
		$prev = NULL;
		if(is_object($ticketId)){ $prev = $ticketId->assigned; }
		else{
			$prev = $this->db
				->where('id', $ticketId)
			->getValue('ticket', 'assigned');
			if($prev === FALSE){ return NULL; }
		}
		if(empty($toUser)){ $toUser = $this->user->id; }

		$data = ['assigned' => $toUser];
		if($lock){ $data['locked'] = date("Y-m-d H:i:s", strtotime("+2 hours")); }

		$this->db
			->where('id', $ticketId)
		->update('ticket', $data);
		if(empty($prev) or $prev == $toUser){
			$this->log($ticketId, 'change_assigned', "$prev,$toUser");
		}
		return $this;
	}

	public function log($ticketId, $action, $data = NULL){
		$data = [
			'ticket' => $ticketId,
			'uid' => $this->user->id,
			'action' => $action,
			'content' => $data,
			'date' => $this->db->now()
		];

		return $this->db->insert('ticket_log', $data);
	}

	public function lock($ticketId, $lock = TRUE, $user = NULL){
		if(empty($user)){ $user = $this->user; }
		if($user instanceof User){ $user = $user->id; }

		$lock = ((bool) $lock ? date("Y-m-d H:i:s", strtotime("+2 hours")) : "0000-00-00 00:00:00");
		// if($user != CREATOR){ $this->db->where('assigned', $user); }
		$q = $this->db
			->where('id', $ticketId)
		->update('ticket', ['locked' => $lock]);

		if($q){ $this->log($ticketId, ($lock ? 'lock' : 'unlock'), $user); }
		return $this;
	}

	// De momento la funcion avisa de tickets no cerrados.
	private function notify($ticketId){
		$ticket = $ticketId;
		if(!is_object($ticket)){
			$ticket = $this->get($ticketId);
			if(!$ticket){ return FALSE; }
		}
		if($this->is_closed($ticket->status)){ return FALSE; }

		$flags = $this->db->subQuery();
		$flags->where('value', 'ticketer')->get('user_flags', NULL, 'user');

		$users = $this->db
			->where('type', 'ticket_notify')
			->where('value IS NOT NULL')
			->where('value', FALSE, '!=')
			->where('uid', $flags, 'IN')
		->get('settings', NULL, 'uid');
		$users = array_column($users, 'uid');

		$str = ":exclamation: " .$this->strings->get('ticket_notify_new') ."\n"
			.":id: <b>#" .$ticket->id ."</b>\n"
			.":man_frowning: " .$this->telegram->userlink($ticket->uid, $ticket->uid);

		$r = array();

		foreach($users as $user){
			$r[] = $this->telegram->send
				->notification(TRUE)
				->chat($user)
				->inline_keyboard()
					->row()
						->button($this->telegram->emoji(":memo: ") .$this->strings->get('ticket_action_read'), "ticket show " .$ticket->id, "TEXT")
						->button($this->telegram->emoji(":man_office_worker: ") .$this->strings->get('ticket_action_assign'), "ticket assign " .$ticket->id, "TEXT")
					->end_row()
				->show()
				->text($str, 'HTML')
			->send();
		}

		return count($r);
	}

	public function status($ticketId, $status = NULL, $user = NULL){
		if(empty($user)){ $user = $this->user; }
		if($user instanceof User){ $user = $user->id; }

		if($status !== NULL){
			// if($user != CREATOR or $user !== TRUE){ $this->db->where('assigned', $user); }
			$this->db
				->where('id', $ticketId)
			->update('ticket', ['status' => $status]);
			$this->log($ticketId, 'status', $status);
			return $this;
		}

		$status = $this->db
			->where('id', $ticketId)
		->getValue('ticket', 'status');
		return $status;
	}

	private function status_icon($status, $emoji = TRUE){
		$icons = [
			self::TICKET_WRITING   => ':pencil:',
			self::TICKET_NEW       => ':incoming_envelope:',
			self::TICKET_PROGRESS  => ':arrow_forward:',
			self::TICKET_WAITING   => ':speech_balloon:',
			self::TICKET_DELAYED   => ':pause_button:',
			self::TICKET_REJECTED  => ':x:',
			self::TICKET_COMPLETED => ':white_check_mark:',
			self::TICKET_ARCHIVED  => ':card_file_box:',
		];

		if($emoji){ return $this->telegram->emoji($icons[$status] ." "); }
		return $icons[$status];
	}

	private function status_can_change($current, $target){
		switch ($current) {
			case self::TICKET_WRITING:
				return (in_array($target, [
					self::TICKET_NEW,
					self::TICKET_REJECTED
				]));
			break;

			case self::TICKET_NEW:
				return (in_array($target, [
					self::TICKET_PROGRESS,
					self::TICKET_DELAYED,
					self::TICKET_COMPLETED,
					self::TICKET_REJECTED
				]));
			break;

			case self::TICKET_PROGRESS:
				return (in_array($target, [
					self::TICKET_PROGRESS,
					self::TICKET_WAITING,
					self::TICKET_DELAYED,
					self::TICKET_COMPLETED,
					self::TICKET_REJECTED,
					self::TICKET_ARCHIVED
				]));
			break;

			case self::TICKET_WAITING:
				return (in_array($target, [
					self::TICKET_PROGRESS,
					self::TICKET_DELAYED,
					self::TICKET_ARCHIVED
				]));
			break;

			case self::TICKET_DELAYED:
				return (in_array($target, [
					self::TICKET_PROGRESS,
					self::TICKET_ARCHIVED
				]));
			break;

			// Delayed, rejected or completed
			default:
				return FALSE;
			break;
		}
	}

	public function is_closed($data){
		$closed = [
			self::TICKET_ARCHIVED,
			self::TICKET_REJECTED,
			self::TICKET_COMPLETED
		];
		if($data === TRUE){ return $closed; }

		if(is_object($data) and isset($data->status)){ $data = $data->status; }
		if($data >= 8){ $data = $this->status($data); }

		return in_array($data, $closed);
	}

	public function add_message($ticket, $message, $user = NULL, $private = FALSE){
		$ref = NULL;
		$type = "text";

		if(empty($user)){ $user = $this->user; }
		if(is_array($user)){
			if(count($user) == 2){
				$ref = array_pop($user);
				$user = array_shift($user);
				if($ref instanceof User){ $ref = $ref->id; }
			}else{
				$user = current($user);
			}
		}
		if($user instanceof User){ $user = $user->id; }

		if(is_array($message) and count($message) == 1){
			$type = key($message);
			$message = current($message);
		}

		$data = [
			'uid' => $user,
			'tid' => $ticket,
			'cid' => $this->chat->id,
			'mid' => $this->telegram->message_id,
			'ref' => $ref,
			'message' => $message,
			'type' => $type,
			'private' => (bool) $private,
			'date' => $this->db->now()
		];

		return $this->db->insert('ticket_messages', $data);
	}

	public function count_last_messages($ticketId, $getUser = NULL, $amount = 10){
		// TODO Esto solo busca los ultimos 10 mensajes totales
		// Como segundo parametro por si acaso, habria que indicar si son
		//     despues del cambio de usuario (AA-BBBB-AAA-BB-A)
		$messages = $this->db
			->where('tid', $ticketId)
			->orderBy('date', 'DESC')
		->get('ticket_messages', $amount, 'uid');

		$count = array();
		foreach($messages as $msg){
			if(!isset($count[$msg['uid']])){ $count[$msg['uid']] = 0; }
			$count[$msg['uid']]++;
		}

		if(!empty($getUser)){
			if(!isset($count[$getUser])){ return 0; }
			return $count[$getUser];
		}

		return $count;
	}

	public function ticket_new(){
		if(in_array(['troll', 'rager', 'troll_ticket'], $this->user->flags)){ $this->end(); }
		if($this->chat->is_group()){
			$this->telegram->send
				->text($this->strings->get('ticket_not_in_group'))
			->send();
			$this->end();
		}

		$ticket = [
			'uid'         => $this->user->id,
			'assigned'    => NULL,
			'locked'      => "0000-00-00 00:00:00",
			'date_create' => $this->db->now(),
			'status'      => self::TICKET_WRITING,
		];

		$ticketId = $this->db->insert('ticket', $ticket);
		$this->user->step = "TICKET_WRITING";
		$this->user->settings('ticket_writing', $ticketId);
		$this->user->settings('ticket_new', TRUE);

		$this->telegram->send
			->chat($this->user->id)
			->keyboard()
				->row()
					->button($this->telegram->emoji(":arrow_forward: ") .$this->strings->get('ticket_action_send'))
					->button($this->telegram->emoji(":x: ") .$this->strings->get('ticket_action_delete'))
				->end_row()
			->show(TRUE, TRUE)
			->text($this->strings->get('ticket_writing_new'))
		->send();

		$str = $this->telegram->emoji(":information_source: ") ."El usuario %s - %s está creando un ticket.";
		$ulink = $this->telegram->userlink($this->telegram->user->id, $this->telegram->user->id);

		$this->telegram->send
			->chat(self::CHAT_TICKETS)
			->notification(FALSE)
			->text_replace($str, [$ulink, strval($this->telegram->user)])
		->send();
	}

	private function step_ticket_writing(){
		// TODO cuidado que eso puede afectarme a mi o al que escribe el ticket tambien.

		// Cuando se envia el boton de Guardar, ver si es principal y cambiar estado según corresponda.
		if($this->chat->is_group()){ $this->end(); }

		$data = NULL;
		$type = NULL;
		$ref = NULL;
		if($this->telegram->text()){
			$data = $this->telegram->text();
			$type = "text";
		}elseif($this->telegram->photo()){
			$data = $this->telegram->photo();
			$type = "photo";
		}elseif($this->telegram->document()){
			$data = $this->telegram->document();
			$type = "document";
		}
		if(empty($data) or empty($type)){
			$this->telegram->send
				->text("Eing? " .$this->telegram->emoji(":kissing:"))
			->send();
			$this->end();
		}

		if($this->telegram->has_forward){
			$ref = $this->telegram->forward_user->id;
			if($ref == $this->user->id){ $ref = NULL; } // No poner forward si es él mismo.
		}

		$error = FALSE;

		$ticketId = $this->user->settings('ticket_writing');
		$status = $this->status($ticketId);

		// Si no hay ticket
		if(!$ticketId){
			$error = $this->strings->get('ticket_writing_no_id');
		// Si el ticket está cerrado
		}elseif($this->is_closed($status)){
			$error = $this->strings->parse('ticket_writing_closed', $this->strings->get_multi('ticket_status', $status));
		}

		if($error){
			$this->telegram->send
				->keyboard()->hide(TRUE)
				->text($error, 'HTML')
			->send();

			$this->user->step = NULL;
			$this->user->settings('ticket_writing', 'DELETE');
			$this->end();
		}

		if(!($this->telegram->text() and $this->telegram->words() <= 2) and !$this->telegram->callback){
			$private = $this->user->settings('ticket_private');
			$message = [$type => $data];
			$users = [$this->user->id, $ref];
			$this->add_message($ticketId, $message, $users, $private);
		}
		// Si ya hay mas de 10 elementos desde la ultima persona que lo haya enviado, mata.
		if($this->count_last_messages($ticketId, $this->user->id) >= 10){
			// Mensajes excedidos, enviar respuesta / cambiar estado.
			$this->telegram->send
				->text($this->strings->get('ticket_writing_too_much'))
			->send();
			$this->ticket_writing_finish();
		}elseif(
			($this->telegram->text_has($this->strings->get('ticket_action_send')) and $this->telegram->words() <= 2) or
			($this->telegram->callback and $this->telegram->text_has("ticket reply"))
		){
			if($this->count_last_messages($ticketId, $this->user->id) == 0){
				if($this->telegram->callback){
					$this->telegram->answer_if_callback($this->strings->get('ticket_writing_nothing', TRUE));
				}else{
					$this->telegram->send
						->text($this->strings->get('ticket_writing_nothing'))
					->send();
				}
				$this->end();
			}
			$this->ticket_writing_finish();
		}elseif($this->telegram->text_has($this->strings->get('ticket_action_delete')) and $this->telegram->words() <= 2){
			$this->telegram->send
				->text($this->strings->get('ticket_writing_delete'))
			->send();

			$this->status($ticketId, self::TICKET_ARCHIVED);
			$this->user->settings('ticket_new', 'DELETE');
			$this->user->settings('ticket_writing', 'DELETE');
			$this->user->step = NULL;
		}

		$this->end();
	}

	private function ticket_writing_finish(){
		// Si yo respondo, estado es esperando respuesta.
		// Si responda el tio, en espera de continuacion.

		$ticketId = $this->user->settings('ticket_writing');
		$ticket = $this->get($ticketId);

		if(!$ticket){
			$this->user->settings('ticket_new', 'DELETE');
			$this->user->settings('ticket_writing', 'DELETE');
			$this->user->settings('ticket_private', 'DELETE');
			$this->user->step = NULL;

			$this->end();
		}
		if($this->user->id == $ticket->uid){ // From user
			if($this->user->settings('ticket_new')){
				$this->status($ticketId, self::TICKET_NEW, TRUE);
				$this->user->settings('ticket_new', 'DELETE');
				$this->notify($ticket);
				$str = $this->strings->parse('ticket_writing_finished_ticketid', $ticket->id);
			}else{
				$this->status($ticketId, self::TICKET_DELAYED, TRUE);

				// Enviar las respuestas al asignado.
				if($ticket->assigned){
					$messages = $this->messages($ticketId);
					$messages = array_reverse($messages);
					$new = array();
					$k = key($messages);
					while($messages[$k]['uid'] != $ticket->uid){
						$new[] = $messages[$k];
						$k++;
					}
					$new = array_reverse($new);
					if(count($new) > 0){
						$this->telegram->send
							->notification(TRUE)
							->chat($ticket->assigned)
							->text($this->strings->parse('ticket_notify_reply', $ticket->id), 'HTML')
						->send();

						$msnd = $this->send_messages_advanced($new, $ticket->assigned);
						// WIP Guardar los MID del bot por si hace reply a estos.
						$msnd = array_column($msnd, 'message_id');
						$utarget = new User($ticket->assigned);
						$utarget->settings('ticket_reply_id', implode(',', $msnd));
					}
				}

				$str = $this->strings->get('ticket_writing_finished');
			}
			$this->telegram->send
				->keyboard()->hide(TRUE)
				->text($str, 'HTML')
			->send();

			$this->user->step = NULL;
			$this->user->settings('ticket_writing', 'DELETE');
		}else{ // From assigner
			if($this->user->settings('ticket_private')){
				$this->user->settings('ticket_private', 'DELETE'); // TODO CHECK
				// No es necesario cambiar el estado despues de agregar notas
				// Ni avisar al usuario
			}else{
				$this->status($ticketId, self::TICKET_PROGRESS);
				// Show keyboard change status
				$this->user->step = "TICKET_SETSTATUS";
				$this->ticket_status_keyboard($this->user->id);

				// Enviar las respuetas al usuario.
				$messages = $this->messages($ticketId);
				if($messages){
					$messages = array_reverse($messages);
					$new = array();
					$k = key($messages);
					while($messages[$k]['uid'] != $ticket->uid){
						$new[] = $messages[$k];
						$k++;
					}
					$new = array_reverse($new);
					if(count($new) > 0){
						$this->telegram->send
							->notification(TRUE)
							->chat($ticket->uid)
							->text($this->strings->parse('ticket_notify_reply', $ticket->id), 'HTML')
						->send();

						$msnd = $this->send_messages($new, $ticket->uid);
						// WIP Guardar los MID del bot por si hace reply a estos.
						$msnd = array_column($msnd, 'message_id');
						$utarget = new User($ticket->uid);
						$utarget->settings('ticket_reply_id', implode(',', $msnd));
					}
				}
			}
		}

		$str = $this->telegram->emoji(":warning: ") ."El usuario %s - %s ha enviado mensajes al ticket #%s";
		$ulink = $this->telegram->userlink($this->telegram->user->id, $this->telegram->user->id);
		$this->telegram->send
			->chat(self::CHAT_TICKETS)
			->notification(FALSE)
			->text_replace($str, [$ulink, strval($this->telegram->user), $ticketId])
		->send();

		$this->end();
	}

	private function ticket_status_keyboard($chat = NULL, $add_text = TRUE){
		if($chat === FALSE){ $add_text = FALSE; $chat = NULL; }
		if(empty($chat)){ $chat = $this->user->id; }
		$this->telegram->send
			->chat($chat)
			->keyboard()
				->row()
					->button($this->status_icon(self::TICKET_PROGRESS) .$this->strings->get_multi('ticket_status', self::TICKET_PROGRESS))
					->button($this->status_icon(self::TICKET_WAITING) .$this->strings->get_multi('ticket_status', self::TICKET_WAITING))
					->button($this->status_icon(self::TICKET_DELAYED) .$this->strings->get_multi('ticket_status', self::TICKET_DELAYED))
				->end_row()
				->row()
					->button($this->status_icon(self::TICKET_REJECTED) .$this->strings->get_multi('ticket_status', self::TICKET_REJECTED))
					->button($this->status_icon(self::TICKET_COMPLETED) .$this->strings->get_multi('ticket_status', self::TICKET_COMPLETED))
				->end_row()
			->show(TRUE, TRUE);
		if($add_text){
			return $this->telegram->send
				->text($this->strings->get("ticket_status_set_keyb"))
			->send();
		}
	}

	private function step_ticket_setstatus(){
		// Si es el usuario asignado a este ticket, o bien si soy yo
		// Al pulsar sobre un boton, cambiar el estado.
		// Cualquier otra respuesta sera ignorada y cancelara el STEP
		$status_txt = $this->strings->get('ticket_status');
		// Remove Emoji
		$next_status = array_search(trim($this->telegram->text(TRUE)), $status_txt);
		if(!$next_status){
			$this->ticket_status_keyboard(FALSE);
			$this->telegram->send
				->text($this->strings->get('ticket_status_unknown'))
			->send();
			$this->end();
		}

		$ticketId = $this->user->settings('ticket_writing');
		$ticket = $this->get($ticketId);
		$error = FALSE;

		if(!$ticket){
			$error = $this->strings->get('ticket_writing_no_id');
		}elseif(!$this->status_can_change($ticket->status, $next_status)){
			$error = $this->strings->parse('ticket_status_cant_change', $status_txt[$next_status]);
		}

		if($error){
			$this->telegram->send
				->text($error, 'HTML')
			->send();
			$this->end();
		}

		$this->status($ticketId, $next_status, $this->user->id);
		$this->user->step = NULL;

		$str = $this->strings->parse('ticket_status_change', [$ticket->id, $status_txt[$next_status]]);

		// Enviar al editor.
		$this->telegram->send
			->notification(FALSE)
			->keyboard()->hide(TRUE)
			->text($str, 'HTML')
		->send();

		// Avisar al usuario
		if($this->is_closed($next_status)){
			$this->user->settings('ticket_writing', 'DELETE');
			$str .= "\n\n" .$this->strings->get('ticket_status_closed');
		}elseif($next_status == self::TICKET_WAITING){
			$this->user->settings('ticket_writing', 'DELETE');
			$str .= "\n\n" .$this->strings->get('ticket_status_waiting_user');
			$this->telegram->send
				->inline_keyboard()
					->row()
						->button($this->telegram->emoji(":speech_balloon: ") .$this->strings->get('ticket_action_reply'), "ticket reply " .$ticketId, "TEXT")
						->button($this->telegram->emoji(":x: ") .$this->strings->get('ticket_action_close'), "ticket close " .$ticketId, "TEXT")
					->end_row()
				->show();
		}
		$this->telegram->send
			->chat($ticket->uid)
			->text($str, 'HTML')
		->send();

		// Avisar al chat de tickets
		$str = $this->strings->parse('ticket_status_change', [$ticket->id, $status_txt[$next_status]]);
		$this->telegram->send
			->chat(self::CHAT_TICKETS)
			->notification(FALSE)
			->text($str, 'HTML')
		->send();

		$this->end();
	}
}
