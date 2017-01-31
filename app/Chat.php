<?php

class Chat extends TelegramApp\Chat {
	public $loaded = FALSE;
	/* public function __construct($input = NULL){

	} */

	private function set_chat($chat = NULL){
		if($chat !== NULL){ $this->chat = $chat; }
		else{ $chat = $this->chat; }
		return $chat;
	}

	public function get_userid($user){
		if(is_numeric($user)){ return $user; }
		elseif($user instanceof \Telegram\User){ return $user->id; }
	}

	public function ban($user){
		$user = $this->get_userid($user);
		return ($this->telegram->send->ban($user, $this->id) !== FALSE);
	}

	public function kick($user){
		$user = $this->get_userid($user);
		return ($this->telegram->send->kick($user, $this->id) !== FALSE);
	}

	public function unban($user){
		$user = $this->get_userid($user);
		return ($this->telegram->send->unban($user, $this->id) !== FALSE);
	}

	public function update($key, $value, $table = 'chats', $idcol = 'id'){
		// get set variables and set them to DB-table
		$query = $this->db
			->where($idcol, $this->id)
		->update($table, [$key => $value]);
		if($this->db->getLastErrno() !== 0){
			throw new Exception('Error en la consulta: ' .$this->db->getLastError());
		}
		return $query;
	}

	protected function insert($data, $table){

	}

	protected function delete($data, $table){

	}

	public function register(){
		$data = [
			'id' => $this->id,
			'type' => $this->type,
			'title' => $this->title,
			'register_date' => date("Y-m-d H:i:s"),
			'last_date' => date("Y-m-d H:i:s"),
			'active' => TRUE,
			'messages' => 0,
			'users' => $this->telegram->send->get_members_count($this->id),
			// 'spam' => to setting
		];
		return $this->db->insert('chats', $data);
	}

	public function load(){
		// load variables and set them here.
		$query = $this->db
			->where('id', $this->id)
		->getOne('chats');
		if(empty($query)){ return NULL; }
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
		$query = $this->db
			->where('uid', $this->id)
		->get('settings');
		if(count($query) > 0){
			$this->settings = array_column($query, 'value', 'type');
		}
		return $this->settings;
	}

	private function load_admins(){
		$this->admins = array();
		$admins = array();
		$query = $this->db
			->where('gid', $this->id)
			->where('expires >=', date("Y-m-d H:i:s"))
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

	function is_admin($user){
		$user = $this->get_userid($user);
		$query = $this->db
			->where('gid', $this->id)
			->where('uid', $user)
		->getOne('user_admins');
		return !empty($query);
	}

	function log($key, $value){
		// level -> 5 + timestamp
	}


}
