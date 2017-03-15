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
			$target = NULL;
			if($this->user->id == CREATOR){
				// if($this->telegram->reply_target('forward'))
			}
			$v = $this->get($this->telegram->words(1), NULL);
			$this->telegram->send
				->text($v)
			->send();
			$this->end();
		}elseif($this->telegram->text_command("set") && in_array($this->telegram->words(), [3,4])){

			$this->end();
		}
	}

	public function set($key, $value, $chat = NULL){
		if(empty($key) or empty($value)){ return FALSE; }
	}

	public function get($key, $chat = NULL){
		if(empty($chat)){ $chat = $this->chat->id; }

		// Si pide todos
		if(in_array(strtolower($key), ["all", "*"])){
			// Y no es el creador, fuera.
			if($this->user->id != CREATOR){ return NULL; }
		}else{
			// Si sólo pide uno
			$this->db->where('type', $key);
		}

		$res = $this->db
			->where('chat', $chat)
		->get('settings');

		if($this->db->count == 0){ return NULL; }
		elseif($this->db->count == 1){
			$v = $this->parse_value($res[0]['value']);
			if(is_array($v) or is_bool($v)){ $v = json_encode($v); }
			return $v;
		}

		$str = "";
		foreach($res as $k => $v){
			$v = $this->parse_value($v);
			if(is_array($v) or is_bool($v)){ $v = json_encode($v); }

			$str .= $k .": " .$v ."\n";
		}
		return $str;
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
