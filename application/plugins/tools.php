<?php

if($telegram->text_command("avoice") && $telegram->words() == 2){
    $voice = $telegram->last_word();
    $telegram->send->file("voice", $telegram->download($voice));
    return;
}elseif($telegram->text_command("cinfo") && $telegram->user->id == $this->config->item('creator')){
    $id = $telegram->last_word();
    if(empty($id) or $id == "/cinfo"){ $id = $telegram->chat->id; }
    $info = $telegram->send->get_chat($id);
    $count = $telegram->send->get_members_count($id);
    $telegram->send->text( json_encode($info) ."\n$count" )->send();
    $info = $telegram->send->get_member_info($this->config->item('telegram_bot_id'), $id);
    $telegram->send->text( json_encode($info) )->send();
    exit();
}elseif($telegram->text_has(["oak", "profe"], "dónde estoy") && $telegram->words() <= 4){
    // DEBUG
    if($telegram->is_chat_group()){
        $joke = "Estás en *" .$telegram->chat->title ."* ";
        if(isset($telegram->chat->username)){ $joke .= "@" .$telegram->chat->username ." "; }
        $joke .= "(" .$telegram->chat->id .").";
    }else{
        $joke = "Estás hablando por privado conmigo :)\n";
        if(isset($telegram->chat->username)){ $joke .= "@" .$telegram->chat->username ." "; }
        $joke .= "(" .$telegram->chat->id .").";
    }
}elseif($telegram->text_has("oak", "versión")){
    $date = (time() - filemtime(__FILE__));
    $joke = "Versión de hace " .floor($date / 60) ." minutos.";
}

// Sistema de mención de usuarios
if(
    (
        ($telegram->text_contains("@") && !$telegram->text_contains("@ ")) or
        ($telegram->text_mention())
    ) && $telegram->is_chat_group()
    && ($pokemon->settings($telegram->chat->id, 'no_mention') != TRUE)
){
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
            $admins = TRUE;
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
            foreach($find as $u){
                // Valida que el entrenador esté en el grupo
                if($telegram->user_in_chat($u['telegramid']) && $pokemon->settings($u['telegramid'], 'no_mention') != TRUE){
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

// Buscar ubicación en mapa
if($telegram->text_has(["ubicación", "mapa de"], TRUE)){
    $flags = $pokemon->user_flags($user->id);
    if(in_array('ratkid', $flags)){ exit(); }
    $text = $telegram->text();
    $text = $telegram->clean('alphanumeric-full-spaces', $text);
    if($telegram->text_has("ubicación", TRUE)){
        $text = substr($text, strlen("ubicación"));
    }elseif($telegram->text_has("mapa de", TRUE)){
        $text = substr($text, strlen("mapa de"));
    }
    $text = trim($text);
    $text = str_replace("en ", "in ", $text);
    if(empty($text) or strlen($text) <= 2){ return; }
    $data = ["text" => $text, "sourceCountry" => "ESP", "f" => "json"];
    $data = http_build_query($data);
    $web = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/find?" .$data;
    $loc = file_get_contents($web);
    $ret = json_decode($loc);
    $str = "No lo encuentro.";
    if(!empty($ret->locations)){
        $loc = $ret->locations[0];
        $str = $loc->name ." (" .$loc->feature->attributes->Score ."%)";

        $lat = round($loc->feature->geometry->y, 6);
        $lon = round($loc->feature->geometry->x, 6);
        $telegram->send
            ->location($lat, $lon)
        ->send();
    }
    $this->analytics->event('Telegram', 'Map search');
    $telegram->send
        ->text($str)
    ->send();
}

if($telegram->text_has(["invertir", "invertir ubicación", "reverse"]) && $telegram->words() <= 5 && $telegram->has_reply && isset($telegram->reply->location)){
    $loc = (object) $telegram->reply->location;
    $telegram->send
        ->notification(FALSE)
        ->text($loc->latitude ."," .$loc->longitude)
    ->send();
    exit();
}

?>
