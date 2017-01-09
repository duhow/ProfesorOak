<?php

if($telegram->user->id != $this->config->item('creator')){ return; }

if($telegram->text_command("plugin") && $telegram->words() >= 2){
    // $plugin = new Plugin();

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
                if(!$this->plugin->exists($pname)){
                    $telegram->send
                        ->text($telegram->emoji(":warning: Plugin *$pname* no existe."), TRUE)
                    ->send();
                    return;
                }
                $text = NULL;
                $res = FALSE;
                if($command == "enable"){
                    $res = $this->plugin->enable($pname);
                    $text = ":ok: Plugin *$pname* activado.";
                }else{
                    $res = $this->plugin->disable($pname);
                    $text = ":times: Plugin *$pname* desactivado.";
                }
                if(!$res){ $text = ":warning: Error al cambiar el estado."; }
                $telegram->send
                    ->text($telegram->emoji($text), TRUE)
                ->send();
                return;
            }
        break;
    }
}

?>
