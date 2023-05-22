<?php


if(
	!$telegram->is_chat_group() and
	$telegram->text_has("olvidate de mi") and
	$telegram->words() <= 5
){
	if($pokemon->user_flags($this->telegram->user->id, 'selfblock')){ return -1; }

	if(!$this->telegram->callback){
		$str = $this->telegram->emoji(':exclamation-red: ') ."¿Estás seguro? Después de esto, no hay peros que valga. No sabré nada más de tí." ."\n\n"
			."Recuerda que si quieres corregir tu información, puedes ir a @ProfesorOak_Ayuda .";

		$this->telegram->send
			->text($str)
			->inline_keyboard()
				->row_button("No", "olvidate de mi no", 'TEXT')
				->row_button("BORRAME YA", "olvidate de mi si", 'TEXT')
			->show()
			->notification(TRUE)
		->send();

		return -1;
	}

	$this->telegram->send->delete(TRUE);

	if($this->telegram->text_has("no")){
		return -1;
	}

	$id = $telegram->user->id;

	if($id >= 500000000){
		$this->pokemon->user_flags($id, 'newuser', TRUE);
	}

	$user = $this->pokemon->user($id);
	$flags = $this->pokemon->user_flags($id);

	$this->pokemon->user_flags($id, 'selfblock', TRUE);
	$this->pokemon->update_user_data($id, 'blocked', TRUE);
	$this->pokemon->update_user_data($id, 'anonymous', TRUE);
	$this->pokemon->settings($id, 'follow_join', TRUE);

	$telegram->send
		->text("Pues... Hasta nunca.")
	->send();

	$str = $this->telegram->emoji(':exclamation-red: ') .'<a href="tg://user?id=' .$id .'">' .$id .'</a> - ' .$user->username .' se borra.' ."\n";
	$str .= implode(', ', $flags);

	$chats = ['-1001108551764', '-221103258'];
	$this->telegram->send
		->chats($chats)
		->text($str, 'HTML')
		->notification(TRUE)
	->send();

	return -1;
}

?>
