<?php

$joke = NULL;

if($telegram->text_has(["tira", "lanza", "tirar", "roll"], ["el dado", "los dados", "the dice"], TRUE) or $telegram->text_has("/dado", TRUE)){
    $this->analytics->event('Telegram', 'Games', 'Dice');
    $can = $pokemon->settings($telegram->chat->id, 'play_games');
    if($can != NULL and $can == FALSE){ return; }

    $num = $telegram->last_word();
    if(!is_numeric($num) or ($num < 0 or $num > 1000)){ $num = 6; } // default MAX
    $joke = "*" .mt_rand(1,$num) ."*";
}elseif(
    ( $telegram->text_has("piedra") and
    $telegram->text_has("papel") and
    $telegram->text_has(["tijera", "tijeras"]) ) or
    $telegram->text_has(["/rps", "/rpsls"], TRUE)
){
    $this->analytics->event('Telegram', 'Games', 'RPS');
    $rps = ["Piedra", "Papel", "Tijera"];
    if($telegram->text_contains(["lagarto", "/rpsls"])){ $rps[] = "Lagarto"; }
    if($telegram->text_contains(["spock", "/rpsls"])){ $rps[] = "Spock"; }
    $n = mt_rand(0, count($rps) - 1);

    $can = $pokemon->settings($telegram->chat->id, 'play_games');
    if($can != NULL and $can == FALSE){ return; }
    $joke = "*" .$rps[$n] ."!*";
}elseif($telegram->text_has(["cara o cruz", "/coin", "/flip"])){
    $this->analytics->event('Telegram', 'Games', 'Coin');
    $n = mt_rand(0, 99);
    $flip = ["Cara!", "Cruz!"];

    $can = $pokemon->settings($telegram->chat->id, 'play_games');
    if($can != NULL and $can == FALSE){ return; }
    $joke = "*" .$flip[$n % 2] ."*";
}elseif(($telegram->text_has("Recarga", TRUE) or $telegram->text_command("recarga")) && $telegram->words() <= 3){
    $can = $pokemon->settings($telegram->chat->id, 'play_games');
    if($can != NULL and $can == FALSE){ return; }

    $shot = $pokemon->settings($telegram->chat->id, 'russian_roulette');
    $text = NULL;
    if(empty($shot)){
        $this->analytics->event('Telegram', 'Games', 'Roulette Reload');
        $shot = mt_rand(1, 6);
        $pokemon->settings($telegram->chat->id, 'russian_roulette', $shot);
        $text = "Bala puesta.";
    }else{
        if($telegram->user->id == $this->config->item('creator')){
            $pokemon->settings($telegram->chat->id, 'russian_roulette', 'DELETE');
            $this->_begin(); // HACK vigilar
        }
        $text = "Ya hay una bala. ¡*Dispara* si te atreves!";
    }
    $telegram->send
        ->notification(FALSE)
        ->text($text, TRUE)
    ->send();
    return;
}elseif($telegram->text_has(["Dispara", "Bang", "Disparo", "/dispara"], TRUE) && $telegram->words() <= 3){
    $can = $pokemon->settings($telegram->chat->id, 'play_games');
    if($can != NULL and $can == FALSE){ return; }

    $shot = $pokemon->settings($telegram->chat->id, 'russian_roulette');
    $text = NULL;
    $last = NULL; // Ultimo en disparar
    if(empty($shot)){
        $text = "No hay bala. *Recarga* antes de disparar.";
    }else{
        if($telegram->is_chat_group()){
            $last = $pokemon->settings($telegram->chat->id, 'russian_roulette_last');
            if($last == $telegram->user->id){
                $last = -1;
                $text = "Tu ya has disparado, ¡pásale el arma a otra persona!";
            }else{
                $pokemon->settings($telegram->chat->id, 'russian_roulette_last', $telegram->user->id);
            }
        }
        if($shot == 6 && $last != -1){
            $this->analytics->event('Telegram', 'Games', 'Roulette Shot');
            $pokemon->settings($telegram->chat->id, 'russian_roulette', 'DELETE');
            $text = ":die: :collision::gun:";
        }elseif($last != -1){
            $this->analytics->event('Telegram', 'Games', 'Roulette Shot');
            $pokemon->settings($telegram->chat->id, 'russian_roulette', $shot + 1);
            $faces = ["happy", "tongue", "smiley"];
            $r = mt_rand(0, count($faces) - 1);
            $text = ":" .$faces[$r] .": :cloud::gun:";
        }
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text( $telegram->emoji($text) )
        ->send();

        if($shot == 6 && $last != -1 && $telegram->is_chat_group()){
            if(!$pokemon->settings($telegram->chat->id, 'russian_roulette_easy')){
                $telegram->send->ban( $telegram->user->id );
            }
            // Implementar modo light o hard (ban)
            // Avisar al admin?
            $pokemon->settings($telegram->chat->id, 'russian_roulette_last', 'DELETE');
        }
    }
}

if(!empty($joke)){
    $telegram->send
        ->notification(FALSE)
        ->text($joke, TRUE)
    ->send();

    exit();
}

?>
