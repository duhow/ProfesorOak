<?php

// TODO hacer Ataque X, Defensa X, Vida X para subir el IV de un Pokémon de forma permanente.
// TODO hacer Carameloraro para subir de nivel a un Pokémon.
// TODO hacer policía o incienso para alejar al Team Rocket y atraer más Pokémon.

function pokegame_exists($user){
    $CI =& get_instance();
    $query = $CI->db->where('uid', $user)->get('pokegame_account');
    return ($query->num_rows() == 1);
}

function pokegame_register($user){
    $CI =& get_instance();
    $query = $CI->db->set('uid', $user)->insert('pokegame_account');
    return $query;
}

function pokegame_half_inventory($user, $div = 2){
    $CI =& get_instance();
    $query = $CI->db
        ->set('pokeball', '(pokeball * ' .(1 - (1/$div)) .')', FALSE)
        ->set('superball', '(superball * ' .(1 - (1/$div)) .')', FALSE)
        ->set('ultraball', '(ultraball * ' .(1 - (1/$div)) .')', FALSE)
        ->where('uid', $user)
    ->update('pokegame_account');
    return $query;
}

function pokegame_pokestop_register($chat, $spins = 10){
    $CI =& get_instance();
    $query = $CI->db
        ->set('chat', $chat)
        ->set('spins', $spins)
    ->insert('pokegame_pokestop');
    return $CI->db->insert_id();
}

function pokegame_pokestop_spin($id, $user, $amount = NULL){
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

function pokegame_notify_item($target, $item){
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

function pokegame_pokemon_add($data){
    $CI =& get_instance();
    $query = $CI->db->insert('pokegame_sightseens', $data);
    return $CI->db->insert_id();
}

function pokegame_pokemon_view($id){
    $CI =& get_instance();
    $query = $CI->db->where('id', $id)->get('pokegame_sightseens');
    if($query->num_rows() == 1){ return $query->row(); }
    return FALSE;
}

function pokegame_pokemon_setowner($id, $user){
    $CI =& get_instance();
    return $query = $CI->db
        ->set('owner', $user)
        ->set('tries', 0)
        ->where('id', $id)
    ->update('pokegame_sightseens');
}

function pokegame_pokemon_trydown($id){
    $CI =& get_instance();
    return $CI->db
        ->where('id', $id)
        ->set('tries', 'tries - 1', FALSE)
    ->update('pokegame_sightseens');
}

function pokegame_pokemon_generate($chat, $num = NULL, $res = FALSE){
	if(empty($num)){
		$num = pokegame_number(FALSE, 251);
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

function pokegame_pokemon_appearview($chat, $id, $poke = NULL){
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

function pokegame_pokemon_find_last($pokemon, $chat){
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

function pokegame_egg_generate($user, $pokemon = NULL){
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

function pokegame_egg_active($user){
    $CI =& get_instance();
    $query = $CI->db
        ->where('uid', $user)
        ->where('steps > 0')
    ->get('pokegame_eggs');

    if($query->num_rows() == 0){ return FALSE; }
    return array_column($query->result_array(), 'id');
}

function pokegame_egg_step($user){
    $CI =& get_instance();
    return $CI->db
        ->where('uid', $user)
        ->where('steps >', 0)
        ->set('steps', 'steps - 1', FALSE)
    ->update('pokegame_eggs');
}

function pokegame_egg_open($user, $open = FALSE, $force = FALSE){
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

function __pokegame_item_manage($user, $item, $action = "+", $amount = 1){
    $item = strtolower($item);
    if(!in_array($item, ['pokeball', 'superball', 'ultraball', 'lure', 'incense'])){ return FALSE; }

    $CI =& get_instance();
    $query = $CI->db
        ->set($item, $item ." $action $amount", FALSE)
        ->where('uid', $user)
    ->update('pokegame_account');
    return $query;
}

function pokegame_item_add($user, $item, $amount = 1){ return __pokegame_item_manage($user, $item, "+", $amount); }
function pokegame_item_remove($user, $item, $amount = 1){ return __pokegame_item_manage($user, $item, "-", $amount); }

function pokegame_items($user){
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

function pokegame_captured($user){
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

function pokegame_message_items($user){
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

function pokegame_delay_can_continue($chat, $amount = 2) {
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

function pokegame_is_legendary($number){
    return in_array($number, [144, 145, 146, 150, 151, 243, 244, 245, 249, 250, 251]);
}

function pokegame_number($legendary = FALSE, $top = 251){
	$num = mt_rand(1, $top);
	if(!$legendary && pokegame_is_legendary($num)){ return call_user_func(__FUNCTION__, $legendary, $top); }
	return $num;
}

function pokegame_duel($user, $target, $tg){

}

// Anti cheats
if($pokemon->user_flags($telegram->user->id, ['summonear', 'poketelegram_cheat'])){ return; }

if($telegram->text_has("mis pokemon") && $telegram->words() <= 4){
    if(!pokegame_exists($telegram->user->id)){
        pokegame_register($telegram->user->id);
    }

    $captured = pokegame_captured($telegram->user->id);
    if(empty($captured)){
        $telegram->send
            ->text("No tienes ningún Pokémon capturado.")
        ->send();
        return -1;
    }

    $pokedex = $pokemon->pokedex();
    $str = "";
    $ln = count($captured);

    if($telegram->is_chat_group()){
        $str = "Tienes <b>" .count($captured) ."</b> Pokémon capturados" .(count($captured) > 6 ? ", entre ellos" : "") .":\n";
        $ln = min(6, count($captured));
    }

    for($i = 0; $i < $ln; $i++){
        $str .= "- L" .$captured[$i]['lvl'] ." <b>" .$pokedex[$captured[$i]['pokemon']]->name ."</b> " .$captured[$i]['cp'] ." PC" ."\n";
    }

    $telegram->send
        ->text($str, 'HTML')
    ->send();
}

if($telegram->text_has("inventario") && $telegram->words() <= 3){
	$target = $telegram->user->id;
	if($telegram->user->id == $this->config->item('creator') && $telegram->has_reply){
		$target = $telegram->reply_target('forward')->id;
	}

    if(!pokegame_exists($target)){
        pokegame_register($target);
    }

	$str = pokegame_message_items($target);

    $telegram->send
	->notification(TRUE)
	->chat($telegram->user->id)
        ->text($str, TRUE)
    ->send();
}

if(
	$telegram->text_command("pogo") &&
	$telegram->user->id == $this->config->item('creator') &&
	$telegram->words() > 1
){
	$data = array();
	$spanish = ['cebo' => 'lure', 'pokecebo' => 'lure', 'incienso' => 'incense'];

	foreach($telegram->words(TRUE) as $w){
		$w = strtolower($w);

		// Pokemon
		if(!isset($data['pokemon'])){
			$poke = pokemon_parse($w);
			if(!empty($poke['pokemon'])){ $data['pokemon'] = $poke['pokemon']; }
		}

		if(substr($w, -1) == "s"){ $w = substr($w, 0, -1); } // quitar S final.

		// Items
		if(!isset($data['item'])){
			if(in_array($w, ['pokeball', 'superball', 'ultraball', 'lure', 'incense'])){ $data['item'] = $w; }
			elseif(in_array($w, array_keys($spanish))){ $data['item'] = $spanish[$w]; }
		}

		if(!isset($data['amount']) && is_numeric($w) && $w <= 1000){
			$data['amount'] = (int) $w;
		}

		if(!isset($data['uid']) && is_numeric($w) && $w >= 100000){
			$data['uid'] = (int) $w;
		}

		if(!isset($data['all']) && in_array($w, ['all', 'todo', 'todos'])){ $data['all'] = TRUE; }
	}

	$give = FALSE;
	switch($telegram->words(1)){
		case 'give':
		case 'add':
		case 'dar':
		$give = TRUE;
		// ---------
		case 'steal':
		case 'quitar':
		case 'remove': // last pokemon
			// pokeball, superball, ultraball, all
			if(isset($data['item'])){
				$amount = ($data['amount'] ?: 1);
				$target = NULL;
				if($telegram->has_reply){
					$target = $telegram->reply_target('forward')->id;
				}elseif($telegram->text_mention()){
					$target = $telegram->text_mention();
					if(is_array($target)){ $target = key($target); }
					// TODO buscar @user
				}

				if($give){
					pokegame_item_add($target, $data['item'], $amount);
				}else{
					pokegame_item_remove($target, $data['item'], $amount);
				}

				$telegram->send
					->chat($target)
					->notification(FALSE)
					->text("¡Guau, te han " .($give ? "dado" : "quitado") ." $amount " .ucwords($data['item'])  ."s!")
				->send();

				$telegram->send
					->notification(FALSE)
					->text( ($give ? "Doy" : "Quito") ." $amount " .$data['item'] .".")
				->send();
			}elseif(isset($data['pokemon'])){

			}
			break;
		case 'all':
		case 'todos':

			break;
		case 'summon':
		case 'invocar':
			if(isset($data['pokemon'])){

			}elseif(isset($data['item']) && $data['item'] = 'lure'){

			}
			break;
        case 'info':
            if($telegram->has_reply && $telegram->reply_user->id == $this->config->item('telegram_bot_id') && isset($telegram->reply->sticker)){
                $sticker = $telegram->reply->sticker['file_id'];
                $poke = $pokemon->find($sticker, 'sticker');
                if($poke !== FALSE){
                    $reg = pokegame_pokemon_find_last($poke['id'], $telegram->chat->id);
                    if($reg){
                        $str = "*" .$poke['name'] ." #" .$poke['id'] ."*\n"
                            ."L" .$reg->lvl .", PC " .$reg->cp .": "
                            .$reg->atk ."/" .$reg->def ."/" .$reg->sta
                            ." (*" .round((($reg->atk + $reg->def + $reg->sta)/45)*100, 1) ."%*) \n"
                            ."Owner: " .($reg->owner ?: "---") ." " .date("d/m H:i:s", strtotime($reg->date));
                        $telegram->send->text($str, TRUE)->send();
                    }
                }
                // $telegram->send->text($sticker)->send();
                return -1;
            }elseif($telegram->has_reply){
				$target = $telegram->reply_target('forward')->id;

				$str = "No registrado.";
				if(pokegame_exists($target)){
					$items = pokegame_items($target);
					$str = json_encode($items);
				}

				$telegram->send
					->text($str)
				->send();
				return -1;
			}
            break;
		case 'pokestop':
		case 'stop':

			break;
		case 'teamrocket':
		case 'rocket':

			break;
	}
	return -1;
}

// ---------------------------------

if(!$telegram->is_chat_group()){ return; }
$play = $pokemon->settings($telegram->chat->id, 'pokegram');
if($play != NULL && $play == FALSE){ return; }

$pokeballs_sticker = [
    'pokeball' => 'BQADBAADDAgAAjbFNAAB6xJe3sYfW5QC',
    'superball' => 'BQADBAADDggAAjbFNAABArAsznkfzmAC',
    'ultraball' => 'BQADBAADEAgAAjbFNAABc1c6B4iXJosC',
    'masterball' => 'BQADBAADEggAAjbFNAABanVUlBU9VxwC'
];

if(
    ($telegram->callback && $telegram->text_has("capturar") && $telegram->words() == 2) or
    ($telegram->sticker() && in_array($telegram->sticker(), array_values($pokeballs_sticker)))
){

    /* if($telegram->user->id == $this->config->item('creator')){
        $sa = $pokemon->user_in_group($telegram->user->id, $telegram->chat->id);
        $pa = $telegram->send->get_member_info($telegram->user->id, $telegram->chat->id);
        $telegram->send->text(json_encode($sa) ."\n\n" .json_encode($pa))->send();
    } */

	/* if($pokemon->user_flags($telegram->user->id, 'pokegram')){
		$telegram->answer_if_callback("¡No tienes espacio para tantos Pokémon!", TRUE);
		return -1;
	} */

	// Check if can do action.
	if(!pokegame_delay_can_continue($telegram->chat->id, 2)){
		$telegram->answer_if_callback("");
		return -1;
	}

    if(!$telegram->user_in_chat($telegram->user->id, $telegram->chat->id)){
        $pokemon->user_delgroup($telegram->user->id, $telegram->chat->id);
        // $telegram->send->text("Eh, que " .$telegram->user->id ." no está!")->send();
        $telegram->answer_if_callback("Eh, tu no estás ahí!", TRUE);
		return -1;
	}

	$mid = TRUE; // Message ID for edit message

    if($telegram->sticker()){
        $pid = $pokemon->settings($telegram->chat->id, 'pokemon_summon');
        if(empty($pid)){ return; }
        $pid = explode(":", $pid);
        $mid = $pid[1];

        $poke = pokegame_pokemon_view($pid[0]);
    }else{
        $poke = pokegame_pokemon_view($telegram->last_word(TRUE));
    }

    if($poke === FALSE){
        $telegram->answer_if_callback($telegram->emoji(":forbid:") ." El Pokémon no existe.");
        $telegram->send
            ->message($mid)
            ->chat(TRUE)
            ->text($telegram->text_message() ."\nPero se fue...")
        ->edit('text');
        return -1;
    }

    if(!empty($poke->owner)){ return -1; } // FIX para escapes

    $items = pokegame_items($telegram->user->id);

    if(empty($items) or $items->total_balls <= 0){
        $telegram->answer_if_callback($telegram->emoji(":warning:") ." No tienes más Pokeballs!", TRUE);
        if($telegram->sticker()){
            $telegram->send
                ->notification(TRUE)
                ->chat($telegram->user->id)
                ->text($telegram->emoji(":warning:") ." No tienes más Pokeballs!")
            ->send();
        }
        return -1;
    }

    $sel = "";

    if($telegram->sticker()){
        foreach($pokeballs_sticker as $k => $stk){
            if($telegram->sticker() == $stk){
                if($items->{$k} > 0){ $sel = $k; }
                break;
            }
        }
    }

    if(empty($sel)){
        if($items->ultraball > 0){ $sel = "ultraball"; }
        elseif($items->superball > 0){ $sel = "superball"; }
        elseif($items->pokeball > 0){ $sel = "pokeball"; }
    }

    pokegame_item_remove($telegram->user->id, $sel);

    $prob = [
        "masterball" => 999,
        "ultraball" => 30,
        "superball" => 20,
        "pokeball" => 10,
    ];

    $pokeuser = $pokemon->user($telegram->user->id);

    $strbase = $telegram->text_message() ."\n- ";
    $str =  $pokeuser->username ." lanza ";

    $escape = $poke->flee - $prob[$sel];
    if(mt_rand(1, 4) == 4){
        $str .= "<b>¡CURVA!</b> ";
        $escape = $escape - 15;
    }

    $escape = max(0, $escape);
    $success = (mt_rand(1, 100) > $escape);

	// DEBUG
	/* $file = 'prueba.txt';
	$fp = fopen($file, 'a');
	fwrite($fp, date("H:i:s") ." " .microtime() . " - " . (int) $success ." / " .$poke->id ." | " .$telegram->user->id ."\n");
	fclose($fp); */

    if($success){
        pokegame_pokemon_setowner($poke->id, $telegram->user->id);
        $pokemon->settings($telegram->chat->id, 'pokemon_summon', 'DELETE');
    }

    $str .= ($success ? "<b>Y CAPTURA!</b>" : "y falla...");

    if(!$success or ($poke->tries - 1) < 0 ){
        pokegame_pokemon_trydown($poke->id);
        if(($poke->tries - 1) <= 0){
            $pokemon->settings($telegram->chat->id, 'pokemon_summon', 'DELETE');
			if(empty($poke->owner)){
				$str .= "\n¡El Pokémon ha escapado!";
			}
        }
    }

    if(!$success && ($poke->tries - 1) > 0){
        $telegram->send
            ->inline_keyboard()
                ->row_button("Capturar", "Capturar " .$poke->id, "TEXT")
            ->show();
    }

    if($telegram->sticker()){
        $telegram->send
            ->notification(FALSE)
            ->chat(TRUE)
            ->text($str, 'HTML')
        ->send();
    }else{
        $telegram->send
            ->message($mid)
            ->chat(TRUE)
            ->text($strbase .$str, 'HTML')
        ->edit('text');
    }

    $telegram->answer_if_callback("");
    return -1;
}

if($telegram->callback && $telegram->text_has("pokespin") && $telegram->words() == 2){
    if(!pokegame_exists($telegram->user->id)){
        pokegame_register($telegram->user->id);
    }

	// Check if can do action.
	if(!pokegame_delay_can_continue($telegram->chat->id, 4)){
		$telegram->answer_if_callback("");
		return -1;
	}

    if(!$telegram->user_in_chat($telegram->user->id, $telegram->chat->id)){
        $pokemon->user_delgroup($telegram->user->id, $telegram->chat->id);
        // $telegram->send->text("Eh, que " .$telegram->user->id ." no está!")->send();
        $telegram->answer_if_callback("Eh, tu no estás ahí!", TRUE);
        return -1;
    }

    $num = $telegram->last_word(TRUE);

    $spinid = $pokemon->settings($telegram->user->id, 'pokespin');
    if($spinid >= $num){
        $telegram->answer_if_callback("¡Ya has girado esta Pokeparada!", TRUE);
        return -1;
    }

    $pokemon->settings($telegram->user->id, 'pokespin', $num);
    $spins = pokegame_pokestop_spin($num, $telegram->user->id);

    // DEBUG
	/* $telegram->send
		->chat("-1001073465889")
		->text("S: " .$telegram->chat->id ." " .$telegram->user->id ." " .$spins)
	->send(); */

    $telegram->send->message(TRUE)->chat(TRUE);
    if($spins === FALSE or $spins === 0){
        $telegram->send
            ->text("¡No hay más objetos!", 'HTML')
        ->edit('text');
    }else{
        $telegram->send
            ->text('¡Hay una <b>Poképarada</b> cerca!', 'HTML')
            ->inline_keyboard()
                ->row_button("Girar ($spins)", "pokespin $num", "TEXT")
            ->show()
        ->edit('text');
    }

    return -1;
}

if($telegram->id % 66 == 0){
    if(!pokegame_exists($telegram->user->id)){
        pokegame_register($telegram->user->id);
    }

    if($pokemon->group_count_members($telegram->chat->id) >= 15){
        $items = ["pokeball", "superball", "ultraball"];
        $sel = mt_rand(0, count($items) - 1);

        pokegame_item_add($telegram->user->id, $items[$sel]);
        pokegame_notify_item($telegram->user->id, $items[$sel]);
    }

}

// Egg
if($pokemon->group_count_members($telegram->chat->id) >= 22){
    pokegame_egg_step($telegram->user->id);
}

$opens = pokegame_egg_open($telegram->user->id);

if($opens){
    $pokedex = $pokemon->pokedex();
    foreach($opens as $egg){
        $telegram->send
            ->notification(FALSE)
            ->chat($telegram->user->id)
        ->file('sticker', $pokedex[$egg['pokemon']]->sticker);

        $telegram->send
            ->chat($telegram->user->id)
            ->text("¡Ha salido un <b>" .$pokedex[$egg['pokemon']]->name ."</b> del huevo!", 'HTML')
        ->send();

        usleep(300000);
    }
}

if(
    $telegram->id % 111 == 0 or
    ($telegram->user->id == $this->config->item('creator') && $telegram->text_command("egg"))
){
    $target = $telegram->user->id;
    if($telegram->text_command("egg")){
        if($telegram->has_reply){ $target = $telegram->reply_user->id; }
    }

    if(!pokegame_exists($target)){
        pokegame_register($target);
    }

    if(!pokegame_egg_active($target)){
        $r = pokegame_egg_generate($target);

        $telegram->send
            ->notification(FALSE)
            ->chat($target)
        ->file('sticker', 'BQADBAADcggAAjbFNAAB4a2hODCkLw8C');

        $telegram->send
            ->notification(TRUE)
            ->chat($target)
            ->text("¡Guau! ¡Has encontrado un <b>huevo</b>!", 'HTML')
        ->send();
    }
}

if(
    (!$telegram->callback &&
    $telegram->id % 1337 == 0) or
    ($telegram->text_command("teamrocket") && $telegram->user->id == $this->config->item('creator'))
){
	if($telegram->text_command() or $pokemon->group_count_members($telegram->chat->id) >= 22){
		$members = $pokemon->group_get_members($telegram->chat->id);
		foreach($members as $m){
			if(pokegame_exists($m)){
				$items = pokegame_items($telegram->user->id);
				if($items->total_balls > 0){
					pokegame_half_inventory($m, 4);
				}
			}
		}

		$telegram->send
			->notification(TRUE)
		->file('sticker', 'BQADBAADKAgAAjbFNAABUl6LvyvgffoC');

		$telegram->send
			->text("¡Ha aparecido <b>el Team Rocket</b> y os ha robado un cuarto de vuestro inventario!", 'HTML')
		->send();
	}

}

if(
    (!$telegram->callback && $telegram->message % 667 == 0) or
    ($telegram->sticker() == "BQADBAADdAgAAjbFNAABYcghFn7ZiQIC") or
    ($telegram->text_command("pokestop") && $telegram->user->id == $this->config->item('creator'))
){
    if($telegram->sticker() == "BQADBAADdAgAAjbFNAABYcghFn7ZiQIC"){
        if($pokemon->group_count_members($telegram->chat->id) <= 15){
            $telegram->send
                ->notification(FALSE)
                ->text("No hay ninguna Pokeparada por aquí cerca...")
            ->send();
            return -1;
        }

        $items = pokegame_items($telegram->user->id);
        if($items->lure == 0){
            $telegram->send
                ->notification(FALSE)
                ->text("¡No tienes módulos!")
            ->send();
            return -1;
        }
        pokegame_item_remove($telegram->user->id, 'lure');
    }else{
        if(
            !$telegram->text_command("pokestop") &&
            $pokemon->group_count_members($telegram->chat->id) <= 25
        ){ return; }
    }

    $spins = mt_rand(10, 15);
    $id = pokegame_pokestop_register($telegram->chat->id, $spins);

    $telegram->send
        ->notification(FALSE)
        ->file('sticker', 'BQADAgADAQIAApb6EgUZaAnSEGh43gI');
    $telegram->send
        ->notification(FALSE)
        ->text('¡Hay una <b>Poképarada</b> cerca!', 'HTML')
        ->inline_keyboard()
            ->row_button("Girar ($spins)", "pokespin $id", "TEXT")
        ->show()
    ->send();
}

if(
	$telegram->chat->id == "-1001091905005" &&
	($telegram->key == 'message' &&
	$telegram->message % 30 == 0)
){

	/* $set = $pokemon->settings($telegram->chat->id, 'pokegame_spawns');
	if($set >= 20){ return; } */
	$num = pokegame_number(FALSE, 251);

	$poke = $pokemon->pokedex($num);

	$pokedata = pokegame_pokemon_generate($telegram->chat->id, $poke, TRUE);
	$id = pokegame_pokemon_add($pokedata);
	pokegame_pokemon_appearview($telegram->chat->id, $id, $poke);

	$pokemon->settings($telegram->chat->id, 'pokegame_spawns', ($set + 1));
}

if(
    ($telegram->key == 'message' && // No edited or callback
    $telegram->message % 49 == 0) or
    $telegram->sticker() == 'BQADBAADcAgAAjbFNAABpv0WcvCzRoIC' or
    ($telegram->text_command("summon") && $telegram->user->id == $this->config->item('creator'))
){
    if($telegram->sticker() == 'BQADBAADcAgAAjbFNAABpv0WcvCzRoIC'){
        if($pokemon->group_count_members($telegram->chat->id) <= 25){
            $telegram->send
                ->notification(FALSE)
                ->text("Esto está muy desierto...")
            ->send();
            return -1;
        }

        $items = pokegame_items($telegram->user->id);
        if($items->incense == 0){
            $telegram->send
                ->notification(FALSE)
                ->text("¡No tienes incienso!")
            ->send();
            return -1;
        }
        pokegame_item_remove($telegram->user->id, 'incense');
    }else{
        if(
            !$telegram->text_command("summon") &&
            $pokemon->group_count_members($telegram->chat->id) <= 15
        ){ return; }
    }

    $chat = $telegram->chat->id;
	$num = pokegame_number(FALSE, 251);
	if($telegram->text_command("summon") && $telegram->words() == 2){
		if(is_numeric($telegram->words(1, TRUE))){
			$num = intval($telegram->words(1, TRUE));
            if($num < 0){
                $chat = $num;
                $num = pokegame_number(FALSE, 251);
            }
		}else{
			$pk = pokemon_parse($telegram->words(1, TRUE));
			if(!empty($pk['pokemon'])){ $num = $pk['pokemon']; }
			else{ return -1; }
		}
	}

    if($telegram->text_command("summon") && $telegram->words() == 3){
        $chat = $telegram->last_word();
        if(!is_numeric($chat)){
            // TODO
        }
    }

	$poke = $pokemon->pokedex($num); // object

	$pokedata = pokegame_pokemon_generate($chat, $poke, TRUE);
	$id = pokegame_pokemon_add($pokedata);
	pokegame_pokemon_appearview($chat, $id, $poke);
}

/*

if(
    ($telegram->text_command("summon") or $telegram->text_command("pokestop") or
    $telegram->text_command("egg") or $telegram->text_command("pogo")) &&
    $telegram->user->id != $this->config->item('creator')
){
    $frases = [
        "¿Conque haciendo trampas? Muy bien. Tu ya no juegas.",
        "¿Ah si? Pues a tomar viento.",
        "Otro tocando lo que no debe... Ale, castigado."
    ];

    $rnd = mt_rand(0, count($frases) - 1);
    $pokemon->update_user_data($telegram->user->id, 'blocked', TRUE);
    $pokemon->user_flags($telegram->user->id, 'summonear', TRUE);

    $telegram->send
        ->text($frases[$rnd])
    ->send();
}

*/

if($telegram->sticker() == 'BQADBAADKAgAAjbFNAABUl6LvyvgffoC'){
    if(pokegame_exists($telegram->user->id)){
		$telegram->send->notification(FALSE);

		$items = pokegame_items($telegram->user->id);
		if($items->total_balls <= -5){
			$pokemon->update_user_data($telegram->user->id, 'blocked', TRUE);
			$pokemon->user_flags($telegram->user->id, 'team_rocket', TRUE);

			$frases = [
				"Te quedas sin jugar por gracioso.",
				"Ya veo que te gusta insistir... ¡SEGURIDAD!",
				"Tu no aprendes, ¿verdad? Luego no me vengas llorando.",
				"Has muerto para mi.",
				"Mira tio, vete a la mierda.",
				"Tu sigues en parvulitos, ¿no? Teniendo esa mentalidad, no mereces ni que te hable."
			];

			$rand = mt_rand(0, count($frases) - 1);
			$telegram->send
				->text($frases[$rand])
			->send();

			$telegram->send
				->chat($this->config->item('creator'))
				->text("<b>TR</b> al " .$telegram->user->id ." en " .$telegram->chat->id, 'HTML')
			->send();
			return -1;
		}

		if($items->total_balls <= 5 or mt_rand(1, 2) == 2){
			pokegame_half_inventory($telegram->user->id);
			pokegame_item_remove($telegram->user->id, 'pokeball', 15);
			pokegame_item_remove($telegram->user->id, 'superball', 15);
			pokegame_item_remove($telegram->user->id, 'ultraball', 15);

			$telegram->send
		        ->text("¡Un subnormal ha llamado al <b>Team Rocket</b> y <b>le han endeudado muchas Pokeball</b>! No va a poder jugar durante un tiempo...\n(Yo no volvería a hacerlo. Tu mismo. Luego no hay perdón que valga.)", 'HTML')
		    ->send();
		}else{
			pokegame_half_inventory($telegram->user->id);
			pokegame_half_inventory($telegram->user->id);
			pokegame_half_inventory($telegram->user->id);

			$items = pokegame_items($telegram->user->id);
			if($items->pokeball >= 1){ pokegame_item_remove($telegram->user->id, 'pokeball'); }
			if($items->superball >= 1){ pokegame_item_remove($telegram->user->id, 'superball'); }
			if($items->ultraball >= 1){ pokegame_item_remove($telegram->user->id, 'ultraball'); }

			$telegram->send
		        ->text("¡Un tio ha llamado al <b>Team Rocket</b> y <b>le han robado casi todo</b>!", 'HTML')
		    ->send();
		}

    }
}

?>
