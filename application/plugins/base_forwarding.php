<?php

/*
##############################
# Interactive chat / forward #
##############################
*/

if($pokemon->settings($telegram->chat->id, 'forward_interactive')){
    $telegram->send
        ->notification(FALSE)
        ->chat($telegram->chat->id)
        ->message(TRUE)
        ->forward_to($this->config->item('creator'))
    ->send();
}

?>
