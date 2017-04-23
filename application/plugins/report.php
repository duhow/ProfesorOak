<?php

// Puede ser una descripción, un forward de mensaje o bien una foto.
function report_user($source, $target, $reason = NULL, $type = NULL){
	$CI =& get_instance();
	$data = [
		'user' => $source,
		'reported' => $target,
		'reason' => $reason,
		'type' => $type,
		'date' => date("Y-m-d H:i:s"),
	];
	$res = $CI->db->insert('reports', $data);
	if($res !== FALSE){ return $CI->db->insert_id(); }
	return FALSE;
}

function report_exists($user, $target, $type = NULL){
	$CI =& get_instance();
	if(!empty($type)){ $CI->db->where('type', $type); }
	$query = $CI->db
		->where('user', $user)
		->where('reported', $target)
	->get('reports');
	if($query->num_rows() == 0){ return FALSE; }
	return $query->row_array();
}

function report_user_get($user, $target = TRUE, $valid = TRUE){
	$CI =& get_instance();

	$target = ($target === TRUE ? 'reported' : 'user');
	if($valid){ $CI->db->where('valid', TRUE); }
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
	if($pokemon->user_flags($telegram->user->id, ['troll', 'ratkid', 'rager', 'spam', 'bot', 'hacks', 'gps', 'fly', 'multiaccount', 'report'])){ return -1; }
	$pokeuser = $pokemon->user($this->telegram->user->id);
	if(!$pokeuser->verified or strtotime("+1 month", strtotime($pokeuser->register_date)) > time() ){
		$this->telegram->send
			->text($this->telegram->emoji(":warning: ") ."Sólo pueden reportar los usuarios validados de hace tiempo.")
		->send();
		return -1;
	}

	$target = NULL;
	$type = NULL;
	$extra = NULL;

	// Mención primero por si se hace autoreply de una foto.
	if($telegram->text_mention()){
		$target = $telegram->text_mention();
		if(is_array($target)){ $target = key($target); }
	}elseif($telegram->words() >= 2 && strtolower($telegram->words(1)) != "por"){
		$target = $telegram->words(1);
	}elseif($telegram->has_reply){
		$target = $telegram->reply_target('forward')->id;
	}else{
		$str = $telegram->text_command() ." <usuario>";
		if($telegram->text_command("report")){ $str .= " <motivo>"; }

		$this->telegram->send
			->notification(FALSE)
			->text($str)
		->send();
		return -1;
	}

	$pkuser = $pokemon->find($target, TRUE);
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
			'hacks' => ["hack", "hacks", "trampa", "trampas"],
			'bot' => ["bot", "bots"],
			'multiaccount' => ["multi", "multiple", "multicuenta", "multicuentas"],
			'spam' => ["spam", "publi", "publicidad"],
			'troll' => ["liante", "liarla", "trol", "troll", "acusar"]
		];

		foreach($flags as $k => $v){
			if($telegram->text_has($v)){
				$type = $k; break;
			}
		}
	}

	$reason = $telegram->words(2, 100);
	if($telegram->has_reply){
		if(isset($telegram->reply->photo)){
			$phts = $telegram->reply->photo;
			$photo = array_pop($phts);
			$extra = ['photo' => $photo['file_id']];
		}elseif(isset($telegram->reply->text)){
			$extra = ['text' => $telegram->reply->text];
		}
	}

	// TODO REWRITE

	$report = $extra;
	$report['reason'] = $reason;
	$report = serialize($report);

	if($target && ($type or $reason or $extra)){
		$str = ":times: Error al generar report.";
		if(report_exists($this->telegram->user->id, $target, $type)){
			$res = FALSE;
			$str = ":times: Report duplicado.";
		}else{
			$res = report_user($this->telegram->user->id, $target, $report, $type);
		}

		if($res){
			report_user_chat($res, $this->telegram->chat->id);
			$str = ":ok: Report enviado!";
		}

		$this->telegram->send
			->text($this->telegram->emoji($str))
		->send();
	}
	return -1;
}

?>
