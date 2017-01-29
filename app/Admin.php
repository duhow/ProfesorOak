<?php

class Admin extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){

	}

	public function antipalmera(){
		if($this->telegram->is_chat_group() && $this->telegram->sticker()){
			$palmeras = [
		        'BQADBAADzw0AAu7oRgABumTXtan23SUC',
		        'BQADBAAD0Q0AAu7oRgABXE1L0Qpaf_sC',
		        'BQADBAAD0w0AAu7oRgABq22PAgABeiCcAg',
		        // GATO
		        'BQADBAAD4wEAAqKYZgABGO27mNGhdSUC',
		        'BQADBAAD5QEAAqKYZgABe9jp1bTT8jcC',
		        'BQADBAAD5wEAAqKYZgABiX1O201m5X0C',
				// Puke Rainbow
				'BQADBAADjAADl4mhCRp2GRnaNZ2EAg',
				'BQADBAADpgADl4mhCfVfg6PMDlAyAg',
				'BQADBAADqAADl4mhCVMHew7buZpwAg',
		    ];
			if(in_array($this->telegram->sticker(), $palmeras)){
		        // $admins = array();
		        /* if(function_exists('telegram_admins')){
		            $admins = telegram_admins(TRUE);
		            if(in_array($this->config->item('telegram_bot_id'), $admins)){
		                if(in_array($telegram->user->id, $admins)){ return; }
		                $telegram->send->text("¡¡PALMERAS NO!!")->send();
		                $telegram->send->kick($telegram->user->id, $telegram->chat->id);
		            }
		        } */
		        return TRUE;
		    }
		}
		return FALSE;
	}

	public function kick($user, $chat){
		$this->ban($user, $chat);
		return $this->unban($user, $chat);
	}

	public function ban($user, $chat){
		return $this->telegram->send->ban($user, $chat);
	}

	public function unban($user, $chat){
		return $this->telegram->send->unban($user, $chat);
	}
}
