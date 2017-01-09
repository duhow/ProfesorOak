<?php

$joke = NULL;

if($telegram->text_has("Team Rocket despega")){
    $this->analytics->event('Telegram', 'Jokes', 'Team Rocket');
    $telegram->send->notification(FALSE)->file('photo', FCPATH . "files/teamrocket.jpg", "¡¡El Team Rocket despega de nuevoooooo...!!");
    $telegram->send->notification(FALSE)->file('audio', FCPATH . "files/teamrocket.ogg");
}elseif($telegram->text_contains("sextape")){
    $telegram->send->notification(FALSE)->file('video', FCPATH . "files/sextape.mp4");
}elseif($telegram->text_has(["GTFO", "vale adiós"], TRUE)){
    // puerta revisar
    $this->analytics->event('Telegram', 'Jokes', 'GTFO');
    $telegram->send->notification(FALSE)->file('document', "BQADBAADHgEAAuK9EgOeCEDKa3fsFgI"); // Puerta
}elseif($telegram->text_contains(["badumtss", "ba dum tss"])){
    $this->analytics->event('Telegram', 'Jokes', 'Ba Dum Tss');
    $telegram->send->notification(FALSE)->file('document', "BQADBAADHgMAAo-zWQOHtZAjTKJW2QI");
}elseif($telegram->text_has(["métemela", "por el culo", "por el ano"])){
    // if($this->is_shutup_jokes()){ return; }
    $this->analytics->event('Telegram', 'Jokes', 'Metemela');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/metemela.ogg");
}elseif($telegram->text_has(["seguro", "plan"], "dental")){
    $this->analytics->event('Telegram', 'Jokes', 'Seguro dental');
    $telegram->send->chat_action('upload_video')->send();
    $telegram->send->notification(FALSE)->file('video', FCPATH . "files/seguro_dental.mp4");
}elseif($telegram->text_has("no paras") && $telegram->words() < 10){
    $this->analytics->event('Telegram', 'Jokes', 'Paras');
    $telegram->send->notification(FALSE)->file('photo', FCPATH . "files/paras.png");
}elseif($telegram->text_contains("JOHN CENA") && $telegram->words() < 10){
    $this->analytics->event('Telegram', 'Jokes', 'John Cena');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/john_cena.ogg");
}elseif($telegram->text_has(["soy", "soi", "eres"], ["100tifiko", "científico"])){
    $this->analytics->event('Telegram', 'Jokes', '100tifiko');
    $telegram->send->notification(FALSE)->file('sticker', 'BQADBAADFgADPngvAtG9NS3VQEf5Ag');
}elseif($telegram->text_has(["hola", "buenas"], ["profesor", "profe", "oak"]) && $telegram->words() <= 4){
    $this->analytics->event('Telegram', 'Jokes', 'Me gusta el dinero');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/hola_dinero.ogg");
}elseif($telegram->text_has(["muéstrame", "mostrar"]) && $telegram->text_has(["pokebola", "pokeball"]) && $telegram->words() <= 5){
    $this->analytics->event('Telegram', 'Jokes', 'Muestrame tu Pokebola');
    $telegram->send->chat_action('upload_audio')->send();
    $telegram->send->notification(FALSE)->file('audio', FCPATH . "files/pokebola.mp3");
}elseif($telegram->text_has(["msn", "zumbido"])){
    $this->analytics->event('Telegram', 'Jokes', 'Zumbido');
    $telegram->send->chat_action('upload_audio')->send();
    $telegram->send->notification(FALSE)->file('audio', FCPATH . "files/msn.ogg");
}elseif($telegram->text_has(["maincra"])){
    $this->analytics->event('Telegram', 'Jokes', 'Maincra');
    $audio = ["maincra_1.mp3", "maincra_2.mp3", "maincra_3.mp3", "maincra_4.mp3"];
    $rand = mt_rand(0, count($audio) - 1);
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/" .$audio[$rand]);
}elseif($telegram->text_command("taladro") && $telegram->user->id == $this->config->item('creator')){
    $this->analytics->event('Telegram', 'Jokes', 'Taladro');
    $telegram->send->chat_action('upload_video')->send();
    $telegram->send->notification(FALSE)->reply_to(FALSE)->file('document', 'BQADBAADq08AAlVRZArqEZcMIc4iJQI');
}elseif((($telegram->text_has("yo", "no") && !$telegram->text_has(["tuyo", "suyo"])) or $telegram->text_has(["votos a favor", "votos en contra"])) && $telegram->words() <= 3){
    $this->analytics->event('Telegram', 'Jokes', 'Yo no');
    // if($this->is_shutup(FALSE)){ return; }
    if(mt_rand(1, 4) == 4){
        $telegram->send->chat_action('record_audio')->send();
        $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/yono.mp3");
    }
}elseif($telegram->text_command("fichas") or $telegram->text_has(["te follo", "te follaba"])){
    $this->analytics->event('Telegram', 'Jokes', 'Fichas');
    $telegram->send->notification(FALSE)->file('document', 'BQADBAADQQMAAgweZAcaoiy0cZEn5wI');
}elseif(
    $telegram->text_command("tennis") or
    $telegram->text_has("maria", ["sharapova", "sarapova"]) or
    $telegram->text_has($telegram->emoji(":tennis:"), TRUE)
){
    $this->analytics->event('Telegram', 'Jokes', 'Tenis con Maria Sharapova');
    $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/tennis.ogg");
}elseif($telegram->text_has("corre", "corre")){
    $this->analytics->event('Telegram', 'Jokes', 'Running');
    $telegram->send->chat_action('upload_audio')->send();
    $telegram->send->notification(FALSE)->file('audio', FCPATH ."files/running.ogg");
}elseif($telegram->text_has(["quiero", "necesito"], ["abrazo", "abrazarte", "un abrazo"])){
    $this->analytics->event('Telegram', 'Jokes', 'Hug');
    $telegram->send->notification(FALSE)->file('document', FCPATH ."files/hug.gif");
}elseif($telegram->text_has(["transferir", "transfiere", "recicla"]) && $telegram->text_has(["pokémon"])){
    $this->analytics->event('Telegram', 'Jokes', 'Transfer Pokemon');
    $telegram->send->notification(FALSE)->file('document', FCPATH . "pidgey.gif", "Espera entrenador, que te voy a transferir un caramelo...");
}elseif($telegram->text_has("fanta") && $telegram->words() > 3){
    $this->analytics->event('Telegram', 'Jokes', 'Fanta');
    $fantas = [
        "BQADBAADLwEAAjSYQgABe8eWP7cgn9gC", // Naranja
        "BQADBAADQwEAAjSYQgABVgn9h2J6NfsC", // Limon
        "BQADBAADRQEAAjSYQgABsDEEUjdh0w8C", // Uva
        "BQADBAADRwEAAjSYQgABu1UlOqU2-8IC", // Fresa
    ];
    $n = mt_rand(0, count($fantas) - 1);
    if($telegram->text_has('naranja')){ $n = 0; }
    elseif($telegram->text_has('limón')){ $n = 1; }
    elseif($telegram->text_has('uva')){ $n = 2; }
    elseif($telegram->text_has('fresa')){ $n = 3; }

    // if(!$this->is_shutup()){
        $telegram->send->notification(FALSE)->file('sticker', $fantas[$n]);
    // }
}elseif($telegram->text_has(["vas a la", "hay una", "es una"], "fiesta")){
    $this->analytics->event('Telegram', 'Jokes', 'Party');
    // if($this->is_shutup()){ return; }
    $telegram->send
        ->notification(FALSE)
        ->caption("¿Fiesta? ¡La que te va a dar ésta!")
        ->file('document', "BQADBAADpgMAAnMdZAePc-TerW2MSwI");
}elseif($telegram->text_has("oak", "oak") or $telegram->text_has("toda", "toda")){
    $this->analytics->event('Telegram', 'Jokes', 'Oak Oak');
    $telegram->send->chat_action('upload_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH ."files/te_necesito.ogg");
}elseif($telegram->text_has(["soy", "luke"]) and $telegram->text_has(["tu padre", "papa"])){
    $this->analytics->event('Telegram', 'Jokes', 'Yo soy tu padre');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH ."files/luke_padre.ogg");
}elseif($telegram->text_has(["subnor", "subnormal"]) and $telegram->text_has(["alerta", "detectado", "detected", "eres"])){
    $this->analytics->event('Telegram', 'Jokes', 'Alerta por subnormal');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH ."files/alerta_subnormal.ogg");
}elseif($telegram->text_has(["saca", "dame"]) and $telegram->text_has("látigo")){
    $this->analytics->event('Telegram', 'Jokes', 'Látigo');
    $telegram->send->chat_action('upload_video')->send();
    $telegram->send->notification(FALSE)->file('document', FCPATH ."files/whip.gif");
}elseif($telegram->text_has(["guerra", "callaos", "callaros"]) and $telegram->words() <= 6){
    $this->analytics->event('Telegram', 'Jokes', 'Callaos Hipoglúcidos');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH ."files/hipoglucidos.mp3");
}elseif($telegram->text_has(["warns", "/warns"])){
    $this->analytics->event('Telegram', 'Jokes', 'Buarns');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH ."files/buarns.mp3");
}elseif($telegram->text_has(["no llevo nada", "no llevara nada"])){
    $this->analytics->event('Telegram', 'Jokes', 'No llevara nada');
    $telegram->send->chat_action('upload_video')->send();
    $telegram->send->notification(FALSE)->file('document', FCPATH ."files/flanders.mp4");
}elseif($telegram->text_has(["pedaso"])){
    $this->analytics->event('Telegram', 'Jokes', 'Pedaso');
    $telegram->send->chat_action('record_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH ."files/pedaso.mp3");
}elseif($telegram->text_has("suspense") && $telegram->words() <= 10){
	$this->analytics->event("Telegram", "Jokes", "Suspense");
	$telegram->send->notification(FALSE)->file('voice', 'AwADBAADcyEAAsGgBgx9Qm3d_Dp7lgI');
}elseif($telegram->text_has(["tdfw", "turn down for what"])){
    $this->analytics->event('Telegram', 'Jokes', 'Turn Down');
    $files = ["tdfw_botella.mp3", "tdfw_turndown.mp3"];
    $file = $files[mt_rand(0, count($files) - 1)];
    $telegram->send->chat_action('upload_audio')->send();
    $telegram->send->notification(FALSE)->file('voice', FCPATH ."files/$file");
}elseif($telegram->text_has(["es", "eres"], "tonto") && $telegram->words() <= 5){
    $this->analytics->event('Telegram', 'Jokes', 'Tonto');
    // if($this->is_shutup()){ return; }
    $telegram->send->chat_action('record_audio')->send();
    if($telegram->has_reply){ $telegram->send->reply_to(FALSE); }
    $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/tonto.ogg");
}elseif($telegram->text_has(["eres muy", "que"], ["sexy", "saxo"]) && $telegram->words() <= 5){
    $this->analytics->event('Telegram', 'Jokes', 'Sexy Saxofon');
    // if($this->is_shutup()){ return; }
    $telegram->send->chat_action('record_audio')->send();
    if($telegram->has_reply){ $telegram->send->reply_to(FALSE); }
    $telegram->send->notification(FALSE)->file('voice', FCPATH . "files/careless_whisper.mp3");
}elseif($telegram->text_has(["bug", "bugeate", "bugeado"]) && $telegram->words() <= 4){
    if(mt_rand(1, 4) == 4){
        $telegram->send->file('voice', FCPATH . 'files/modem.ogg', 'ERROR 404 PKGO_FC_CHEATS NOT_FOUND');
    }
}elseif($telegram->text_has("es fly")){
	$telegram->send->file('video', 'BAADBAAD9AgAAjbFNAABxUA6dF63m1YC');
}

?>
