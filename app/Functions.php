<?php

class Functions extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){}
	public function run(){ return; }

	public function message_assign_set($mid, $chat = NULL, $user = NULL){
		if(is_array($mid)){
			if(empty($user) and !empty($chat)){
				$user = $chat;
				$chat = $mid['chat']['id'];
				$mid = $mid['message_id'];
			}elseif(empty($chat) and empty($user)){
				$user = $mid['from']['id'];
				$chat = $mid['chat']['id'];
				$mid = $mid['message_id'];
			}
		}
		if(!$mid){ return FALSE; }

		$data = [
			'mid' => $mid,
			'cid' => $chat,
			'target' => $user,
			'date' => $this->db->now(),
		];

		$id = $this->db->insert('user_message_id', $data);
		// TODO Cache
		// $key = 'message_assign_' .md5($mid .$chat);
		// $this->cache->save($key, $user, 3600*24);
		return $id;
	}

	public function message_assign_get($mid = NULL, $chat = NULL){
		// mirar si hay reply y llamar directamente
		if(empty($mid)){
			if(!$this->telegram->has_reply){ return FALSE; }
			$mid = $this->telegram->reply->message_id;
			$chat = $this->chat->id;
		}

		if($chat instanceof Chat){ $chat = $chat->id; }
		// TODO Cache
		// $key = 'message_assign_' .md5($mid .$chat);
		// $cache = $this->cache->get($key);
		// if($cache){ return $cache; }
		$uid = $this->db
			->where('mid', $mid)
			->where('cid', $chat)
		->getValue('user_message_id', 'target');
		return $uid;
	}

}
