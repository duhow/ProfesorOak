<?php

class Location extends TelegramApp\Module {
	protected $runCommands = FALSE;

	// TODO Módulo ideado para interactuar rápidamente con las ubicaciones.

	public function run(){
		if(!$this->telegram->location()){ return; }
	}

	protected function hooks(){
		// Privado, NO CANAL.
		if(!$this->chat->is_group()){

		}
	}

	private function live_parse(){
		// Logear live location, y poner la ultima en settings.
		//
	}

	public function distance($data, $target){
		// $data puede ser User, Chat, array, string de coord o Telegram\Location
		// $target se procesara igual, pero priorizando que haya coordenadas.
		// Si cualquiera de los dos da blanco, NULL.

		if($data instanceof User or $data instanceof Chat){
			
		}
	}

	public function string_parser($string, $retall = FALSE){
		$regex = '/([+-]?\d+\.\d+)[,;]\s?([+-]?\d+\.\d+)/';
		$r = preg_match_all($regex, $string, $matches);
		if(!$r){ return FALSE; }
		$res = array();
		if(isset($matches[1]) and isset($matches[2])){
			for($i = 0; $i < $r; $i++){
				$res[] = [$matches[1][$i], $matches[2][$i]];
			}
		}
		if(!$retall){ return $res[0]; }
		return $res;
	}
}
