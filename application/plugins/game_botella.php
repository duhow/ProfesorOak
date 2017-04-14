<?php

if(!$this->telegram->is_chat_group()){ return; }

if(
	$this->telegram->text_command("botella") or
	($this->telegram->text_has("botella") and $this->telegram->words() <= 4) or
	($this->telegram->text_has(["usuario", "persona"], ["al azar", "aleatorio", "random"]) and $this->telegram->words() <= 8)
){
	$can = $this->pokemon->settings($this->telegram->chat->id, 'play_games');
	if($can != NULL && $can == FALSE){ return; }

	$query = $this->db
		->where('cid', $this->telegram->chat->id)
		->order_by('RAND()', FALSE)
		->limit(1)
	->get('user_inchat');

	if($query->num_rows() == 1){
		$u = $this->telegram->send->get_member_info($query->row()->uid, $this->telegram->chat->id);

		$frases = [
			'Pues %s está de suerte.',
			'¡Le ha tocado a %s!',
			'Pobre %s, lo que le espera...',
			'¡%s se ha cagao en el bote de Colacao!'
		];
		$n = mt_rand(0, count($frases) - 1);

		$nombre = $u['user']['first_name'] .' ' .$u['user']['last_name'];
		$str = str_replace("%s", $nombre, $frases[$n]);

		$this->telegram->send
			->text($str)
		->send();
	}

	return -1;
}

?>
