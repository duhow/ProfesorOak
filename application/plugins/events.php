<?php

if($this->telegram->is_chat_group()){ return; }

if(
	( $telegram->text_has("evento") or $telegram->text_command("event") ) and
	$telegram->words() == 2
){
	$key = $this->telegram->last_word();

	$query = $this->db
		->where('key', $key)
		->where('active', TRUE)
	->get('events');

	if($query->num_rows() != 1){
		$this->telegram->send
			->text("Evento no encontrado.")
		->send();

		return -1;
	}

	$event = $query->row();

	// ------------------

	$query = $this->db
		->where('event', $event->id)
		->where('uid', $this->telegram->user->id)
	->get('events_join');

	if($query->num_rows() == 1){
		$this->telegram->send
			->text("¡Ya estás apuntado al evento!")
		->send();

		return -1;
	}

	// ------------------
	$pkuser = $pokemon->user($this->telegram->user->id);

	$data = [
		'uid' => $this->telegram->user->id,
		'username' => $pkuser->username,
		'event' => $event->id,
		'date' => date("Y-m-d H:i:s"),
		'assisted' => FALSE
	];

	try {
		$q = $this->db->insert('events_join', $data);

		$this->telegram->send
			->text("Guachi! Te espero allí! :D")
		->send();
	} catch (Exception $e) {
		$this->telegram->send
			->text("Error general.")
		->send();
	}

	return -1;
}

?>
