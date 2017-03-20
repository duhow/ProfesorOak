<?php

class Location extends TelegramApp\Module {
	protected $runCommands = FALSE;

	// TODO Módulo ideado para interactuar rápidamente con las ubicaciones.

	public function run(){
		if(!$this->telegram->location()){ return; }
	}

	protected function hooks(){

	}
}
