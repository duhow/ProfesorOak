<?php

if($telegram->voice() && $telegram->voice(TRUE)->duration <= 7){
    // $telegram->send->text("Recibo voz de " .$telegram->voice(TRUE)->duration ." segundos.")->send();
    $tmp = tempnam("/tmp", "vrec");

    file_put_contents($tmp, file_get_contents($telegram->download($telegram->voice())));
    exec("ffmpeg -i $tmp -ar 16000 $tmp.wav");

    $dir = APPPATH ."third_party/sphinxbase/es";
    $data = array();
    exec("pocketsphinx_continuous -infile $tmp.wav -lm $dir/es-20k.lm -dict $dir/voxforge_es_sphinx.dic -hmm $dir/model/", $data);

    $str = "";
    foreach($data as $t){
        if(strpos($t, "INFO") === FALSE){
            $str .= $t ."\n";
        }
    }

    if(!empty($str)){
        $telegram->send->text($str)->send();
    }

    unlink($tmp);
    unlink("$tmp.wav");
    // ffmpeg -i vrecdU2nB9.ogg -ar 16000 audio.wav
    // pocketsphinx_continuous -infile vrecvk5TrD.ogg -lm /es-20k.lm -dict /voxforge_es_sphinx.dic -hmm /model/
}

?>
