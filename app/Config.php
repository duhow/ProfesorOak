<?php

class Config extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		if($this->telegram->is_chat_group() and !$this->chat->is_admin($this->user)){ return; }
		parent::run();
	}

	protected function hooks(){
		if($this->telegram->text_command("get") && $this->telegram->words() > 1){
			if($this->telegram->text_has(["private", "p"])){
				$this->telegram->send->chat($this->user->id);
			}
			$this->get($this->telegram->words(1));
			$this->end();
		}elseif($this->telegram->text_command("set") && in_array($this->telegram->words(), [3,4])){

			$this->end();
		}
	}

	public function set($key, $value, $chat = NULL){
		if(empty($key) or empty($value)){ return FALSE; }
	}

	public function get($key, $chat = NULL){
		$lchat = $this->chat;
		if(!empty($chat)){ $lchat = new Chat($chat); }
		$lchat->load();

		$res = @$lchat->settings[$key];
		if(in_array(strtolower($key), ["all", "*"]) && $this->user->id == CREATOR){
			$res = "";
			foreach($lchat->settings as $k => $v){
				$v = $this->parse_value($v);
				if(is_array($v)){ $v = json_encode($v); }

				$res .= $k .": " .$v ."\n";
			}
		}

		return $this->telegram->send
			->text($res)
		->send();
		// return loaded settings?
	}

	public function parse_value($value){
		if(in_array(strtolower($value), ["yes", "true", "on"]) or $value == 1){ $value = TRUE; }
		elseif(in_array(strtolower($value), ["no", "false", "off"]) or $value == 0){ $value = FALSE; }

		// Array Type converter
		elseif(is_array($value)){ $value = serialize($value); }
		elseif(@unserialize($value) !== FALSE){ $value = unserialize($value); }

		// TODO detect \d+,\d+ and unserialize

		return $value;
	}


}
