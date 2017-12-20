<?php

class User extends TelegramApp\User {
	public function __construct($input = NULL, $db = NULL){
		if(empty($db)){ $db = $GLOBALS["mysql"]; }
		parent::__construct($input, $db);
	}

	// Custom Properties
	// Flags
	// Settings
	private $chat = NULL;

	private function set_chat($chat = NULL, $asint = TRUE){
		if($chat !== NULL){ $this->chat = $chat; }
		else{ $chat = $this->chat; }
		if($asint and is_object($chat)){ return $chat->id; }
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
			if(!$this->settings){ $this->load_settings(); }
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
		$this->cache->set('settings_' .$this->id, $settings, 60);

		return $ret;
	}

	public function settings_incr($key, $amount = 1){ return $this->settings_numberchange($key, max($amount, 1), "+"); }
	public function settings_decr($key, $amount = 1){ return $this->settings_numberchange($key, max($amount, 1), "-"); }
	private function settings_numberchange($key, $amount, $sign){

	}

	public function settings_delete($key){
		if(array_key_exists($key, $this->settings)){ unset($this->settings[$key]); }
		$this->cache->delete('settings_' .$this->id);
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
		if(!$this->team){ return FALSE; }
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
		if(!$query){ return NULL; }
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
		$cache = $this->cache->get('user_inchat_' .$this->id);
		if($cache){
			$this->chats = $cache;
			return $this->chats;
		}
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
			->where('uid', (string) $this->id)
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
			->where('expires >= NOW()')
		->getOne('user_admins');
		return ($this->db->count == 1);
	}

	public function trust($chat = NULL){
		$cachekey = 'trust_' . md5($this->id . ($chat ? '_' .$chat : ''));
		$cache = $this->cache->get($cachekey);
		if($cache){ return $cache; }

		$points = 0;
		if(in_array('untrusted', $this->flags)){ return -1; }

		$badflags = ['fly', 'multiaccount', 'ratkid', 'spam', 'untrusted', 'troll_nest'];

		foreach($badflags as $flag){
			if(in_array($flag, $this->flags)){
				$points = $points - 2;
			}
		}

		$helpers = $this->db
			->where('value', 'helper')
			->where('user', CREATOR, '!=')
		->get('user_flags', NULL, 'user');
		$helpers = array_column($helpers, 'user');

		// If group, load admin users.
		$admins = array();
		if($chat){
			$admins = $this->cache->get('user_admins_' .$chat);
			if(!$admins){
				$admins = $this->db
					->where('expires', date("Y-m-d H:i:s"), '>=')
					->where('gid', $chat)
				->get('user_admins', NULL, 'uid');
				if($this->db->count > 0){
					$admins = array_column($admins, 'uid');
				}
			}
		}

		// ----------------------

		$flaged_users = $this->db->subQuery();
		$flaged_users
			->where('value', $badflags, 'IN')
		->get('user_flags', NULL, 'user');

		$old_users = $this->db->subQuery();
		$old_users
			->where('register_date', date("Y-m-d H:i:s", strtotime("-60 days")) ,'<=')
			->where('last_action', date("Y-m-d H:i:s", strtotime("-15 days")), '>=')
			->where('verified', TRUE)
			->where('blocked', FALSE)
			->where('anonymous', FALSE)
		->get('user', NULL, 'telegramid');

		if($chat){
			$inchat_users = $this->db->subQuery();
			$inchat_users
				->where('cid', $chat)
				->where('messages', 10, '>=')
				->where('last_date', date("Y-m-d H:i:s", strtotime('-15 days')), '>=')
			->get('user_inchat', NULL, 'uid');
			$this->db->where('user', $inchat_users, 'IN');
		}

		$inlinks = $this->db
			->where('target', $this->id)
			->where('valid', TRUE)
			->where('date', date("Y-m-d H:i:s", strtotime("-3 days")) , '>=')
			->where('user', $flaged_users, 'NOT IN')
			->where('user', $old_users, 'IN')
		->get('user_trust', NULL, 'user');

		if($this->db->count > 0){
			$inlinks = array_column($inlinks, 'user');
			if(in_array(CREATOR, $inlinks)){
				$points = $points + 3;
			}
			// SUM Helpers
			$husers = 0;
			foreach($helpers as $helper){
				if(in_array($helper, $inlinks) and $husers <= 3){
					$points = $points + 0.9;
					$husers++;
				}
			}

			// SUM Admins from group
			if($admins){
				$husers = 0;
				foreach($admins as $admin){
					if(in_array($admin, $inlinks) and $husers <= 3){
						$points = $points + 0.8;
						$husers++;
					}
				}
			}

			if($chat){
				$points = $points + (count($inlinks) / 5);
			}else{
				// (x*0.3)^0.4
				$points = $points + ((count($inlinks) / 3) ** 0.4);
			}
		}

		// ----------------------

		if($chat){
			$inchat_users = $this->db->subQuery();
			$inchat_users
				->where('cid', $chat)
				->where('messages', 10, '>=')
				->where('last_date', date("Y-m-d H:i:s", strtotime('-15 days')), '>=')
			->get('user_inchat', NULL, 'uid');
			$this->db->where('user', $inchat_users, 'IN');
		}
		$incount = $this->db
			->where('valid', TRUE)
			->where('target', $this->id)
		->getValue('user_trust', 'COUNT(*)');

		if($chat){
			$inchat_users = $this->db->subQuery();
			$inchat_users
				->where('cid', $chat)
				->where('messages', 10, '>=')
				->where('last_date', date("Y-m-d H:i:s", strtotime('-15 days')), '>=')
			->get('user_inchat', NULL, 'uid');
			$this->db->where('target', $inchat_users, 'IN');
		}
		$outcount = $this->db
			->where('valid', TRUE)
			->where('user', $this->id)
		->getValue('user_trust', 'COUNT(*)');

		if($chat){
			$inchat_users = $this->db->subQuery();
			$inchat_users
				->where('cid', $chat)
				->where('messages', 10, '>=')
				->where('last_date', date("Y-m-d H:i:s", strtotime('-15 days')), '>=')
			->get('user_inchat', NULL, 'uid');
			$this->db->where('A.target', $inchat_users, 'IN');
		}
		$common = $this->db
			->join('user_trust B', 'A.target = B.user')
			->where('A.user', $this->id)
			->where('B.target', $this->id)
			->where('A.valid', TRUE)
			->where('B.valid', TRUE)
		->getValue('user_trust A', 'COUNT(*)');

		$incount = $incount - $common;
		$outcount = $outcount - $common;

		$diff = abs($incount - $outcount);
		if($chat){
			$points = $points - ($diff / 8);
		}else{
			$points = $points - ($diff / 20);
		}

		// ----------------------

		// TODO Ratio + Add Nests - Ask nests
		// TODO Help points.

		$this->cache->set($cachekey, $points, 3600*24);
		return min($points, 10);
	}

	function log($key, $value){
		// level -> 5 + timestamp
	}


}
