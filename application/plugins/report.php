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

// Devuelve ID de grupo.
function report_multiaccount_exists($names, $retall = FALSE){
	$CI =& get_instance();
	if(!is_array($names)){ $names = [$names]; }
	$query = $CI->db
		->where_in('username', $names)
	->get('user_multiaccount');

	if($query->num_rows() == 0){ return FALSE; }
	if(!$retall){ return $query->row()->grouping; }

	$gid = $query->row()->grouping;
	return report_multiaccount_grouping($gid);
}

// Devuelve el ultimo ID de grupo creado.
function report_multiaccount_last_grouping(){
	$CI =& get_instance();

	$query = $CI->db
		->select('grouping')
		->order_by('grouping', 'DESC')
		->limit(1)
	->get('user_multiaccount');

	if($query->num_rows() == 0){ return 0; }
	return $query->row()->grouping;
}

function report_multiaccount_grouping($group, $onlynames = FALSE){
	$CI =& get_instance();

	$query = $CI->db
		->where('grouping', $group)
	->get('user_multiaccount');

	if($query->num_rows() == 0){ return array(); }
	$final = ['grouping' => $group];
	$final['usernames'] = array_column($query->result_array(), 'username');

	if($onlynames){ return $final['usernames']; }
	return $final;
}

function report_multiaccount_add($users, $referer = NULL, $group = NULL){
	$CI =& get_instance();

	if(!is_array($users)){ $users = [$users]; }
	if(empty($group)){ $group = report_multiaccount_last_grouping() + 1; }

	$data = array();
	foreach($users as $user){
		$data[] = [
			'grouping' => $group,
			'username' => $user,
			'referer' => $referer,
			'date' => date("Y-m-d H:i:s")
		];
	}

	return $CI->db->insert_batch('user_multiaccount', $data);
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

	$pkuser = $pokemon->user($target, TRUE);
	if($pkuser){
		if(!empty($pkuser->username)){
			$target = $pkuser->username;
		}else{
			$target = $pkuser->telegramid;
		}
	}

	// Evitar falsos resultados.
	if(strlen($target) <= 3 or strpos($target, " ") !== FALSE){ return -1; }

	if($telegram->text_command("reportv")){
		// Información de sobre qué está reportado.
		return -1;
	}

	$pkuser = $pokemon->user($telegram->user->id);
	if(strtolower($pkuser->username) == strtolower($target)){
		$this->telegram->send
			->notification(FALSE)
			->text($this->telegram->emoji(":warning: ") ."¿Porqué ibas a reportarte a ti mismo?")
		->send();

		$this->telegram->send
			->notification(FALSE)
			->chat(TRUE)
			->message(TRUE)
			->forward_to($this->config->item('creator'))
		->send();

		$this->telegram->send
			->notification(TRUE)
			->chat($this->config->item('creator'))
			->text("Autoreporte del usuario " .$telegram->user->id .".")
		->send();

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
			'bot' => ["bot", "bots", "botter"],
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

		$str = ":id: %s\n"
				.":male: %s\n"
				.":warning: %s\n\n"
				.$str;
		$str = $this->telegram->emoji($str);

		$this->telegram->send
			->notification(FALSE)
			->chat("-246585563") // Reportes
			->text_replace($str, [$this->telegram->user->id, $target, $type])
		->send();

		$this->telegram->send
			->notification(TRUE)
			->chat(TRUE)
			->message(TRUE)
			->forward_to("-246585563") // Reportes
		->send();
	}
	return -1;
}

elseif($this->telegram->text_command("reportm")){
	if($pokemon->user_flags($telegram->user->id, ['troll', 'ratkid', 'rager', 'spam', 'bot', 'hacks', 'gps', 'fly', 'multiaccount', 'report'])){ return -1; }
	$pokeuser = $pokemon->user($this->telegram->user->id);
	if(!$pokeuser->verified or strtotime("+1 month", strtotime($pokeuser->register_date)) > time() ){
		$this->telegram->send
			->text($this->telegram->emoji(":warning: ") ."Sólo pueden reportar los usuarios validados de hace tiempo.")
		->send();
		return -1;
	}

	if($this->telegram->words() <= 2){
		if($pokemon->command_limit("report", $telegram->chat->id, $telegram->message, 5)){ return -1; }

		$str = "Uso: " .$this->telegram->text_command() ." <Nombre principal> <Nombre 1> <Nombre 2> ...";
		$this->telegram->send
			->text($str)
		->send();

		return -1;
	}

	$names = $this->telegram->words(TRUE);
	array_shift($names); // Quitar comando

	$res = report_multiaccount_exists($names, TRUE);
	if($res){
		$final_names = array_diff($names, $res['usernames']);
		$grouping = $res['grouping']; // Agregar sobre el primer grouping que exista.
		// Bucle mientras haya nombres repetidos / agrupados.
		while(!empty($final_names) && report_multiaccount_exists($final_names)){
			$res = report_multiaccount_exists($final_names, TRUE);
			$final_names = array_diff($final_names, $res['usernames']);
		}

		$str = "No hay usuarios nuevos.";
		if(count($final_names) > 0){
			// Transformar original el acortado / final.
			$names = array_values($final_names);
			$q = report_multiaccount_add($names, $this->telegram->user->id, $grouping);
			$str = $this->telegram->emoji(":ok: ") . count($names) ." usuarios agregados.";
		}

		$this->telegram->send
			->text($str)
		->send();
	}else{
		$q = report_multiaccount_add($names, $this->telegram->user->id);
		$str = $this->telegram->emoji(":ok: ") . "%s usuarios nuevos agregados.";
		$this->telegram->send
			->text_replace($str, count($names))
		->send();
	}

	return -1;
}

?>
