<?php

class GameDubs extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		if(
			isset($this->chat->settings['dubs']) && $this->chat->settings['dubs'] == TRUE &&
			$this->telegram->key == "message"
		){
			$nums = array_merge(
				range(11111, 99999, 11111),
				range(1111, 9999, 1111),
				range(111, 999, 111)
				// range(11, 99, 11)
			);
			$lon = NULL;
			$id = $this->telegram->message;
			foreach($nums as $n){
				if(@strpos(strval($id), strval($n), strlen($id) - strlen($n)) !== FALSE){
					// $telegram->send->text("hecho en $id con $n")->send();
					$lon = strlen($n);
					break;
				}
			}
			$str = NULL;
			// if($lon == 2){ $str = "Dubs! :D"; }
			if($lon == 3){ $str = "Trips checked!"; }
			elseif($lon == 4){ $str = "QUADS *GET*!"; }
			elseif($lon == 5){ $str = "QUINTUPLE *GET! OMGGG!!*"; }
			if($str){
				$this->telegram->send
					->reply_to(TRUE)
					->text($str, TRUE)
				->send();
			}
		}
	}
}
