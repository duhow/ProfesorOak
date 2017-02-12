<?php

// AMBTempsBus
function autobus_barcelona($codigo){
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

if(
	$this->telegram->words() == 2 &&
	(
		$this->telegram->text_command("amb") or
		$this->telegram->text_command("aucorsa") or
		$this->telegram->text_command("titsa")
	)
){
    $num = $this->telegram->last_word(TRUE);
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
	}

    $str = "No encuentro paradas.";
    if(!empty($paradas)){
        $str = "";
        foreach($paradas as $parada){ $str .= $parada ."\n"; }
    }
    $this->telegram->send
        ->message($q['message_id'])
        ->chat(TRUE)
        ->text($str)
    ->edit('text');
    return -1;
}

?>
