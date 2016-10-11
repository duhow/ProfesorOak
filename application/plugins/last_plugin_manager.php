<?php

if($telegram->user->id != $this->config->item('creator')){ return; }

if($telegram->text_command("plugin") && $telegram->words() >= 2){
    $plugin = new Plugin();

    $command = $telegram->words(1);
    switch ($command) {
        case 'list':
            $telegram->send
                ->text(implode(", ", $this->plugin->dump()))
            ->send();
            return;
        break;
        case 'enable':
        case 'disable':
            if($telegram->words() == 3){
                $pname = $telegram->words(2);
                if(!$plugin->exists($pname)){
                    $telegram->send
                        ->text($telegram->emoji(":warning: Plugin *$pname* no existe."), TRUE)
                    ->send();
                    return;
                }
                $text = NULL;
                if($command == "enable"){
                    $plugin->enable($pname);
                    $text = ":ok: Plugin *$pname* activado.";
                }else{
                    $plugin->disable($pname);
                    $text = ":times: Plugin *$pname* desactivado.";
                }
                $telegram->send
                    ->text($telegram->emoji($text), TRUE)
                ->send();
                return;
            }
        break;
    }
}

?>
