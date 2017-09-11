<?php

class Autobus extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){
		if(
			$this->telegram->words() == 2 &&
			(
				$this->telegram->text_command("amb") or
				$this->telegram->text_command("aucorsa") or
				$this->telegram->text_command("titsa") or
				$this->telegram->text_command("emtmal") or
				$this->telegram->text_command("emtval")
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
		        ->text($this->telegram->emoji(":clock: ") ."Ejecutando...")
		    ->send();

			if($this->telegram->text_command("amb")){
				$paradas = $this->barcelona($num);
			}elseif($this->telegram->text_command("aucorsa")){
				$paradas = $this->cordoba($num);
			}elseif($this->telegram->text_command("titsa")){
				$paradas = $this->titsa($num);
			}elseif($this->telegram->text_command("emtmal")){
				$paradas = $this->malaga($num);
			}elseif($this->telegram->text_command("emtval")){
				$paradas = $this->valencia($num);
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
		    $this->end();
		}
	}

	private function curlear($url, $post = FALSE){
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		if(is_array($post)){
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}

		$get = curl_exec($ch);
		curl_close ($ch);

		return $get;
	}

	// AMBTempsBus
	public function barcelona($codigo, $last = FALSE){
	    $url = "http://www.ambmobilitat.cat/AMBtempsbus";
	    $data = ['codi' => str_pad($codigo, 6, '0', STR_PAD_LEFT)];

		$get = $this->curlear($url, $data);

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
			if(empty($lineas) && !$last){ return $this->barcelona($codigo, TRUE); }
	        return $lineas;
	    }
	    return array();
	}

	// AUCORSA
	public function cordoba($codigo){
	    $url = "http://m.aucorsa.es/action.admin_operaciones.php";
	    $data = ['op' => 'tiempos', 'parada' => $codigo];
		$url = $url ."?" .http_build_query($data);

		$get = $this->curlear($url);

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
	public function titsa($codigo){
		$url = "http://titsa.com/ajax/xGetInfoParada.php";
		$data = ['id_parada' => $codigo];
		$url = $url ."?" .http_build_query($data);

		$get = $this->curlear($url);

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
	public function malaga($codigo){
		$url = "http://www.emtmalaga.es/emt-mobile/informacionParada.html";
		$data = ['codParada' => $codigo];
		$url = $url ."?" .http_build_query($data);

		$get = $this->curlear($url);

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
	public function valencia($codigo, $rec = FALSE){
		$url = "https://www.emtvalencia.es/ciudadano/modules/mod_tiempo/sugiere_parada.php";
		$data = ['id_parada' => $codigo];

		if($rec){
			$url = "https://www.emtvalencia.es/ciudadano/modules/mod_tiempo/busca_parada.php";
			$data = ['parada' => $codigo, 'adaptados' => 0, 'usuario' => 'Anonimo', 'idioma' => 'es'];
		}

		$get = $this->curlear($url, $data);

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
}
