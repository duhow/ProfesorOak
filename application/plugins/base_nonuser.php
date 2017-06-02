<?php

if($pokemon->user_exists($telegram->user->id)){ return; }

// Comando de información de registro para la gente que tanto lo spamea...
if($telegram->text_command("register")){
	if($pokemon->command_limit("register", $telegram->chat->id, $telegram->message, 7)){
		$this->telegram->send->delete(TRUE);
		return -1;
	}

    $this->analytics->event('Telegram', 'Register', 'command');
    $str = "Hola " .$telegram->user->first_name ."! Me podrías decir tu color?\n"
            ."(*Soy* ...)";
    if($telegram->is_chat_group()){
        $str = "Hola! Ábreme y registrate por privado :)";
        $telegram->send
            ->inline_keyboard()
                ->row_button("Registrar", "https://t.me/ProfesorOak_bot")
            ->show();
    }
    $telegram->send
        ->notification(FALSE)
        ->text($str, TRUE)
    ->send();
    return -1;
}

// Guardar color de user
elseif(
    ($telegram->text_has(["Soy", "Equipo", "Team"]) && color_parse($telegram->text()) ) or
    (color_parse($telegram->text()) && $telegram->words() == 1)
){
	if($telegram->text_command()){ return -1; }
    if(!$pokemon->user_exists($telegram->user->id)){
		$color = color_parse($telegram->text());

        // Registrar al usuario si es del color correcto
        if($pokemon->register($telegram->user->id, $color ) !== FALSE){
            $this->analytics->event('Telegram', 'Register', $color);

            $name = $telegram->user->first_name ." " .$telegram->user->last_name;
            $pokemon->update_user_data($telegram->user->id, 'fullname', $name);
            $pokemon->step($telegram->user->id, 'SETNAME'); // HACK Poner nombre con una palabra
            // enviar mensaje al usuario
            $telegram->send
                ->notification(FALSE)
                ->reply_to(TRUE)
                ->text("Muchas gracias " .$telegram->user->first_name ."! Por cierto, ¿cómo te llamas *en el juego*? \n_(Me llamo...)_", TRUE)
            ->send();
        }else{
            $this->analytics->event('Telegram', 'Register', 'wrong', $text);
            $telegram->send
                ->notification(FALSE)
                ->reply_to(TRUE)
                ->text("No te he entendido bien...\n¿Puedes decirme sencillamente *soy rojo*, *soy azul* o *soy amarillo*?", TRUE)
            ->send();
        }

		return -1;
    }
}


?>
