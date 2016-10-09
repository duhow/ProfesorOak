<?php

$joke = NULL;

if(
    ( $telegram->text_has(["necesitas", "necesitáis"], ["novio", "un novio", "novia", "una novia", "pareja", "una pareja", "follar"]) ) or
    ( $telegram->text_has("Tengo", TRUE) && $telegram->words() == 2 && !is_numeric($telegram->last_word()) )
){
    $word = ($telegram->text_has("Tengo", TRUE) ? ucwords(strtolower($telegram->last_word())) : "Novia");
    if(strlen($word) > 8){ return; }
    $joke = "¿$word? Qué es eso, ¿se come?";
}elseif($telegram->text_has(["saluda", "saludo"]) && $telegram->text_has(["profe", "profesor", "oak"])){
    /* if(!$this->is_shutup()){
        $joke = "Un saludo para todos mis fans! :D";
    } */
}elseif($telegram->text_has("Profesor Oak", TRUE)){
    // if(!$this->is_shutup()){ $joke = "Dime!"; }
}elseif($telegram->text_has(["alguien", "alguno"]) && $telegram->text_has(["decir", "dice", "sabe"])){
    if(mt_rand(1, 7) == 7){ $joke = "pa k kieres saber eso jaja salu2"; }
}elseif($telegram->text_has(["programado", "funcionas"]) && $telegram->text_has(["profe", "profesor", "oak", "bot"])){
    $joke = "Pues yo funciono con *PHP* (_CodeIgniter_) :)";
}elseif($telegram->text_has("qué hora", ["es", "son"]) && !$telegram->text_has("a qué hora") && $telegram->text_contains("?") && $telegram->words() <= 5){
    $this->analytics->event('Telegram', 'Jokes', 'Time');
    $joke = "Son las " .date("H:i") .", una hora menos en Canarias. :)";
}elseif($telegram->text_has(["profe", "profesor", "oak"]) && $telegram->text_has("te", ["quiero", "amo", "adoro"])){
    // if(!$this->is_shutup()){ $joke = "¡Yo también te quiero! <3"; }
}elseif($telegram->text_contains(["te la com", "te lo com", "un hijo", "me ha dolido"]) && $telegram->text_has(["oak", "profe", "bot"])){
    if($telegram->text_has("no")){
        $joke = "¿Pues entonces para que me dices nada? Gilipollas.";
    }else{
        // if($this->is_shutup_jokes()){ return; }

        $joke = "Tu sabes lo que es el fiambre? Pues tranquilo, que no vas a pasar hambre... ;)";
        $telegram->send
            ->notification(FALSE)
            ->file('sticker', 'BQADBAADGgAD9VikAAEvUZ8dGx1_fgI');
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
