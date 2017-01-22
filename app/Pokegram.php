<?php

class Pokegram extends TelegramApp\Module {
	protected $runCommands = FALSE;
	protected $tables = [
		'account' => 'pokegame_account',
		'pokestop' => 'pokegame_pokestop',
		'pokemon' => 'pokegame_sightseens',
		'eggs' => 'pokegame_eggs',
	];

	// TODO hacer Ataque X, Defensa X, Vida X para subir el IV de un Pokémon de forma permanente.
	// TODO hacer Carameloraro para subir de nivel a un Pokémon.
	// TODO hacer policía o incienso para alejar al Team Rocket y atraer más Pokémon.

	function exists($user){
	    $query = $this->db
			->where('uid', $user)
		->get('pokegame_account');
	    return ($this->db->count == 1);
	}

	function register($user){
	    $query = $this->db->insert('pokegame_account', ['uid' => $user]);
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
		->update('pokegame_account', $data);
		return $query;
	}

	function pokestop_register($chat, $spins = 10){
	    $CI =& get_instance();
	    $query = $CI->db
	        ->set('chat', $chat)
	        ->set('spins', $spins)
	    ->insert('pokegame_pokestop');
	    return $CI->db->insert_id();
	}

	function pokestop_spin($id, $user, $amount = NULL){
	    $CI =& get_instance();
	    $query = $CI->db
	        ->where('id', $id)
	    ->get('pokegame_pokestop');

	    if($query->num_rows() != 1){ return FALSE; }
	    $pokestop = $query->row();
	    if($pokestop->spins <= 0){ return FALSE; }

	    $items = ['pokeball', 'superball', 'ultraball'];

	    $query = $CI->db
	        ->where('id', $id)
	        ->set('spins', 'spins - 1', FALSE)
	    ->update('pokegame_pokestop');

		if(!is_numeric($amount)){ $amount = mt_rand(3, 5); }
	    for($i = 0; $i < $amount; $i++){
	        $rand = mt_rand(0, count($items) - 1);
	        pokegame_item_add($user, $items[$rand]);
	        pokegame_notify_item($user, $items[$rand]);
	    }

	    return ($pokestop->spins - 1);
	}

	function notify_item($target, $item){
	    $telegram = new Telegram();

	    $items = [
	        'pokeball' => 'BQADBAADDAgAAjbFNAAB6xJe3sYfW5QC',
	        'superball' => 'BQADBAADDggAAjbFNAABArAsznkfzmAC',
	        'ultraball' => 'BQADBAADEAgAAjbFNAABc1c6B4iXJosC'
	    ];

	    if(!in_array($item, array_keys($items))){ return FALSE; }

	    $telegram->send
	        ->chat($target)
	        ->notification(FALSE)
	    ->file('sticker', $items[$item]);
	    usleep(100000);

	    $telegram->send
	        ->chat($target)
	        ->notification(FALSE)
	        ->text("¡Has encontrado una <b>" .ucwords($item) ."</b>!", 'HTML')
	    ->send();
	    usleep(100000);

	    if($telegram->callback){
	        $telegram->answer_if_callback("¡Has encontrado una " .ucwords($item) ."!");
	        usleep(100000);
	    }
	}

	function pokemon_add($data){
	    $CI =& get_instance();
	    $query = $CI->db->insert('pokegame_sightseens', $data);
	    return $CI->db->insert_id();
	}

	function pokemon_view($id){
	    $CI =& get_instance();
	    $query = $CI->db->where('id', $id)->get('pokegame_sightseens');
	    if($query->num_rows() == 1){ return $query->row(); }
	    return FALSE;
	}

	function pokemon_setowner($id, $user){
	    $CI =& get_instance();
	    return $query = $CI->db
	        ->set('owner', $user)
	        ->set('tries', 0)
	        ->where('id', $id)
	    ->update('pokegame_sightseens');
	}

	function pokemon_trydown($id){
	    $CI =& get_instance();
	    return $CI->db
	        ->where('id', $id)
	        ->set('tries', 'tries - 1', FALSE)
	    ->update('pokegame_sightseens');
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
		}elseif(pokegame_is_legendary($poke->id)){
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
		$telegram = new Telegram();
		$pokemon = new Pokemon();

		$data = pokegame_pokemon_view($id);
		if(empty($data)){ return; }

		if(empty($poke)){
			$poke = $pokemon->pokedex($data->pokemon);
		}

		$telegram->send
			->notification(FALSE)
			->chat($chat)
		->file('sticker', $poke->sticker);

	    $q = $telegram->send
	        ->inline_keyboard()
	            ->row_button("Capturar", "Capturar $id", "TEXT")
	        ->show()
	        ->text("¡Ha aparecido un <b>" .$poke->name ."</b> de <b>" .$data->cp ." PC</b>!", 'HTML')
	    ->send();

	    $pokemon->settings($chat, 'pokemon_summon', $id .":" .$q['message_id']);
	}

	function pokemon_find_last($pokemon, $chat){
	    $CI =& get_instance();
	    $query = $CI->db
	        ->where('pokemon', $pokemon)
	        ->where('gid', $chat)
	        ->order_by('id', 'DESC')
	        ->limit(1)
	    ->get('pokegame_sightseens');
	    if($query->num_rows() != 1){ return FALSE; }
	    return $query->row();
	}

	function egg_generate($user, $pokemon = NULL){
	    $CI =& get_instance();

	    if(empty($pokemon)){
	        $pokemon = pokegame_number(FALSE, 251);
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

	    $CI->db->insert('pokegame_eggs', $data);
	    $data['id'] = $CI->db->insert_id();

	    return (object) $data;
	}

	function egg_active($user){
	    $CI =& get_instance();
	    $query = $CI->db
	        ->where('uid', $user)
	        ->where('steps > 0')
	    ->get('pokegame_eggs');

	    if($query->num_rows() == 0){ return FALSE; }
	    return array_column($query->result_array(), 'id');
	}

	function egg_step($user){
	    $CI =& get_instance();
	    return $CI->db
	        ->where('uid', $user)
	        ->where('steps >', 0)
	        ->set('steps', 'steps - 1', FALSE)
	    ->update('pokegame_eggs');
	}

	function egg_open($user, $open = FALSE, $force = FALSE){
	    $CI =& get_instance();
	    if($open == FALSE){
	        if(!$force){ $CI->db->where('steps <= 0'); }
	        $query = $CI->db
	            ->where('date_open IS NULL')
	            ->where('uid', $user)
	        ->get('pokegame_eggs');
	        if($query->num_rows() == 0){ return FALSE; }
	        pokegame_egg_open($user, TRUE, $force); // RECURSIVE
	        return $query->result_array();
	    }else{
	        if(!$force){ $CI->db->where('steps <= 0'); }
	        $query = $CI->db
	            ->where('date_open IS NULL')
	            ->where('uid', $user)
	            ->set('date_open', date("Y-m-d H:i:s"))
	            ->set('steps', 0)
	        ->update('pokegame_eggs');
	    }
	}

	function _item_manage($user, $item, $action = "+", $amount = 1){
	    $item = strtolower($item);
	    if(!in_array($item, ['pokeball', 'superball', 'ultraball', 'lure', 'incense'])){ return FALSE; }

	    $CI =& get_instance();
	    $query = $CI->db
	        ->set($item, $item ." $action $amount", FALSE)
	        ->where('uid', $user)
	    ->update('pokegame_account');
	    return $query;
	}

	function item_add($user, $item, $amount = 1){ return $this->_item_manage($user, $item, "+", $amount); }
	function item_remove($user, $item, $amount = 1){ return $this->_item_manage($user, $item, "-", $amount); }

	function items($user){
	    $CI =& get_instance();
	    $query = $CI->db->where('uid', $user)->get('pokegame_account');

	    $items = array();
	    if($query->num_rows() == 1){
	        $items = $query->row_array();
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
	    $CI =& get_instance();
	    $query = $CI->db
	        ->where('owner', $user)
		->where('disabled', FALSE)
	        // ->order_by('pokemon', 'ASC')
	        ->order_by('cp', 'DESC')
	    ->get('pokegame_sightseens');

	    if($query->num_rows() == 0){ return array(); }
	    return $query->result_array();
	}

	function message_items($user){
		$items = pokegame_items($user);
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
		$pokemon = new Pokemon();
		$last_throw = $pokemon->settings($chat, 'pokegame_lastthrow');
		if(!empty($last_throw)){
			$last_throw = (float) $last_throw;
			if(microtime(TRUE) < ($last_throw + $amount)){
				return FALSE;
			}
		}

		$pokemon->settings($chat, 'pokegame_lastthrow', microtime(TRUE));
		return TRUE;
	}

	function is_legendary($number){
	    return in_array($number, [144, 145, 146, 150, 151, 243, 244, 245, 249, 250, 251]);
	}

	function number($legendary = FALSE, $top = 251){
		$num = mt_rand(1, $top);
		if(!$legendary && pokegame_is_legendary($num)){ return call_user_func(__FUNCTION__, $legendary, $top); }
		return $num;
	}

	function duel($user, $target, $tg){

	}


	public function hooks(){

	}
}
