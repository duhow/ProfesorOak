<?php

class Pokegram extends TelegramApp\Module {
	protected $runCommands = FALSE;
	protected $tables = [
		'account' => 'pokegame_account',
		'pokestop' => 'pokegame_pokestop',
		'pokemon' => 'pokegame_sightseens',
		'eggs' => 'pokegame_eggs',
	];
	protected $pokeballs_sticker = [
	    'pokeball' => 'BQADBAADDAgAAjbFNAAB6xJe3sYfW5QC',
	    'superball' => 'BQADBAADDggAAjbFNAABArAsznkfzmAC',
	    'ultraball' => 'BQADBAADEAgAAjbFNAABc1c6B4iXJosC',
	    'masterball' => 'BQADBAADEggAAjbFNAABanVUlBU9VxwC'
	];

	public function run(){
		if(!$this->account_exists($this->user->id)){
			$this->account_register($this->user->id);
		}

		if($this->telegram->key == "edited_message"){ return; }
		if(in_array($this->user->flags, ['summonear', 'poketelegram_cheat'])){ return; }
		parent::run();
	}

	protected function hooks(){
		if($this->telegram->text_has("Mis Pokémon") && $this->telegram->words() <= 4){
			if($this->chat->is_group()){
				$this->telegram->send
					->notification(FALSE)
					->text($this->view_pokemon($this->user->id, 6))
				->send();
			}else{
				$this->telegram->send
					->text($this->view_pokemon($this->user->id))
				->send();
			}
			$this->end();
		}elseif($this->telegram->text_has("inventario") && $this->telegram->words() <= 3){
			$this->telegram->send
				->notification(TRUE)
				->text($this->view_inventory($this->user->id))
			->send();
			$this->end();
		}elseif($this->telegram->text_command("pogo") && $this->user->id == CREATOR){
			$this->end();
		}

		// -----------------
		if(!$this->chat->is_group()){ return; }
		if(isset($this->chat->settings['pokegram']) && $this->chat->settings['pokegram'] == FALSE){ return; }

		if(
			($this->telegram->callback && $this->telegram->text_has("capturar") && $this->telegram->words() == 2) or
			($this->telegram->sticker() && in_array($this->telegram->sticker(), array_values($this->pokeballs_sticker)))
		){
			// TODO
			$this->pokemon_capture($this->telegram->last_word());

			if(!$this->delay_can_continue(2)){
				$this->telegram->answer_if_callback("");
				$this->end();
			}
			// .................
			$this->answer_if_callback("");
			$this->end();
		}

		elseif($this->telegram->callback && $this->telegram->text_has("pokespin") && $this->telegram->words() == 2){

		}
	}

	// TODO hacer Ataque X, Defensa X, Vida X para subir el IV de un Pokémon de forma permanente.
	// TODO hacer Carameloraro para subir de nivel a un Pokémon.
	// TODO hacer policía o incienso para alejar al Team Rocket y atraer más Pokémon.

	function account_exists($user){
	    $query = $this->db
			->where('uid', $user)
		->get($this->tables['account']);
	    return ($this->db->count == 1);
	}

	function account_register($user){
	    $query = $this->db->insert($this->tables['account'], ['uid' => $user]);
	    return $query;
	}

	function half_inventory($user, $div = 2){
		$data = [
			'pokeball' => '(pokeball * ' .(1 - (1/$div)) .')',
			'superball' => '(superball * ' .(1 - (1/$div)) .')',
			'ultraball' => '(ultraball * ' .(1 - (1/$div)) .')',
		];
		$query = $this->db
			->where('uid', $user)
		->update($this->tables['account'], $data);
		return $query;
	}

	function pokestop_register($chat, $spins = 10){
		$data = ['chat' => $chat, 'spins' => $spins];
	    $query = $this->db->insert($this->tables['pokestop'], $data);
	    return $query;
	}

	function pokestop_spin($id, $user, $amount = NULL){
	    $pokestop = $this->db
	        ->where('id', $id)
	    ->get($this->tables['pokestop']);

	    if($this->db->count != 1){ return FALSE; }
	    if($pokestop['spins'] <= 0){ return FALSE; }

	    $items = ['pokeball', 'superball', 'ultraball'];

		$data = ['spins' => 'spins - 1'];
	    $query = $this->db
	        ->where('id', $id)
	    ->update($this->tables['pokestop'], $data);

		if(!is_numeric($amount)){ $amount = mt_rand(3, 5); }
	    for($i = 0; $i < $amount; $i++){
	        $rand = mt_rand(0, count($items) - 1);
	        $this->item_add($user, $items[$rand]);
	        $this->notify_item($user, $items[$rand]);
	    }

	    return ($pokestop->spins - 1);
	}

	function notify_item($target, $item){
	    if(!in_array($item, array_keys($this->pokeballs_sticker))){ return FALSE; }

	    $this->telegram->send
	        ->chat($target)
	        ->notification(FALSE)
	    ->file('sticker', $this->pokeballs_sticker[$item]);
	    usleep(100000);

	    $this->telegram->send
	        ->chat($target)
	        ->notification(FALSE)
	        ->text("¡Has encontrado una <b>" .ucwords($item) ."</b>!", 'HTML')
	    ->send();
	    usleep(100000);

	    if($this->telegram->callback){
	        $this->telegram->answer_if_callback("¡Has encontrado una " .ucwords($item) ."!");
	        usleep(100000);
	    }
	}

	function pokemon_add($data){
	    $query = $this->db->insert($this->tables['pokemon'], $data);
	    return $query;
	}

	function pokemon_view($id){
	    $query = $this->db
			->where('id', $id)
		->get($this->tables['pokemon']);
	    if($this->db->count == 1){ return (object) $query; }
	    return FALSE;
	}

	function pokemon_setowner($id, $user){
	    $data = ['owner' => $user, 'tries' => 0];
	    return $this->db
	        ->where('id', $id)
	    ->update($this->tables['pokemon']);
	}

	function pokemon_trydown($id){
	    $data = ['tries' => 'tries - 1'];
	    return $this->db
	        ->where('id', $id)
	    ->update($this->tables['pokemon'], $data);
	}

	function pokemon_generate($chat, $num = NULL, $res = FALSE){
		if(empty($num)){
			$num = $this->number(FALSE, 251);
			// if(in_array($num, [144, 145, 146])){ $num = 1; } // HACK No legendarios
		}

		if($res){
			$poke = $num;
		}else{
			$pokemon = new Pokemon();
			$poke = $pokemon->pokedex($num); // object
		}

		$ivATK = mt_rand(0, 15);
		$ivDEF = mt_rand(0, 15);
		$ivSTA = mt_rand(0, 15);
		$lvl = mt_rand(10, 30);

		$hp = floor(($poke->stamina + $ivSTA) * ($lvl / 30));
		$hp = max($hp, 10);

		$cp = ((($poke->attack + $ivATK) * sqrt($poke->defense + $ivDEF) * sqrt($poke->stamina + $ivSTA)) * ($lvl / 30)) / 10;
		$cp = max(floor($cp), 10);

		// Suma de STATS da MAX 742, MIN 171.
		// LVL 50 + CP 20 + IVs 30
		$flee = (($lvl / 30) * 0.5) + (($cp / 3200) * 0.2) + ((($ivATK + $ivDEF + $ivSTA) / 45) * 0.3);
		$flee = floor($flee * 100);

		$tries = mt_rand(1, 5);

		if(in_array($poke->id, [144, 145, 146])){
			$tries = mt_rand(5, 15);
			$flee = min(($flee * 2), 110);
		}elseif($this->is_legendary($poke->id)){
			$tries = mt_rand(10, 25);
			$flee = min(($flee * 3), 124);
		}

		$data = [
			'pokemon' => $poke->id,
			'gid' => $chat,
			'atk' => $ivATK,
			'def' => $ivDEF,
			'sta' => $ivSTA,
			'lvl' => $lvl,
			'hp' => $hp,
			'cp' => $cp,
			'flee' => $flee,
			'tries' => $tries
		];

		return $data;
	}

	function pokemon_appearview($chat, $id, $poke = NULL){
		$pokemon = new Pokemon();

		$data = $this->pokemon_view($id);
		if(empty($data)){ return; }

		if(empty($poke)){
			$poke = $pokemon->pokedex($data->pokemon);
		}

		$this->telegram->send
			->notification(FALSE)
			->chat($chat)
		->file('sticker', $poke->sticker);

	    $q = $this->telegram->send
	        ->inline_keyboard()
	            ->row_button("Capturar", "Capturar $id", "TEXT")
	        ->show()
	        ->text("¡Ha aparecido un <b>" .$poke->name ."</b> de <b>" .$data->cp ." PC</b>!", 'HTML')
	    ->send();

		$this->chat->settings['pokemon_summon'] = $id .":" .$q['message_id'];
	}

	function pokemon_find_last($pokemon, $chat){
	    $query = $this->db
	        ->where('pokemon', $pokemon)
	        ->where('gid', $chat)
	        ->orderBy('id', 'DESC')
	    ->get($this->tables['pokemon'], 1);
	    if($this->db->count != 1){ return FALSE; }
	    return (object) $query;
	}

	function egg_generate($user, $pokemon = NULL){
	    if(empty($pokemon)){
	        $pokemon = $this->number(FALSE, 251);
	    }

	    $data = [
	        'uid' => $user,
	        'pokemon' => $pokemon,
	        'atk' => mt_rand(8, 15),
	        'def' => mt_rand(8, 15),
	        'sta' => mt_rand(8, 15),
	        'lvl' => mt_rand(25, 30)
	    ];

	    $stepIV = (($data['atk'] + $data['def'] + $data['sta']) / 45);
	    $stepLVL = ($data['lvl'] / 30);

	    $data['steps'] = ((250 * $stepLVL) + (250 * $stepIV));

	    $id = $this->db->insert($this->tables['eggs'], $data);
	    $data['id'] = $id;

	    return (object) $data;
	}

	function egg_active($user){
	    $query = $this->db
	        ->where('uid', $user)
	        ->where('steps > 0')
	    ->get($this->tables['eggs']);

	    if($this->db->count == 0){ return FALSE; }
	    return array_column($query, 'id');
	}

	function egg_step($user){
		$data = ['steps' => 'steps - 1'];
	    return $this->db
	        ->where('uid', $user)
	        ->where('steps >', 0)
	    ->update($this->tables['eggs'], $data);
	}

	function egg_open($user, $open = FALSE, $force = FALSE){
	    if($open == FALSE){
	        if(!$force){ $this->db->where('steps <= 0'); }
	        $query = $this->db
	            ->where('date_open IS NULL')
	            ->where('uid', $user)
	        ->get($this->tables['eggs']);
	        if($this->db->count == 0){ return FALSE; }
	        $this->egg_open($user, TRUE, $force); // RECURSIVE
	        return $query;
	    }else{
	        if(!$force){ $this->db->where('steps <= 0'); }
			$data = ['date_open' => date("Y-m-d H:i:s"), 'steps' => 0];
	        $query = $this->db
	            ->where('date_open IS NULL')
	            ->where('uid', $user)
	        ->update($this->tables['eggs'], $data);
			return $query;
	    }
	}

	function _item_manage($user, $item, $action = "+", $amount = 1){
	    $item = strtolower($item);
	    if(!in_array($item, ['pokeball', 'superball', 'ultraball', 'lure', 'incense'])){ return FALSE; }

		$data = [$item => $item ." $action $amount"];
	    $query = $this->db
	        ->where('uid', $user)
	    ->update($this->tables['account'], $data);
	    return $query;
	}

	function item_add($user, $item, $amount = 1){ return $this->_item_manage($user, $item, "+", $amount); }
	function item_remove($user, $item, $amount = 1){ return $this->_item_manage($user, $item, "-", $amount); }

	function items($user){
	    $query = $this->db->where('uid', $user)->get($this->tables['account']);

	    $items = array();
	    if($this->db->count == 1){
	        $items = $query;
			unset($items['uid']);
			unset($items['exp']);

	        $items['total_balls'] = 0;
			if($items['pokeball'] < 0 && $items['superball'] < 0 && $items['ultraball'] < 0){
				$items['total_balls'] = $items['pokeball'] + $items['superball'] + $items['ultraball']; // + MASTERBALL ?
			}else{
				if($items['pokeball'] > 0){ $items['total_balls'] += $items['pokeball']; }
				if($items['superball'] > 0){ $items['total_balls'] += $items['superball']; }
				if($items['ultraball'] > 0){ $items['total_balls'] += $items['ultraball']; }
			}
			/* $items['pokeball'] + $items['superball']
								+ $items['ultraball'];  */
	    }

	    return (object) $items;
	}

	function captured($user){
	    $query = $this->db
	        ->where('owner', $user)
		->where('disabled', FALSE)
	        // ->order_by('pokemon', 'ASC')
	        ->orderBy('cp', 'DESC')
	    ->get($this->tables['pokemon']);

	    if($this->db->count == 0){ return array(); }
	    return $query;
	}

	function message_items($user){
		$items = $this->items($user);
	    $str = "El inventario está vacío.";

	    $itarr = array();
		$itdev = array();
	    foreach($items as $name => $val){
	        if($name == "total_balls"){ continue; }
	        if($val > 0){ $itarr[] = "*$val* " .ucwords($name); }
			if($val < 0){ $itdev[] = "*" .abs($val) ."* " .ucwords($name); }
	    }
		if(!empty($itarr)){ $str = "Tienes " .implode(", ", $itarr) ."."; }
		if(!empty($itdev)){ $str .= "\nDebes " .implode(", ", $itdev) ." al banco regional. Por liante."; }

		return $str;
	}

	function delay_can_continue($chat, $amount = 2) {
		// $pokemon = new Pokemon();
		$last_throw = $this->chat->settings['pokegame_lastthrow'];
		if(!empty($last_throw)){
			$last_throw = (float) $last_throw;
			if(microtime(TRUE) < ($last_throw + $amount)){
				return FALSE;
			}
		}

		$this->chat->settings['pokegame_lastthrow'] = microtime(TRUE);
		return TRUE;
	}

	function is_legendary($number){
	    return in_array($number, [144, 145, 146, 150, 151, 243, 244, 245, 249, 250, 251]);
	}

	function number($legendary = FALSE, $top = 251){
		$num = mt_rand(1, $top);
		if(!$legendary && $this->is_legendary($num)){ return call_user_func(__FUNCTION__, $legendary, $top); }
		return $num;
	}

	function duel($user, $target, $tg){

	}
}
