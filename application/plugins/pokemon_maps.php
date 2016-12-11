<?php

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
    // RIP Pokecrew
    return array();

    $n = ($radius / 3);
    $rhor = ($n * 2);
    $rver = ($n * 1);
    $locNE = location_add($location, $rhor, 'RIGHT');
    $locNE = location_add($locNE, $rver, 'UP');
    $locSW = location_add($location, $rhor, 'LEFT');
    $locSW = location_add($locSW, $rver, 'DOWN');

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

function pokeradar($location, $radius = 3000, $limit = 10, $pokemon = 0){
    // Pokeradar.io
    $n = ($radius / 3);
    $rhor = ($n * 2);
    $rver = ($n * 1);
    $locNE = location_add($location, $rhor, 'RIGHT');
    $locNE = location_add($locNE, $rver, 'UP');
    $locSW = location_add($location, $rhor, 'LEFT');
    $locSW = location_add($locSW, $rver, 'DOWN');

    $data = [
        'minLatitude' => $locNE[0],
        'maxLatitude' => $locNE[1],
        'minLongitude' => $locSW[0],
        'maxLongitude' => $locSW[1],
        'pokemonId' => $pokemon,
    ];
    $url = "https://www.pokeradar.io/api/v1/submissions";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, ($url ."?" .http_build_query($data)) );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $json = curl_exec($ch);
    curl_close($ch);

    return strlen($json); // TEMP
    $json = json_decode($json, TRUE);
    if(count($json['data']) == 0){ return array(); }
    $seens = array();
    $pokes = array();
    foreach($json['data'] as $pk){
        if(in_array($pk['pokemonId'], $pokes)){ continue; }
        if(count($seens) >= $limit){ break; }
        if(!empty($pokemon) && $pokemon != $pk['pokemonId']){ continue; }
        if(!empty($pokemon) && count($seens) == 1){ break; } // HACK Están ordenados por más reciente, asi que me quedo sólo con el primero.
        $locpk = [$pk['latitude'], $pk['longitude']];
        $seens[] = [
            'id' => $pk['id'],
            'lat' => $pk['latitude'],
            'lng' => $pk['longitude'],
            'pokemon' => $pk['pokemonId'],
            'last_seen' => $pk['expires'],
            'points' => 0,
            'distance' => location_distance($location, $locpk),
        ];
    }

    return $seens;
}

function pokeradarasset(){
    // https://s3.amazonaws.com/pokeradarassets/node-data.json
}



?>
