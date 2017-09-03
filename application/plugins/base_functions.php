<?php

function text_find($find, $text){
    $text = strtolower($text);
    $text = str_replace(["á","é"], ["a","e"], $text);
    $text = str_replace("?", "", $text);

    if(!is_array($find)){ $find = [$find]; }
    foreach($find as $w){
        // if(strpos($text, $find) !== FALSE){ return TRUE; }
		if(strpos($text, $w) !== FALSE){ return TRUE; }
    }
    return FALSE;
}

function telegram_admins($add_creator = TRUE, $custom = NULL, $chatid = NULL){
    $CI =& get_instance();
    $pokemon = new Pokemon();
    $telegram = new Telegram();

	if(empty($chatid)){ $chatid = $telegram->chat->id; }

    $admins = $pokemon->group_admins($chatid);
    if(empty($admins)){
        $admins = $telegram->get_admins($chatid); // Del grupo
        $pokemon->group_admins($chatid, $admins);
    }
	$setadmins = $pokemon->settings($chatid, "admins");
	if(!empty($setadmins)){
		$setadmins = explode(",", $setadmins);
		foreach($setadmins as $a){ $admins[] = $a; }
	}
    if($add_creator){ $admins[] = $CI->config->item('creator'); }
    if($custom != NULL){
        if(!is_array($custom)){ $custom = [$custom]; }
        foreach($custom as $c){ $admins[] = $c; }
    }
	$admins = array_unique($admins);
    return $admins;
}

function time_parse($string){
	$string = strtolower($string);
	$string = str_replace(["á","é"], ["a","e"], $string);
	$string = str_replace(["?", "¿", "!"], "", $string);
    $s = explode(" ", $string);
    $data = array();
    $number = NULL;
    // ---------
    $days = [
        'lunes' => 'monday', 'martes' => 'tuesday',
        'miercoles' => 'wednesday', 'jueves' => 'thursday',
        'viernes' => 'friday', 'sabado' => 'saturday',
        'domingo' => 'sunday'
    ];
    $months = [
        'enero' => 'january', 'febrero' => 'february', 'marzo' => 'march',
        'abril' => 'april', 'mayo' => 'may', 'junio' => 'june',
        'julio' => 'july', 'agosto' => 'august', 'septiembre' => 'september',
        'octubre' => 'october', 'noviembre' => 'november', 'diciembre' => 'december'
    ];
    $waiting_month = FALSE;
    $waiting_time = FALSE;
    $waiting_time_add = FALSE;
    $select_week = FALSE;
    $next_week = FALSE;
    $last_week = FALSE;
    $this_week_day = FALSE;
    foreach($s as $w){
        if($w == "de" && (!isset($data['date']) or empty($data['date']) )){ $waiting_month = TRUE; } // FIXME not working?
        if(!isset($data['hour']) and in_array($w, ["la", "las"])){ $waiting_time = TRUE; }
        if(!isset($data['hour']) and $w == "en"){ $waiting_time_add = TRUE; }

        if(is_int($w) or (in_array(strlen($w), [2,3]) && substr($w, -1) == "h")){
            $number = (int) abs($w);
            if($waiting_time){
                if($number >= 24){ continue; }
                if($number <= 6){ $number = $number + 12; }
                $data['hour'] = $number .":00";
                $waiting_time = FALSE;
            }
            continue;
        }

        if(!isset($data['hour']) && preg_match("/(\d\d?)[:.](\d\d)/", $w, $hour)){
            if($hour[1] >= 24){ $hour[1] = "00"; }
            if($hour[2] >= 60){ $hour[2] = "00"; }
            $data['hour'] = "$hour[1]:$hour[2]";
            continue;
        }

        if($waiting_time && in_array($w, ['noche']) && !isset($data['hour'])){
            $data['hour'] = "22:00";
            $waiting_time = FALSE;
            continue;
        }
        if($waiting_time && in_array($w, ['tarde']) && !isset($data['hour'])){
            $data['hour'] = "18:00";
            $waiting_time = FALSE;
            continue;
        }
        if($waiting_time && in_array($w, ['mañana', 'maana', 'manana']) && !isset($data['hour'])){
            $data['hour'] = "11:00";
            $waiting_time = FALSE;
            continue;
        }
        if($waiting_time_add && in_array($w, ['hora', 'horas']) && !isset($data['hour'])){
            $hour = date("H") + $number;
            if(date("i") >= 30){ $hour++; } // Si son más de y media, suma una hora.
            $data['hour'] = $hour .":00";
            if(!isset($data['date'])){ $data['date'] = date("Y-m-d"); } // HACK bien?
            $waiting_time_add = FALSE;
            continue;
        }
        if(in_array($w, array_keys($days)) && ($next_week or $last_week or $this_week_day) && !isset($data['date'])){
            $selector = "+1 week next";
            if($this_week_day && date("w") <= date("w", strtotime($days[$w]))){ $selector = "this"; }
            if($this_week_day && date("w") > date("w", strtotime($days[$w]))){ $selector = "next"; }
            if($last_week){ $selector = "last"; } // && date("w") > date("w", strtotime($days[$w]))
            if($next_week && date("w") >= date("w", strtotime($days[$w]))){ $selector = "next"; }
            $data['date'] = date("Y-m-d", strtotime($selector ." " .$days[$w]));
            $next_week = FALSE;
            $last_week = FALSE;
            $this_week_day = FALSE;
            continue;
        }
        if(in_array($w, array_keys($months))){ // FIXME $waiting_month no funciona
            if($number >= 1 && $number <= 31){
                $data['date'] = date("Y-m-d", strtotime($months[$w] ." " .$number));
            }
            $waiting_month = FALSE;
            continue;
        }
        if($w == "semana" && !isset($data['date'])){
            if($next_week){
                $data['date'] = date("Y-m-d", strtotime("next week"));
                $next_week = FALSE;
                continue;
            }
            $select_week = TRUE;
            continue;
        }
        if(in_array($w, ["proximo", "próximo", "proxima", "próxima", "siguiente"])){
            // proximo lunes != ESTE lunes, esta semana
            if($select_week && !isset($data['date'])){
                $data['date'] = date("Y-m-d", strtotime("next week"));
                $select_week = FALSE;
                continue;
            }
            $next_week = TRUE;
            continue;
        }
        if(in_array($w, ["pasado", "pasada"])){
            if(!isset($data['date']) or empty($data['date'])){
                if($this_week_day){ $this_week_day = FALSE; }
                if($select_week){
                    // last week = LUNES, marca el dia de hoy!
                    $en_days = array_values($days);
                    $data['date'] = date("Y-m-d", strtotime("last week " .$en_days[date("N") - 1]));
                    $select_week = FALSE;
                    continue;
                }
                $last_week = TRUE;
                continue;
            }
            // el pasado martes, el martes pasado.
            $tmp = new DateTime($data['date']);
            $tmp->modify('-1 week');
            $data['date'] = $tmp->format('Y-m-d');
            continue;
        }
        if(in_array($w, ["este", "el"])){
            // este lunes
            $this_week_day = TRUE;
            continue;
        }
        if(in_array($w, ['mañana', 'maana', 'manana']) && !isset($data['date'])){
            // Distinguir mañana de "por la mañana"
            $data['date'] = date("Y-m-d", strtotime("tomorrow"));
            continue;
        }
        if($w == "hoy" && !isset($data['date'])){
            $data['date'] = date("Y-m-d"); // TODAY
            continue;
        }
        if($w == "ayer" && !isset($data['date'])){
            $data['date'] = date("Y-m-d", strtotime("yesterday"));
            continue;
        }
    }

    if(isset($data['date'])){
        $strdate = $data['date'] ." " .(isset($data['hour']) ? $data['hour'] : "00:00");
        $strdate = strtotime($strdate);
        $data['left_hours'] = floor(($strdate - time()) / 3600);
        $data['left_minutes'] = floor(($strdate - time()) / 60);
    }

    return $data;
}

function pokemon_parse($string){
    $pokemon = new Pokemon();

    $pokes = $pokemon->pokedex();
    $s = explode(" ", $pokemon->misspell($string));
    $data = array();
	$pokespecial = [
		122 => ["mr.mime", "mime", "mrmime"],
		29 => ["nidoran♀", "nidoranf", "nidoranhembra"],
		32 => ["nidoran♂", "nidoranm", "nidoranmacho"]
	];

    $number = NULL;
    $hashtag = FALSE;
    // ---------
    $data['pokemon'] = NULL;
    foreach($s as $w){
        $hashtag = ($w[0] == "#" and strlen($w) > 1);
        // $w = $telegram->clean('alphanumeric', $w);
        $w = strtolower($w);

		if($hashtag and is_numeric(substr($w, 1))){
			$w = substr($w, 1);
		}

        if($data['pokemon'] === NULL and !is_numeric($w)){
            foreach($pokes as $pk){
                if(
                    ($w == strtolower($pk->name)) or
                    // HACK plural a singular
                    (substr($w, -1) == "s" && substr($w, 0, -1) == strtolower($pk->name))
                ){ $data['pokemon'] = $pk->id; break; }
            }
			// Si no lo ha encontrado, busco especiales.
			if($data['pokemon'] === NULL){
				foreach($pokespecial as $num => $list){
					if(in_array($w, $list)){
						$data['pokemon'] = $num;
						break;
					}
				}
			}
			if($data['pokemon'] !== NULL){ continue; } // Si hay resultado, pasa a la siguiente acción
        }

        if(is_numeric($w)){
            // tengo un número pero no se de qué. se supone que la siguiente palabra me lo dirá.
            // a no ser que la palabra sea un "DE", en cuyo caso paso a la siguiente.
            if($hashtag and $data['pokemon'] === NULL){
				$data['pokemon'] = (int) $w;
				continue; // HACK
			}
            $number = (int) $w;
        }

        // Buscar distancia
        if(substr($w, -1) == "m"){ // Metros
            $n = substr($w, 0, -1);
            if(!is_numeric($n) && substr($n, -1) == "k"){ // Kilometros
                $n = substr($n, 0, -1);
                if(is_numeric($n)){ $n = $n * 1000; }
            }
            if(is_numeric($n)){
                $data['distance'] = $n;
				continue;
            }
        }

        // Si se escribe numero junto a palabra, separar
        $conj = ['cp', 'pc', 'hp', 'ps'];
        foreach($conj as $wf){
            if(substr($w, -2) == $wf){
                $n = substr($w, 0, -2);
                if(is_numeric($n)){
                    $number = $n;
                    $w = $wf;
                }
            }
        }

        if(in_array($w, ["ataque", "ATQ", "ATK"])){ $data['attack'] = TRUE; }
        if(in_array($w, ["defensa", "DEF"])){ $data['defense'] = TRUE; }
        if(in_array($w, ["salud", "stamina", "estamina", "STA"])){ $data['stamina'] = TRUE; }
        if(in_array($w, ["huevo"])){ $data['egg'] = TRUE; }

        // $data['powered'] = if(in_array($w, ["mejorado", "entrenado", "powered"]) && !text_find(["sin mejorar", "no mejorado"], $string));

        $search = ['cp', 'pc', 'hp', 'ps', 'polvo', 'polvos', 'caramelo', 'polvoestelar', 'stardust', 'm', 'metro', 'km'];
        $enter = FALSE;
        foreach($search as $q){
            if(strpos($w, $q) !== FALSE){ $enter = TRUE; break; }
        }
        if($enter){
            $action = NULL;
            if(strpos($w, 'cp') !== FALSE or strpos($w, 'pc') !== FALSE){ $action = 'cp'; }
            if(strpos($w, 'hp') !== FALSE or strpos($w, 'ps') !== FALSE){ $action = 'hp'; }
            if(strpos($w, 'polvo') !== FALSE or strpos($w, 'stardust') !== FALSE or strpos($w, 'polvoestelar') !== FALSE){ $action = 'stardust'; }
            if(strpos($w, 'm') !== FALSE && strlen($w) == 1){ $action = 'distance'; }
            if(strpos($w, 'caramelo') !== FALSE){ $action = 'candy'; }
            if(strpos($w, 'metro') !== FALSE){ $action = 'distance'; }
            if(strpos($w, 'km') !== FALSE && strlen($w) == 2){ $action = 'distance'; $number = $number * 1000; }

            if(strlen($w) > 2 && $number === NULL){
                // Creo que me lo ha puesto junto. Voy a sacar números...
                $number = filter_var($w, FILTER_SANITIZE_NUMBER_INT);
            }

            if(
                (!empty($number) && !empty($action)) and
                ( ($action == 'hp' && $number > 5 && $number < 300) or
                ($action == 'stardust' && $number > 200 && $number <= 10000) or
                ($action == 'distance') or
                ($number > 5 && $number < 4000) )
            ){
                $data[$action] = $number;
                $number = NULL;
            }
        }
    }

    if(text_find(["muy fuerte", "lo mejor", "flipando", "fuera de", "muy fuertes", "muy alto", "muy alta", "muy altas"], $string)){ $data['ivcalc'] = [15]; }
    if(text_find(["bueno", "bastante bien", "buenas", "normal", "muy bien"], $string)){ $data['ivcalc'] = [8,9,10,11,12]; }
    if(text_find(["bajo", "muy bajo", "poco que desear", "bien"], $string)){ $data['ivcalc'] = [0,1,2,3,4,5,6,7]; }
    if(text_find(["fuerte", "fuertes", "excelente", "excelentes", "impresionante", "impresionantes", "alto", "alta"], $string)){ $data['ivcalc'] = [13,14]; }

    return $data;
}

function color_parse($string, $retkey = TRUE){
	if(substr($string, 0, 1) != '"'){
		$string = json_encode($string);
	}


	$string = str_replace('"', '', $string);
	$string = strtolower($string);

	// return $string;
	$equipos = [
		'Y' => ['amarillo', 'amarilla', 'yellow', 'instinto', 'instict', 'instinct', 'instincto', 'zapdos', 'sparky', ':heart-yellow:'],
		'R' => ['rojo', 'roja', 'red', 'valor', 'moltres', 'candela', ':heart-red:'],
		'B' => ['azul', 'azúl', 'azules', 'blue', 'sabidurí­a', 'sabiduria', 'mystic', 'articuno', 'blanche', ':heart-blue:']
	];

	$teamsel = NULL;
	foreach($equipos as $team => $find){
		if(text_find($find, $string)){
			$teamsel = $team;
			break;
		}
	}

	if(empty($teamsel)){ return FALSE; }
	if($retkey == TRUE){ return $teamsel; }
	elseif(is_array($retkey) && count($retkey) == 3){ // Pasar texto de team como array directamente
		return $retkey[$teamsel];
	}
	// else
	$teams = ['Y' => 'yellow', 'R' => 'red', 'B' => 'blue'];
	return $teams[$teamsel];

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
	$cal = ($amount / $earth);

	foreach(str_split($direction) as $dir){
		if($dir == 'N'){ $locA[0] = $locA[0] + $cal; }
		elseif($dir == 'S'){ $locA[0] = $locA[0] - $cal; }
		elseif($dir == 'W'){ $locA[1] = $locA[1] - $cal; }
		elseif($dir == 'E'){ $locA[1] = $locA[1] + $cal; }
	}

	return $locA;
}

?>
