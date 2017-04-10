<?php

function renfe_parada_cp($cp){
	$CI =& get_instance();
	$query = $CI->db
		->where('cp', $cp)
	->get('renfe');

	if($query->num_rows() == 1){ return $query->row(); }
	return NULL;
}

function renfe_numero($num){
	$CI =& get_instance();
	$query = $CI->db
		->where('cp', $num)
		->or_where('id', $num)
	->get('renfe');

	if($query->num_rows() == 1){ return $query->row(); }
	return NULL;
}

function renfe_texto($txt){
	$CI =& get_instance();
	$query = $CI->db
		->where('nombre', $txt)
		->or_where('corto', $txt)
	->get('renfe');

	if($query->num_rows() == 1){ return $query->row(); }
	return NULL;
}

function renfe_buscar($search){
	$CI =& get_instance();
	$query = $CI->db
		->where('nombre', $search)
		->or_where('corto', $search)
		->or_where('cp', $search)
		->or_where('id', $search)
	->get('renfe');

	if($query->num_rows() == 1){ return $query->row(); }
	return NULL;
}

function renfe_motes($texto, $retval = TRUE){
	$CI =& get_instance();
	$query = $CI->db
		->where('nombre', $texto)
	->get('renfe_motes');

	if($query->num_rows() == 1){
		if($retval){ return renfe_numero($query->row()->parada); }
		return $query->row();
	}
	return NULL;
}

function renfe_consulta($origen, $destino, $nucleo = 50, $hora = NULL){
	if(empty($hora)){ $hora = max(date("H") - 1, 0); }

	$url = "http://horarios.renfe.com/cer/hjcer310.jsp?";

	$data = [
		'nucleo' => $nucleo, // BARCELONA
		'o' => $origen,
		'd' => $destino,
		'tc' => 'DIA',
		'td' => 'D', // Tipo día -> Lunes y despues de festivo
		'df' => date("Ymd"),
		'th' => 1,
		'ho' => $hora,
		'I' => 's',
		'cp' => 'NO',
		'TXTInfo' => ''
	];

	$tipodia = [
		"L" => "Laborable (Martes a Jueves)",
		"D" => "Lunes y Despues de Festivo",
		"V" => "Viernes",
		"S" => "Sábados",
		"F" => "Domingos y Festivos",
	];

	$url .= http_build_query($data);

	$data = file_get_contents($url);
	$pos = strpos($data, "<table");
	$las = strpos($data, "</table>") + strlen("</table>");
	$las = $las - $pos;

	// Corta la web y centrate en la tabla
	$data = substr($data, $pos, $las);

	$fixes = [
		"'" => "\"",
		"align=center" => 'align="center"',
		'alt="Tren accesible" >' => 'alt="Tren accesible" />'
	];

	// Arregla el HTML de Renfe para parse correcto.
	$data = str_replace(array_keys($fixes), array_values($fixes), $data);

	$data = '<?xml version="1.0" encoding="iso-8859-1"?>' ."\n" .$data;
	$xml = simplexml_load_string($data);

	foreach($xml->tbody->tr as $fila){
		$hora = strval($fila->td[2]);
		if(strpos($hora, "Hora") !== FALSE){ continue; } // Omite la cabecera de la tabla
		$hora = str_replace(".", ":", $hora);

		if(strtotime($hora) < time()){ continue; } // Si el tren ha pasado, coge el siguiente

		return $hora;
	}

	return NULL;
}

if(
	$telegram->text_command("renfe") and
	in_array($telegram->words(), [2, 3])
){
	$origen = NULL;
	$destino = NULL;
	$pkuser = $pokemon->user($telegram->user->id);

	if($telegram->words() == 2){
		// Si tenemos su última ubicación guardada,
		// Buscar el tren más cercano y ese será el origen.

		/* if(strtolower($telegram->last_word()) == "casa"){
			if()
		} */
	}elseif($telegram->words() == 3){
		$origen = renfe_buscar($telegram->words(1));
		$destino = renfe_buscar($telegram->words(2));

		if(empty($origen) or empty($destino)){
			$telegram->send
				->text($telegram->emoji(":warning: ") ."Parada no reconocida.")
			->send();

			return -1;
		}
	}

	$str = "\ud83d\ude88 " .$origen->nombre ."\n"
		  ."\ud83c\udfc1 " .$destino->nombre ."\n\n";

	$q = $telegram->send
		->text($telegram->emoji($str ."\ud83d\udd51 ") . "Ejecutando...")
	->send();

	$res = renfe_consulta($origen->id, $destino->id, $origen->nucleo);

	if($res){
		$fecha = strtotime($res);
		$minutos = ceil(($fecha - time()) / 60);

		if($minutos <= 1){
			$str .= ":red-exclamation: inminente.";
		}else{
			$str .= "\u25b6\ufe0f En $minutos min. - $res";
		}

	}else{
		$str .= ":times: No hay trenes.";
	}

	$str = $telegram->emoji($str);

	$telegram->send
		->message($q['message_id'])
		->text($str)
	->edit('text');

	return -1;
}

?>
