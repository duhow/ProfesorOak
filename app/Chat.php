<?php

class Chat extends TelegramApp\Chat {
	public $loaded = FALSE;

	// EXTRA VARIABLES
	// $settings = array();
	// $step - Action to do
	// $username
	// $team
	// $lvl
	// $verified
	// $blocked
	// $authorized

	// $admins - Admin list

	private function set_chat($chat = NULL){
		if($chat !== NULL){ $this->chat = $chat; }
		else{ $chat = $this->chat; }
		return $chat;
	}

	public function get_userid($user){
		if($user instanceof \Telegram\User or $user instanceof User){ return $user->id; }
		return $user;
	}

	public function update($key, $value, $table = 'chats', $idcol = 'id'){
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
			return (isset($this->settings[$key]) ? $this->settings[$key] : NULL);
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

		if(isset($this->settings[$key])){
			$ret = $this->db
				->where('type', $key)
				->where('uid', $this->id)
			->update('settings', ['value' => $value]);
		}else{
			$data = [
				'uid' => $this->id,
				'type' => $key,
				'value' => $value,
				'hidden' => FALSE,
				'displaylist' => TRUE,
				'lastupdate' => date("Y-m-d H:i:s")
			];
			$ret = $this->db->insert('settings', $data);
		}
		// ---------
		$settings = $this->settings;
		$settings[$key] = $value;
		$this->settings = $settings;
		// ---------

		return $ret;
	}

	public function settings_delete($key){
		if(isset($this->settings[$key])){ unset($this->settings[$key]); }
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

	public function register($ret = FALSE){
		$data = [
			'id' => $this->id,
			'type' => $this->type,
			'title' => $this->title,
			'register_date' => $this->db->now(),
			'last_date' => $this->db->now(),
			'active' => TRUE,
			'messages' => 0,
			'users' => $this->telegram->send->get_members_count($this->id),
			// 'spam' => to setting
		];
		$id = $this->db->insert('chats', $data);
		return ($ret == FALSE ? $id : $data);
	}

	public function load($force = FALSE){
		// load variables and set them here.
		if($this->loaded && !$force){ return TRUE; }
		$query = $this->db
			->where('id', $this->id)
		->getOne('chats');
		if($this->db->count == 0){ $query = $this->register(TRUE); }
		foreach($query as $k => $v){
			$this->$k = $v;
		}
		$this->count = $this->users; // Move setting

		// TODO check
		if($this->is_group()){
			$this->load_users();
			$this->load_admins();
		}

		$this->load_settings();

		$this->loaded = TRUE;
		return TRUE;
	}

	private function load_users(){
		$this->users = array();
		$users = array();
		$query = $this->db
			->where('cid', $this->id)
		->get('user_inchat');
		if(count($query) > 0){
			foreach($query as $user){
				$userobj = $user;
				unset($userobj['cid']);
				$userobj['id'] = $userobj['uid'];
				$users[$userobj['id']] = (object) $userobj;
			}
			$this->users = $users;
		}
		return $this->users;
	}

	private function load_settings(){
		$this->settings = array();
		$settings = array();
		$query = $this->db
			->where('uid', $this->id)
		->get('settings');
		if(count($query) > 0){
			$settings = array_column($query, 'value', 'type');
			foreach($settings as $k => $v){
				if(@unserialize($v) !== FALSE){ $settings[$k] = unserialize($v); }
				elseif(is_numeric($v) && $v == 1){ $settings[$k] = TRUE; }
				elseif(is_numeric($v) && $v == 0){ $settings[$k] = FALSE; }
			}
			$this->settings = $settings;
		}
		return $this->settings;
	}

	private function load_admins(){
		$this->admins = array();
		$admins = array();
		$query = $this->db
			->where('gid', $this->id)
			->where('expires', date("Y-m-d H:i:s"), ">=")
		->get('user_admins');
		if(count($query) == 0){
			// Load and insert
			$admins = $this->telegram->get_admins();
			$timeout = 3600;
			$data = array();
			foreach($admins as $admin){
				$data[] = [
					'gid' => $this->id,
					'uid' => $admin,
					'expires' => time() + $timeout,
				];
			}
			$ids = $this->db->insertMulti('user_admins', $data);
			if(!$ids) {
				// TODO DEBUG
    			echo 'insert failed: ' . $this->db->getLastError();
			}
		}else{
			$admins = array_column($query, 'uid');
		}
		$this->admins = $admins;
		return $this->admins;
	}

	public function in_chat($chat = NULL, $check_telegram = FALSE){
		$chat = $this->set_chat($chat);
		if($check_telegram == FALSE){
			return in_array($chat, array_keys($this->chats));
		}
	}

	public function is_admin($user, $forcequery = FALSE){
		$user = $this->get_userid($user);
		if(!empty($this->admins) && !$forcequery){ return in_array($user, $this->admins); }
		$query = $this->db
			->where('gid', $this->id)
			->where('uid', $user)
		->getOne('user_admins');
		return ($this->db->count == 1);
	}

	function log($key, $value){
		// level -> 5 + timestamp
	}

	public function disable(){
		// TODO algo mas?
		return $this->update('active', FALSE);
	}

	public function command_limit($command, $minimal = 7){
		// @return FALSE si puedes ejecutar el comando. / NO se limita el comando
		$repeat = $this->settings('command_limit');
		$command = strtolower($command);
		$id = $this->telegram->message_id;

		$commands = array();
		if(!empty($repeat)){
			$commands = unserialize($repeat);
		}

		if(!isset($commands[$command]) && $value == 0){ return FALSE; }
		if(isset($commands[$command]) && $id < $commands[$command]){ return TRUE; }

		$commands[$command] = ($id + $value);
		$commands = serialize($commands);
		$this->settings('command_limit', $commands);
		return FALSE;
	}

	public function active_member($user){
		// Chats
		$data = [
			'id' => $this->id,
			'type' => $this->telegram->chat->type,
			'title' => $this->telegram->chat->title,
			'register_date' => $this->db->now(),
			'last_date' => $this->db->now(),
			'active' => TRUE,
			'messages' => 0,
			'users' => 0,
			'spam' => 0.00
		];

		$update = [
			'title' => $this->telegram->chat->title,
			'last_date' => $this->db->now(),
			'active' => TRUE,
			'messages' => $this->db->inc(1)
		];

		$this->db->onDuplicate($update);
		$this->db->insert('chats', $data);

		// -------------------
		// User InChat
		$user = $this->get_userid($user);
		$query = $this->db
			->where('uid', $user)
			->where('cid', $this->id)
		->get('user_inchat');

		if($this->db->count == 1){
			// UPDATE
			$data = [
				'messages' =>  $this->db->inc(1),
				'last_date' => $this->db->now()
			];
			$this->db
				->where('uid', $user)
				->where('cid', $this->id)
			->update('user_inchat', $data);
		}else{
			$data = [
				'uid' => $user,
				'cid' => $this->id,
				'messages' => 0,
				'last_date' => $this->db->now(),
				'register_date' => $this->db->now(),
			];
			$this->db->insert('user_inchat', $data);
		}
	}
}
