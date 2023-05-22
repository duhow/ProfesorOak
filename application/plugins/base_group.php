<?php

if(!$telegram->is_chat_group()){ return; }

// Agregar usuarios en el chat
if(!$telegram->callback){
    $pokemon->user_addgroup($telegram->user->id, $telegram->chat->id);
}

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
		if(strpos($telegram->sticker(), "CAACA") === FALSE){ // + BQADBAAD - Oak Games
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

        if($ban){
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
				$str = ":forbid: Antiflood\n"
						.":id: " .$telegram->user->id ."\n"
						.":male: " .(isset($telegram->user->username) ? " @" .$telegram->user->username : "");
				$str = $this->telegram->emoji($str);

                if($ban){
    				$this->telegram->send
    					->inline_keyboard()
    						->row_button("Desbanear", "desbanear " .$telegram->user->id, "TEXT")
    					->show();
                }

                $this->telegram->send
                    ->chat($adminchat)
                    ->text($str)
                ->send();
				// Forward del mensaje afectado
				$this->telegram->send
					->chat(TRUE)
					->message(TRUE)
					->forward_to($adminchat)
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

if($telegram->is_chat_group()){
	$info = $pokemon->user_in_group($telegram->user->id, $telegram->chat->id);
	if(
		$info->last_date == $info->register_date and
		$info->messages <= 3 and
		(
			$telegram->photo() or
			$telegram->text_url() or
			$telegram->video()
		)
	){
		$telegram->send->delete(TRUE);
		$msg = $telegram->user->id ." / " .$telegram->user->first_name .' ' .$telegram->user->last_name;
		$telegram->send
			->text($msg)
			->chat("-221103258")
			->notification(FALSE)
		->send();
		return -1;
	}
}

if($telegram->text_url() && $telegram->is_chat_group()){
    $info = $pokemon->user_in_group($telegram->user->id, $telegram->chat->id);
    // $telegram->send->text(json_encode($info))->send();
    if($info->messages <= 5 && $pokemon->settings($telegram->chat->id, 'antispam') !== FALSE){
        if(
            !$telegram->text_contains(["http", "www", ".com", ".es", ".net"]) &&
            !$telegram->text_contains("telegram.me") or
            $telegram->text_contains(["PokéTrack", "PokeTrack"]) or
            $telegram->text_contains(["maps.google", "google.com/maps"]) or
			$telegram->text_contains("poke") // Pokémon related things
        ){ return -1; } // HACK Falsos positivos.

        // TODO mirar antiguedad del usuario y mensajes escritos. - RELACIÓN.
        /* $telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->forward_to("-211573726")
        ->send();

        $telegram->send
            ->chat("-211573726")
            ->text("*SPAM* del grupo " .$telegram->chat->id .".", TRUE)
            ->inline_keyboard()
                ->row_button("No es spam", "/nospam " .$telegram->user->id ." " .$telegram->chat->id, "TEXT")
            ->show()
	    ->send(); */

        $pokemon->user_flags($telegram->user->id, 'spam_dis', TRUE);

// HACK 2020
// ANTISPAM DESHABILITADO POR MUCHOS FALLOS
/*
        $telegram->send
            ->text("¡*SPAM* detectado!", TRUE)
        ->send();

		$ban = $telegram->send->ban($telegram->user->id, $telegram->chat->id);

		$adminchat = $pokemon->settings($telegram->chat->id, 'admin_chat');
		if($adminchat){
			$str = ($ban !== FALSE ? ":ok:" : ":warning:") ." Antispam\n"
					.":id: " .$this->telegram->user->id;

			$str = $this->telegram->emoji($str);

			$this->telegram->send
				->notification(TRUE)
				->chat($adminchat)
				->text($str)
			->send();

			$this->telegram->send
				->notification(FALSE)
				->chat(TRUE)
				->message(TRUE)
				->forward_to($adminchat)
			->send();
		}

		$telegram->send->delete(TRUE);

	return -1;

*/
    }
}

/*
#####################
#     Mute user     #
#####################
*/

$mute = $pokemon->settings($telegram->user->id, 'mute');
if($mute and $mute > time()){
	$this->telegram->send->delete(TRUE);
	return -1;
}

/*
#####################
#   Mute content    #
#####################
*/

$mute = $pokemon->settings($telegram->chat->id, 'mute_content');
if($mute){
	$mute = explode(",", $mute);
	if(
		(in_array("url", $mute) and $telegram->text_url()) or
		(in_array("command", $mute) and $telegram->text_command()) or
		(in_array("gif", $mute) and $telegram->gif()) or
		(in_array("photo", $mute) and $telegram->photo()) or
		(in_array("sticker", $mute) and $telegram->sticker()) or
		(in_array("voice", $mute) and $telegram->voice()) or
		(in_array("audio", $mute) and $telegram->audio()) or
		(in_array("video", $mute) and $telegram->video()) or
		(in_array("game", $mute) and $telegram->game()) or
		(in_array("document", $mute) and $telegram->document())
	){
		$q = $this->telegram->send->delete(TRUE);
		if($q !== FALSE){ return -1; }
	}
}

/*
#####################
#   AntiAFK Newbie  #
#####################
*/

$antiafk = $pokemon->settings($telegram->chat->id, 'antiafk');
if($antiafk and !$this->telegram->callback){
    // Si no habla, last_date = register_date y mensajes = 0
    if(!is_numeric($antiafk) or $antiafk <= 1){ $antiafk = 5; }
    $except = [$this->config->item('telegram_bot_id'), $telegram->user->id];

    $query = $this->db
        ->select(['uid', 'register_date'])
        ->where('cid', $this->telegram->chat->id)
        ->where('messages', 0)
        ->where('register_date = last_date')
        ->where('register_date IS NOT NULL')
        ->where("DATE_ADD(register_date, INTERVAL $antiafk MINUTE) < NOW()")
        ->where_not_in('uid', $except)
        ->limit(1) // HACK
    ->get('user_inchat');

    if($query->num_rows() == 1){
        $afk = $query->row();

        $q = $this->telegram->send->kick($afk->uid, $this->telegram->chat->id);
        if($q !== FALSE){
            $pokemon->user_delgroup($afk->uid, $this->telegram->chat->id);
            $adminchat = $pokemon->settings($telegram->chat->id, 'admin_chat');

            if($adminchat){
                $str = ":warning: AntiAFK Newbie\n"
                        .":id: " .$afk->uid ."\n"
                        ."\ud83d\udcc5 " .$afk->register_date;

                $str = $this->telegram->emoji($str);

                $this->telegram->send
                    ->notification(TRUE)
                    ->chat($adminchat)
                    ->text($str)
                ->send();
            }
        }
    }
}

/*
#####################
#   Require avatar  #
#####################
*/

$avatar = $pokemon->settings($telegram->chat->id, 'require_avatar');
if($avatar){
	$query = $this->db
		->select('messages')
		->where('cid', $telegram->chat->id)
		->where('uid', $telegram->chat->id)
	->get('user_inchat');
	if($query->num_rows() == 1 and $query->row()->messages == 5){
		// TODO Get avatar
		if(1 == 2){
			$q = $this->telegram->send->kick($telegram->user->id, $telegram->chat->id);
			$adminchat = $pokemon->settings($telegram->chat->id, 'admin_chat');

			if($q === FALSE){
				if($adminchat){
					$str = ":warning: No foto de perfil\n"
							.":id: " .$telegram->user->id ."\n"
							.":male: " .$telegram->user->first_name;

					$str = $this->telegram->emoji($str);

					$this->telegram->send
						->notification(TRUE)
						->chat($adminchat)
						->text($str)
					->send();
				}
				return -1;
			}

			// Si está kick, quitar del grupo.
			$pokemon->user_delgroup($telegram->user->id, $this->telegram->chat->id);

			if($adminchat){
				$str = ":forbid: Kick por no foto de perfil\n"
						.":id: " .$telegram->user->id ."\n"
						.":male: " .$telegram->user->first_name;

				$str = $this->telegram->emoji($str);

				$this->telegram->send
					->notification(TRUE)
					->chat($adminchat)
					->text($str)
				->send();
			}

			return -1;
		}
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
	if($this->telegram->new_user and $this->telegram->new_user->id == $this->config->item('telegram_bot_id')){
		$this->telegram->send->leave_chat();
	}
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
