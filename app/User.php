<?php

class User extends TelegramApp\User {
	public function __construct($input = NULL, $db = NULL){
		if(empty($db)){ $db = $GLOBALS["mysql"]; }
		parent::__construct($input, $db);
	}

	// Custom Properties
	// Flags
	// Settings

	private function set_chat($chat = NULL){
		if($chat !== NULL){ $this->chat = $chat; }
		else{ $chat = $this->chat; }
		return $chat;
	}

	public function update($key, $value, $table = 'user', $idcol = 'telegramid'){
		if(in_array($key, ['settings'])){ return NULL; }
		// get set variables and set them to DB-table
		$query = $this->db
			->where($idcol, $this->id)
		->update($table, [$key => $value]);
		if($this->db->getLastErrno() !== 0){
			throw new Exception('Error en la consulta: ' .$this->db->getLastError());
		}
		return $query;
	}

	public function settings($key, $value = NULL){
		if($value === NULL){
			return (array_key_exists($key, $this->settings) ? $this->settings[$key] : NULL);
		}elseif(strtoupper($value) == "DELETE"){
			$settings = $this->settings;
			unset($settings[$key]);
			$this->settings = $settings;
			// ---------
			return $this->settings_delete($key);
		}

		// Si los datos son array, serializar para insertar en DB.
		if(is_array($value)){
			$value = serialize($value);
		}

		$data = [
			'uid' => $this->id,
			'type' => $key,
			'value' => $value,
			'hidden' => FALSE,
			'displaylist' => TRUE,
			'lastupdate' => date("Y-m-d H:i:s")
		];

		$update = [
			'value' => $value,
			'lastupdate' => date("Y-m-d H:i:s")
		];

		$ret = $this->db
			->onDuplicate($update)
		->insert('settings', $data);

		// ---------
		$settings = $this->settings;
		$settings[$key] = $value;
		$this->settings = $settings;
		// ---------

		return $ret;
	}

	public function settings_delete($key){
		if(array_key_exists($key, $this->settings)){ unset($this->settings[$key]); }
		return $this->db
			->where('uid', $this->id)
			->where('type', $key)
		->delete('settings');
	}

	protected function insert($data, $table){
		return $this->db->insert($table, $data);
	}

	protected function delete($table, $where, $value, $usercol = FALSE){
		if($usercol !== FALSE){
			$this->db->where($usercol, $this->id);
		}
		return $this->db
			->where($where, $value)
		->delete($table);
	}

	public function register($team){
		if(!empty($this->team)){ return FALSE; }
		$data = [
			'telegramid' => $this->id,
			'telegramuser' => @$this->telegram->username,
			'username' => NULL,
			'fullname' => @$this->telegram->first_name,
			'team' => $team,
			'register_date' => date("Y-m-d H:i:s"),
			'verified' => FALSE,
			'blocked' => FALSE
		];
		return $this->db
			->setQueryOption('IGNORE')
		->insert('user', $data);
	}

	public function register_username($name, $force = FALSE){
		if($name[0] == "@"){ $name = substr($name, 1); }
		$username = $this->username;

		// Invalid user or already set name.
		if(
			(!$this->load()) or
			(!$force && !empty($username))
		){ return -1; }

		// Name too long or short.
		if(
			(strlen($name) < 4 or strlen($name) > 18)
		){ return -2; }

		try {
			$this->username = $name;
		} catch (Exception $e) {
			// si el nombre ya existe
			return FALSE;
		}
		return TRUE;
	}

	public function load($force = FALSE){
		// load variables and set them here.
		if($this->loaded && !$force){ return TRUE; }
		$query = $this->db
			->where('telegramid', $this->id)
			->where('anonymous', FALSE)
			// ->orWhere('username', $this->username)
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
		->get('user_flags', NULL, 'value');
		if(count($query) > 0){
			$this->flags = array_column($query, 'value');
		}
		return $this->flags;
	}

	private function load_chats(){
		$this->chats = array();
		$chats = array();
		$query = $this->db
			->where('uid', $this->id)
		->get('user_inchat', NULL, ['cid', 'messages', 'last_date', 'register_date']);
		if(count($query) > 0){
			foreach($query as $chat){
				$chatobj = $chat;
				$chatobj['id'] = $chat['cid'];
				$chats[$chat['cid']] = (object) $chatobj;
			}
			$this->chats = $chats;
			$this->cache->set('user_inchat_' .$this->id, $chats, 120);
		}
		return $this->chats;
	}

	private function load_cache_chats(){
		$res = $this->cache->get('user_inchat_' .$this->id);
		if($res){
			$this->chats = $res;
			return $this->chats;
		}
		return $this->load_chats();
	}

	private function load_settings(){
		$this->settings = array();
		$settings = array();
		$query = $this->db
			->where('uid', $this->id)
		->get('settings', NULL, ['type', 'value']);
		if(count($query) > 0){
			$settings = array_column($query, 'value', 'type');
			foreach($settings as $k => $v){
				if(@unserialize($v) !== FALSE){ $settings[$k] = unserialize($v); }
				elseif(is_numeric($v) && $v == 1){ $settings[$k] = TRUE; }
				elseif(is_numeric($v) && $v == 0){ $settings[$k] = FALSE; }
			}
			$this->settings = $settings;
			$this->cache->set('settings_' .$this->id, $settings, 60);
		}
		return $this->settings;
	}

	private function load_cache_settings(){
		$res = $this->cache->get('settings_' .$this->id);
		if($res){
			$this->settings = $res;
			return $this->settings;
		}
		return $this->load_settings();
	}

	public function warns($chat = NULL, $full = FALSE){
		if($chat === TRUE){ $chat = $this->chat->id; }
		if(!empty($chat)){ $this->db->where('chat', $chat); }
		$this->db->where('user', $this->id);
		if(!$full){
			$warns = $this->db->getValue('user_warns', 'count(*)');
		}else{
			$warns = $this->db->get('user_warns');
		}
		return $warns;
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
			->where('timeout >=', $this->db->now())
		->getOne('user_admins');
		return ($this->db->count == 1);
	}

	function log($key, $value){
		// level -> 5 + timestamp
	}


}
