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

	public function user_languages($users, $default = "en"){
		$single = FALSE;
		if(is_object($users)){ $users = [$users->id]; $single = TRUE; }
		elseif(is_string($users) or is_numeric($users)){ $users = [$users]; $single = TRUE; }

		$query = $this->db
			->where('uid', $users, 'IN')
			->where('type', 'language')
		->get('settings', NULL, 'uid, value');
		if($query){ $query = array_column($query, 'value', 'uid'); }
		$final = array();
		foreach($users as $user){ $final[$user] = $default; }
		foreach($query as $user => $lang){ $final[$user] = $lang; }
		return ($single ? current($final) : $final);
	}

	public function is_allowed_whois_hidden($user, $chat = NULL){
		if($user instanceof User){ $user = $user->id; }
		if($chat instanceof Chat){ $chat = $chat->id; }

		// TODO Cache

		$query = $this->db
			->where('user', $user)
			->where('active', TRUE)
			->where('amount < `limit`')
		->get('user_whois_allow');

		if($this->db->count == 0){ return FALSE; }
		$allowed = FALSE;
		foreach($query as $row){
			if($row['chat'] == NULL or $row['chat'] == $chat){
				$allowed = TRUE;

				$data = [
					'amount' => $this->db->inc(1),
					'last_query' => $this->db->now()
				];

				$this->db
					->where('id', $row['id'])
				->update('user_whois_allow', $data);
			}
		}
		return $allowed;
	}

	public function resolve_username($id, $retselfempty = FALSE){
		$result = $this->db
			->where('telegramid', $id)
		->getValue('user', 'username');
		if(!$result){
			return ($retselfempty ? $id : FALSE);
		}
		return $result;
	}

	// INFO:
	// Hay dos funciones diferentes para calcular la distancia.
	// Una es más precisa si la distancia es más pequeña de 500m aprox.
	// La otra conviene más para distancias largas.
	// Tener en cuenta que es distancia en linea recta.

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
		$cal = ($amount / $earth);

		foreach(str_split($direction) as $dir){
			if($dir == 'N'){ $locA[0] = $locA[0] + $cal; }
			elseif($dir == 'S'){ $locA[0] = $locA[0] - $cal; }
			elseif($dir == 'W'){ $locA[1] = $locA[1] - $cal; }
			elseif($dir == 'E'){ $locA[1] = $locA[1] + $cal; }
		}

		return $locA;
	}

	public function location_string_extract($string, $retobj = FALSE, $multiple = FALSE){
		$matches = array();
		if(preg_match_all("/([+-]?\d+\.\d+)[,;]\s?([+-]?\d+\.\d+)/", $string, $loc)){
			$c = count($loc[0]);
			for($i = 0; $i < $c; $i++){
				if($retobj){
					$matches[] = new \Telegram\Elements\Location($loc[1][$i], $loc[2][$i]);
				}else{
					$matches[] = [$loc[1][$i], $loc[2][$i]];
				}
			}
			return ($multiple ? $matches : $matches[0]);
		}
		return FALSE;
	}

	public function location_search_sql($fieldLat, $fieldLng, $locLat, $locLng){
		$earth = 6371000;
		return "ASIN(SQRT(POW(SIN((RADIANS($locLat) - RADIANS($fieldLat)) / 2), 2) + COS(RADIANS($fieldLat)) * COS(RADIANS($locLat)) * "
			."POW(SIN((RADIANS($locLng) - RADIANS($fieldLng)) / 2), 2) )) * 2 * $earth";
	}

	public function location_timezone($location){
		if(!is_numeric($timestamp)){ $timestamp = strtotime($timestamp); }
		if(is_object($location)){ $location = [$location->latitude, $location->longitude]; }
		if(is_string($location)){ $location = $this->location_string_extract($location); }

		/* $maxdist = 20000;
		$locs = array();
		foreach(DateTimeZone::listIdentifiers() as $tzn){
			$tz = new DateTimeZone($tzn);
			$tzloc = [$tz->getLocation()['latitude'], $tz->getLocation()['longitude']];
			$distance = $this->location_distance($location, $tzloc);
			$locs[$tzn] = $distance;
			if($distance <= $maxdist){ return $tzn; }
		}
		asort($locs);
		*/

		$location = implode(",", $location);
		$url = "https://maps.googleapis.com/maps/api/timezone/json?location=$location&timestamp=" .time();
		$loc = json_decode(file_get_contents($url));
		if(!$loc or $loc->status != "OK"){ return FALSE; }
		return $loc->timeZoneId;
	}
}
