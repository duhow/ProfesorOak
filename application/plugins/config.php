<?php

// Configurar el bot (solo creador/admin/chat privado)
if(
    $telegram->text_command("set") &&
    $telegram->words() == 3 &&
    (
        ( $telegram->is_chat_group() && in_array($telegram->user->id, telegram_admins(TRUE)) ) or
        ( !$telegram->is_chat_group() )
    )
){
	if(
		$pokemon->user_flags($telegram->user->id, 'set_abuse') or
		($telegram->user->id != $this->config->item('creator') and $pokemon->settings($telegram->chat->id, 'noset') )
	){ return -1; }
    $key = $telegram->words(1);
    $value = $telegram->words(2);

    $this->analytics->event('Telegram', 'Set config', $key);
    $set = $pokemon->settings($telegram->chat->id, $key, $value);
    $announce = $pokemon->settings($telegram->chat->id, 'announce_settings');
	$str = "\ud83d\udcbe Config\n"
			.":id: " .$this->telegram->user->id ." - @" .$this->telegram->user->username . " " .$this->telegram->user->first_name ."\n"
			.":multiuser: " .$this->telegram->chat->id ." - " .(@$this->telegram->chat->title ?: @$this->telegram->chat->first_name) ."\n"
			.":ok: $key -";
	$str = $this->telegram->emoji($str);
	$str .= " " .json_encode($value);
    $telegram->send
        ->chat( $this->config->item('creator') )
        ->text($str)
    ->send();

    if( ($set !== FALSE or $set > 0) && ($announce == TRUE) ){
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text("Configuración establecida: *$value*", TRUE)
        ->send();
    }
    return -1;
}

elseif(
    $telegram->text_command("get") &&
    in_array($telegram->words(), [2,3])
){
	if($pokemon->user_flags($telegram->user->id, 'set_abuse')){ return -1; }
    $get = $telegram->chat->id;
    if($telegram->is_chat_group()){
        $admins = $pokemon->telegram_admins(TRUE);
        if(!in_array($telegram->user->id, $admins)){ return -1; }
        if($telegram->has_reply && $telegram->user->id == $this->config->item('creator')){ $get = $telegram->reply_user->id; }
    }

    $word = $telegram->words(1);
    $chat = $telegram->chat->id;
    if(strpos($word, "+private") !== FALSE){
        $chat = $telegram->user->id;
        $word = trim(str_replace("+private", "", $word));
    }
    if(strtolower($word) == "all"){ $word = "*" ; } // ['say_hello', 'say_hey', 'play_games', 'announce_welcome', 'announce_settings', 'shutup']; }
    if($telegram->words() == 3){
        if($telegram->user->id != $this->config->item('creator')){ return -1; }
        if(is_numeric($telegram->last_word())){ $get = $telegram->last_word(); }
        else{
            $get = $pokemon->group_find($telegram->last_word());
            if(!$get){
                $get = $pokemon->user($telegram->last_word());
                if(!$get){ return -1; }
                $get = $get->telegramid;
            }
        }
    }
    $value = $pokemon->settings($get, $word);
    $text = "";
    if(is_array($value)){
        foreach($value as $k => $v){
            $text .= "$k: $v\n";
        }
    }else{
        $text = "*" .json_encode($value) ."*";
    }
    $telegram->send
        ->chat($chat)
        ->notification( ($chat != $telegram->chat->id) )
        // ->reply_to( ($chat == $telegram->chat->id) )
        ->text($text, (!is_array($value)))
    ->send();
    return -1;
}

elseif(
	$telegram->text_has(["oak", "profe", "profesor"]) and
	$telegram->text_has("qué", ["puedes hacer", "tienes activo", "tienes activado"]) and
	$telegram->text_contains("?") and
	$telegram->words() <= 7 and
	$telegram->is_chat_group() and
	in_array($telegram->user->id, telegram_admins(TRUE))
){
	if($pokemon->command_limit("settingsview", $telegram->chat->id, $telegram->message, 7)){ return -1; }

	$str = "";
	$opts = array();
	$chat = $telegram->chat->id;
	$admin = (in_array($this->config->item('telegram_bot_id'), telegram_admins()));

	// ----------------
	$str = "";
	$s = $pokemon->settings($chat, 'announce_welcome');
	if($s != NULL and $s == FALSE){ $str = "*NO* "; }
	$str .= "Saludaré a los nuevos usuarios";

	if($pokemon->settings($chat, 'welcome')){
		$str .= " con un mensaje personalizado";
	}
	$str .= ".";
	$opts[] = $str;
	// ----------------
	$str = "";
	$s = $pokemon->settings($chat, 'blacklist');
	if($s){
		$s = explode(",", $s);
		$str = "Los que sean " .implode(", ", $s) ." no pueden entrar aquí";
		if(!$admin){ $str .= ", pero no puedo echarlos"; }
		$str .= ".";
	}
	$opts[] = $str;
	// ----------------
	$str = "";
	$s = $pokemon->settings($chat, 'team_exclusive');
	if(!empty($s)){
		$col = ['R' => 'rojos', 'B' => 'azules', 'Y' => 'amarillos'];
		$str = "Es un grupo exclusivo para *" .$col[$s] ."*.";
		if($pokemon->settings($chat, 'team_exclusive_kick')){
			if($admin){ $str .= " Echaré a los que no sean de ese color cuando intenten entrar."; }
			else{ $str .= " Pero no puedo echarlos."; }
		}
	}
	$opts[] = $str;
	// ----------------
	$str = "";
	if($pokemon->settings($chat, 'require_verified')){
		$str = "Es *obligatorio* estar validado para estar en este grupo.";
		if($pokemon->settings($chat, 'require_verified_kick')){
			if($admin){ $str .= " Si no, directamente no podrán ni entrar aquí."; }
			else{ $str .= " Pero no podré echarlos si entran."; }
		}
	}
	$opts[] = $str;
	// ----------------
	$str = "";
	$can = array();
	$cant = array();

	$s = $pokemon->settings($chat, 'jokes');
	if($s != NULL and $s == FALSE){ $cant[] = 'bromas'; }
	else{ $can[] = 'bromas'; }

	$s = $pokemon->settings($chat, 'play_games');
	if($s != NULL and $s == FALSE){ $cant[] = 'juegos'; }
	else{ $can[] = 'juegos'; }

	$opts[] = $str;
	// ----------------

	$str = "";
	foreach($opts as $t){
		$str .= "- $t\n";
	}

	$this->telegram->send
		->text($str)
	->send();

	return -1;
}

?>
