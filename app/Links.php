<?php

class Links extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){
		// Link del grupo azul
		// Enlace de Valencia
		// Link de Barcelona Raids
		// Link Zaragoza Rojo
	}

	public function get_search($search){
		// Buscar por tipo, prioritariamente general
		// Evitar que sea de color si no es del mismo color.
		// Si es color, enviar por privado.

		// Buscar por setting name
		// Buscar por CP, nombre, cercanía, Google Maps, ubicacion.
	}

	public function get_by_location($location){

	}
}
