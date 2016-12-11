<?php

if($telegram->text_has("participar") && $telegram->text_has(["página", "sorteo"]) && $telegram->words() <= 9){
	if($telegram->is_chat_group()){
		$str = "Puedes conseguir objetos especiales entrando en mi web!\nhttp://oak.duhowpi.net";
	}else{
		$key = md5($telegram->user->id .":" .time());
		$query = $this->db
			->set('uid', $telegram->user->id)
			->set('webkey', $key)
		->insert('weblogin');

		$str = "Este es un link exclusivo para ti, ¡no se lo pases a nadie!\nhttp://oak.duhowpi.net/login/$key";
	}

	$telegram->send
		->notification(FALSE)
		->text($str)
	->send();

	return -1;
}

if($telegram->is_chat_group()){ return; }

if($telegram->text_command("start") && $telegram->text_has("weblogin") && $telegram->words() <= 3){
	$this->db
		->set('uid', $telegram->user->id)
		->set('webkey', $telegram->last_word(TRUE))
	->insert('weblogin');

	$telegram->send
		->text("¡Login hecho! Ya puedes volver.")
		->inline_keyboard()
			->row_button("Abrir web", "http://oak.duhowpi.net")
		->show()
	->send();
	return -1;
}


?>
