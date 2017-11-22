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
			!in_array("ticketer", $this->user->flags) and
			$ticketId
		){
			$ticket = $this->get($ticketId);
			if($this->user->id != $ticket->uid){
				$this->telegram->send
					->text($this->telegram->emoji(":x: ") .$this->strings->get('ticket_not_belong_user'), 'HTML')
				->send();
				$this->end();
			}

			// TODO Check Date parser
			$date = Tools::DateParser($ticket->date_create);
			$str = "Ticket <b>#" .$ticket->id ."</b>\n"
				.$this->strings->parse('ticket_show_created', $date) ."\n"
				.$this->strings->parse('ticket_show_status', $this->strings->get_multi('ticket_status', $ticket->status)) ."\n";

			$this->telegram->send
				->text($str, 'HTML')
			->send();
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
				$this->telegram->send
					->keyboard()
						->row_button($this->telegram->emoji(":arrow_forward: ") .$this->strings->get('ticket_action_send'))
					->show(TRUE, TRUE)
					->text($this->strings->get('ticket_writing_answer_user'))
				->send();
				$this->user->step = "TICKET_WRITING";
				$this->end();
			}
		}
		if(in_array('ticketer', $this->user->flags)){
			if($this->telegram->text_command("ticket", "list")){

			}elseif($this->telegram->text_command("ticket", "show")){
				$ticket = $this->get($ticketId);
				if(!$ticket){
					if(!$ticket){
						$this->telegram->send
							->text($this->telegram->emoji(":x: ") .$this->strings->get('ticket_not_exists'))
						->send();
						$this->end();
					}
				}
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

	public function messages($ticketId, $filter = NULL, $private = FALSE){
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
		if($prev == $toUser or empty($toUser)){ return; } // Ya estaba, no es necesario volver a ponerlo.

		$data = ['assigned' => $toUser];
		if($lock){ $data['locked'] = date("Y-m-d H:i:s", strtotime("+2 hours")); }

		$this->db
			->where('id', $ticketId)
		->update('ticket', $data);
		$this->log($ticketId, 'change_assigned', "$prev,$toUser");
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
		if($data >= 8){ $data = $this->status($data); }
		return in_array($data, [
			self::TICKET_ARCHIVED,
			self::TICKET_REJECTED,
			self::TICKET_COMPLETED
		]);
	}

	// TODO Add type default text
	public function add_message($ticket, $message, $user = NULL, $private = FALSE){
		if(empty($user)){ $user = $this->user; }
		if($user instanceof User){ $user = $user->id; }

		$data = [
			'uid' => $user,
			'tid' => $ticket,
			'cid' => $this->chat->id,
			'mid' => $this->telegram->message_id,
			'message' => $message,
			'type' => $type, // TODO text/photo/document
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

		$this->telegram->send
			->chat(self::CHAT_TICKETS)
			->notification(FALSE)
			->text_replace($str, [$this->telegram->user->id, strval($this->telegram->user)])
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
			$data = "PHOTO:" .$this->telegram->photo();
			$type = "photo";
		}elseif($this->telegram->document()){
			$data = "DOC:" .$this->telegram->document();
			$type = "document";
		}
		if(empty($data) or empty($type)){
			$this->telegram->send
				->text("Eing? " .$this->telegram->emoji(":kissing:"))
			->send();
			$this->end();
		}

		if($this->telegram->has_forward){
			// TODO CHECK forward not getting.
			$data .= ";ref:" .$this->telegram->forward_from->id;
			$ref = $this->telegram->forward_from->id;
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

		if(!($this->telegram->text() and $this->telegram->words() <= 2)){
			$private = $this->user->settings('ticket_private');
			$this->add_message($ticketId, $data, $this->user->id, $private);
		}
		// Si ya hay mas de 10 elementos desde la ultima persona que lo haya enviado, mata.
		if($this->count_last_messages($ticketId, $this->user->id) >= 10){
			// Mensajes excedidos, enviar respuesta / cambiar estado.
			$this->telegram->send
				->text($this->strings->get('ticket_writing_too_much'))
			->send();
			$this->ticket_writing_finish();
		}elseif($this->telegram->text_has($this->strings->get('ticket_action_send')) and $this->telegram->words() <= 2){
			if($this->count_last_messages($ticketId, $this->user->id) == 0){
				$this->telegram->send
					->text($this->strings->get('ticket_writing_nothing'))
				->send();
				$this->end();
			}
			$this->ticket_writing_finish();
		}elseif($this->telegram->text_has($this->strings->get('ticket_action_delete')) and $this->telegram->words() <= 2){
			$this->telegram->send
				->text($this->strings->get('ticket_writing_delete'))
			->send();

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
				$str = $this->strings->parse('ticket_writing_finished_ticketid', $ticket->id);
			}else{
				$this->status($ticketId, self::TICKET_DELAYED, TRUE);
				$str = $this->strings->get('ticket_writing_finished');
				// Enviar las respuestas al asignado.
			}
			$this->telegram->send
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
					$msnd = array();
					foreach($new as $msg){
						$msnd[] = $this->telegram->send
							->chat($ticket->uid)
							->notification(FALSE)
							->text($msg['message'])
						->send();
						usleep(mt_rand(100, 200) * 100);
					}
					// WIP Guardar los MID del bot por si hace reply a estos.
					$msnd = array_column($msnd, 'message_id');
					$utarget = new User($ticket->uid);
					$utarget->settings('ticket_reply_id', implode(',', $msnd));
				}
			}
		}

		$str = $this->telegram->emoji(":warning: ") ."El usuario %s - %s ha enviado mensajes al ticket #%s";
		$this->telegram->send
			->chat(self::CHAT_TICKETS)
			->notification(FALSE)
			->text_replace($str, [$this->telegram->user->id, strval($this->telegram->user), $ticketId])
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
					->button($this->telegram->emoji(":arrow_forward: ") .$this->strings->get_multi('ticket_status', self::TICKET_PROGRESS))
					->button($this->telegram->emoji(":speech_balloon: ") .$this->strings->get_multi('ticket_status', self::TICKET_WAITING))
					->button($this->telegram->emoji(":pause_button: ") .$this->strings->get_multi('ticket_status', self::TICKET_DELAYED))
				->end_row()
				->row()
					->button($this->telegram->emoji(":x: ") .$this->strings->get_multi('ticket_status', self::TICKET_REJECTED))
					->button($this->telegram->emoji(":white_check_mark: ") .$this->strings->get_multi('ticket_status', self::TICKET_COMPLETED))
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
