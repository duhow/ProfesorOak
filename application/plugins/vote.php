<?php

function vote_info($vote){
	$CI =& get_instance();
	$query = $CI->db
		->where('id', $vote)
	->get('vote_question');
	if($query->num_rows() != 1){ return NULL; }
	return $query->row();
}

function vote_count($vote, $retall = FALSE){
	$CI =& get_instance();
	$query = $CI->db
		->where('qid', $vote)
	->get('vote_response');
	if($retall === FALSE){ return $query->num_rows(); }
	$votes = array();
	foreach($query->result_array() as $v){
		$votes[$v['response']]++;
	}
	return $votes;
}

function vote_closed($vote, $retall = FALSE){
	$CI =& get_instance();
	$query = $CI->db
		->where('id', $vote)
	->get('vote_question');
	if($query->num_rows() != 1){ return TRUE; } // Aunque no exista, o debería ser NULL ?
	return ($retall === FALSE ? (bool) $query->row()->closed : $query->row());
}

function vote_close($vote, $value = TRUE){
	$CI =& get_instance();
	return $CI->db
		->where('id', $vote)
		->set('closed', $value)
	->update('vote_question');
}

function vote_response($user, $vote, $value = NULL){
	$CI =& get_instance();
	if($value === NULL or $value === TRUE){
		// GET
		$query = $CI->db
			->where('uid', $user)
			->where('qid', $vote)
		->get('vote_response');
		if($query->num_rows() == 1){
			return ($value === TRUE ? $query->row() : (int) $query->row()->response);
		}
	}

	$response = vote_response($user, $vote);

	if($response === NULL){
		// INSERT
		$data = [
			'uid' => $user,
			'qid' => $vote,
			'response' => $value
		];
		$CI->db->insert('vote_response', $data);
		$id = $CI->db->insert_id();

		// TODO date_limit
		$info = vote_info($vote);
		$close = ($info->votelimit >= vote_count($vote));
		vote_close($vote, $close);
		if($close == TRUE){
			$info = vote_info($vote);
			$type = strtolower($info->type);
			if($type != "vote" && function_exists('vote_trigger_' .$type)){
				call_user_func('vote_trigger_' .$type, $vote);
			}
		}
		return $id;
	}

	$CI->db
		->where('uid', $user)
		->where('qid', $vote);

	if($response == $value){
		// DELETE
		return $CI->db->delete('vote_response');
	}

	// UPDATE
	return $CI->db->update('vote_response', $data);
}

function vote_display($vote, $tg = NULL){
	$info = vote_info($vote);
	if(empty($info)){
		$tg->send
			->text("Esa votación no existe.")
		->send();
		exit();
		// return -1;
	}

	$options = unserialize($info->options);
	$title = $info->title;
	$len = array();

	$newoptions = array();

	for($i = 0; $i < count($options); $i++){
		// Contar los strpos de UNICODE estando codificado,
		// ya que al decode sigue contanddo 4 bytes cuando es sólo un carácter.
		$lentmp = 0;
		$c = 0;
		if(strpos($options[$i], '\u') !== FALSE){
			$pos = 0;
			while($pos !== FALSE){
				$pos = strpos($options[$i], '\u', $pos);
				if($pos !== FALSE){ $pos++; }
				$lentmp = $lentmp + (-4 + 1);
			}
		}
		// EXTRA: aparte de buscar UNICODES, busca \ud83d y si lo encuentras, no lo cuentes.
		// Al menos, no una vez. El problema es si son varios emojis.
		if(strpos($options[$i], '\ud83d') !== FALSE){
			$lentmp = $lentmp + 4 + 2; // ya habrá descontado UNICODE anterior
		}
		if(strpos($options[$i], '\u263a') !== FALSE){
			$lentmp = $lentmp + 4;
		}
		$options[$i] = json_decode('"' .$options[$i] .'"');
		$lentmp = $lentmp + strlen($options[$i]);
		// $lentmp = strlen($options[$i]);
		$len[$i] = $lentmp;
	}

	sort($len);
	$lmax = 0;
	$lmin = 100;
	foreach($len as $l){
		if($l > $lmax){ $lmax = $l; }
		if($l < $lmin){ $lmin = $l; }
	}

	$amount = count($options);
	$i = 0;

	// TODO cambio en Telegram API para agregar función.
	while($i < $amount){
		$buttons = array();
		if($lmax <= 2){
			// 6 x row
			foreach($options as $j => $opt){
				$i = ($j+1);
				$buttons[] = [$opt, "vota $vote $i"];
			}
			$i = $amount;
		}elseif($lmax <= 14 && ($amount - $i+1) > 1){
			// 2 x row
			$buttons[] = [$options[$i], "vota $vote $i"];
			$i++;
			$buttons[] = [$options[$i], "vota $vote $i"];
		}else{
			// 1 x row
			$buttons[] = [$options[$i], "vota $vote $i"];
		}

		$tg->send->inline_keyboard()->row($buttons);
		$i++;
	}

	$tg->send
		->inline_keyboard()->show()
		->text(json_decode('"' .$title .'"'))
	->send();
}

function vote_register($user, $question, $options, $limit = NULL){
	$CI =& get_instance();

	$data = array();
	if(strtotime($limit) > time() or $limit > 1000){
		if(!is_numeric($limit)){ $limit = strtotime($limit); }
		$data['date_limit'] = date("Y-m-d H:i:s", $limit);
	}else{
		$data['votelimit'] = $limit;
	}

	$data['owner'] = $user;
	$data['title'] = $question;
	$data['options'] = serialize($options);
	$data['date'] = date("Y-m-d H:i:s");

	$CI->db->insert('vote_question', $data);
	return $CI->db->insert_id();
}

if($telegram->text_command("vote") && $telegram->words() >= 4){
	$text = substr($telegram->text_encoded(), 1, -1); // remove quotes
	$text = str_replace("/vote ", "", $text);
	$text = explode('\n', $text);
	if(count($text) == 1){
		$telegram->send
			->text("¿Y las opciones?")
		->send();
		return -1;
	}
	// $text[0] = str_replace('\\', "", $text[0]);
	if(strpos($text[0], '\\') === 0){ $text[0] = substr($text[0], 1); }
	$len = array();
	for($i = 1; $i < count($text); $i++){
		$text[$i] = trim($text[$i]);
		$len[$i] = strlen($text[$i]);
	}

	$title = array_shift($text);
	$count = $pokemon->group_count_members($telegram->chat->id) * 5 / 100;
	$reg = vote_register($telegram->user->id, $title, $text, max(5, $count));
	if($reg){
		vote_display($reg, $telegram);
	}

	return -1;
}

if($telegram->callback && strpos($telegram->callback, "vota") === 0){
	// $options = explode(" ", $telegram->callback);
	// $vote = $options[1];
	// $select = $options[2];

	// $telegram->send->text("has votado ")->send();
	// vote_response($telegram->user->id, $vote, $select);

	// $telegram->answer_if_callback("¡Hecho!");

	$telegram->answer_if_callback("");
	return -1;
}

if($telegram->user->id == $this->config->item('creator')){
	// $telegram->send->text("asdf")->send();
	return -1;
}

?>
