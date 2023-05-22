<?php
class Pokemon extends CI_Model{

	private $settings_loaded = array();
	private $step_loaded = array();
	private $admins_loaded = array();
	private $user_loaded = array();
	private $group_admin_loaded = array();

	// --------------------------------
	//   Funciones de usuario
	// --------------------------------

	function user_firstload($user, $offline = FALSE){
		if($user == NULL){ return NULL; }
		if(!is_int($user) && $user[0] == "@"){ $user = substr($user, 1); }
		if(array_key_exists($user, $this->user_loaded)){
			return $this->user_loaded[$user];
		}

		$cache = $this->cache->get('user_' .$user);
		if($cache !== FALSE){ return $cache; }

		$query = $this->db
			// ->group_start()
				->where('telegramid', $user)
				// ->or_where('telegramuser', (string) $user)
				// ->or_where('username', (string) $user)
			// ->group_end()
			->where('anonymous', FALSE)
		->get('user');
		if($query->num_rows() == 1){
			// $this->cache->save('user_' .$user, $query->row(), 3600);
			$id = $query->row()->telegramid;
			$this->step_loaded[$id] = $query->row()->step;
			$this->user_loaded[$id] = $query->row();
			return $query->row();
		}
		return ($offline ? $this->user_offline($user) : NULL);
	}

	function user($user, $offline = FALSE){
		if(!is_int($user) && $user[0] == "@"){ $user = substr($user, 1); }
		if(array_key_exists($user, $this->user_loaded)){
			return $this->user_loaded[$user];
		}

		$cache = $this->cache->get('user_' .$user);
		if($cache !== FALSE){ return $cache; }

		$query = $this->db
			->group_start()
				->where('telegramid', $user)
				->or_where('telegramuser', (string) $user)
				->or_where('username', (string) $user)
			->group_end()
			->where('anonymous', FALSE)
		->get('user');
		if($query->num_rows() == 1){
			// $this->cache->save('user_' .$user, $query->row(), 3600);
			$id = $query->row()->telegramid;
			$this->step_loaded[$id] = $query->row()->step;
			$this->user_loaded[$id] = $query->row();
			return $query->row();
		}
		return ($offline ? $this->user_offline($user) : NULL);
	}

	function user_offline($user){
		$query = $this->db
			->where('username', $user)
		->get('user_offline');
		$data = NULL;
		if($query->num_rows() == 1){ $data = $query->row(); }
		$this->user_loaded[$user] = $data;
		return $data;
	}

	function user_verified($user){
		if(array_key_exists($user, $this->user_loaded)){
			return $this->user_loaded[$user]->verified;
		}
		$query = $this->db
			->select('verified')
			->where('telegramid', $user)
		->get('user');
		return ($query->num_rows() == 1 ? (bool) $query->row()->verified : FALSE);
	}

	function user_blocked($user){
		if(array_key_exists($user, $this->user_loaded)){
			return $this->user_loaded[$user]->blocked;
		}
		$query = $this->db
			->select('blocked')
			->where('telegramid', $user)
		->get('user');
		return ($query->num_rows() == 1 ? (bool) $query->row()->blocked : FALSE);
	}

	function user_registered_not_blocked($uid){
		// if($cache = $this->cache->get('user_ok_'.$uid)){ return $cache; }
		$query = $this->db
			->select('telegramid')
			->where('telegramid', $uid)
			->where('anonymous', FALSE)
			->where('blocked', FALSE)
		->get('user');
		$ret = ($query->num_rows() == 1);
		$this->cache->save('user_ok_'.$uid, $ret, 300);
		return $ret;
	}

	function user_exists($data, $hidden = FALSE){
		/* $cache = $this->cache->get('exists_' .$data);
		if($cache !== FALSE){ return $cache; } */

		if(array_key_exists($data, $this->user_loaded)){
			return $this->user_loaded[$data]->telegramid;
		}
		if(!$hidden){ $this->db->where('anonymous', FALSE); }

		$query = $this->db
			->select('telegramid')
			->group_start()
				->where('telegramid', $data)
				// ->or_where('telegramuser', $data) FIXME CONFLICTO con Username normal para registro
				->or_where('username', $data)
				->or_where('email', $data)
			->group_end()
			// ->where('anonymous', FALSE)
			->limit(1)
		->get('user');
		if($query->num_rows() == 1){
			$id = $query->row()->telegramid;
			// $this->cache->save('exists_'.$data, $id, 300);
			return $id;
			// return ($retid == TRUE ? $query->row()->telegramid : TRUE);
		}
		return FALSE;
	}

	function get_users($team = TRUE, $alldata = FALSE){
		if($team !== TRUE){
			$this->db->where_in('team', $team);
		}

		$query = $this->db->get('user');
		if($query->num_rows() > 0){
			if($alldata){
				return $query->result_array();
			}else{
				return array_column($query->result_array(), 'telegramid');
			}
		}
	}

	function find_users($array){
		$query = $this->db
			->where_in('username', $array)
			->or_where_in('telegramid', $array)
			->or_where_in('telegramuser', $array)
		->get('user');

		return ($query->num_rows() > 0 ? $query->result_array() : array());
	}

	function user_flags($user, $flag = NULL, $set = NULL){
		// if(!$this->user_exists($user)){ return FALSE; }
		if($flag != NULL && is_bool($set)){
			if($set == TRUE){
				$q = $this->user_flags($user, $flag, NULL);
				if($q == FALSE){
					return $this->db
						->set('user', $user)
						->set('value', $flag)
					->insert('user_flags');
				}
			}else{
				if(!is_array($flag)){ $flag = [$flag]; }
				return $this->db
					->where('user', $user)
					->where_in('value', $flag)
				->delete('user_flags');
			}
		}else{
			$cache = $this->cache->get('oak_flags_' .$user);
			if($cache){
				if($flag == NULL){
					return $cache;
				}elseif(!empty($flag)){
					return (in_array($flag, $cache));
				}
				return FALSE;
			}

			// ----------------
			
			if($flag != NULL && !is_array($flag)){ $flag = [$flag]; }

			// ------
			$query = $this->db
				->where('user', $user)
			->get('user_flags');

			if($query->num_rows() > 0){
				$flagcachs = array_column($query->result_array(), 'value');
				$this->cache->save('oak_flags_' .$user, $flagcachs, 3600);
			}
			// ------		
			
			if(is_array($flag)){ $this->db->where_in('value', $flag); }

			$query = $this->db
				->where('user', $user)
			->get('user_flags');

			if($flag == NULL && $query->num_rows() == 1){
				return array($query->row()->value);
			}elseif($flag != NULL && count($flag) == 1 && $query->num_rows() == 1){
				return TRUE;
			}elseif($query->num_rows() > 0){
				return array_column($query->result_array(), 'value');
			}else{
				return FALSE;
			}
		}
	}

	function verify_user($validator, $target){
		$validator = $this->user($validator);
		$target = $this->user($target);

		if(empty($validator) or empty($target)){ return FALSE; }
		if(!$validator->verified or $validator->blocked and !$validator->authorized){ return FALSE; }
		$this->update_user_data($target->telegramid, 'verified', TRUE);
		$this->log($validator->telegramid, 'verify', $target->telegramid);

		return TRUE;
	}

	function team($user){
		$query = $this->db
			->select('team')
			->or_where('telegramid', $user)
			->or_where('username', $user)
			->or_where('email', $user)
		->get('user');
		return ($query->num_rows() == 1 ? $query->row()->team : FALSE);
	}

	function team_text($text){
		$equipos = [
			'Y' => ['amarillo', 'yellow', 'instinto'],
			'R' => ['rojo', 'red', 'valor'],
			'B' => ['azul', 'blue', 'sabidurí­a', 'sabiduria']
		];

		$text = strtolower($text);

		foreach($equipos as $k => $t){ if(in_array($text, $t)){ $text = $k; break; } }

		if(strlen($text) != 1){ return FALSE; }
		return $text;
	}

	function log($user, $action, $target = NULL){
		$this->db
			->set('user', $user)
			->set('target', $target)
			->set('action', $action)
		->insert('logs');

		return $this->db->insert_id();
	}

	function register($telegramid, $team){
		$team = $this->team_text($team);
		if($team === FALSE or empty($team)){ return FALSE; }

		if($this->user_exists($telegramid, TRUE)){ return -1; }

		$r = $this->db
			->set('telegramid', $telegramid)
			->set('team', $team)
			->set('verified', FALSE)
			->set('register_date', date("Y-m-d H:i:s"))
		->insert('user');
		return $r;
		return $this->db->insert_id();
	}

	function register_offline($username, $team, $referral = NULL, $lvl = 1){
		if($this->user($username, TRUE)){ return FALSE; }

		$this->db
			->set('username', $username)
			->set('team', $team)
			->set('lvl', $lvl)
			->set('referral', $referral)
			->set('register_date', date("Y-m-d H:i:s"))
		->insert('user_offline');
		return $this->db->insert_id();
	}

	function step($user, $step = FALSE){
		if($step === FALSE){
			// GET
			if(array_key_exists($user, $this->step_loaded)){ return $this->step_loaded[$user]; }
			$query = $this->db
				->select('step')
				->where('telegramid', $user)
			->get('user');
			$step = NULL;
			if($query->num_rows() == 1){ $step = $query->row()->step; }
			$this->step_loaded[$user] = $step;
			return $step;
		}else{
			// SET
			if(!empty($step)){ $step = strtoupper($step); }
			$this->step_loaded[$user] = $step;
			$query = $this->db
				->set('step', $step)
				->where('telegramid', $user)
			->update('user');
			return $this;
		}
	}

	function load_settings_cache($uids){
		foreach($uids as $k => $uid){
			$data = $this->cache->get('settings_' .$uid);
			if(is_array($data)){
				$this->settings_loaded[$uid] = $data;
				unset($uids[$k]);
			}
		}
		return $uids;
	}

	function load_settings($uids){
		if(!is_array($uids)){ $uids = [ (string) $uids]; }
		$uids = $this->load_settings_cache($uids);

		if(empty($uids)){ return; }
		$query = $this->db
			->select(['uid', 'type', 'value'])
			->where_in('uid', $uids)
		->get('settings');
		if($query->num_rows() > 0){
			$final = $this->settings_loaded;
			if(empty($final)){ $final = array(); }
			foreach($query->result_array() as $r){
				$final[$r['uid']][$r['type']] = $r['value'];
			}
			$this->settings_loaded = $final;
		}
		foreach($this->settings_loaded as $uid => $data){
			$cache = $this->cache->get('settings_' .$uid);
			if(empty($cache)){
				$this->cache->save('settings_' .$uid, $data, 60);
			}
		}
	}

	function settings($user, $key, $value = NULL){
		// $full = FALSE;
		// if(strtolower($value) == "fullinfo"){ $value = NULL; $full = TRUE; }
		if(empty($user)){ return FALSE; }
		if($value === NULL){
			if(!is_array($key) && array_key_exists($user, $this->settings_loaded)){
				// Si se ha cargado todo lo del usuario
				if(array_key_exists($key, $this->settings_loaded[$user])){
					return $this->settings_loaded[$user][$key];
				}
				// Si no existe
				return NULL;
			}

			$this->load_settings($user);
			if(array_key_exists($user, $this->settings_loaded) && array_key_exists($key, $this->settings_loaded[$user])){
				return $this->settings_loaded[$user][$key];
			}
			// Si no existe
			return NULL;

			/* if(is_array($key)){
				$this->db->where_in('type', $key);
			}elseif(in_array($key, ["all", "*"])){
				// NADA. Coje todo lo del UID.
			}else{
				if(!$full){ $this->db->select('value'); }
				$this->db
					->where('type', $key)
					->limit(1); // Solo un resultado, seguridad
			}
			$query = $this->db
				->where('uid', $user)
			->get('settings');
			if($query->num_rows() > 1){
				if($full){ return $query->result_array(); }
				return array_column($query->result_array(), 'value', 'type');
			}
			elseif($query->num_rows() == 1){
				$value = $query->row()->value;
				$this->settings_loaded[$user][$key] = $value;
				// Cache
				/* $cache = $this->cache->get('settings_' .$user);
				$cache[$key] = $value;
				$this->cache->save('settings_' .$user, $cache, 300); *-/
				// --------
				return ($full ? $query->row() : $value);
			}
			return NULL; */
		}else{
			if(in_array(strtolower($value), ["true", "on", "yes"])){ $value = 1; }
			elseif(in_array(strtolower($value), ["false", "off", "no"])){ $value = 0; }
			elseif(in_array(strtolower($value), ["null", "none"])){ $value = NULL; }

			if(strtoupper($value) == "DELETE"){
				// DELETE
				// Cache
				/* $cache = $this->cache->get('settings_' .$user);
				unset($cache[$key]);
				$this->cache->save('settings_' .$user, $cache, 300); */
				// ---------
				if(array_key_exists($user, $this->settings_loaded)){
					unset($this->settings_loaded[$user][$key]);
					$this->cache->delete('settings_' .$user);
				}

				return $this->db
					->where('uid', $user)
					->where('type', $key)
					->where('hidden', FALSE)
				->delete('settings');
			}else{
				// INSERT or UPDATE
				// Cache
				/* $cache = $this->cache->get('settings_' .$user);
				$cache[$key] = $value;
				$this->cache->save('settings_' .$user, $cache, 300); */
				// ---------
				$data = [
					'uid' => $user,
					'type' => $key,
					'value' => $value
				];
				$query = $this->db->insert_string('settings', $data) ." ON DUPLICATE KEY UPDATE value = " .$this->db->escape($value) ."";
				$this->db->query($query);
				$id = $this->db->insert_id();

				$this->settings_loaded[$user][$key] = $value;
				$this->cache->delete('settings_' .$user);
				// ----------
				// $this->load_settings($user);
				return $id;
			}
		}
	}

	function update_user_data($telegram, $key, $value){
		try {
			return $this->db
				->set($key, $value)
				->where('telegramid', $telegram)
			->update('user');
		} catch (Exception $e) {
			return FALSE;
		}
	}

	function inc_user_data($telegram, $key, $inc){
		try {
			return $this->db
				->set($key, "$key + $inc", FALSE)
				->where('telegramid', $telegram)
			->update('user');
		} catch (Exception $e) {
			return FALSE;
		}
	}

	function update_user_offline_data($id, $key, $value){
		return $this->db
			->set($key, $value)
			->where('id', $id)
			// ->or_where('username', $id)
		->update('user_offline');
	}

	// --------------------------------
	//   Funciones de Grupos
	// --------------------------------

	function group($id){
		$query = $this->db
			->where('id', $id)
			->limit(1)
		->get('chats');
		return ($query->num_rows() == 1 ? $query->row() : NULL);
	}

	function is_group_admin($chat){
		if(array_key_exists($chat, $this->group_admin_loaded)){
			return $this->group_admin_loaded[$chat];
		}
		
		$cache = $this->cache->get('oak_group_admin_' .$chat);
		if($cache){ return $cache; }
		
		$query = $this->db
			->select('uid')
			->where('type', 'admin_chat')
			->where('value', $chat)
		->get('settings');

		$val = ($query->num_rows() == 1 ? $query->row()->uid : FALSE);
		$this->group_admin_loaded[$chat] = $val;
		return $val;
	}

	function group_find($data){
		$possible[] = $data;
		if($data[0] != "@"){ $possible[] = "@" .$data; }
		$query = $this->db
			->like('uid', '-', 'after')
			->group_start()
				->where('type', 'link_chat')
				->or_where('type', 'name')
			->group_end()
			->where_in('value', $possible)
			->order_by('lastupdate', 'DESC')
		->get('settings');
		if($query->num_rows() > 0){ return $query->row()->uid; }
		return NULL;
	}

	function group_spamcount($gid, $amount = NULL){
		$group = $this->group($gid);
		if(!$group){ return FALSE; }
		if($amount === NULL){ return $group->spam; }
		if($amount === FALSE or ($group->spam + $amount) < 0){ $amount = ($group->spam * (-1)); }
		if($amount !== NULL && $amount < 0 && $group->spam == 0){ return $group->spam; } // No need to update.
		$this->db
			->set('spam', "spam + ($amount)", FALSE)
			->where('id', $gid)
		->update('chats');
		return $this->group_spamcount($gid);
	}

	function group_users_active($gid, $hours = 24, $count = FALSE){
		if(is_bool($hours)){
			$count = $hours;
			$hours = 24;
		}
		if(intval($hours) <= 0){ $hours = 24; }
		$query = $this->db
			->where('cid', $gid)
			->where('last_date >=', 'NOW() - INTERVAL ' .$hours .' HOUR', FALSE)
		->get('user_inchat');

		if($count){ return $query->num_rows(); }
		if($query->num_rows() == 0){ return array(); }

		// TODO Exportar toda la info?
		return array_column($query->result_array(), 'uid');
	}

	function group_admins($gid, $useradd = NULL, $time = 3600){
		if($useradd === NULL){
			// GET
			if(array_key_exists($gid, $this->admins_loaded)){
				return $this->admins_loaded[$gid];
			}

			$cache = $this->cache->get('oak_useradmins_' .$gid);
			if($cache){ return $cache; }

			$query = $this->db
				->select('uid')
				->where('gid', $gid)
				->where('expires >=', date("Y-m-d H:i:s"))
			->get('user_admins');
			if($query->num_rows() > 0){
				$uids = array_column($query->result_array(), 'uid');
				$change = $this->admins_loaded;
				$change[$gid] = $uids;
				$this->admins_loaded = $change;
				return $uids;
			}
			return NULL;
		}elseif(is_string($useradd) && strtoupper($useradd) == "DELETE"){
			// DELETE
			return $this->db
				->where('gid', $gid)
			->delete('user_admins');
		}
		// INSERT
		$admins = $this->group_admins($gid);
		if(!is_array($useradd)){ $useradd = [$useradd]; }
		$list = (is_array($admins) ? array_diff($useradd, $admins) : $useradd);
		if(empty($list)){ return $admins; } // FIXME ?
		$this->group_admins($gid, "DELETE");
		$time = date("Y-m-d H:i:s", (time() + $time));

		$data = array();
		foreach($list as $a){
			$data[] = [
				'gid' => $gid,
				'uid' => $a,
				'expires' => $time
			];
		}

		// Add admins to local array
		$this->admins_loaded[$gid] = $list;

		$this->db->insert_batch('user_admins', $data);
		return $list;
	}

	function telegram_admins($add_creator = TRUE, $custom = NULL){
		$admins = $this->group_admins($this->telegram->chat->id);
		if(empty($admins)){
			$admins = $this->telegram->get_admins(); // Del grupo
			$this->group_admins($this->telegram->chat->id, $admins);
		}
		$setadmins = $this->settings($this->telegram->chat->id, "admins");
		if(!empty($setadmins)){
			$setadmins = explode(",", $setadmins);
			foreach($setadmins as $a){ $admins[] = $a; }
		}
		if($add_creator){ $admins[] = $this->config->item('creator'); }
		if($custom != NULL){
			if(!is_array($custom)){ $custom = [$custom]; }
			foreach($custom as $c){ $admins[] = $c; }
		}
		$admins = array_unique($admins);
		return $admins;
	}

	function user_time_in_group($uid, $gid, $timestamp = FALSE){
		$query = $this->db
			->select('register_date')
			->where('uid', $uid)
			->where('cid', $gid)
		->get('user_inchat');

		if($query->num_rows() == 0){ return FALSE; }
		$time = strtotime($query->row()->register_date);
		return ($timestamp ? $time : date("Y-m-d H:i:s", $time));
	}

	function group_get_members($cid, $full = FALSE){
		$query = $this->db
			->where('cid', $cid)
			->order_by('last_date', 'DESC')
		->get('user_inchat');
		if($query->num_rows() > 0){
			if($full){ return $query->result_array(); }
			return array_column($query->result_array(), 'uid');
		}
		return NULL;
	}

	function group_get_users($cid){
		$query = $this->db
			->select("user.*")
			->from("user")
			->join("user_inchat", "user.telegramid = user_inchat.uid")
			->where("user_inchat.cid", $cid)
		->get();
		if($query->num_rows() > 0){
			return $query->result_array();
		}
		return NULL;
	}

	function group_count_members($cid, $min_messages = 0){
		$query = $this->db
			->select('uid')
			->where('cid', $cid)
			->where('messages >=', $min_messages)
		->get('user_inchat');
		return $query->num_rows();
	}

	function group_find_member($uid, $cid = NULL){
		if($cid !== NULL && !is_bool($cid) && !is_array($cid)){ $cid = [$cid]; }
		if(is_array($cid)){ $this->db->where_in('cid', $cid); }
		$query = $this->db
			->where('uid', $uid)
		->get('user_inchat');
		if($query->num_rows() == 0){ return FALSE; }
		if($cid === TRUE){ return array_column($query->result_array(), 'last_date', 'cid'); }
		if(is_array($cid) && count($cid) == 1){ return TRUE; } // Un grupo, no 0 results? Pues TRUE por narices.
		return array_column($query->result_array(), 'cid');
	}

	function group_pair($group, $team){
		$pair = $this->settings($group, 'pair_team_' .$team);
		if(empty($pair)){ return NULL; }
		$query = $this->db
			->like('uid', '-', 'after') // GROUPS
			->where('type', 'pair_groups')
			->like('value', $pair)
			->order_by('id', 'DESC')
			// ->limit(1)
		->get('settings');
		return (($query->num_rows() == 1) ? $query->row()->uid : FALSE);
	}

	function get_groups($shownames = FALSE){
		$query = $this->db
			->where_in('type', ['group', 'supergroup'])
			->where('active', TRUE)
			->order_by('last_date', 'DESC')
		->get('chats');
		if($query->num_rows() > 0){
			if($shownames){
				return array_column($query->result_array(), 'title', 'id');
			}else{
				return array_column($query->result_array(), 'id');
			}
		}
	}

	function group_disable($id, $stat = TRUE){
		$query = $this->db
			->where('id', $id)
			->set('active', !$stat)
		->update('chats');
		return $query;
	}

	function user_addgroup($tid, $cid){
		if($this->user_in_group($tid, $cid)){ return TRUE; }
		return $this->db
			->set('uid', $tid)
			->set('cid', $cid)
			->set('register_date', date("Y-m-d H:i:s"))
		->insert('user_inchat');
	}

	function user_delgroup($tid, $cid){
		if(!$this->user_in_group($tid, $cid)){ return TRUE; }
		return $this->db
			->where('uid', $tid)
			->where('cid', $cid)
		->delete('user_inchat');
	}

	function user_in_group($tid, $cid = NULL){
		if(!empty($cid)){
			if(!is_array($cid)){ $cid = [$cid]; }
			$this->db->where_in('cid', $cid);
		}

		$query = $this->db
			->where('uid', $tid)
		->get('user_inchat');
		if($query->num_rows() > 0){
			if(count($cid) == 1){ return $query->row(); }
			return $query->result_array();
		}
		return FALSE;
	}

	// --------------------------------
	//   Funciones de Datos Pokemon
	// --------------------------------

	function find($search){
		$search = preg_replace('/[^\w\.]/', '', $search);

		$query = $this->db
			->where('id', $search)
			->or_where('name', $search)
		->get('pokedex');
		if($query->num_rows() == 1){ return $query->row_array(); }
		return FALSE;
	}

	function pokedex($pokemon = NULL){
		if(!empty($pokemon)){
			if(!is_array($pokemon)){ $pokemon = [$pokemon]; }
			$this->db
				->where_in('id', $pokemon)
				->or_where_in('name', $pokemon);
		}
		$query = $this->db->get('pokedex');
		if($query->num_rows() == 1){ return $query->row(); }
		if($query->num_rows() > 1){
			$pokedex = array();
			foreach($query->result_array() as $pk){
				$pokedex[$pk['id']] = (object) $pk;
			}
			return $pokedex;
		}
	}

	function evolution($search, $retval = TRUE){
		if(!is_array($search)){ $search = [$search]; }
		$query = $this->db
			->where_in('id', $search)
			->or_where_in('evolved_from', $search)
			->or_where_in('evolves_to', $search)
			->order_by('id', 'ASC')
		->get('pokedex');
		if($query->num_rows() > count($search)){
			$pks = array_column($query->result_array(), 'id');
			return $this->evolution($pks, $retval);
		}else{
			// CASO del Eevee, CUIDADO!
			foreach($query->result_array() as $p){ $pks[$p['id']] = $p; }
			$full = array();
			$full = $pks;
			foreach($pks as $p){
				if($p['evolves_to'] != NULL){ $full[$p['id']]['evolves_to'] = $pks[$p['evolves_to']]; }
				if($p['evolved_from'] != NULL){ $full[$p['id']]['evolved_from'] = $pks[$p['evolved_from']]; }
			}
			return $full;
		}
	}

	function misspell($text){
		$orig = $text;
		if(!is_array($text)){ $text = explode(" ", $text); }
		$pokedex = $this->pokedex();
		$query = $this->db
			->select(['LOWER(word) AS word', 'pokedex.name'])
			->from('pokemon_misspell')
			->join('pokedex', 'pokemon_misspell.pokemon = pokedex.id')
			->where_in('word', $text)
		->get();
		if($query->num_rows() == 0){ return $orig; }
		$rep = array_column($query->result_array(), 'word', 'name');
		foreach($text as $k => $w){
			$q = array_search(strtolower($w), $rep);
			if($q !== FALSE){ $this->misspell_count($w); $text[$k] = $q; }
		}
		return implode(" ", $text);
	}

	function misspell_count($word, $return = FALSE){
		if($return){
			$query = $this->db
				->where('word', $word)
				->or_where('id', $word)
			->get('pokemon_misspell');
			if($query->num_rows() == 1){ return $query->row()->visits; }
			return 0;
		}
		return $this->db
			->set('visits', 'visits+1', FALSE)
			->where('word', $word)
			->or_where('id', $word)
		->update('pokemon_misspell');
	}

	function attack_types(){
		$query = $this->db->get('pokedex_types');
		return ($query->num_rows() > 0 ? array_column($query->result_array(), 'name_es', 'id') : array());
	}

	function attack_type($search = NULL){
		$search = preg_replace('/[^\w\.]/', '', $search);

		if($search !== NULL){
			$this->db->where('id', $search)
			->or_where('type', $search)
			->or_where('name_es', $search);
		}
		$query = $this->db->get('pokedex_types');
		if($query->num_rows() > 0){
			if($query->num_rows() == 1){ return $query->row_array(); }
			return $query->result_array();
		}else{
			return FALSE;
		}
	}

	function attack_table($attackid){
		$query = $this->db
			->where('source', $attackid)
			->or_where('target', $attackid)
		->get('pokedex_attack');
		if($query->num_rows() > 0){ return $query->result_array(); }
		return NULL;
	}

	function level($level = NULL){
		if(!empty($level)){ $this->db->where('level', $level); }
		$query = $this->db->get('pokemon_level');

		if($query->num_rows() == 1){ return $query->row(); }
		if($query->num_rows() > 1){
			$levels = array();
			foreach($query->result_array() as $lv){
				$levels[$lv['level']] = (object) $lv;
			}
			return $levels;
		}
		return NULL;
	}

	function stardust($stardust, $powered = FALSE){
		if(!is_array($stardust)){ $stardust = [$stardust]; }
		if($powered == FALSE){ $this->db->like('level', '.0'); } // Si no se ha mejorado, son niveles enteros.
		$query = $this->db
			->where_in('stardust', $stardust)
			->order_by('level')
		->get('pokemon_level');
		if($query->num_rows() > 0){ return array_column($query->result_array(), 'level'); }
		return array();
	}

	function skill($find = NULL, $type = NULL){
		if(!empty($find)){
			$this->db
				->where('name', $find)
				->or_where('name_es', $find);
		}
		if(!empty($type)){ $this->db->where('type', $type); }
		$query = $this->db->get('pokemon_skills');
		if($query->num_rows() == 1){ return $query->row(); }
		if($query->num_rows() > 1){
			$skills = array();
			foreach($query->result_array() as $sk){
				$skills[$sk['id']] = (object) $sk;
			}
			return $skills;
		}
	}

	function skill_learn($pokemon){
		$query = $this->db
			->select('*')
			->from('pokemon_skills')
			->join('pokemon_skills_learn', 'pokemon_skills.id = pokemon_skills_learn.sid')
			->where('pokemon_skills_learn.pid', $pokemon)
		->get();
		if($query->num_rows() > 0){
			$skills = array();
			foreach($query->result_array() as $sk){
				$skills[$sk['id']] = (object) $sk;
			}
			return $skills;
		}
	}

	function trainer_rewards($find){
		if(is_numeric($find)){ $this->db->where('lvl', $find); }
		elseif(is_string($find)){ $this->db->where('item', $find); }
		$query = $this->db->get('trainer_rewards');

		if($query->num_rows() > 0){ return $query->result_array(); }
		return array();
	}

	function items($find = NULL){
		if(!empty($find)){ $this->db->where('name', $find)->or_where('display', $find); }
		$query = $this->db->get('items');

		if($query->num_rows() > 0){ return array_column($query->result_array(), 'display', 'name'); }
		return array();
	}

	function hatch_egg($distance, $pokemon = NULL, $add = FALSE){
		if($add !== FALSE && !empty($pokemon)){
			// INSERT
			$this->db
				->set('uid', $add)
				->set('pokemon', $pokemon)
				->set('km', $distance)
			->insert('pokemon_eggs');
			return $this->db->insert_id();
		}else{
			// GET
			if(!empty($pokemon)){ $this->db->where('pokemon', $pokemon); }
			$query = $this->db
				->where('km', $distance)
				->order_by('date_seen', 'DESC')
			->get('pokemon_eggs');
			if($query->num_rows() == 0){ return NULL; }

		}
	}

	// --------------------------------
	//   Funciones de Ubicación Pokemon
	// --------------------------------

	function add_found($poke, $user, $lat, $lng){
		$data = [
			'type' => 'pokemon',
			'pokemon' => $poke,
			'user' => $user,
			'lat' => $lat,
			'lng' => $lng,
			'register_date' => date("Y-m-d H:i:s"),
			'points' => 0,
		];
		$this->db->insert('pokemon_spawns', $data);
		return $this->db->insert_id();
	}

	function add_lure_found($user, $lat, $lng){
		$data = [
			'type' => 'lure',
			'user' => $user,
			'lat' => $lat,
			'lng' => $lng,
			'register_date' => date("Y-m-d H:i:s"),
			'points' => 0,
		];
		$this->db->insert('pokemon_spawns', $data);
		return $this->db->insert_id();
	}

	function location_distance($locA, $locB, $locC = NULL, $locD = NULL){
		$earth = 6371000;
		if($locC !== NULL && $locD !== NULL){
			$locA = [$locA, $locB];
			$locB = [$locC, $locD];
		}
		$locA[0] = deg2rad($locA[0]);
		$locA[1] = deg2rad($locA[1]);
		$locB[0] = deg2rad($locB[0]);
		$locB[1] = deg2rad($locB[1]);

		$latD = $locB[0] - $locA[0];
		$lonD = $locB[1] - $locA[1];

		$angle = 2 * asin(sqrt(pow(sin($latD / 2), 2) + cos($locA[0]) * cos($locB[0]) * pow(sin($lonD / 2), 2)));
		return ($angle * $earth);
	}

	function location_add($locA, $locB, $amount = NULL, $direction = NULL){
		// if(is_object($locA)){ $locA = [$locA->latitude, $locA->longitude]; }
		if(!is_array($locA) && $direction === NULL){ return FALSE; }
		if(!is_array($locA)){ $locA = [$locA, $locB]; }
		// si se rellenan 3 y direction es NULL, entonces locA es array.
		if(is_numeric($locB) && $amount !== NULL && $direction === NULL){
			$direction = $amount;
			$amount = $locB;
		}
		$direction = strtoupper($direction);
		$steps = [
			'N' => ['NORTE', 'NORTH', 'N', 'UP'],
			'NW' => ['NOROESTE', 'NORTHWEST', 'NW', 'UP_LEFT'],
			'NE' => ['NORESTE', 'NORTHEAST', 'NE', 'UP_RIGHT'],
			'S' => ['SUD', 'SOUTH', 'S', 'DOWN'],
			'SW' => ['SUDOESTE', 'SOUTHWEST', 'SW', 'DOWN_LEFT'],
			'SE' => ['SUDESTE', 'SOUTHEAST', 'SE', 'DOWN_RIGHT'],
			'W' => ['OESTE', 'WEST', 'W', 'O', 'LEFT'],
			'E' => ['ESTE', 'EAST', 'E', 'RIGHT']
		];
		foreach($steps as $s => $k){ if(in_array($direction, $k)){ $direction = $s; break; } } // Buscar y asociar dirección
		$earth = (40075 / 360 * 1000);

		if($direction == 'N'){ $locA[0] = $locA[0] + ($amount / $earth); }
		elseif($direction == 'S'){ $locA[0] = $locA[0] - ($amount / $earth); }
		elseif($direction == 'W'){ $locA[1] = $locA[1] - ($amount / $earth); }
		elseif($direction == 'E'){ $locA[1] = $locA[1] + ($amount / $earth); }
		elseif($direction == 'NW'){
			$locA[0] = $locA[0] + ($amount / $earth); // N
			$locA[1] = $locA[1] - ($amount / $earth); // W
		}elseif($direction == 'NE'){
			$locA[0] = $locA[0] + ($amount / $earth); // N
			$locA[1] = $locA[1] + ($amount / $earth); // E
		}elseif($direction == 'SW'){
			$locA[0] = $locA[0] - ($amount / $earth); // S
			$locA[1] = $locA[1] - ($amount / $earth); // W
		}elseif($direction == 'SE'){
			$locA[0] = $locA[0] - ($amount / $earth); // S
			$locA[1] = $locA[1] + ($amount / $earth); // E
		}

		return $locA;
	}

	function pokecrew($location, $radius = 3000, $limit = 10, $pokemon = NULL){
		$n = ($radius / 3);
		$rhor = ($n * 2);
		$rver = ($n * 1);
		$locNE = $this->location_add($location, $rhor, 'RIGHT');
		$locNE = $this->location_add($locNE, $rver, 'UP');
		$locSW = $this->location_add($location, $rhor, 'LEFT');
		$locSW = $this->location_add($locSW, $rver, 'DOWN');

		$data = [
			'center_latitude' => $location[0],
			'center_longitude' => $location[1],
			'live' => 'false',
			'minimal' => 'true',
			'northeast_latitude' => $locNE[0],
			'northeast_longitude' => $locNE[1],
			'pokemon_id' => $pokemon,
			'southwest_latitude' => $locSW[0],
			'southwest_longitude' => $locSW[1],
		];
		$url = "https://api.pokecrew.com/api/v1/seens";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, ($url ."?" .http_build_query($data)) );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		$json = curl_exec($ch);
		curl_close($ch);
		// $json = file_get_contents($url ."?" .http_build_query($data));
		$json = json_decode($json, TRUE);
		if(count($json['seens']) == 0){ return array(); }
		$seens = array(); // Lista completa
		$pokes = array(); // ID de Pokemon (para evitar duplicados)
		foreach($json['seens'] as $pk){
			if(in_array($pk['pokemon_id'], $pokes)){ continue; } // Un Pokemon por ubicación
			if(count($seens) >= $limit){ break; } // Limitar
			if(!empty($pokemon) && $pokemon != $pk['pokemon_id']){ continue; }
			if(!empty($pokemon) && count($seens) == 1){ break; } // HACK Están ordenados por más reciente, asi que me quedo sólo con el primero.
			$locpk = [$pk['latitude'], $pk['longitude']];
			$seens[] = [
				'id' => $pk['id'],
				'lat' => $pk['latitude'],
				'lng' => $pk['longitude'],
				'pokemon' => $pk['pokemon_id'],
				'last_seen' => $pk['expires_at'],
				'points' => ($pk['upvote_count'] - $pk['downvote_count'] - 1),
				'distance' => $this->location_distance($location, $locpk),
			];
		}
		return $seens;
	}

	function user_near($location, $radius = 500, $limit = 10){
		if(!is_array($location) or count($location) != 2){ return FALSE; }
		$lat = "SUBSTRING_INDEX(value, ',', 1)";
		$lng = "SUBSTRING_INDEX(value, ',', -1)";
		$sql_dist = "ASIN(SQRT(POW(SIN((RADIANS($location[0]) - RADIANS($lat)) / 2), 2) + COS(RADIANS($lat)) * COS(RADIANS($location[0])) * "
					."POW(SIN((RADIANS($location[1]) - RADIANS($lng)) / 2), 2) )) * 2 * 6371000";

		$query = $this->db
			->select(['uid', 'lastupdate', "$sql_dist AS distance"])
			->where('type', 'location_now')
			->where("($sql_dist) <=", $radius)
			->not_like("uid", "-", "after")
			// TODO ->where('lastupdate', '')
			->limit($limit)
			->order_by($sql_dist, 'ASC', FALSE)
		->get('settings');
		return ($query->num_rows() > 0 ? $query->result_array() : array());
	}

	function group_near($location, $limit = 10){
		if(!is_array($location) or count($location) != 2){ return FALSE; }

		// GET de todos los grupos el setting location Y location radius.
		// Agrupar.
		// Calcular distancia y ver si está en el radio, y agregar a array.
		// Devolver array de grupos cercanos.

		$query = $this->db
			->like("uid", "-", "after")
			->where_in("type", ["location", "location_radius"])
			->order_by("uid", "DESC")
		->get('settings');
		$data = array();
		if($query->num_rows() > 0){
			foreach($query->result_array() as $row){
				$data[$row['uid']][$row['type']] = $row['value'];
			}
		}
		$ret = array();
		foreach($data as $g => $d){
			if(!isset($d['location_radius'])){
				continue;
			}
			$loc = explode(",", $d['location']);
			$diff = $this->location_distance($location, $loc);
			if($diff <= $d['location_radius']){
				$ret[] = $g;
			}
		}

		return $ret;
	}

	function spawn_near($location, $radius = 500, $limit = 10, $pokemon = NULL){
		if(!is_array($location) or count($location) != 2){ return FALSE; }
		$sql_dist = "ASIN(SQRT(POW(SIN((RADIANS($location[0]) - RADIANS(lat)) / 2), 2) + COS(RADIANS(lat)) * COS(RADIANS($location[0])) * "
					."POW(SIN((RADIANS($location[1]) - RADIANS(lng)) / 2), 2) )) * 2 * 6371000";
		$query = $this->db
			->select(['*', "$sql_dist AS distance"])
			->where('type', 'pokemon')
			->where("($sql_dist) <=", $radius)
			->where_in('pokemon', $pokemon)
			->limit($limit)
			->order_by($sql_dist, 'ASC', FALSE)
			// ->order_by('last_seen', 'DESC')
			->order_by('id', 'DESC')
			->group_by('pokemon')
		->get('pokemon_spawns');
		return ($query->num_rows() > 0 ? $query->result_array() : array());
	}

	function pokestops($location, $radius = 500, $limit = 10){
		if(!is_array($location) or count($location) != 2){ return FALSE; }
		$sql_dist = "ASIN(SQRT(POW(SIN((RADIANS($location[0]) - RADIANS(lat)) / 2), 2) + COS(RADIANS(lat)) * COS(RADIANS($location[0])) * "
					."POW(SIN((RADIANS($location[1]) - RADIANS(lng)) / 2), 2) )) * 2 * 6371000";
		$query = $this->db
			->select(['*', "$sql_dist AS distance"])
			->where("($sql_dist) <=", $radius)
			->where("gym", FALSE)
			->where("disabled", FALSE)
			->limit($limit)
			->order_by($sql_dist, 'ASC', FALSE)
			// ->order_by('last_seen', 'DESC')
			->order_by('id', 'DESC')
		->get('pokestops');
		return ($query->num_rows() > 0 ? $query->result_array() : NULL);
	}

	function pokestops_search($search, $near = NULL){
		if(!empty($near)){
			$res = $this->pokestops($near, 30000, 500);
			if(empty($res)){ return NULL; }
			$ids = array_column($res, 'id');

			$this->db->where_in('id', $ids);
		}
		$query = $this->db
			->like('title', $search)
		->get('pokestops');

		return ($query->num_rows() > 0 ? $query->result_array() : NULL);
	}

	// --------------------------------
	//   Funciones de información general
	// --------------------------------

	function command_limit($command, $group, $id, $value = 0){
		// @return FALSE si puedes ejecutar el comando. / NO se limita el comando
		$repeat = $this->settings($group, 'command_limit');
		$command = strtolower($command);

		$commands = array();
		if(!empty($repeat)){
			$commands = unserialize($repeat);
		}

		if(!isset($commands[$command]) && $value == 0){ return FALSE; }
		if(isset($commands[$command]) && $id < $commands[$command]){ return TRUE; }

		$commands[$command] = ($id + $value);
		$commands = serialize($commands);
		$this->settings($group, 'command_limit', $commands);
		return FALSE;
	}

	function link($text, $count = FALSE, $table = 'links'){
		if(strtolower($text) != "all" && $text !== TRUE){
			$this->db
				->where('name', $text)
				->or_where('id', $text);
		}
		$query = $this->db->get($table);

		if($query->num_rows() > 1){
			if($count === FALSE){ return $query->result_array(); }
			else{ return $query->num_rows(); }
		}elseif($query->num_rows() == 1){
			$link = $query->row();
			$this->db
				->set('visits', 'visits+1', FALSE)
				->where('id', $link->id)
			->update($table);
			if($table == "meanings"){ return $link->text; }
			if($table == "public_groups"){ return $link->link; }
			return $link->url;
		}
	}

	function meeting($key){
		$query = $this->db
			->where('joinkey', $key)
		->get('meetings');
		return ($query->num_rows() == 1 ? $query->row() : NULL);
	}

	function meeting_member($id, $uid){
		$query = $this->db
			->where('mid', $id)
			->where('uid', $uid)
			->order_by('register_date', 'DESC')
		->get('meetings_join');
		if($query->num_rows() == 1){ return $query->row()->active; }
		return NULL;
	}

	function meeting_members($id){
		$query = $this->db
			->where('mid', $id)
			->order_by('register_date', 'DESC')
		->get('meetings_join');
		if($query->num_rows() > 0){ return $query->num_rows(); }
		return array();
	}

	function meeting_members_count($id, $all = FALSE){
		if(!$all){ $this->db->where('active', TRUE); }
		$query = $this->db
			->where('mid', $id)
			// ->or_where('joinkey', $id)
		->get('meetings_join');
		return $query->num_rows();
	}

	function meeting_members_total($id){
		$query = $this->db
			->where('mid', $id)
			// ->or_where('joinkey', $id)
		->get('meetings_join');

		$amount = [0, 0];
		if($query->num_rows() > 0){
			foreach($query->result_array() as $m){
				$i = (int) $m['active'];
				$amount[$i] = $amount[$i] + 1;
			}
		}
		return $amount;
	}

	function meeting_create($user, $date, $location, $private = FALSE){
		$data = [
			'creator' => $user,
			'location' => $location,
			'date_event' => $date,
			'private' => $private,
			'register_date' => date("Y-m-d H:i:s"),
		];
		$key = sha1(serialize($data));
		$key = substr($key, 0, 8);
		$data['joinkey'] = $key;
		$this->db->insert('meetings', $data);
		$id = $this->db->insert_id();
		$this->meeting_join($user, $id, TRUE);
		return $key;
	}

	function meeting_join($user, $meeting, $join = NULL){
		if($join === NULL){
			// GET
			$query = $this->db
				->where('uid', $user)
				->where('mid', $meeting)
			->get('meetings_join');
			if($query->num_rows() == 1){ return (bool) $query->row()->active; }
			return NULL;
		}else{
			if($this->meeting_join($user, $meeting) !== NULL){
				// UPDATE
				return $this->db
					->set('active', $join)
					->where('uid', $user)
					->where('mid', $meeting)
				->update('meetings_join');
			}else{
				// INSERT
				return $this->db
					->set('uid', $user)
					->set('mid', $meeting)
					->set('active', $join)
				->insert('meetings_join');
			}
		}
	}

	function meaning($text, $count = FALSE){
		return $this->link($text, $count, 'meanings');
	}

	function group_link($text, $count = FALSE){
		return $this->link($text, $count, 'public_groups');
	}

	function joke($full = FALSE, $random = TRUE, $type = NULL){
		if(is_numeric($random)){ $this->db->where('id', $random); }
		if($type !== NULL){
			if(!is_array($type)){ $type = [$type]; }
			$this->db->where_in('type', $type);
		}
		$query = $this->db
			->limit(1)
			->order_by('RAND()')
		->get('jokes');
		if($query->num_rows() == 0){ return NULL; }
		$ret = $query->row();
		$this->db
			->where('id', $ret->id)
			->set('visits', 'visits+1', FALSE)
		->update('jokes');
		return ($full ? $ret : $ret->joke);
	}

    function count_teams(){
		$teams = ['Y' => 0, 'R' => 0, 'B' => 0];
		foreach(['user', 'user_offline'] as $table){
			$query = $this->db
				->select(['team', 'count(*) AS count'])
				->where_in('team', array_keys($teams))
				->group_by('team')
			->get($table);
			if($query->num_rows() > 0){
				foreach($query->result_array() as $r){ $teams[$r['team']] += $r['count']; }
			}
		}
		return $teams;
    }

} ?>
