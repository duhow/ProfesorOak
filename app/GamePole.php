<?php

class GamePole extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public function pole(){
		return $this->polear(1);
	}

	public function subpole(){
		return $this->polear(2);
	}

	public function bronce(){
		return $this->polear(3);
	}

	private function polear($position){

	}

	public function polerank(){

	}

	protected function hooks(){
		if($this->telegram->text_has(["pole", "!pole"], TRUE)){
			return $this->pole();
		}elseif($this->telegram->text_has(["subpole", "!subpole"], TRUE)){
			return $this->subpole();
		}elseif($this->telegram->text_has(["bronce", "!bronce"], TRUE)){
			return $this->bronce();
		}elseif($this->telegram->text_has(["!polerank", "!pole rank"]), TRUE){
			return $this->polerank();
		}
	}
}
