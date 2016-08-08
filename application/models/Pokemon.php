<?php

class Pokemon extends CI_Model{

	function user($user){
		$query = $this->db
			->where('telegramid', $user)
			->or_where('username', $user)
		->get('user');
		if($query->num_rows() == 1){ return $query->row(); }
	}

	function user_verified($user){
		$query = $this->db
			->select('verified')
			->where('telegramid', $user)
		->get('user');
		return ($query->num_rows() == 1 ? (bool) $query->row()->verified : FALSE);
	}

	function user_blocked($user){
		$query = $this->db
			->select('blocked')
			->where('telegramid', $user)
		->get('user');
		return ($query->num_rows() == 1 ? (bool) $query->row()->blocked : FALSE);
	}

	function user_exists($data){
		$query = $this->db
			->where('telegramid', $data)
			->or_where('username', $data)
			->or_where('email', $data)
			->limit(1)
		->get('user');
		return ($query->num_rows() == 1);
	}

	function find_users($array){
		$query = $this->db
			->where_in('username', $array)
			->or_where_in('telegramid', $array)
			->or_where_in('telegramuser', $array)
		->get('user');

		if($query->num_rows() > 0){
			return $query->result_array();
		}else{
			return array();
		}
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

	function register($telegramid, $team){
		$team = $this->team_text($team);
		if($team === FALSE){ return FALSE; }

		if($this->user_exists($telegramid)){ return FALSE; }

		$this->db
			->set('telegramid', $telegramid)
			->set('team', $team)
			->set('verified', FALSE)
			->set('register_date', date("Y-m-d H:i:s"))
		->insert('user');
		return $this->db->insert_id();
	}

	function step($user, $step = FALSE){
		if($step === FALSE){
			// GET
			$query = $this->db
				->select('step')
				->where('telegramid', $user)
			->get('user');
			return ($query->num_rows() == 1 ? $query->row()->step : NULL);
		}else{
			// SET
			$query = $this->db
				->set('step', strtoupper($step))
				->where('telegramid', $user)
			->update('user');
			return $this;
		}
	}

	function location_name($name){
		$query = $this->db
			->from('locations')
			->where('id = (SELECT locid FROM locations_name WHERE name = "' .$name .'")', NULL, FALSE)
		->get();
		return ($query->num_rows() == 1 ? $this->parse_location($query->row()) : NULL );
	}

	function location_zipcode($zipcode){
		$query = $this->db
			->where('zipcode', $zipcode)
		->get('locations');
		return ($query->num_rows() == 1 ? $this->parse_location($query->row()) : NULL);
	}

	function parse_location($row){
		$row->location = new Location($row->latitude, $row->longitude);
		return $row;
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

	function settings($user, $key, $value = NULL){
		if(strtolower($value) == "true"){ $value = TRUE; }
		if(strtolower($value) == "false"){ $value = FALSE; }
		if(strtolower($value) == "null"){ $value = NULL; }
		if($value === NULL){
			if(is_array($key)){
                $this->db->where_in('type', $key);
            }elseif(in_array($key, ["all", "*"])){
                // NADA. Coje todo lo del UID.
            }else{
                $this->db
                    ->where('type', $key)
                    ->limit(1); // Solo un resultado, seguridad
            }
			$query = $this->db
				->where('uid', $user)
			->get('settings');
            if($query->num_rows() > 1){
                return array_column($query->result_array(), 'value', 'type');
            }
			elseif($query->num_rows() == 1){ return $query->row()->value; }
			return NULL;
		}else{
			if($this->settings($user, $key) === NULL){
				// INSERT
				$data = [
					'uid' => $user,
					'type' => $key,
					'value' => $value
				];
				$query = $this->db->insert('settings', $data);
				return $this->db->insert_id();
			}elseif(strtoupper($value) == "DELETE"){
                // DELETE
                return $this->db
                    ->where('uid', $user)
                    ->where('type', $key)
                ->delete('settings');
			}else{
                // UPDATE
                return $this->db
                    ->where('uid', $user)
                    ->where('type', $key)
                    ->set('value', $value)
                ->update('settings');
            }
		}
	}

	function create($telegram, $phone = NULL){
		$data = [
			'telegramid' => $telegram,
			'valid' => FALSE,
			'step' => 'REGISTER_NEW'
		];
		$this->db->insert('user');
		$id = $this->db->insert_id();
		if($phone !== NULL){
			$this->settings($telegram, 'phone', $phone);
		}

		return $id;
	}

	function update_user_data($telegram, $key, $value){
		return $this->db
			->set($key, $value)
			->where('telegramid', $telegram)
		->update('user');
	}

	// ---------------------

	function attack_types(){
		$query = $this->db->get('pokedex_types');
		if($query->num_rows() > 0){
			return array_column($query->result_array(), 'name_es', 'id');
		}else{
			return array();
		}
	}

	function attack_type($search = NULL){
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
	}

	function find($search){
		$query = $this->db
			->where('id', $search)
			->or_where('name', $search)
		->get('pokedex');
		if($query->num_rows() == 1){ return $query->row_array(); }
		return FALSE;
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

	function get_groups($shownames = FALSE){
		$query = $this->db
			->where_in('type', ['group', 'supergroup'])
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
			return $link->url;
		}
	}

	function meaning($text, $count = FALSE){
		return $this->link($text, $count, 'meanings');
	}

    function count_teams(){
        $query = $this->db
            ->select(['team', 'count(*) AS count'])
            ->where_in('team', ['R','B','Y'])
            ->group_by('team')
        ->get('user');
        if($query->num_rows() > 0){
            return array_column($query->result_array(), 'count', 'team');
        }else{
            return array();
        }
    }

} ?>
