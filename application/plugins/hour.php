<?php

if($this->telegram->text_has("Hora", TRUE) && $telegram->words() == 2){
    $zonas = ['PDT' => -7, 'PST' => -8, 'CET' => 1, 'UTC' => 0, 'GMT' => 0];
    $sel = NULL;
    foreach($zonas as $z => $t){
        if($this->telegram->text_contains($z)){ $sel = $z; break; }
    }

    if(empty($sel)){
        $this->telegram->send
            ->text("Zona horaria no reconocida.")
        ->send();
        return -1;
    }

    $sum = 0;

    foreach($this->telegram->words(TRUE) as $n => $w){
        if($n == 0){ continue; }
        if(strpos($w, $zonas[$sel]) === 0){
            if(strlen($w) > strlen($zonas[$sel]) && in_array(substr($w, 3, 1), ["-", "+"])){
                $sum = substr($w, 3);
                $sum = intval($sum);
            }
        }
    }

    $time = time() - 3600; // Madrid - Rome
    $time = $time + (3600 * $zonas[$sel]) + (3600 * $sum);

    $str = ":clock: " .date("H:i:s") ."\n"
            .":world: " .date("H:i:s", $time);

    $str = $this->telegram->emoji($str);
    $this->telegram->send
        ->text($str)
    ->send();

    return -1;
}

?>
