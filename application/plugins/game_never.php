<?php

function game_never_text($id = NULL){
	$CI =& get_instance();
	if($id !== NULL && is_numeric($id)){ $CI->db->where('id', $id); }

	$query = $CI->db
		->limit(1)
		->order_by('rand()')
	->get('game_never');

	if($query->num_rows() == 1){ return $query->row()->question; }
	return NULL;
}

if($telegram->callback && $telegram->callback == "yo nunca si"){
	if(strpos($telegram->text_message(), $telegram->user->first_name) !== FALSE){
		$telegram->answer_if_callback("Ya lo sabemos, tranquilo. " .$telegram->emoji("<3"), TRUE);
		return -1;
	}
	$str = $telegram->text_message() ."\n" .$telegram->user->first_name ." lo ha hecho.";
	$telegram->send
		->message(TRUE)
		->chat(TRUE)
		->inline_keyboard()
			->row_button("Yo si", "yo nunca si")
		->show()
		->text($str)
	->edit('text');

	$telegram->answer_if_callback("");

	return -1;
}

if(
	$telegram->text_has("yo nunca", TRUE) or
	$telegram->text_has(["oak", "profe", "profesor"], "yo nunca")
){
	$str = "Yo nunca " .game_never_text() .".";

	$telegram->send
		->inline_keyboard()
			->row_button("Yo si", "yo nunca si")
		->show()
		->text($str)
	->send();
	return -1;
}

if(
	$telegram->text_command("yonunca") &&
	$telegram->user->id == $this->config->item('creator')
){
	$id = NULL;
	$target = $telegram->chat->id;

	if($telegram->words() >= 2){
		$id = $telegram->words(1, TRUE);
	}

	if($telegram->words() >= 3){
		$target = $telegram->words(2);
	}

	$str = "Yo nunca " .game_never_text($id) .".";

	$telegram->send
		->inline_keyboard()
			->row_button("Yo si", "yo nunca si")
		->show()
		->chat($target)
		->text($str)
	->send();

	return -1;
}

?>
