<?php

if($pokemon->user_exists($telegram->user->id)){ return; }

$colores_full = [
    'Y' => ['amarillo', 'instinto', 'yellow', 'instinct'],
    'R' => ['rojo', 'valor', 'red'],
    'B' => ['azul', 'sabiduría', 'blue', 'mystic'],
];
$colores = array();
foreach($colores_full as $c){
    foreach($c as $col){ $colores[] = $col; }
}

// Comando de información de registro para la gente que tanto lo spamea...
if($telegram->text_command("register")){
    $this->analytics->event('Telegram', 'Register', 'command');
    $str = "Hola " .$telegram->user->first_name ."! Me podrías decir tu color?\n"
            ."(*Soy* ...)";
    // if($this->is_shutup()){
        $str = "Hola! Ábreme y registrate por privado :)";
    // }
    $telegram->send
        ->notification(FALSE)
        ->text($str, TRUE)
    ->send();
    return;
}

// Guardar color de user
elseif(
    ($telegram->text_has(["Soy", "Equipo", "Team"]) && $telegram->text_has($colores)) or
    ($telegram->text_has($colores) && $telegram->words() == 1)
){
    if(!$pokemon->user_exists($telegram->user->id)){
        $text = trim(strtolower($telegram->last_word('alphanumeric-accent')));

        // Registrar al usuario si es del color correcto
        if(strlen($text) >= 4 and $pokemon->register($telegram->user->id, $text) !== FALSE){
            $this->analytics->event('Telegram', 'Register', $text);

            $name = $telegram->user->first_name ." " .$telegram->user->last_name;
            $pokemon->update_user_data($telegram->user->id, 'fullname', $name);
            $pokemon->step($telegram->user->id, 'SETNAME'); // HACK Poner nombre con una palabra
            // enviar mensaje al usuario
            $telegram->send
                ->notification(FALSE)
                ->reply_to(TRUE)
                ->text("Muchas gracias " .$telegram->user->first_name ."! Por cierto, ¿cómo te llamas *en el juego*? \n_(Me llamo...)_", TRUE)
            ->send();
            die(); // HACK
        }else{
            $this->analytics->event('Telegram', 'Register', 'wrong', $text);
            $telegram->send
                ->notification(FALSE)
                ->reply_to(TRUE)
                ->text("No te he entendido bien...\n¿Puedes decirme sencillamente *soy rojo*, *soy azul* o *soy amarillo*?", TRUE)
            ->send();
        }
    }
}


?>
