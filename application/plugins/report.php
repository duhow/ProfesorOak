<?php

if(
	$telegram->text_command("report") or
	$telegram->text_command("reportv")
){
	$target = NULL;
	$inputname = FALSE;
	if($telegram->has_reply){
		if($telegram->reply_is_forward){
			$target = $telegram->reply->forward_from['id'];
		}else{
			$target = $telegram->reply_user->id;
		}
	}elseif($telegram->text_mention()){
		$target = $telegram->text_mention();
		if(is_array($target)){ $target = key($target); }
	}elseif($telegram->words() == 2){
		$inputname = TRUE;
		$target = $telegram->last_word();
	}

	$pkuser = $pokemon->find($target);

	if($telegram->text_command("reportv")){

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
