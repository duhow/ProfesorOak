<?php

if($telegram->sticker() && $telegram->is_chat_group()){
    $palmeras = [
        'BQADBAADzw0AAu7oRgABumTXtan23SUC',
        'BQADBAAD0Q0AAu7oRgABXE1L0Qpaf_sC',
        'BQADBAAD0w0AAu7oRgABq22PAgABeiCcAg',
        // GATO
        'BQADBAAD4wEAAqKYZgABGO27mNGhdSUC',
        'BQADBAAD5QEAAqKYZgABe9jp1bTT8jcC',
        'BQADBAAD5wEAAqKYZgABiX1O201m5X0C',
    ];
    if(in_array($telegram->sticker(), $palmeras)){
        $admins = array();
        if(function_exists('telegram_admins')){
            $admins = telegram_admins(TRUE);
            if(in_array($this->config->item('telegram_bot_id'), $admins)){
                if(in_array($telegram->user->id, $admins)){ return; }
                $telegram->send->text("¡¡PALMERAS NO!!")->send();
                $telegram->send->kick($telegram->user->id, $telegram->chat->id);
            }
        }
        return TRUE;
    }
}

?>
