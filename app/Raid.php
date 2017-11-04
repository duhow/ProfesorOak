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

		if($this->telegram->callback and strpos($this->telegram->callback, "raid") === 0){
			list($raid, $id, $action, $args) = explode(" ", $this->telegram->callback, 4);
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

		// Join y GET team, username, lvl
		$trainers = $this->db
			->where('rid', $id)
		->get('raid_join');

		$data = [
			'raid' => (object) $raid,
			'trainers' => $trainers
		];

		return $data;
	}

	public function owner($id, $user = NULL){
		$owner = $this->db
			->where('id', $id)
		->getValue('raid', 'owner');

		if(empty($user)){ return $owner; }
		return ($user == $owner);
	}

	public function create($data = NULL){
		if(!is_array($data)){ $data = $this->parse_text($this->telegram->text()); }
		if(empty($data)){ $this->end(); } // No se ha detectado información sobre la Raid.

		$str = $this->text_head($data);

		/* Desactivado por canal y gente que crea y no va.
		$user = $pokemon->user($this->telegram->user->id);
		$team = ['R' => 'red', 'B' => 'blue', 'Y' => 'yellow'];
		$str .= "- " . $this->telegram->emoji(":heart-" .$team[$user->team] .":") ." L" .$user->lvl ." @" .$user->username ."\n";
		*/

		// TODO Generar raid en DB y devolver ID.

		// Borrar el mensaje original si es posible.
		$this->telegram->send->delete(TRUE);

		$this->telegram->send
			->text($this->generate_text($raid))
			->inline_keyboard()
				->row_button($this->strings->get('raid_button_join'), "raid $id join")
			->show()
		->send();
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

	public function join($id, $user = NULL){
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

	private function text_head($data){
		$str = array();
		$str[] = $this->strings->get('raid_new_raid');

		if(!empty($data) and isset($data['pokemon'])){
			$poke = $this->Pokemon->name($data['pokemon']);
			$str[] = $this->strings->parse('raid_pokemon', $poke);
		}

		if(!empty($data) and isset($data['hour'])){
			$str[] = $this->strings->parse('raid_time', $data['hour']);
		}

		if(!empty($data) and isset($data['place'])){
			$str[] = $this->strings->parse('raid_place', $data['place']);
		}

		return implode(" ", $str) . "!\n";
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
				.' <a href="tg://user?id=' .$p['telegramid'] .'">' .$p['username'] .'</a>';

			if($p['buddies'] > 0){
				$str .= " <b>+" .$p['buddies'] ."</b>";
			}
			$str .= "\n";
		}

		return $this->telegram->emoji($str);
	}

	private function generate_text($id){
		// Cargar Raid y gente
		// $raid =
		$str = $this->text_head($id) ."\n";
		$str .= $this->text_trainers($id);

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
					->button("+1", "raid $id buddy 1")
					->button("-1", "raid $id buddy 0")
				->end_row()
				->row()
					->button($this->strings->get('raid_button_join'), "raid $id join")
					->button($this->strings->get('raid_button_here'), "raid $id here")
					->button($this->strings->get('raid_button_late'), "raid $id late")
				->end_row()
				->row()
					->button($this->strings->get('raid_button_location'), "raid $id location")
					->button($this->strings->get('raid_button_setup'), "raid $id setup")
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
		// TODO Parse Pokémon
		// TODO Parse time
		// TODO Search gym or place
		$pokemon = $this->Pokemon->parse_text($string, TRUE, TRUE);
		// 1 or 2 stars (pink egg) - Exeggutor, Magikarp, Bayleef, Croconaw, Muk, Weezing, Magmar, Electabuzz, Quilava
		// 3 or 4 stars (yellow egg) - Venusaur, Charizard, Blastoise, Alakazam, Rhydon, Jolteon, Lapras, Snorlax, Tyranitar, Arcanine, Machamp, Gengar, Vaporeon, Flareon

		return $data;
	}

}
