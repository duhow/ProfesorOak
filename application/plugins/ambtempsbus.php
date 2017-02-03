<?php

function autobus($codigo){
    $url = "http://www.ambmobilitat.cat/AMBtempsbus";
    $data = ['codi' => str_pad($codigo, 6, '0', STR_PAD_LEFT)];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $get = curl_exec($ch);
    curl_close ($ch);

    if(!empty($get)){
        $get = substr($get, strpos($get, '<ul data-role='));
        $pos = 0;
        $lineas = array();
        while( ($pos = strpos($get, '<li>', $pos)) !== FALSE){
            $pos = strpos($get, '<b>', $pos) + strlen('<b>');
            $last = strpos($get, '</b>', $pos) - $pos;
            $linea = strip_tags(substr($get, $pos, $last));
            if(strpos($linea, "no disponible")){ return array(); } // HACK
            $lineas[] = $linea;
        }
        return $lineas;
    }
    return array();
}

if($this->telegram->text_command("amb") && $this->telegram->words() == 2){
    $num = $this->telegram->last_word(TRUE);
    if(!is_numeric($num)){
        $this->telegram->send
            ->text($this->telegram->emoji(":times: ") ."No has puesto el código de parada correcto!")
        ->send();
        return -1;
    }

    $q = $this->telegram->send
        ->text($this->telegram->emoji("\ud83d\udd51 ") ."Ejecutando...")
    ->send();

    $paradas = autobus($num);

    $str = "No encuentro paradas.";
    if(!empty($paradas)){
        $str = "";
        foreach($paradas as $parada){ $str .= $parada ."\n"; }
    }
    $this->telegram->send
        ->message($q['message_id'])
        ->chat(TRUE)
        ->text($str)
    ->edit('text');
    return -1;
}

?>
