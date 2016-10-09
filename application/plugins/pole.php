<?php

if($telegram->text_has(["pole", "subpole", "bronce"], TRUE) or $telegram->text_command("pole") or $telegram->text_command("subpole")){
    $this->analytics->event("Telegram", "Pole");
    $pole = $pokemon->settings($telegram->chat->id, 'pole');
    if($pole != NULL && $pole == FALSE){ return; }
    if($pokemon->settings($telegram->user->id, 'no_pole') == TRUE){ return; }

    // Si está el Modo HARDCORE, la pole es cada hora. Si no, cada día.
    $timer = ($pokemon->settings($telegram->chat->id, 'pole_hardcore') ? "H" : "d");

    if(!empty($pole)){
        $pole = unserialize($pole);
        if(
            ( $telegram->text_has("pole", TRUE) &&    is_numeric($pole[0]) && date($timer) == date($timer, $pole[0]) ) or
            ( $telegram->text_has("subpole", TRUE) && is_numeric($pole[1]) && date($timer) == date($timer, $pole[1]) ) or
            ( $telegram->text_has("bronce", TRUE) &&  is_numeric($pole[2]) && date($timer) == date($timer, $pole[2]) )
        ){
            return;  // Mismo dia? nope.
        }
    }
    $pole_user = unserialize($pokemon->settings($telegram->chat->id, 'pole_user'));
    $pkuser = $pokemon->user($telegram->user->id);

    if($telegram->text_has("pole", TRUE)){ // and date($timer) != date($timer, $pole[0])
        $pole = [time(), NULL, NULL];
        $pole_user = [$telegram->user->id, NULL, NULL];
        $action = "la *pole*";
        if($pkuser && $timer == "d"){ $pokemon->update_user_data($pkuser->telegramid, 'pole', ($pkuser->pole + 3)); }
    }elseif($telegram->text_has("subpole", TRUE) and date($timer) == date($timer, $pole[0]) and $pole_user[1] == NULL){
        if(in_array($telegram->user->id, $pole_user)){ return; } // Si ya ha hecho pole, nope.
        $pole[1] = time();
        $pole_user[1] = $telegram->user->id;
        $action = "la *subpole*";
        if($pkuser && $timer == "d"){ $pokemon->update_user_data($pkuser->telegramid, 'pole', ($pkuser->pole + 2)); }
    }elseif($telegram->text_has("bronce", TRUE) and date($timer) == date($timer, $pole[0]) and $pole_user[1] != NULL and $pole_user[2] == NULL){
        if(in_array($telegram->user->id, $pole_user)){ return; } // Si ya ha hecho sub/pole, nope.
        $pole[2] = time();
        $pole_user[2] = $telegram->user->id;
        $action = "el *bronce*";
        if($pkuser && $timer == "d"){ $pokemon->update_user_data($pkuser->telegramid, 'pole', ($pkuser->pole + 1)); }
    }else{
        return;
    }

    $pokemon->settings($telegram->chat->id, 'pole', serialize($pole));
    $pokemon->settings($telegram->chat->id, 'pole_user', serialize($pole_user));
    $telegram->send->text($telegram->user->first_name ." ha hecho $action!", TRUE)->send();
    // $telegram->send->text("Lo siento " .$telegram->user->first_name .", pero hoy la *pole* es mía! :D", TRUE)->send();
    return;
}

?>
