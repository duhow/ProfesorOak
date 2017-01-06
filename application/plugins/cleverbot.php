<?php

if(
    ( $telegram->text_command("cleverbot") or $telegram->text_command("jordi") or
    $telegram->text_has(["oye", "dime", "escucha"], ["oak", "profe", "profesor"], TRUE) ) &&
    $telegram->words() > 1
){
    // if($pokemon->settings($telegram->chat->id, 'shutup') == TRUE){ return; }

    require APPPATH ."third_party/chatter-bot-api/php/chatterbotapi.php";
    $factory = new ChatterBotFactory();
    $bot = $factory->create(ChatterBotType::CLEVERBOT);
    $clever = $bot->createSession();

    if(!$telegram->text_command()){
        $text = $telegram->words(2, 50);
    }else{
        $text = $telegram->text();
        $text = trim(str_replace($telegram->text_command(), "", $text));
    }

    if(strlen($text) <= 1){ return -1; }

    $res = $clever->think($text);

    $this->analytics->event("Telegram", "Cleverbot");
    $telegram->send->text($res)->send();
    return -1;
}


?>
