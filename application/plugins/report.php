<?php

// Puede ser una descripción, un forward de mensaje o bien una foto.
function report_user($source, $target, $reason, $type = 'text'){
	$CI =& get_instance();
	return $CI->db
		->set('user', $source)
		->set('reported', $target)
		->set('reason', $reason)
		->set('type', $type)
		->set('date', date("Y-m-d H:i:s"))
	->insert('reports');
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
	$inputname = FALSE;
	if($telegram->has_reply){
		$target = $telegram->reply_target('forward');
	}elseif($telegram->text_mention()){
		$target = $telegram->text_mention();
		if(is_array($target)){ $target = key($target); }
	}elseif($telegram->words() == 2){
		$inputname = TRUE;
		$target = $telegram->last_word();
	}

	$pkuser = $pokemon->find($target);

	if($telegram->text_command("reportv")){
		// Información de sobre qué está reportado.
		return -1;
	}

	if(!$inputname && $telegram->words() >= 2){
		// Buscar motivo por defecto o avisar a duhow para unificar con existente.
		// Crear asociación en DB para futuros casos, quitar puntuaciones y demás.
		// Trabajar con tipos con base de flags comunes, como puede ser:
		// spam, troll, ratkid, gps/fly/hacks, etc.
	}
}


?>
