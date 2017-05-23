<?php

if(!$this->telegram->is_chat_group()){ return; }

$blackwords = $this->pokemon->settings($this->telegram->chat->id, 'blackword');

if($this->telegram->text_command("bw") && $telegram->words() > 1){
	// Target chat to save
	$target = $this->telegram->chat->id;

	$query = $this->db
		->where('type', 'admin_chat')
		->where('value', $target)
	->get('settings');

	// Si estás en un grupo admin, cargar info del grupo que administras.
	if($query->num_rows() == 1){
		$target = $query->row()->uid;
		$blackwords = $this->pokemon->settings($target, 'blackword');
	}else{
		// Si estás en el grupo no-admin porque no existe
		// y tu no eres admin, entonces... adios.
		if(!in_array($this->telegram->user->id, telegram_admins(TRUE))){ return; }
	}

    $txt = $this->telegram->words(1, 10);
    $txt = strtolower(trim($txt));

    if(!empty($blackwords)){
        $blackwords = explode(",", $blackwords);
        if(!is_array($blackwords)){ $blackwords = [$blackwords]; }
    }else{
        $blackwords = array();
    }

	$add = !(in_array($txt, $blackwords)); // Agregar si NO existe.
	if($add){
		$blackwords[] = $txt;
	}else{
		$k = array_search($txt, $blackwords);
		if($k !== FALSE){ unset($blackwords[$k]); }
		$blackwords = array_values($blackwords);
	}
    $blackwords = array_unique($blackwords);
    if(count($blackwords) == 1){ $blackwords = $blackwords[0]; }
    else{ $blackwords = implode(",", $blackwords); }
    $this->pokemon->settings($target, 'blackword', $blackwords);

    $this->telegram->send
        ->text($this->telegram->emoji(":ok: ") .($add ? "Agregado." : "Quitado."))
    ->send();
    return -1;
}

elseif($telegram->text_command("bwl")){
	// Target chat to save
	$target = $this->telegram->chat->id;

	$query = $this->db
		->where('type', 'admin_chat')
		->where('value', $target)
	->get('settings');

	// Si estás en un grupo admin, cargar info del grupo que administras.
	if($query->num_rows() == 1){
		$target = $query->row()->uid;
		$blackwords = $this->pokemon->settings($target, 'blackword');
	}else{
		// Si estás en el grupo no-admin porque no existe
		// y tu no eres admin, entonces... adios.
		if(!in_array($this->telegram->user->id, telegram_admins(TRUE))){ return; }
	}

	$str = "";
	$blackwords = explode(",", $blackwords);
	if(!is_array($blackwords)){ $blackwords = [$blackwords]; }
	foreach($blackwords as $word){
		$str .= "- $word\n";
	}

	$this->telegram->send
		->text($str)
	->send();

	return -1;
}

if(!empty($blackwords)){
    $blackwords = (strpos($blackwords, ",") === FALSE ? [$blackwords] : explode(",", $blackwords) );
    if(!$this->telegram->text_contains($blackwords)){ return; }
    if(in_array($this->telegram->user->id, telegram_admins(TRUE))){ return; }

    $adminchat = $pokemon->settings($this->telegram->chat->id, 'admin_chat');
    if($adminchat){
        $this->telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->forward_to($adminchat)
        ->send();
    }else{
        $q = $this->telegram->send
            ->text("Eh, te calmas.")
        ->send();

		sleep(2);
		$this->telegram->send->delete($q);
    }

	$this->telegram->send->delete(TRUE);
	return -1;
}

?>
