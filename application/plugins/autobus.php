<?php

// AMBTempsBus
function autobus_barcelona($codigo, $last = FALSE){
    $url = "http://www.ambmobilitat.cat/AMBtempsbus";
    $data = ['codi' => str_pad($codigo, 6, '0', STR_PAD_LEFT)];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $get = curl_exec($ch);
    curl_close ($ch);

    if(!empty($get)){
        $get = substr($get, strpos($get, '<ul data-role='));
        $pos = 0;
        $lineas = array();
        while( ($pos = strpos($get, '<li>', $pos)) !== FALSE){
            $pos = strpos($get, '<b>', $pos) + strlen('<b>');
            $last = strpos($get, '</b>', $pos) - $pos;
            $linea = strip_tags(substr($get, $pos, $last));
            if(strpos($linea, "no disponible")){ return array(); } // HACK
            $lineas[] = $linea;
        }
		// FIX La primera consulta devuelve valor nulo. Hacer segunda.
		if(empty($lineas) && !$last){ return autobus_barcelona($codigo, TRUE); }
        return $lineas;
    }
    return array();
}

// AUCORSA
function autobus_cordoba($codigo){
    $url = "http://m.aucorsa.es/action.admin_operaciones.php";
    $data = ['op' => 'tiempos', 'parada' => $codigo];
	$url = $url ."?" .http_build_query($data);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $get = curl_exec($ch);
    curl_close ($ch);

    if(!empty($get)){
        $json = json_decode($get, TRUE);
		if($json['res'] == FALSE){ return array(); }
		$lineas = array();
		foreach($json['estimaciones'] as $r){
			$lineas[] = "Linea " .$r['linea'] ." - en " .$r['minutos1'] ." min y " .$r['minutos2'] ." min.";
		}
		return $lineas;
    }
    return array();
}

// Titsa - Tenerife
function autobus_titsa($codigo){
	$url = "http://titsa.com/ajax/xGetInfoParada.php";
	$data = ['id_parada' => $codigo];
	$url = $url ."?" .http_build_query($data);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $get = curl_exec($ch);
    curl_close ($ch);

    if(!empty($get)){
        $json = json_decode($get, TRUE);
		if($json['success'] == FALSE){ return array(); }
		if(empty($json['lineas'])){ return array(); }
		if($json['parada'] == FALSE){ return array(); }
		$lineas = array();
		foreach($json['lineas'] as $r){
			$lineas[] = "Linea " .$r['id'] ." - en " .$r['tiempo'] ." min - " .$r['descripcion'];
		}
		return $lineas;
    }
    return array();
}

// EMT - Málaga
function autobus_malaga($codigo){
	$url = "http://www.emtmalaga.es/emt-mobile/informacionParada.html";
	$data = ['codParada' => $codigo];
	$url = $url ."?" .http_build_query($data);

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$get = curl_exec($ch);
	curl_close ($ch);

	if(!empty($get)){
		if(strpos($get, 'No se encontr') !== FALSE){ return array(); } // 404 Parada
		$get = substr($get, strpos($get, '<ul data-role='));
        $pos = 0;
        $lineas = array();
        while( ($pos = strpos($get, '<li', $pos)) !== FALSE){
            $pos = strpos($get, '<span', $pos);
            $last = strpos($get, '</li>', $pos) - $pos;
            $linea = trim(strip_tags(substr($get, $pos, $last)));
			$linea = preg_replace('/\s+/', ' ', $linea); // Remove space between
            $lineas[] = "Línea " .$linea;
        }
        return $lineas;
    }
    return array();
}

// EMT - Valencia
function autobus_valencia($codigo, $rec = FALSE){
	$url = "https://www.emtvalencia.es/ciudadano/modules/mod_tiempo/sugiere_parada.php";
	$data = ['id_parada' => $codigo];

	if($rec){
		$url = "https://www.emtvalencia.es/ciudadano/modules/mod_tiempo/busca_parada.php";
		$data = ['parada' => $codigo, 'adaptados' => 0, 'usuario' => 'Anonimo', 'idioma' => 'es'];
	}

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$get = curl_exec($ch);
	curl_close ($ch);

	if(!empty($get)){
		if($rec == FALSE){
			$pos = 0;
			$lineas = array();
			while( ($pos = strpos($get, '<li', $pos)) !== FALSE){
				$last = strpos($get, '</li>', $pos) - $pos;
				$linea = trim(strip_tags(substr($get, $pos, $last)));
				$linea = explode(" - ", $linea, 2);
				$lineas[$linea[0]] = $linea[1];
				$pos++; // HACK avoid infinte loop
			}
			if(isset($lineas[$codigo])){
				$parada = $codigo ." - " . $lineas[$codigo];
				return autobus_valencia($parada, TRUE);
			}
			return array(); // Not found
		}
		// ----------
		$pos = 0;
		$lineas = array();
		while(($pos = strpos($get, '<span', $pos)) !== FALSE){
			$pos = strpos($get, "title=", $pos) + strlen('title="');
			$last = strpos($get, '" ', $pos) - $pos;
			$parada = trim(substr($get, $pos, $last));

			$pos = strpos($get, "<span", $pos);
			$last = strpos($get, "<br>", $pos) - $pos;
			$linea = trim(strip_tags(substr($get, $pos, $last)));
			$linea = str_replace("&nbsp;", "", $linea);
			$pos++;

			$lineas[] = "Linea " .$parada ." - " .$linea;
		}
		return $lineas;
	}
	return array();
}

if(
	$this->telegram->text_command("amb") or
	$this->telegram->text_command("aucorsa") or
	$this->telegram->text_command("titsa") or
	$this->telegram->text_command("emtmal") or
	$this->telegram->text_command("emtval")
){
	$num = NULL;

	if($telegram->words() == 2){
		$num = $this->telegram->last_word(TRUE);
	}else{
		$num = $pokemon->settings($telegram->user->id, 'last_bus');
	}

	if(empty($num)){
		$this->telegram->send
			->text("<b>Uso: </b>" .$telegram->text_command() ." [Parada]", 'HTML')
		->send();
		return -1;
	}

    if(!is_numeric($num)){
        $this->telegram->send
            ->text($this->telegram->emoji(":times: ") ."No has puesto el código de parada correcto!")
        ->send();
        return -1;
    }

    $q = $this->telegram->send
        ->text($this->telegram->emoji("\ud83d\udd51 ") ."Ejecutando...")
    ->send();

	if($this->telegram->text_command("amb")){
		$paradas = autobus_barcelona($num);
	}elseif($this->telegram->text_command("aucorsa")){
		$paradas = autobus_cordoba($num);
	}elseif($this->telegram->text_command("titsa")){
		$paradas = autobus_titsa($num);
	}elseif($this->telegram->text_command("emtmal")){
		$paradas = autobus_malaga($num);
	}elseif($this->telegram->text_command("emtval")){
		$paradas = autobus_valencia($num);
	}

    $str = "No encuentro paradas.";
    if(!empty($paradas)){
        $str = "";
        foreach($paradas as $parada){ $str .= $parada ."\n"; }
		$pokemon->settings($telegram->user->id, 'last_bus', $num);
    }
    $this->telegram->send
        ->message($q['message_id'])
        ->chat(TRUE)
        ->text($str)
    ->edit('text');
    return -1;
}

?>
