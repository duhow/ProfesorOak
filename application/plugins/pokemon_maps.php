<?php

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
