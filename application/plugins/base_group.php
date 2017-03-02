<?php

if(!$telegram->is_chat_group()){ return; }

// Agregar usuarios en el chat
$pokemon->user_addgroup($telegram->user->id, $telegram->chat->id);

/*
#####################
# Forwarding system #
#####################
*/

$chat_forward = $pokemon->settings($telegram->chat->id, 'forwarding_to');
if($chat_forward){ // Si no hay, po na.
    if($telegram->user_in_chat($this->config->item('telegram_bot_id'), $chat_forward)){ // Si el Oak está en el grupo forwarding
        $chat_accept = explode(",", $pokemon->settings($chat_forward, 'forwarding_accept'));
        if(in_array($telegram->chat->id, $chat_accept)){ // Si el chat actual se acepta como forwarding...
            $telegram->send
                ->message($telegram->message)
                ->chat($telegram->chat->id)
                ->forward_to($chat_forward)
            ->send();
        }
    }
}

/*
#####################
#   Flood filter    #
#####################
*/

$flood = $pokemon->settings($telegram->chat->id, 'antiflood');
if($flood && !in_array($telegram->user->id, $pokemon->telegram_admins(TRUE))){
    $amount = NULL;
    if($telegram->text_command()){ $amount = 1; }
    elseif($telegram->photo()){ $amount = 0.8; }
    elseif($telegram->sticker()){
		if(strpos($telegram->sticker(), "CAADBAAD") === FALSE){ // + BQADBAAD - Oak Games
			$amount = 1;
		}
	}
    // elseif($telegram->document()){ $amount = 1; }
    elseif($telegram->gif()){ $amount = 1; }
    elseif($telegram->text() && $telegram->words() >= 50){ $amount = 0.5; }
    elseif($telegram->text()){ $amount = -0.4; }
    // Spam de text/segundo.
    // Si se repite la última palabra.

    $countflood = 0;
    if($amount !== NULL){ $countflood = $pokemon->group_spamcount($telegram->chat->id, $amount); }

    if($countflood >= $flood){

        $ban = $pokemon->settings($telegram->chat->id, 'antiflood_ban');

        if($ban == TRUE){
            $res = $telegram->send->ban($telegram->user->id);
			if($pokemon->settings($telegram->chat->id, 'antiflood_ban_hidebutton') != TRUE){
				$telegram->send
				->inline_keyboard()
					->row_button("Desbanear", "desbanear " .$telegram->user->id, "TEXT")
				->show();
			}
        }else{
            $res = $telegram->send->kick($telegram->user->id);
        }

        if($res){
            $pokemon->group_spamcount($telegram->chat->id, -1.1); // Avoid another kick.
            $pokemon->user_delgroup($telegram->user->id, $telegram->chat->id);
            $telegram->send
                ->text("Usuario expulsado por flood. [" .$telegram->user->id .(isset($telegram->user->username) ? " @" .$telegram->user->username : "") ."]")
            ->send();
            $adminchat = $pokemon->settings($telegram->chat->id, 'admin_chat');
            if($adminchat){
                // TODO forward del mensaje afectado
                $telegram->send
                    ->chat($adminchat)
                    ->text("Usuario " .$telegram->user->id .(isset($telegram->user->username) ? " @" .$telegram->user->username : "") ." expulsado del grupo por flood.")
                ->send();
            }
            return -1; // No realizar la acción ya que se ha explusado.
        }
        // Si tiene grupo admin asociado, avisar.
    }
}

/*
#####################
#   Abandon chat    #
#####################
*/

$abandon = $pokemon->settings($telegram->chat->id, 'abandon');
if($abandon){
    if(json_decode($abandon) != NULL){ $abandon = json_decode($abandon); }
    $str = ($abandon == TRUE ? "Este chat ha sido abandonado." : $abandon);
    $telegram->send
        ->text($str)
    ->send();
}

/*
#####################
#     Anti spam     #
#####################
*/

if($telegram->text_url() && $telegram->is_chat_group()){
    $info = $pokemon->user_in_group($telegram->user->id, $telegram->chat->id);
    // $telegram->send->text(json_encode($info))->send();
    if($info->messages <= 5 && $pokemon->settings($telegram->chat->id, 'antispam') !== FALSE){
        if(
            !$telegram->text_contains(["http", "www", ".com", ".es", ".net"]) &&
            !$telegram->text_contains("telegram.me") or
            $telegram->text_contains(["PokéTrack", "PokeTrack"]) or
            $telegram->text_contains(["maps.google", "google.com/maps"])
        ){ return -1; } // HACK Falsos positivos.

        // TODO mirar antiguedad del usuario y mensajes escritos. - RELACIÓN.
        $telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->forward_to($this->config->item('creator'))
        ->send();

        $telegram->send
            ->chat($this->config->item('creator'))
            ->text("*SPAM* del grupo " .$telegram->chat->id .".", TRUE)
            ->inline_keyboard()
                ->row_button("No es spam", "/nospam " .$telegram->user->id ." " .$telegram->chat->id, "TEXT")
            ->show()
        ->send();

        $pokemon->user_flags($telegram->user->id, 'spam', TRUE);

        $telegram->send
            ->text("¡*SPAM* detectado!", TRUE)
        ->send();

        $telegram->send->ban($telegram->user->id, $telegram->chat->id);
        return -1;
    }
}

/*
######################
# Migrate supergroup #
######################
*/

if($telegram->data_received("migrate_to_chat_id")){
    $pokemon->group_disable($telegram->chat->id);
	// TODO mover settings
    return -1;
}

/*
#####################
# Ignore chat speak #
#####################
*/

$die = $pokemon->settings($telegram->chat->id, 'die');
if($die && $telegram->user->id != $this->config->item('creator')){
    die();
}

/*
#####################
#  Custom commands  #
#####################
*/

$commands = $pokemon->settings($telegram->chat->id, 'custom_commands');
if($commands){
    $commands = unserialize($commands);
    if(is_array($commands) && $pokemon->step($telegram->user->id) == NULL){
        foreach($commands as $word => $action){
            if($telegram->text_has($word, TRUE)){
                if(
                    $pokemon->user_flags($telegram->user->id, ['ratkid', 'troll', 'rager', 'spamkid']) or
                    $pokemon->user_blocked($telegram->user->id)
                ){ return -1; }
                $content = current($action);
                $action = key($action);
                if($action == "text"){
                    $telegram->send->text(json_decode($content))->send();
                }else{
                    $telegram->send->file($action, $content);
                }
                return -1;
            }
        }
    }
}

/*
#####################
#    Dub message    #
#####################
*/

$dubs = $pokemon->settings($telegram->chat->id, 'dubs');
if($dubs && $telegram->key == "message"){ // HACK para editados no vale.
    $nums = array_merge(
        range(11111, 99999, 11111),
        range(1111, 9999, 1111),
        range(111, 999, 111)
        // range(11, 99, 11)
    );
    $lon = NULL;
    $id = $telegram->message;
    foreach($nums as $n){
        if(@strpos(strval($id), strval($n), strlen($id) - strlen($n)) !== FALSE){
            // $telegram->send->text("hecho en $id con $n")->send();
            $lon = strlen($n);
            break;
        }
    }
    $str = NULL;
    // if($lon == 2){ $str = "Dubs! :D"; }
    if($lon == 3){ $str = "Trips checked!"; }
    elseif($lon == 4){ $str = "QUADS *GET*!"; }
    elseif($lon == 5){ $str = "QUINTUPLE *GET! OMGGG!!*"; }
    if($str){
        $telegram->send
            ->reply_to(TRUE)
            ->text($str, TRUE)
        ->send();
    }
}

// Cancelar acciones sobre comandos provenientes de mensajes de channels. STOP SPAM.
if($telegram->has_forward && $telegram->forward_type("channel")){ return -1; }

?>
