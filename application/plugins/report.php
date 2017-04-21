<?php

// Puede ser una descripción, un forward de mensaje o bien una foto.
function report_user($source, $target, $reason, $type = 'text'){
	$CI =& get_instance();
	$data = [
		'user' => $source,
		'reported' => $target,
		'reason' => $reason,
		'type' => $type,
		'date' => date("Y-m-d H:i:s"),
	];
	return $CI->db->insert('reports', $data);
}

function report_user_get($user, $target = TRUE){
	$CI =& get_instance();

	$target = ($target === TRUE ? 'reported' : 'user');
	$query = $CI->db
		->where($target, $user)
	->get('reports');

	return $query->result_array();
}

function report_user_chat($id, $chat){
	$CI =& get_instance();
	return $CI->db
		->where('id', $id)
		->set('chat', $chat)
	->update('reports');
}

if(
	$telegram->text_command("report") or
	$telegram->text_command("reportv")
){
	$target = NULL;
	if($telegram->has_reply){
		$target = $telegram->reply_target('forward');
	}elseif($telegram->text_mention()){
		$target = $telegram->text_mention();
		if(is_array($target)){ $target = key($target); }
	}elseif($telegram->words() >= 2){
		$target = $telegram->words(1);
	}else{
		$str = $telegram->text_command() ." <usuario>";
		if($telegram->text_command("report")){ $str .= " <motivo>"; }

		$this->telegram->send
			->notification(FALSE)
			->text($str)
		->send();
		return -1;
	}

	$pkuser = $pokemon->find($target);
	if($pkuser){
		if(!empty($pkuser->username)){
			$target = $pkuser->username;
		}else{
			$target = $pkuser->telegramid;
		}
	}

	if($telegram->text_command("reportv")){
		// Información de sobre qué está reportado.
		return -1;
	}

	if($telegram->words() >= 2){
		// Buscar motivo por defecto o avisar a duhow para unificar con existente.
		// Crear asociación en DB para futuros casos, quitar puntuaciones y demás.
		// Trabajar con tipos con base de flags comunes, como puede ser:
		// spam, troll, ratkid, gps/fly/hacks, etc.
		$flags = [
			'fly' => ["volar", "volador", "fly", "gps", "fakegps", "fake gps"],
			'spam' => ["spam", "publi", "publicidad", ""]
		]
	}
}


?>
