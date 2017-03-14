<?php

if(!$this->telegram->is_chat_group()){ return; }

$blackwords = $this->pokemon->settings($this->telegram->chat->id, 'blackword');

if($this->telegram->text_command("bw") && $telegram->words() > 1){
    $txt = $this->telegram->words(1, 10);
    $txt = strtolower(trim($txt));

    if(!empty($blackwords)){
        $blackwords = explode(",", $blackwords);
        if(!is_array($blackwords)){ $blackwords = [$blackwords]; }
    }else{
        $blackwords = array();
    }

    $blackwords[] = $txt;
    $blackwords = array_unique($blackwords);
    if(count($blackwords) == 1){ $blackwords = $blackwords[0]; }
    else{ $blackwords = implode(",", $blackwords); }
    $this->pokemon->settings($this->telegram->chat->id, 'blackword', $blackwords);

    $this->telegram->send
        ->text($this->telegram->emoji(":ok: ") ."Agregado.")
    ->send();
    return -1;
}

if(!empty($blackwords)){
    $blackwords = (strpos($blackwords, ",") === FALSE ? [$blackwords] : explode(",", $blackwords) );
    if(!$this->telegram->text_contains($blackwords)){ return; }
    if(in_array($this->telegram->user->id, telegram_admins(TRUE))){ return; }

    $adminchat = $pokemon->settings($this->telegram->chat->id, 'admin_chat');
    if(!empty($adminchat)){
        $this->telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->forward_to($adminchat)
        ->send();

        $this->telegram->send
            ->chat($adminchat)
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
