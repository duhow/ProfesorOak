<?php

if(!$this->telegram->is_chat_group()){ return; }

$blackwords = $this->pokemon->settings($this->telegram->chat->id, 'blackword');

if($this->telegram->text_command("bw") && $telegram->words() > 1){
    $txt = $this->telegram->words(1, 10);
    $txt = strtolower(trim($txt));

    if(!empty($blackwords)){
        $blackwords = array();
    }else{
        $blackwords = explode(",", $blackwords);
    }

    $blackwords[] = $txt;
    $blackwords = array_unique($blackwords);
    $this->pokemon->settings($this->telegram->chat->id, 'blackword', implode(",", $blackwords));

    $this->telegram->send
        ->text($this->telegram->emoji(":ok: ") ."Agregado.")
    ->send();
    return -1;
}

if(!empty($blackwords)){
    $blackwords = explode(",", $blackwords);
    if(!$this->telegram->text_has($blackwords)){ return; }
    if(in_array($this->telegram->user->id, telegram_admins(TRUE))){ return; }

    $adminchat = $pokemon->settings($this->telegram->chat->id, 'admin_chat');
    if(!empty($adminchat)){
        $this->telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->forward_to($adminchat)
        ->send();

        $this->telegram->send
            ->text("Ha dicho algo malo :(")
        ->send();
    }else{
        $this->telegram->send
            ->text("Eh, te calmas.")
        ->send();
    }
    return -1;
}

?>
