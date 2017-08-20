<?php

function map_search($search, $tg = NULL){
	// $search = str_replace("en ", "in ", trim($search));
	if(empty($search) or strlen($search) <= 2){ return NULL; }

	$data = ["address" => $search];
	$web = "https://maps.googleapis.com/maps/api/geocode/json?" .http_build_query($data);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $web);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 7); //timeout in seconds

	$loc = curl_exec($ch);
	curl_close($ch);

	$ret = json_decode($loc);
	// $str = "No lo encuentro.";
	if(!empty($ret) && $ret->status == "OK"){
		$loc = $ret->results[0]->geometry->location;
		// $str = $ret->results[0]->formatted_address;
		if($tg === TRUE){
			return [$loc->lat, $loc->lng];
			// return $loc;
		}elseif($tg !== NULL){
			return $tg->send
				->location($loc->lat, $loc->lng)
			->send();
		}
	}
	if($tg === TRUE){ return FALSE; }
	return $ret;
}

if($telegram->text_command("avoice")){
    $voice = NULL;
    if($telegram->has_reply){
        $voice = $telegram->reply->audio['file_id'];
    }elseif($telegram->words() == 2){
        $voice = $telegram->last_word();
    }
    if(empty($voice)){ return; }
    $telegram->send->chat_action('upload_audio')->send();
    $telegram->send->file("voice", $telegram->download($voice));
    return -1;
}elseif($telegram->text_command("dvideo")){
    $doc = NULL;
    if($telegram->has_reply && strpos($telegram->reply->document['mime_type'], "video") !== FALSE){
        $doc = $telegram->reply->document['file_id'];
    }elseif($telegram->words() == 2){
        $doc = $telegram->last_word();
    }
    if(empty($doc)){ return; }
    $telegram->send->chat_action('upload_video')->send();
    $telegram->send->file("video", $telegram->download($doc));
    return -1;
}elseif(
	$telegram->text_command("pic") or
	$telegram->text_command("img") or
	$telegram->text_command("photo")
){
    if($telegram->has_reply && isset($telegram->reply->photo)){
	$photo = $telegram->reply->photo;
	$telegram->send->text($photo[count($photo) - 1]['file_id'])->send();
    }elseif($telegram->words() == 2){
        $pic = $telegram->last_word();
        $pic = str_replace('"', '', $pic);
        $telegram->send->file('photo', $pic);
    }
    return -1;
}elseif($telegram->text_command("sticker")){
	if($telegram->words() == 2){
		$telegram->send->file('sticker', $telegram->last_word());
	}elseif($telegram->has_reply){
		if(isset($telegram->reply->sticker)){
			$telegram->send
				->reply_to(FALSE)
				->text($telegram->reply->sticker['file_id'])
			->send();
		}
	}
	return -1;
}elseif($telegram->text_command("vardump") && $telegram->has_reply){
    $telegram->send->text( $telegram->dump(TRUE) )->send();
    return -1;
}elseif($telegram->text_command("timestamp")){
    $str = time();
    if($telegram->words() == 2 && is_numeric($telegram->last_word())){
        $last = intval($telegram->last_word());
        $str = (time() - $last);
    }else{
		$str = substr($telegram->text(), strpos("/timestamp ") + strlen("/timestamp "));
		$str = strtotime($str);
	}

    $telegram->send
        ->notification(FALSE)
        ->text($str)
    ->send();

    return -1;
}elseif($telegram->text_command("str") && $telegram->has_reply && $telegram->words() >= 2){
    $cmd = strtolower($telegram->words(1, TRUE));
    $rtext = $telegram->reply->text;
    if(empty($rtext)){ return; }
    $text = "";

    if(in_array($cmd, ['lenght', 'length', 'len'])){
        $rlenw = count(explode(" ", $rtext));
        $text = strlen($rtext) ." ($rlenw)";
    }elseif(in_array($cmd, ['reverse', 'revertir', 'rev'])){
        $text = strrev($rtext);
    }elseif(in_array($cmd, ['tolower', 'lower', 'low'])){
        $text = strtolower($rtext);
    }elseif(in_array($cmd, ['dec', 'decimal'])){
        // mirar si es octal, binario o hexa
    }elseif(in_array($cmd, ['hex', 'hexadecimal'])){
        // si ya es hexadecimal, pasar a string. CUIDAO con carácteres raros. FIXME
        if(preg_match("/[^A-F0-9]/i", strtoupper($rtext))){
            if(is_numeric($rtext)){
                // TODO mayus o minus?
                $text = strtoupper(dechex($rtext));
            }else{
                $text = strtoupper(bin2hex($rtext));
            }
        }else{
            // Entonces es que es hex.
            $text = hex2bin($rtext);
        }
    }elseif(in_array($cmd, ['rot'])){
        $text = str_rot13($rtext);
    }elseif(in_array($cmd, ['sha1', 'sha'])){
        $text = sha1($rtext);
    }elseif(in_array($cmd, ['md5'])){
        $text = md5($rtext);
    }elseif(in_array($cmd, ['sha2', 'sha256'])){
        $text = hash('sha256', $rtext);
    }

    if(in_array($cmd, ['klingon', 'tlh'])){
        $web = 'https://api.microsofttranslator.com/v2/ajax.svc/TranslateArray?appId="TJ5Ome4IYV52l53wcVXxbCgdtV2w3lV0zFQGqWC9rjL0*"&texts=' . json_encode([$rtext]) .'&from="tlh"&to="en"';
        $web = urlencode($web);
        $tran = file_get_contents($web);
        $text = json_encode($tran);
    }elseif(in_array($cmd, ['got', 'nini', 'nininini'])){
        if($telegram->user->id != $this->config->item('creator')){ return; }
        $text = strtolower($rtext);
        $text = str_replace(['a','e','o','u','á','é','ó','ú'], 'i', $text);
        $text = str_replace("ii", 'i', $text);
        $text .= ' ñiñiñiñi';

        $telegram->send->file('sticker', 'CAADAgADQQEAAksODwABJlVW31Lsf6sC');
		$telegram->send->text($text)->send();
        return -1;
    }

    if(!empty($text)){
        $telegram->send->text($text)->send();
		$this->telegram->send->delete(TRUE);
    }
    return -1;
}elseif($telegram->text_has(["oak", "profe"], "dónde estoy") && $telegram->words() <= 4){
    // DEBUG
    $texto = NULL;
    if($telegram->is_chat_group()){
        $texto = "Estás en *" .$telegram->chat->title ."* ";
        if(isset($telegram->chat->username)){ $texto .= "@" .$telegram->chat->username ." "; }
        $texto .= "(" .$telegram->chat->id .").";
    }else{
        $texto = "Estás hablando por privado conmigo :)\n";
        if(isset($telegram->chat->username)){ $texto .= "@" .$telegram->chat->username ." "; }
        $texto .= "(" .$telegram->chat->id .").";
    }
    if($texto){
        $telegram->send
            ->text($texto, TRUE)
        ->send();
    }
    return;
}

// Sistema de mención de usuarios
if(
    (
        ($telegram->text_contains("@") && !$telegram->text_contains("@ ")) or
        ($telegram->text_mention())
    ) && $telegram->is_chat_group()
    && ($pokemon->settings($telegram->chat->id, 'no_mention') != TRUE)
){

	if($telegram->key == "edited_message" && $telegram->text_has("people voted")){ return; } // Anti-vote

    $users = array();
    preg_match_all("/[@]\w+/", $telegram->text(), $users, PREG_SET_ORDER);
    foreach($users as $i => $u){ $users[$i] = substr($u[0], 1); } // Quitamos la @
    foreach($telegram->text_mention(TRUE) as $u){
        if(is_array($u)){ $users[] = key($u); continue; }
        if($m[0] == "@"){ $users[] = substr($m, 1); }
    }

    // Quitarse usuario a si mismo
    $self = [$telegram->user->id, $telegram->user->username];
    foreach($users as $k => $u){ if(in_array($u, $self)){ unset($users[$k]); } }

    if(!empty($users)){
        $admins = FALSE;
        if(in_array("admin", $users)){
            // FIXME Cambiar function get_admins por la integrada + array merge
            $admins = $telegram->send->get_admins();
            if(!empty($admins)){
                foreach($admins as $a){	$users[] = $a['user']['id']; }
            }
			$admins = $pokemon->settings($telegram->chat->id, "admins");
			if(!empty($admins)){
				$admins = explode(",", $admins);
				foreach($admins as $a){ $users[] = $a; }
			}
			$users = array_unique($users);
            $admins = TRUE;

			$adminchat = $pokemon->settings($telegram->chat->id, "admin_chat");
			if($adminchat){
				$this->telegram->send
					->notification(FALSE)
					->chat(TRUE)
					->message(TRUE)
					->forward_to($adminchat)
				->send();

				$this->telegram->send
					->chat($adminchat)
					->text_replace("Mensaje del usuario %s.", $telegram->user->id)
				->send();
			}
        }
        $find = $pokemon->find_users($users);
        if(!empty($find)){

            // Preparar datos - Link del chat
            $link = $pokemon->settings($telegram->chat->id, 'link_chat');
            if(!empty($link)){
                if($link[0] == "@"){ $link = "https://telegram.me/" .substr($link, 1) ."/" .$telegram->message; }
                else{ $link = "https://telegram.me/joinchat/" .$link; }
            }
            // Preparar datos - Nombre de quien escribe
            $name = (isset($telegram->user->username) ? "@" .$telegram->user->username : $telegram->user->first_name);

            $resfin = FALSE;
            if(count($find) > 15 && $telegram->user->id != $this->config->item('creator')){ return; }
            foreach($find as $u){
                // Valida que el entrenador esté en el grupo
                if(
					$u['telegramid'] != $this->config->item('creator') and // Si es el creador / duhow, avisarle aunque no esté en el grupo. PROV.
					(!$telegram->user_in_chat($u['telegramid']) or
					$pokemon->settings($u['telegramid'], 'no_mention') == TRUE)
				){ continue; }

                if(!$pokemon->user_in_group($u['telegramid'], $telegram->chat->id) and $telegram->user_in_chat($u['telegramid'])){
                    $pokemon->user_addgroup($u['telegramid'], $telegram->chat->id);
                }
                $str = $name ." - ";
                if(!empty($link)){ $str .= "<a href='$link'>" .$telegram->chat->title ."</a>:\n"; }
                else{ $str .= "<b>" .$telegram->chat->title ."</b>:\n"; }
                $str .= $telegram->text();

                $res = $telegram->send
                    ->chat($u['telegramid'])
                    ->notification(TRUE)
                    ->disable_web_page_preview(TRUE)
                    ->text($str, 'HTML')
                ->send();
                if($res != FALSE){ $resfin = TRUE; }
            }

            if($admins && $resfin === FALSE){
                $telegram->send
                    ->chat($telegram->chat->id)
                    ->notification(TRUE)
                    ->text("No puedo avisar a los @admin, no me han iniciado :(")
                ->send();
            }
        }
    }
}

/* ---------------------

    -------------------- */

// Buscar coordenadas
$loc = NULL;

if(preg_match("/^([Cc]alcula([r]?)\s)([+-]?)(\d+.\d+)[,;]\s?([+-]?)(\d+.\d+)\s([+-]?)(\d+.\d+)[,;]\s?([+-]?)(\d+.\d+)/", $telegram->text(), $loc)){

    $l1 = [$loc[3].$loc[4], $loc[5].$loc[6]];
    $l2 = [$loc[7].$loc[8], $loc[9].$loc[10]];

    // https://stackoverflow.com/questions/10053358/measuring-the-distance-between-two-coordinates-in-php
    $r = $pokemon->location_distance($l1, $l2);

    $telegram->send->text($r)->send();
    exit();
}

if($telegram->text_url() && $telegram->text_contains("//goo.gl/maps/")){
    $url = $telegram->text_url();
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FILETIME, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $header = curl_exec($ch);
    $data = curl_getinfo($ch);
    curl_close($ch);

    $url = urldecode($data['url']);
    if(preg_match("/([+-]?)(\d+.\d+)[,;]\s?([+-]?)(\d+.\d+)/", $url, $loc)){
        $loc = $loc[0];
        if(strpos($loc, ";") !== FALSE){ $loc = explode(";", $loc); }
        elseif(strpos($loc, ",") !== FALSE){ $loc = explode(",", $loc); }

        if(count($loc) == 2){
            $this->analytics->event('Telegram', 'Parse coords URL');
            $telegram->send
                ->location($loc[0], $loc[1])
            ->send();
        }
    }
}

if(preg_match("/([+-]?)(\d+.\d+)[,;]\s?([+-]?)(\d+.\d+)/", $telegram->text(), $loc)){
	if($telegram->text_contains(["PokéTrack", "Encontré un", "I found a"])){ return; }
    $loc = $loc[0];
    if(strpos($loc, ";") !== FALSE){ $loc = explode(";", $loc); }
    elseif(strpos($loc, ",") !== FALSE){ $loc = explode(",", $loc); }

    if(count($loc) == 2){
        $this->analytics->event('Telegram', 'Parse coords');
        $telegram->send
            ->location($loc[0], $loc[1])
        ->send();

        // REQUIRE TOKEN API
        /* $data = http_build_query(["location" => $loc[0] ."," .$loc[1], 'f' => 'json']);
        $web = "http://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/reverseGeocode?$data";
        $data = file_get_contents($web);
        $ret = json_decode($data);
        $telegram->send
            ->text(json_encode($ret))
        ->send(); */
    }
}

if(
	$telegram->text_has("radio", TRUE) &&
	$telegram->has_reply &&
	$telegram->words() == 2 &&
	is_numeric($telegram->last_word()) &&
	isset($telegram->reply->location)
){
	$loc = [$telegram->reply->location['latitude'], $telegram->reply->location['longitude']];
	$amount = (int) $telegram->last_word();
	if($amount <= 0){ return -1; }
	$locSW = $pokemon->location_add($loc, $amount, "SW");
	$locNE = $pokemon->location_add($loc, $amount, "NE");

	$str = ":search-left: " .implode(",", $loc) ."\n"
			.":arrow-up-left: " .implode(",", $locNE) ."\n"
			.":arrow-down-right: " .implode(",", $locSW);
	$str = $telegram->emoji($str);

	$this->telegram->send
		->text($str)
	->send();
	return -1;
}

// Buscar ubicación en mapa
if(
    $telegram->text_has(["ubicación", "mapa de"], TRUE) or
    ($telegram->text_command("map") && $telegram->has_reply)
){
    if($pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'spam'])){ return -1; }

    $text = $telegram->text();
    if($telegram->text_command("map") && $telegram->has_reply){ $text = $telegram->reply->text; }

    $text = $telegram->clean('alphanumeric-full-spaces', $text);
    if($telegram->text_has("ubicación", TRUE)){
        $text = substr($text, strlen("ubicación"));
    }elseif($telegram->text_has("mapa de", TRUE)){
        $text = substr($text, strlen("mapa de"));
    }
    // $text = str_replace("en ", "in ", trim($text));
    if(empty($text) or strlen($text) <= 2){ return; }

	$this->analytics->event('Telegram', 'Map search');

	$ret = map_search($text);

    $str = "No lo encuentro.";
    if($ret->status == "OK"){
        $loc = $ret->results[0]->geometry->location;
        $str = $ret->results[0]->formatted_address;
		$name = $ret->results[0]->address_components[0]->short_name;
        $telegram->send
            ->location($loc->lat, $loc->lng)
			->venue($name, $str)
        ->send();
		return -1;
    }

    // GeoCode Argis OLD

    // $data = ["text" => $text, "sourceCountry" => "ESP", "f" => "json"];
    // $web = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/find?" .http_build_query($data);
    /* if(!empty($ret->locations)){
        $loc = $ret->locations[0];
        $str = $loc->name ." (" .$loc->feature->attributes->Score ."%)";

        $lat = round($loc->feature->geometry->y, 6);
        $lon = round($loc->feature->geometry->x, 6);
        $telegram->send
            ->location($lat, $lon)
        ->send();
    } */

    $telegram->send
        ->text($str)
    ->send();
}

if(
    $telegram->text_has(["invertir", "invertir ubicación", "reverse"]) &&
    $telegram->words() <= 5 &&
    $telegram->has_reply &&
    isset($telegram->reply->location)
){
    $loc = (object) $telegram->reply->location;
    $telegram->send
        ->notification(FALSE)
        ->text($loc->latitude ."," .$loc->longitude)
    ->send();
    exit();
}

?>
