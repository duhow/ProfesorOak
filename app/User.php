<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User extends TelegramApp\User {
	public function __construct($input){
		  parent::__construct($input);
	}

	// Custom Properties
	// Flags
	// Settings

	private function set_chat($chat = NULL){
		if($chat !== NULL){ $this->chat = $chat; }
		else{ $chat = $this->chat; }
		return $chat;
	}

	function ban($chat = NULL){
		$chat = $this->set_chat($chat);
		return ($this->telegram->send->ban($this->id, $chat) !== FALSE);
	}

	function kick($chat = NULL){
		$chat = $this->set_chat($chat);
		return ($this->telegram->send->kick($this->id, $chat) !== FALSE);
	}

	function unban($chat = NULL){
		$chat = $this->set_chat($chat);
		return ($this->telegram->send->unban($this->id, $chat) !== FALSE);
	}

	function update($key, $value){
		// get set variables and set them to DB-table
		$this->db
			->where('telegramid', $this->id)
			->set($key, $value)
		->update('user');
	}

	function load(){
		// load variables and set them here.
		$query = $this->db
			->where('telegramid', $this->id)
		->getOne('user');
		if(empty($query)){ return NULL; }
		foreach($query as $k => $v){
			$this->$k = $v;
		}

		$this->load_flags();
		$this->load_chats();
		$this->load_settings();

		$this->loaded = TRUE;
		return TRUE;
	}

	private function load_flags(){
		$this->flags = array();
		$query = $this->db
			->where('user', $this->id)
		->get('user_flags');
		if(count($query) > 0){
			foreach($query as $flag){
				$this->flags[] = $flag['value'];
			}
		}
		return $this->flags;
	}

	private function load_chats(){
		$this->chats = array();
		$query = $this->db
			->where('uid', $this->id)
		->get('user_inchat');
		if(count($query) > 0){
			foreach($query as $chat){
				$chatobj = $chat;
				$chatobj['id'] = $chat['cid'];
				unset($chatobj['uid']);
				$this->chats[$chat['cid']] = (object) $chatobj;
			}
		}
		return $this->chats;
	}

	private function load_settings(){
		$this->settings = array();
		$query = $this->db
			->where('uid', $this->id)
		->get('settings');
		if(count($query) > 0){
			foreach($query as $setting){
				$this->settings[$setting['type']] = $setting['value'];
			}
		}
	}

	function in_chat($chat = NULL, $check_telegram = FALSE){
		$chat = $this->set_chat($chat);
		if($check_telegram == FALSE){
			return in_array($chat, array_keys($this->chats));
		}
	}

	function chats(){
		// return object with id, type_member, messages, register_date y last_message_date
	}

	function is_admin($chat = NULL){
		$chat = $this->set_chat($chat);
		$query = $this->db
			->where('uid', $this->id)
			->where('gid', $chat)
		->getOne('user_admins');
		return !empty($query);
	}

	function log($key, $value){
		// level -> 5 + timestamp
	}


}
