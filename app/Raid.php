<?php

class Raid extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		if(!$this->chat->is_group() and $this->telegram->key != "channel_post"){ return; }

		if(!in_array(date("H"), range(7, 21))){
			$tags = ['time','buddy','join','here','late','location','setup','last','stop'];
			foreach($tags as $t){
				if(strpos($this->telegram->callback, $t) !== FALSE){
					$this->telegram->answer_if_callback("");
					// Remove button
					$this->telegram->send
						->text($this->telegram->text_message())
					->edit('text');
					$this->end();
				}
			}
			return;
		}

		parent::run();
	}

	protected function hooks(){
		if(
			$this->telegram->text_has($this->strings->get('command_raid_create')[0], $this->strings->get('command_raid_create')[1], TRUE) and
			$this->telegram->words() <= 25
		){
			return $this->create();
		}

		if(
			$this->telegram->text_command("raid") and
			!$this->telegram->text_has_emoji() and
			$this->telegram->words() == 2
		){
			$id = $this->telegram->last_word();
			$raid = $this->clone($id, TRUE);
			if(!$raid){ $this->end(); }

			// Borrar el mensaje original si es posible.
			$this->telegram->send->delete(TRUE);

			$this->load_buttons($raid);
			$this->telegram->send
				->text($this->generate_text($raid))
				->disable_web_page_preview(TRUE)
				/* ->inline_keyboard()
					->row_button($this->strings->get('raid_button_join'), "raid $raid join")
				->show() */
			->send();
		}

		if($this->telegram->callback and strpos($this->telegram->callback, "raid") === 0){
			@list($raid, $id, $action, $args) = explode(" ", $this->telegram->callback, 4);
			if($action == "join"){
				$this->join($id);
			}elseif($action == "here"){
				$this->status($id, 1);
			}elseif($action == "late"){
				$this->status($id, 2);
			}elseif($action == "rewrite"){
				// if(IS ADMIN)
				// OR ($this->chat->settings('raid_rewrite_owner') and $this->owner($id, $this->telegram->user->id))
			}elseif($action == "buddy"){
				$args = ($args == "1"); // bool
				$this->buddy($id, $args);
			}elseif($action == "stop"){
				if(
					$this->owner($id, $this->telegram->user->id) // or
					// IS ADMIN
				){
					$this->stop($id);
					$this->end();
				}
			}

			// Edit message if can.
			$timeout = $this->chat->settings('raid_timeout_bt');
			if($timeout and $timeout > time()){
				$this->end();
			} // No callback answer
			$this->chat->settings('raid_timeout_bt', time() + 5);
		}
	}

	public function get($id){
		$raid = $this->db
			->where('id', $id)
		->getOne('raid');
		if($this->db->count == 0){ return FALSE; }

		$trainers = $this->db
			->join('user u', 'r.uid = u.telegramid')
			->where('r.rid', $raid['id'])
		->get('raid_users r', NULL, 'u.username, u.team, u.lvl, r.cid, r.status, r.buddies, r.active');

		$trainers_full = array();
		foreach($trainers as $tr){
			$trainers_full[] = (object) $tr;
		}

		$data = [
			'raid' => (object) $raid,
			'trainers' => $trainers_full
		];

		return (object) $data;
	}

	public function owner($id, $user = NULL){
		if($user instanceof User){ $user = $user->id; }
		if(is_object($id) and isset($id->owner)){
			return ($user ? $user == $id->owner : $id->owner);
		}
		$owner = $this->db
			->where('id', $id)
		->getValue('raid', 'owner');

		if(empty($user)){ return $owner; }
		return ($user == $owner);
	}

	public function create($data = NULL, $publish = TRUE){
		if(!is_array($data)){ $data = $this->parse_text($this->telegram->text()); }
		if(empty($data)){ $this->end(); } // No se ha detectado información sobre la Raid.

		/* Desactivado por canal y gente que crea y no va.
		$user = $pokemon->user($this->telegram->user->id);
		$team = ['R' => 'red', 'B' => 'blue', 'Y' => 'yellow'];
		$str .= "- " . $this->telegram->emoji(":heart-" .$team[$user->team] .":") ." L" .$user->lvl ." @" .$user->username ."\n";
		*/

		// TODO Generar raid en DB y devolver ID.
		$raid = [
			'owner' => $this->user->id
		];

		$raid['invitekey'] = strtoupper(md5($this->user->id . microtime(TRUE)));

		if(isset($data['time'])){
			$raid['date_start'] = date("Y-m-d H:i:s", strtotime($data['time']));
			$raid['date_end'] = date("Y-m-d H:i:s", strtotime("+45 minutes", strtotime($raid['date_start'])) );
		}else{
			$raid['date_end'] = date("Y-m-d H:i:s", strtotime("+45 minutes"));
		}

		$keys = ['invitekey','stars','pokemon','move_1','move_2','cp','owner','date_spawn','date_start','date_end','date_join','gym','place','team'];
		foreach($keys as $key){
			if(isset($data[$key])){ $raid[$key] = $data[$key]; }
		}

		if(
			isset($data['pokemon']) and
			is_numeric($data['pokemon']) and
			$this->Pokemon->Get($data['pokemon'], TRUE)
		){
			$raid['pokemon'] = $data['pokemon'];
		}

		$id = $this->db->insert('raid', $raid);

		if(!$id){
			$this->telegram->send
				->chat(CREATOR)
				->text(":exclamation: Error al generar Raid en " . $this->chat->id)
			->send();

			$this->telegram->send
				->message(TRUE)
				->chat(TRUE)
				->forward_to(CREATOR)
			->send();

			$this->end();
		}

		// Function, return Raid ID
		if(!$publish){ return $id; }

		// Borrar el mensaje original si es posible.
		$this->telegram->send->delete(TRUE);

		$this->load_buttons($id);
		$this->telegram->send
			->text($this->generate_text($id))
			->disable_web_page_preview(TRUE)
			/* ->inline_keyboard()
				->row_button($this->strings->get('raid_button_join'), "raid $id join")
			->show() */
		->send();
	}

	public function clone($id, $trainers = FALSE){
		$raid = $this->db
			->where('id', $id)
			->orWhere('invitekey', $id)
		->getOne('raid');

		// Si no existe o está desactivada.
		if(!$raid or !$raid['active']){ return FALSE; }

		$oldraid = $raid['id'];
		unset($raid['id']);
		$raid['invitekey'] = strtoupper(md5($this->user->id . microtime(TRUE)));
		$raid['owner'] = $this->user->id;

		$id = $this->db->insert('raid', $raid);
		if(!$id){ return FALSE; }

		if($trainers === TRUE){
			$trainers = $this->db
				->where('rid', $oldraid)
			->get('raid_users', NULL, 'uid, cid, status, buddies, active, "' .$id .'" AS rid, NOW() AS register_date, NOW() AS last_date');
		}

		if($trainers){
			$this->db->insertMulti('raid_users', $trainers);
		}

		return $id;
	}

	public function stop($id){
		$this->db
			->where('id', $id)
		->update('raid', ['active' => FALSE]);

		if($this->telegram->callback){
			// Remove buttons
			$this->telegram->send
				->text($this->telegram->text_message())
			->edit('text');
		}
	}

	private function dbFilter($rid, $uid){
		return $this->db
			->where('rid', $rid)
			->where('uid', $uid)
			->where('active', TRUE);
	}

	public function join($id, $user = NULL, $force = FALSE){
		// TODO comprobar que tenga usuario y nivel.
		// TODO comprobar si se ha puesto su ubicación en los úlitmos 5 min.

		if(!$user){ $user = $this->user; }
		elseif(is_numeric($user)){
			$user = new User($user);
			$user->load();
		}

		if(
			!$user->username or
			$user->lvl == 1
		){
			if($this->telegram->callback){
				$this->telegram->answer_if_callback(":x: " .$this->strings->get('raid_error_join_userdata_empty'), TRUE);
				$this->end();
			}
		}

		if($this->telegram->callback){
			$this->telegram->answer_if_callback("Oh. $id", TRUE);
			$this->end();
		}

		$timeout = 10;
		if(empty($user)){ $user = $this->telegram->user->id; }

		$data = [
			'rid' => $id,
			'uid' => $user,
			'cid' => $this->chat->id,
			'last_date' => $this->db->now(),
			'active' => TRUE
		];

		$id = $this->db
			->onDuplicate(["active" => $this->db->not('active')])
		->insert('raid_users', $data);

		// Cargar timeout de DB y no actualizar mensaje si no han pasado 10 segundos.
		if($this->telegram->callback){
			// ------
		}
	}

	public function status($id, $status, $user = NULL){
		if(empty($user)){ $user = $this->telegram->user->id; }

		$this->dbFilter($id, $user);
		return $this->db->update('raid_users', ['status' => $status]);
	}

	public function buddy($id, $add = TRUE, $user = NULL){
		if(empty($user)){ $user = $this->telegram->user->id; }

		$data = [
			'last_date' => $this->db->now(),
			'buddies' => $this->db->inc(1)
		];

		if(!$add){
			$data['buddies'] = $this->db->dec(1);
			$this->db->where('buddies >', 0);
		}

		$this->dbFilter($id, $user);
		return $this->db
			->where('buddies <', 5)
		->update('raid_users', $data);
	}

	private function text_head($raid){
		if(empty($raid)){ return NULL; }
		if(isset($raid->raid)){ $raid = $raid->raid; }

		$str = array();

		$poke = NULL;
		if($raid->pokemon){
			$poke = $this->Pokemon->Get($raid->pokemon);
			$str[] = ':crossed_swords: ' . $this->Pokemon->ResolveName($poke);
		}

		if($raid->stars){
			$star = ($raid->stars >= 4 ? 'star2' : 'star');
			$str[] = ":$star: " . $raid->stars;
		}elseif($poke){
			if(isset($poke->rarity)){
				$str[] = ':star2: -';
			}else{
				$str[] = ':star: -';
			}
		}

		if(
			$raid->date_spawn and
			empty($raid->date_start)
		){
			$time = strtotime($raid->date_spawn);
			if(time() < $time){
				$str[] = ':pause_button: ' . date("H:i", $time);
				$min = round(($time - time()) / 60);
				if($min <= 7 and $min >= 1){
					$str[] = ":exclamation: <b>${min}m</b>";
				}elseif($min > 7){
					$str[] = "${min}m";
				}
			}
		}elseif($raid->date_start){
			$start = strtotime($raid->date_start);
			$end = ($raid->date_end ?
				strtotime($raid->date_end) :
				$start + (45 * 60)
			);

			$str[] = ':clock2: ' .date("H:i", $start) .' - ' .date("H:i", $end);
			$min = round(($end - time()) / 60);
			if($min <= 7 and $min >= 1){
				$str[] = ":exclamation: <b>${min}m</b>";
			}elseif($min > 7){
				$str[] = "${min}m";
			}
		}

		return implode(" ", $str);
	}

	private function text_head_location($raid){
		if(empty($raid)){ return NULL; }
		if(isset($raid->raid)){ $raid = $raid->raid; }

		if($raid->place){
			// TODO check location
			$str = ':pushpin: ' .$raid->place;
		}elseif($raid->gym){
			$gym = $this->Pokemon->GymID($raid->gym);

			$url = "https://maps.google.com?q={$gym->lat},{$gym->lng}";
			$color = [
				'R' => ':heart:',
				'B' => ':blue_heart:',
				'Y' => ':yellow_heart:'
			];

			if($gym->team){
				$str = $color[$gym->team];
			}else{
				$str = ':map:';
			}
			$str .= ' <a href="' .$url .'">' .($gym->title ?: "Ubicación") .'</a>';
		}

		return $str;
	}

	private function text_head_raidinfo($raid){
		if(empty($raid)){ return NULL; }
		// if(isset($raid->raid)){ $raid = $raid->raid; }

		// TODO Cache username?
		$str = array();
		$str[] = ':id: <code>' .$raid->raid->invitekey .'</code>';

		$str[] = ':man_police_officer: ' .$this->telegram->userlink($raid->raid->owner, $this->Functions->resolve_username($raid->raid->owner));

		return implode(" ", $str);
	}

	private function text_head_trainers($trainers){
		if(isset($trainers->trainers)){ $trainers = $trainers->trainers; }
		if(empty($trainers)){ return NULL; }

		$icons = [
			'B' => ':droplet:',
			'R' => ':fire:',
			'Y' => ':zap:',
			'total' => ':busts_in_silhouette:'
		];

		$players = [
			'R' => 0, 'B' => 0, 'Y' => 0,
			'total' => 0, 'going' => 0, 'additional' => 0
		];

		foreach($trainers as $trainer){
			$players['total']++;
			$players[$trainer->team]++;
			$players['going'] += (int) ($trainer->status == 2);
			$players['additional'] += $trainer->buddies;
		}

		$str = "";
		foreach($icons as $key => $emoji){
			$str .= "$emoji {$players[$key]} ";
		}

		if($players['additional'] > 0){
			$str .= '+' .$players['additional'] .' ';
		}

		if($players['going'] > 0){
			$str .= ':man_running::dash: ' .$players['going'] .' ';
		}

		return $str;
	}

	private function text_trainers($data){
		if(is_string($data) or is_numeric($data)){
			$data = $this->people($data);
		}
		if(empty($data)){ return ""; }
		$str = "";
		$status = [
			"", // Empty
			":white_check_mark:", // In place
			":clock2:", // Late
		];
		$color = [
			'R' => ':heart:',
			'B' => ':blue_heart:',
			'Y' => ':yellow_heart:'
		];

		foreach($data as $p){
			$str .=
				$status[$p['status']]
				." " .$color[$p['team']]
				." L" .$p['lvl']
				.' ' .$this->telegram->userlink($p['telegramid'], $p['username']);

			if($p['buddies'] > 0){
				$str .= " <b>+" .$p['buddies'] ."</b>";
			}
			$str .= "\n";
		}

		return $this->telegram->emoji($str);
	}

	private function user_distance($raidId, $user = NULL){
		if($user instanceof User){ $user = $user->id; }
		if($user){ $this->db->where('uid', $user); }

		$users = $this->db
			->where('id', $raidId)
		->get('raid_users', NULL, 'uid');
		$users = array_column($users, 'uid');
		if(empty($users)){ return FALSE; }

		$raid = $raidId;
		if(is_numeric($raidId)){
			$raid = $this->db
				->where('id', $raidId)
			->getOne('raid');
			if(!$raid){ return FALSE; }
			$raid = (object) $raid;
		}

		$select = "uid, " .$this->Functions->location_search_sql('SUBSTRING_INDEX(value, ",", 1)', 'SUBSTRING_INDEX(value, ",", -1)', $raid->latitude, $raid->longitude) ." AS distance";

		$location = $this->db
			->where('type', 'location')
			->where('uid', $users, 'IN')
			->where('lastupdate >= NOW() - INTERVAL 5 MINUTE')
			->where('value > 0')
			->orderBy('2')
		->get('settings', NULL, $select);
		if(!$location){ return NULL; }
		$location = array_column($location, 'distance', 'uid');
		if($user > 0){ return array_key_exists($user, $location) ? $location[$user] : NULL; }
		return $location;
	}

	private function generate_text($id){
		// Cargar Raid y gente
		$raid = $id;

		if(is_numeric($id) or is_string($id)){
			$raid = $this->get($id);
		}

		$str = $this->text_head($raid) ."\n"
				.trim($this->text_head_trainers($raid) ."\n")
				.$this->text_head_location($raid) ."\n"
				.$this->text_head_raidinfo($raid) ."\n"
			;

		return $str;
	}

	public function people($id){
		$cols = [
			'u.telegramid', 'u.username', 'u.team', 'u.lvl',
			'r.buddies', 'r.status'
		];

		return $this->db
			->join('user u', 'r.uid = u.telegramid')
			->where('r.id', $id)
			->where('r.active', TRUE)
			->orderBy('u.username', 'ASC')
		->get('raid_users r', NULL, $cols);
	}

	private function load_buttons($id){
		return $this->telegram->send
			->inline_keyboard()
				->row()
					->button($this->telegram->emoji(':man_raising_hand: ') .$this->strings->get('raid_button_join'), "raid $id join")
					->button("+1", "raid $id buddy 1")
					->button("-1", "raid $id buddy 0")
				->end_row()
				->row()
					->button($this->telegram->emoji(':arrows_counterclockwise: ') . $this->strings->get('raid_button_update'), "raid $id update")
				->end_row()
			->show();
	}

	private function load_buttons_edit($id){
		return $this->telegram->send
			->inline_keyboard()
				->row()
					->button("-5m", "raid $id time -5")
					->button("+15m", "raid $id time +15")
				->end_row()
				->row()
					->button($this->strings->get('raid_button_datelast'), "raid $id last") // A qué hora acaba
					->button($this->strings->get('raid_button_location'), "raid $id location")
					->button($this->strings->get('raid_button_stop'), "raid $id stop")
				->end_row()
			->show();
	}

	// Genera un array con las opciones de la raid.
	public function parse_text($string){
		$data = array();

		$pokemon = $this->Pokemon->parse_text($string, TRUE, FALSE);
		if($pokemon){
			$data['pokemon'] = $pokemon;
		}

		// de 00.00 a 01.00
		// a las 00.00 hasta (las) 01.00

		$timestr = $this->telegram->text();
		$timestr = preg_replace('/(\d{1,2})(h)\s?/', '${1}:00 ', $timestr);

		$timecount = preg_match_all('/((\d{1,2})[.:](\d{2}))/', $timestr, $matches);
		$times = array();

		if($timecount >= 1){
			$times[] = strtotime($matches[2][0] .':' .$matches[3][0]);
		}

		if($timecount == 2){
			$times[] = strtotime($matches[2][1] .':' .$matches[3][1]);
		}

		// Si la hora de la raid ya ha pasado...
		if(
			(isset($times[0]) and $times[0] <= time()) or
			(isset($times[1]) and $times[1] <= $times[0])
		){
			$this->telegram->send
				->notification(FALSE)
				->text(":x: " . $this->strings->get('raid_error_create_time_expired') )
			->send();

			return FALSE;
		}

		if(!isset($times[1]) and isset($times[0])){
			$times[1] = strtotime("+45 minutes", $times[0]);
		}

		if($times){
			if(isset($times[0])){
				$data['date_spawn'] = date("Y-m-d H:i:s", $times[0]);
				$data['date_join'] = date("Y-m-d H:i:s", $times[0]);
			}
			if(isset($times[1])){ $data['date_end'] = date("Y-m-d H:i:s", $times[1]); }
		}

		// TODO Search gym or place
		$place = NULL;

		// 1 or 2 stars (pink egg) - Exeggutor, Magikarp, Bayleef, Croconaw, Muk, Weezing, Magmar, Electabuzz, Quilava
		// 3 or 4 stars (yellow egg) - Venusaur, Charizard, Blastoise, Alakazam, Rhydon, Jolteon, Lapras, Snorlax, Tyranitar, Arcanine, Machamp, Gengar, Vaporeon, Flareon

		return $data;
	}

}
