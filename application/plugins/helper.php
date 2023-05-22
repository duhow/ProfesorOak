<?php

if(
	$this->telegram->text_command("ch") and
	$this->telegram->words() > 1 and
	$this->telegram->has_reply and
	$this->telegram->reply_is_forward and
	in_array('helper', $this->pokemon->user_flags($this->telegram->user->id))
){
	$chgs = $this->telegram->words(TRUE);
	array_shift($chgs); // Quitar comando

	$target = $this->pokemon->user($this->telegram->reply_target('forward')->id);
	if(!$target){
		$this->telegram->send
			->text($this->telegram->emoji(":times: ") ."Usuario no registrado.")
		->send();
		return -1;
	}

	$data = array();
	$change = array();

	foreach($chgs as $val){
		if(empty($val)){ continue; }
		if(
			!array_key_exists('team', $data) and
			strlen($val) == 1 and
			in_array(strtoupper($val), ["R", "B", "Y"]) and
			strtoupper($val) != $target->team
		){
			$data['team'] = strtoupper($val);
			$data['verified'] = FALSE;
			$change[] = 'equipo';
		}elseif(
			!array_key_exists('username', $data) and
			strlen($val) >= 4 and
			!is_numeric($val) and
			$val != $target->username
		){
			$data['username'] = $val;
			$data['verified'] = FALSE;
			$change[] = 'nombre';
		}elseif(
			!array_key_exists('lvl', $data) and
			intval($val) > 5 and intval($val) <= 40 and
			intval($val) != $target->lvl
		){
			$data['lvl'] = intval($val);
			$change[] = 'nivel';
		}
	}

	if(empty($data)){ return -1; }

	if(isset($data['username'])){
		$name = $this->db
			->select(['username', 'telegramid'])
			->where('username', $data['username'])
			->where('telegramid !=', $target->telegramid)
		->get('user');
		if($name->num_rows() == 1){
			$this->telegram->send
				->text($this->telegram->emoji(":warning: ") ."Nombre duplicado. Solicita cambio.")
			->send();
			return -1;
		}
	}

	$res = $this->db
		->where('telegramid', $target->telegramid)
	->update('user', $data);

	$str = $this->telegram->emoji(":green-check: ") ."Cambio " .implode(", ", $change) ."!";
	$this->telegram->send
		->text($str)
	->send();

	$str = $this->telegram->emoji(":warning: ") .$this->telegram->user->first_name ." cambia " .implode(", ", $change)
			." a " .$target->telegramid ." (" .$target->username .")";

	$this->telegram->send
		->notification(FALSE)
		->chat("-221103258")
		->text($str)
	->send();

	return -1;
}

?>
