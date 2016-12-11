<?php

// Oak o otro usuario es añadido a una conversación
if($telegram->is_chat_group() && $telegram->data_received() == "new_chat_participant"){
    $set = $pokemon->settings($telegram->chat->id, 'announce_welcome');
    $new = $telegram->new_user;
    $user = $telegram->user;
    $chat = $telegram->chat;

    if($new->id == $this->config->item("telegram_bot_id")){
        $count = $telegram->send->get_members_count();
        // Si el grupo tiene <= 5 usuarios, el bot abandona el grupo
        if(is_numeric($count) && $count <= 5){
            // A excepción de que lo agregue el creador
            if($telegram->user->id != $this->config->item('creator')){
                $this->analytics->event('Telegram', 'Join low group');
                $telegram->send->text("Nope.")->send();
                $telegram->send->leave_chat();
                return -1;
            }
        }

    // Bot agregado al grupo. Yo no saludo bots :(
    }elseif($telegram->is_bot($new->username)){ return -1; }

    $pknew = $pokemon->user($new->id);
    // El usuario nuevo es creador
    if($new->id == $this->config->item('creator')){
        if($pokemon->settings($telegram->user->id, 'silent_join') == TRUE){ return -1; }
        $telegram->send
            ->notification(TRUE)
            ->reply_to(TRUE)
            ->text("Bienvenido, jefe @duhow! Un placer tenerte aquí! :D")
        ->send();
        return;
    }elseif(!empty($pknew)){
        // Si el grupo es exclusivo a un color y el usuario es de otro color
        $teamonly = $pokemon->settings($telegram->chat->id, 'team_exclusive');
        if(!empty($teamonly) && $teamonly != $pknew->team){
            $this->analytics->event('Telegram', 'Spy enter group');
            $telegram->send
                ->notification(TRUE)
                ->reply_to(TRUE)
                ->text("*¡SE CUELA UN TOPO!* @$pknew->username $pknew->team", TRUE)
            ->send();

            // Kickear (por defecto TRUE)
            $kick = $pokemon->settings($telegram->chat->id, 'team_exclusive_kick');
            // TODO excepto si el que lo agrega es admin.
            if($kick != FALSE){
                $telegram->send->kick($new->id, $telegram->chat->id);
                $pokemon->user_delgroup($new->id, $telegram->chat->id);
            }
            return -1;
        }

        $blacklist = $pokemon->settings($telegram->chat->id, 'blacklist');
        if(!empty($blacklist)){
            $blacklist = explode(",", $blacklist);
            $pknew_flags = $pokemon->user_flags($pknew->telegramid);
            // TODO excepto si el que lo agrega es admin.
            foreach($blacklist as $b){
                if(in_array($b, $pknew_flags)){
                    $this->analytics->event('Telegram', 'Join blacklist user', $b);
                    $telegram->send->kick($new->id, $telegram->chat->id);
                    $pokemon->user_delgroup($new->id, $telegram->chat->id);
                    return -1;
                }
            }
        }
    }

    // Si el grupo no admite más usuarios...
    $nojoin = $pokemon->settings($telegram->chat->id, 'limit_join');
    // TODO excepto si el que lo agrega es admin.
    if($nojoin == TRUE){
        $this->analytics->event('Telegram', 'Join limit users');
        $telegram->send->kick($new->id, $telegram->chat->id);
        $pokemon->user_delgroup($new->id, $telegram->chat->id);
        return -1;
    }

    // Si el grupo requiere validados
    if(
        $pokemon->settings($telegram->chat->id, 'require_verified') == TRUE &&
        $pokemon->settings($telegram->chat->id, 'require_verified_kick') == TRUE
    ){
        if(empty($pknew) or $pknew->verified != TRUE){
            $this->analytics->event('Telegram', 'Kick unverified user');
            $telegram->send->kick($new->id, $telegram->chat->id);
            $pokemon->user_delgroup($new->id, $telegram->chat->id);
            $telegram->send
                ->text("Usuario " .$new->first_name ." / " .$new->id ." kickeado por no estar verificado.")
            ->send();
            return -1;
        }
    }

    // Si un usuario generico se une al grupo
    if($set != FALSE or $set === NULL){
        $custom = $pokemon->settings($telegram->chat->id, 'welcome');
        $text = 'Bienvenido al grupo, $nombre!' ."\n";
        if(!empty($custom)){ $text = json_decode($custom) ."\n"; }
        if(empty($pknew)){
            $text .= "Oye, ¿podrías decirme el color de tu equipo?\n*Di: *_Soy ..._";
        }else{
            $emoji = ["Y" => "yellow", "B" => "blue", "R" => "red"];
            $text .= '$pokemon $nivel $equipo $valido $ingress';

            if(!$pknew->verified && $pokemon->settings($telegram->chat->id, 'require_verified')){
                $text .= "\n" ."Para estar en este grupo *debes estar validado.*";

                $telegram->send
                    ->inline_keyboard()
                        ->row_button("Validar", "quiero validarme", "COMMAND")
                    ->show();
            }
        }

        if($new->id == $this->config->item("telegram_bot_id")){
            $pkuser = $pokemon->user($telegram->user->id);
            if(
                ($pkuser && $pkuser->blocked) or
                $pokemon->user_flags($telegram->user->id, ['hacks', 'ratkid', 'poketelegram_cheat'])
            ){
                $telegram->send->leave_chat();
                return -1;
            }
            $text = "¡Buenas a todos, entrenadores!\n¡Un placer estar con todos vosotros! :D";
        }

        $pokemon->user_addgroup($new->id, $telegram->chat->id);
        $this->analytics->event('Telegram', 'Join user');

        $ingress = NULL;
        if($pokemon->settings($new->id, 'resistance')){ $ingress = ":key:"; }
        elseif($pokemon->settings($new->id, 'enlightened')){ $ingress = ":frog:"; }

        $repl = [
            '$nombre' => $new->first_name,
            '$apellidos' => $new->last_name,
            '$equipo' => ':heart-' .$emoji[$pknew->team] .':',
            '$team' => ':heart-' .$emoji[$pknew->team] .':',
            '$usuario' => "@" .$new->username,
            '$pokemon' => "@" .$pknew->username,
            '$nivel' => "L" .$pknew->lvl,
            '$valido' => $pknew->verified ? ':green-check:' : ':warning:',
            '$ingress' => $ingress
        ];
        $text = str_replace(array_keys($repl), array_values($repl), $text);
		$text = $telegram->emoji($text);
        $telegram->send
            ->notification(FALSE)
            ->reply_to(TRUE)
            ->text( $text , TRUE)
        ->send();

        if(!empty($pknew)){
            $team = $pknew->team;
            $key = $pokemon->settings($telegram->chat->id, 'pair_team_' .$team);
            if(!empty($key)){
                $teamchat = $pokemon->group_pair($telegram->chat->id, $team);
                if(!$teamchat){
                    $telegram->send
                        ->chat($this->config->item('creator'))
                        ->notification(TRUE)
                        ->text("Problema con pairing $team en " .$telegram->chat->id ." (" .substr($key, 0, 10) .")")
                    ->send();
                    return -1;
                }
                // Tengo chat, comprobar blacklist
                $black = explode(",", $pokemon->settings($teamchat, 'blacklist'));
                if($pokemon->user_flags($telegram->new_user->id, $black)){ return -1; }

                $link = $pokemon->settings($teamchat, 'link_chat');
                if(empty($link)){
                    $telegram->send
                        ->chat($this->config->item('creator'))
                        ->notification(TRUE)
                        ->text("Problema con pair link $team en " .$telegram->chat->id ." (" .substr($key, 0, 10) .")")
                    ->send();
                    return -1;
                }
                // Si es validado
                $color = ['Y' => 'Amarillo', 'R' => 'Rojo', 'B' => 'Azul'];
                $text = "Hola! Veo que eres *" .$color[$pknew->team] ."* y acabas de entrar al grupo " .$telegram->chat->title .".\n"
                        ."Hay un grupo de tu team asociado, pero no te puedo invitar porque no estás validado " .$telegram->emoji(":warning:") .".\n"
                        ."Si *quieres validarte*, puedes decirmelo. :)";
                if($pknew->verified){
                    $text = "Hola! Te invito al grupo *" .$color[$pknew->team] ."* asociado a " .$telegram->chat->title .". "
                            ."¡No le pases este enlace a nadie!\n"
                            .$telegram->grouplink($link);
                }

                if(!$telegram->user_in_chat($telegram->user->id, $teamchat)){
                    $telegram->send
                        ->notification(TRUE)
                        ->chat($telegram->user->id)
                        ->text($text, NULL) // TODO NO Markdown.
                    ->send();

                    if($pknew->verified){
                        $telegram->send
                            ->notification(TRUE)
                            ->chat($teamchat)
                            ->text("He invitado a @" .$pknew->username ." a este grupo.")
                        ->send();
                    }
                }
            }
        }
    }
    return -1;
}elseif($telegram->is_chat_group() && $telegram->data_received("left_chat_participant")){
    $pokemon->user_delgroup($telegram->user->id, $telegram->chat->id);
    return -1;
}

?>
