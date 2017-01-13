<?php

function cleverbot_message($text){
	require_once APPPATH ."third_party/chatter-bot-api/php/chatterbotapi.php";
	$factory = new ChatterBotFactory();
	$bot = $factory->create(ChatterBotType::CLEVERBOT);
	$clever = $bot->createSession();

	return $clever->think($text);
}

if(
    ( $telegram->text_command("cleverbot") or $telegram->text_command("jordi") or
    $telegram->text_has(["oye", "dime", "escucha"], ["oak", "profe", "profesor"], TRUE) ) &&
    $telegram->words() > 1 &&
	$telegram->key == "message"
){
    if($pokemon->settings($telegram->chat->id, 'shutup') == TRUE){ return; }

    if(!$telegram->text_command()){
        $text = $telegram->words(2, 50);
    }else{
        $text = $telegram->text();
        $text = trim(str_replace($telegram->text_command(), "", $text));
    }

    if(strlen($text) <= 1){ return -1; }

    $res = cleverbot_message($text);
	if(!empty($res)){
		$this->analytics->event("Telegram", "Cleverbot");
		if(!$telegram->is_chat_group()){ $telegram->send->force_reply(TRUE); }
		$q = $telegram->send->text($res)->send();

		$pokemon->settings($telegram->chat->id, 'cleverbot', $q['message_id']);
	}

    return -1;
}

$clevid = $pokemon->settings($telegram->chat->id, 'cleverbot');
if(
	$clevid &&
	$telegram->has_reply &&
	$telegram->reply->message_id == $clevid &&
	$telegram->text() &&
	$telegram->reply_user->id == $this->config->item('telegram_bot_id')
){
	$res = cleverbot_message($telegram->text());
	if(!empty($res)){
		$this->analytics->event("Telegram", "Cleverbot");
		if(!$telegram->is_chat_group()){ $telegram->send->force_reply(TRUE); }
		$q = $telegram->send->text($res)->send();

		$pokemon->settings($telegram->chat->id, 'cleverbot', $q['message_id']);
	}

    return -1;
}


?>
