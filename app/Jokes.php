<?php

class Jokes extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		if(!$this->chat->settings('jokes')){ return; }
		$res = parent::run();

		if($res !== NULL){ $this->end(); }
	}

	protected function hooks(){
		if($this->telegram->text_has(["alguien", "alguno"]) && $this->telegram->text_has(["decir", "dice", "sabe"])){
			if(mt_rand(1, 7) == 7){
				$this->send_text($this->pa_ke_saberlo());
				$this->end();
			}
		}

		if($this->telegram->text_has("Team Rocket despega")){
			return $this->team_rocket();
		}elseif($this->telegram->text_contains("sextape")){
			return $this->sextape();
		}elseif($this->telegram->text_has(["GTFO", "vale adiós"], TRUE)){
			return $this->gtfo();
		}elseif($this->telegram->text_contains(["badumtss", "ba dum tss"])){
			return $this->badumtss();
		}elseif($this->telegram->text_has(["métemela", "por el culo", "por el ano"])){
			return $this->metemela();
		}elseif($this->telegram->text_has(["seguro", "plan"], "dental")){
			return $this->seguro_dental();
		}elseif($this->telegram->text_has("no paras") && $this->telegram->words() < 10){
			return $this->paras();
		}elseif($this->telegram->text_contains("JOHN CENA") && $this->telegram->words() < 10){
			return $this->john_cena();
		}elseif($this->telegram->text_has(["soy", "soi", "eres"], ["100tifiko", "científico"])){
			return $this->cientifico();
		}elseif($this->telegram->text_has(["hola", "buenas"], ["profesor", "profe", "oak"]) && $this->telegram->words() <= 4){
			return $this->hello_spongebob();
		}elseif($this->telegram->text_has(["muéstrame", "mostrar"]) && $this->telegram->text_has(["pokebola", "pokeball"]) && $this->telegram->words() <= 5){
			return $this->muestrame_pokebola();
		}elseif($this->telegram->text_has(["msn", "zumbido"])){
			return $this->zumbido();
		}elseif($this->telegram->text_has("maincra")){
			return $this->maincra();
		}elseif($this->telegram->text_command("taladro")){
			if($this->user->id != CREATOR){ return; }
			return $this->taladro();
		}elseif($this->telegram->text_has("yo", "no") && !$this->telegram->text_has(["tuyo", "suyo"]) && $this->telegram->words() <= 3){
			return $this->yono();
		}elseif($this->telegram->text_has(["votos a favor", "votos en contra"])){
			return $this->yono();
		}elseif($this->telegram->text_command("fichas") or $this->telegram->text_has("te", ["follo", "follaba"])){
			return $this->fichas();
		}elseif(
			$this->telegram->text_command("tennis") or
			$this->telegram->text_has("maria", ["sharapova", "sarapova"]) or
			$this->telegram->text_has($this->telegram->emoji(":tennis:"), TRUE)
		){
			return $this->tennis();
		}elseif($this->telegram->text_has("corre", "corre")){
			return $this->corre_corre();
		}elseif($this->telegram->text_has(["quiero", "necesito"], ["abrazo", "abrazarte", "un abrazo"])){
			return $this->hug();
		}elseif($this->telegram->text_has(["transferir", "transfiere", "recicla"]) && $this->telegram->text_has(["pokémon"])){
			return $this->pidgey_candy();
		}elseif($this->telegram->text_has("fanta") && $this->telegram->words() > 3){
			return $this->fanta();
		}elseif($this->telegram->text_has(["vas a la", "hay una", "es una"], "fiesta")){
			return $this->fiesta();
		}elseif($this->telegram->text_has("oak", "oak") or $this->telegram->text_has("toda", "toda")){
			return $this->oak_oak();
		}elseif($this->telegram->text_has(["soy", "luke"]) and $this->telegram->text_has(["tu padre", "papa"])){
			return $this->luke_padre();
		}elseif($this->telegram->text_has(["subnor", "subnormal"]) and $this->telegram->text_has(["alerta", "detectado", "detected", "eres"])){
			return $this->alerta_subnormal();
		}elseif($this->telegram->text_has(["saca", "dame"]) and $this->telegram->text_has("látigo")){
			return $this->latigo();
		}elseif($this->telegram->text_command("whip")){
			return $this->latigo();
		}elseif($this->telegram->text_has(["guerra", "callaos", "callaros"]) and $this->telegram->words() <= 6){
			return $this->callaos();
		}elseif($this->telegram->text_has(["warns", "/warns"])){
			return $this->buarns();
		}elseif($this->telegram->text_has("no", ["llevo nada", "llevara nada"])){
			return $this->no_llevara_nada();
		}elseif($this->telegram->text_has("pedaso")){
			return $this->pedaso();
		}elseif($this->telegram->text_has("suspense") && $this->telegram->words() <= 10){
			return $this->suspense();
		}elseif($this->telegram->text_has(["tdfw", "turn down for what"])){
			return $this->turn_down();
		}elseif($this->telegram->text_has(["es", "eres"], "tonto") && $this->telegram->words() <= 5){
			return $this->eres_tonto();
		}elseif($this->telegram->text_has(["eres muy", "que"], ["sexy", "saxo"]) && $this->telegram->words() <= 5){
			return $this->careless_whisper();
		}elseif($this->telegram->text_has(["bug", "bugeate", "bugeado"]) && $this->telegram->words() <= 4){
			if(mt_rand(1, 4) == 4){
				// return $this->bugeate();
			}
			return;
		}elseif($this->telegram->text_has("es fly")){
			return $this->soy_una_avioneta();
		}

		// -----------

		elseif(
			( $this->telegram->text_has(["necesitas", "necesitáis"], ["novio", "un novio", "novia", "una novia", "pareja", "una pareja", "follar"]) ) or
		    ( $this->telegram->text_has("Tengo", TRUE) && $this->telegram->words() == 2 && !is_numeric($this->telegram->last_word()) )
		){
			$word = "Novia";
			if($this->telegram->text_has("Tengo", TRUE)){ $word = $this->telegram->last_word(); }
			return $this->send_text($this->need_something($word));
		}elseif(
			$this->telegram->text_contains(["oak", "profe"]) &&
			$this->telegram->text_has(["cuántos", "cuándo", "qué"]) &&
			$this->telegram->text_contains(["años", "edad", "cumple"])
		){
			return $this->send_text($this->birthday());
		}elseif($this->telegram->text_has("Quién es Ash") && $this->telegram->words() <= 7){
			return $this->send_text($this->whois_ash(), "Ash");
		}elseif($this->telegram->text_has("Quién es Brock") && $this->telegram->words() <= 7){
			return $this->send_text($this->whois_brock(), "Brock");
		}elseif(
			$this->telegram->text_has("Gracias", ["profesor", "Oak", "profe"]) &&
			!$this->telegram->text_has("pero", "no")
		){
			return $this->send_text($this->thank_you());
		}elseif($this->telegram->text_has(["buenos", "buenas", "bon"], ["días", "día", "tarde", "tarda", "tardes", "noches", "nit"])){
			// if($pokemon->command_limit("hello", $telegram->chat->id, $telegram->message, 7)){ return -1; }
			return $this->send_text($this->hello_day());
		}elseif($this->telegram->text_hashtag("novatos")){
			// return $this->send_text($this->novatos());
		}elseif($this->telegram->text_command("drama")){
			return $this->drama_spanish();
		}elseif($this->telegram->text_has(["a que sí"], ["profe", "oak", "profesor"])){
			return $this->send_text($this->reply_yes_no(), 'Reply yes or no');
		}elseif(
			$this->telegram->text_has(["profe", "profesor", "oak", "bot"]) &&
			$this->telegram->text_has(["programado", "funcionas"])
		){
			return $this->send_text($this->dev());
		}elseif(
			!$this->telegram->text_has(["qué", "cómo"]) &&
			$this->telegram->text_has(["quién", "oak", "profe"]) &&
			$this->telegram->text_has(["es", "te", "tu", "hizo a", "le"]) &&
			$this->telegram->text_has(["programado", "hecho", "hizo", "creado", "creador"]) &&
			$this->telegram->words() <= 8
		){
			return $this->send_text($this->creator());
		}elseif(
			$this->telegram->text_has("qué hora", ["es", "son"]) &&
			!$this->telegram->text_has("a qué hora") &&
			$this->telegram->text_contains("?") &&
			$this->telegram->words() <= 5
		){
			return $this->send_text($this->date());
		}elseif(
			$this->telegram->text_has(["profe", "profesor", "oak"]) &&
			$this->telegram->text_has("te", ["quiero", "amo", "adoro"])
		){
			return $this->send_text($this->iloveyou());
		}elseif(
			$this->telegram->text_contains(["te la com", "te lo com", "un hijo", "me ha dolido"]) &&
			$this->telegram->text_has(["oak", "profe", "bot"])
		){
			return $this->send_text($this->eat_dick(TRUE));
		}

		if($this->telegram->text_command("banana")){

		}elseif($this->telegram->text_command("me") && $this->telegram->words() > 1){

		}

		if($this->telegram->text_has("dame", ["un huevo", "pokeball", "pokeballs"]) && $this->telegram->words() <= 6){
			return $this->send_text("Nope.");
		}

	}

	private function send_text($text, $tag_analytic = NULL){
		if(!empty($tag_analytic)){ } // $this->analytics->event('Telegram', 'Jokes', $tag_analytic);
		return $this->telegram->send
			->notification(FALSE)
			->text($text, TRUE)
		->send();
	}

	private function send_sticker($id, $tag_analytic = NULL){
		if(!empty($tag_analytic)){ } // $this->analytics->event('Telegram', 'Jokes', $tag_analytic);
		return $this->telegram->send
			->notification(FALSE)
			->file('sticker', $id);
	}

	private function send_audio($id, $tag_analytic = NULL){
		if(!empty($tag_analytic)){ } // $this->analytics->event('Telegram', 'Jokes', $tag_analytic);
		$this->telegram->send->chat_action('upload_audio')->send();
		return $this->telegram->send
			->notification(FALSE)
			->file('audio', $id);
	}

	private function send_voice($id, $tag_analytic = NULL){
		if(!empty($tag_analytic)){ } // $this->analytics->event('Telegram', 'Jokes', $tag_analytic);
		$this->telegram->send->chat_action('record_audio')->send();
		return $this->telegram->send
			->notification(FALSE)
			->file('voice', $id);
	}

	private function send_photo($id, $tag_analytic = NULL){
		if(!empty($tag_analytic)){ } // $this->analytics->event('Telegram', 'Jokes', $tag_analytic);
		return $this->telegram->send
			->notification(FALSE)
			->file('photo', $id);
	}

	private function send_video($id, $tag_analytic = NULL){
		if(!empty($tag_analytic)){ } // $this->analytics->event('Telegram', 'Jokes', $tag_analytic);
		$this->telegram->send->chat_action('upload_video')->send();
		return $this->telegram->send
			->notification(FALSE)
			->file('video', $id);
	}

	private function send_gif($id, $tag_analytic = NULL){
		if(!empty($tag_analytic)){ } // $this->analytics->event('Telegram', 'Jokes', $tag_analytic);
		$this->telegram->send->chat_action('upload_video')->send();
		return $this->telegram->send
			->notification(FALSE)
			->file('document', $id);
	}

	public function joke(){
		// TODO
		/* $this->analytics->event('Telegram', 'Games', 'Jokes');
		// $this->last_command("JOKE");

		if(
			true == false
			// $this->telegram->is_chat_group()
			// && $this->is_shutup_jokes()
		){ return; }

		$joke = $this->pokemon->joke();

		if(filter_var($joke, FILTER_VALIDATE_URL) !== FALSE){
			// Foto
			$this->telegram->send
				->notification( !$this->telegram->is_chat_group() )
				->file('photo', $joke);
		}else{
			$this->telegram->send
				->notification( !$this->telegram->is_chat_group() )
				->text($joke, TRUE)
			->send();
		}
		return; */
	}

	public function need_something($what = "Novia"){
		if(strlen($what) > 8){ return; }
		$text = "¿$word? Qué es eso, ¿se come?";
		return $text;
	}

	public function birthday(){
		$release = strtotime("2016-07-16 14:27");
		$birthdate = strtotime("now") - $release;
		$days = floor($birthdate / (60*60*24));
		$text = "Cumplo " .floor($days/30) ." meses y " .($days % 30) ." días. ";
		$text .= $telegram->emoji(":)");
		return $text;
	}

	public function whois_ash(){
		// $this->analytics->event('Telegram', 'Jokes', 'Ash');
		$text = "Ah! Ese es un *cheater*, es nivel 100...\nLo que no sé de dónde saca tanto dinero para viajar tanto...";
		return $text;
	}

	public function whois_brock(){
		// $this->analytics->event('Telegram', 'Jokes', 'Brock');
		$text = "Eh! ese es el mejor criador de pokemon, lider de gimnasio, y amigo de Ash... Pero en cuanto ve una falda se pierde... Por cierto llevamos temporadas sin verle :O";
		return $text;
	}

	public function thank_you($nigga = FALSE){
		// $this->analytics->event('Telegram', 'Jokes', 'Thank you');
		if($nigga){ return "Yeah ma nigga 8-)"; }
		$frases = ["De nada, entrenador! :D", "Nada, para eso estamos! ^^", "Gracias a ti :3"];
		$n = mt_rand(0, count($frases) - 1);

		return $frases[$n];
	}

	public function iloveyou(){
		$text = "¡Yo también te quiero! <3";
		return $this->telegram->emoji($text);
	}

	public function hello_day(){
		// if($pokemon->command_limit("hello", $telegram->chat->id, $telegram->message, 7)){ return -1; }
		$text = "Buenas a ti también, entrenador! :D";
		if($this->telegram->text_has(['noches', 'nit'])){
			$text = "Buenas noches fiera, descansa bien! :)";
		}
		return $text;
	}

	public function porn_questions(){
		// $this->analytics->event('Telegram', 'Jokes', 'Question');
		$preguntas = [
			"¿Nombre?", "¿Edad?", "¿Lugar de residencia?",
			"¿Tendencia sexual?", "Foto de tu cara", "¿Tragas o escupes?"
		];
		$text = "";
		for($i = 0; $i < count($preguntas); $i++){
			$text .= ($i+1) .".- $preguntas[$i]\n";
		}
		return $text;
	}

	public function drama_spanish(){
		$drama = [
	        'BQADAwADXgADVC-4BxFsybPmJZnnAg', // Judges you in Spanish
	        'BQADAwADWAADVC-4B-5sJxB9W3QUAg', // Cries in Spanish
	        'BQADAwADaAADVC-4B-Sq7oqcxWkyAg', // Screams in Spanish
	        'BQADAwADxwADVC-4BxbymhHL_2iYAg', // Gets nervous in Spanish
	    ];
	    $n = mt_rand(0, count($drama) - 1);
		return $this->send_sticker($drama[$n], 'Drama');
	}

	public function reply_yes_no(){
		// $this->analytics->event('Telegram', 'Jokes', 'Reply yes or no');
		$resp = ["¡Por supuesto que sí!",
			"Mmm... Te equivocas.",
			"No creo que tu madre esté de acuerdo con eso... ;)",
			"Ahora mismo no te puedo decir...",
			"¿¡Pero tú por quién me tomas!?",
			"Pues ahora me has dejado con la duda...",
		];
		$n = mt_rand(0, count($resp) - 1);
		// if($this->is_shutup()){ return; }
		return $resp[$n];
	}

	public function pa_ke_saberlo(){
		$text = "pa k kieres saber eso jaja salu2";
		return $text;
	}

	public function dev(){
		$text = "Pues yo funciono con *PHP* :)\n"
				."Antes usaba _CodeIgniter_, pero ahora tengo mi propia librería y todo. :cool:";
		return $this->telegram->emoji($text);
	}

	public function creator(){
		$text = "Pues mi creador es @duhow :)";
		return $text;
	}

	public function date(){
		// $this->analytics->event('Telegram', 'Jokes', 'Time');
		$text = "Son las " .date("H:i") .", una hora menos en Canarias. :)";
		return $text;
	}

	public function eat_dick($sticker = FALSE){
		if($this->telegram->text_has("no")){
			$text = "¿Pues entonces para que me dices nada? Gilipollas.";
		}else{
			$text = "Tu sabes lo que es el fiambre? Pues tranquilo, que no vas a pasar hambre... ;)";
			if($sticker){ $this->send_sticker('BQADBAADGgAD9VikAAEvUZ8dGx1_fgI'); }
		}
		return $text;
	}

	public function banana($user = NULL){
		// $this->analytics->event('Telegram', 'Jokes', 'Banana');
		$text = "Oye " .$telegram->reply_user->first_name .", " .$telegram->user->first_name ." quiere darte su banana... " .$telegram->emoji("=P");
		if($telegram->reply_user->id == $this->config->item('telegram_bot_id')){
			$text = "Oh, asi que quieres darme tu banana, " .$telegram->user->first_name ."? " .$telegram->emoji("=P");
			$telegram->send
				->chat($this->config->item('creator'))
				->text($telegram->user->first_name ." @" .$telegram->user->username ." / @" .$pokeuser->username ." quiere darte su banana.")
			->send();
		}
		$telegram->send
			->notification(FALSE)
			->reply_to(FALSE)
			->text($text)
		->send();
	}

	public function me($action = NULL){
		$text = substr($telegram->text(), strlen("/me "));
		if(strpos($text, "/") !== FALSE){ exit(); }
		$joke = trim("*" .$telegram->user->first_name ."* " .$telegram->emoji($text));
		return $joke;
	}

	// ----------------

	public function team_rocket(){
		// $this->analytics->event('Telegram', 'Jokes', 'Team Rocket');
		$this->send_photo(FCPATH . "files/teamrocket.jpg");
		$this->send_audio(FCPATH . "files/teamrocket.ogg");

		// "¡¡El Team Rocket despega de nuevoooooo...!!"
	}

	public function sextape(){
		return $this->send_video(FCPATH . "files/sextape.mp4", "Sextape");
	}

	public function gtfo(){
		return $this->send_gif("BQADBAADHgEAAuK9EgOeCEDKa3fsFgI", "GTFO");
	}

	public function badumtss(){
		return $this->send_gif("BQADBAADHgMAAo-zWQOHtZAjTKJW2QI", "Ba Dum Tss");
	}

	public function metemela(){
		return $this->send_voice(FCPATH . "files/metemela.ogg", "Metemela");
	}

	public function seguro_dental(){
		return $this->send_video(FCPATH . "files/seguro_dental.mp4", "Seguro dental");
	}

	public function no_paras(){
		return $this->send_photo(FCPATH . "files/paras.png", "Paras");
	}

	public function john_cena(){
		return $this->send_voice(FCPATH . "files/john_cena.ogg", "John Cena");
	}

	public function cientifico(){
		return $this->send_sticker("BQADBAADFgADPngvAtG9NS3VQEf5Ag", "100tifiko");
	}

	public function hello_spongebob(){
		return $this->send_voice(FCPATH . "files/hola_dinero.ogg", 'Me gusta el dinero');
	}

	public function muestrame_pokebola(){
		return $this->send_audio(FCPATH . "files/pokebola.mp3", 'Muestrame tu Pokebola');
	}

	public function zumbido(){
		return $this->send_audio(FCPATH . "files/msn.ogg", "Zumbido");
	}

	public function maincra($rand = NULL){
		$audio = ["maincra_1.mp3", "maincra_2.mp3", "maincra_3.mp3", "maincra_4.mp3"];
		if($rand === NULL){ $rand = mt_rand(0, count($audio) - 1); }
		return $this->send_voice(FCPATH . "files/" .$audio[$rand], 'Maincra');
	}

	public function taladro(){
		return $this->send_gif('BQADBAADq08AAlVRZArqEZcMIc4iJQI', "Taladro");
	}

	public function yo_no(){
		return $this->send_voice(FCPATH . "files/yono.mp3", 'Yo no');
	}

	public function fichas(){
		return $this->send_gif('BQADBAADQQMAAgweZAcaoiy0cZEn5wI', 'Fichas');
	}

	public function tennis(){
		return $this->send_voice(FCPATH . "files/tennis.ogg", "Tenis con Maria Sharapova");
	}

	public function corre_corre(){
		return $this->send_audio(FCPATH ."files/running.ogg", "Running");
	}

	public function hug(){
		return $this->send_gif(FCPATH ."files/hug.gif", "Hug");
	}

	public function pidgey_candy(){
		// $this->analytics->event('Telegram', 'Jokes', 'Transfer Pokemon');
		// $telegram->send->notification(FALSE)->file('document', FCPATH . "pidgey.gif", "Espera entrenador, que te voy a transferir un caramelo...");
		return $this->send_gif(FCPATH . "files/pidgey.gif", "Transfer Pokemon");
	}

	public function fanta($sabor = NULL){
		$fantas = [
			"BQADBAADLwEAAjSYQgABe8eWP7cgn9gC", // Naranja
			"BQADBAADQwEAAjSYQgABVgn9h2J6NfsC", // Limon
			"BQADBAADRQEAAjSYQgABsDEEUjdh0w8C", // Uva
			"BQADBAADRwEAAjSYQgABu1UlOqU2-8IC", // Fresa
		];
		$n = mt_rand(0, count($fantas) - 1);
		if($sabor == 'naranja'){ $n = 0; }
		elseif($sabor == 'limón'){ $n = 1; }
		elseif($sabor == 'uva'){ $n = 2; }
		elseif($sabor == 'fresa'){ $n = 3; }

		return $this->send_sticker($fantas[$n], "Fanta");
	}

	public function fiesta(){
		// ->caption("¿Fiesta? ¡La que te va a dar ésta!")
		return $this->send_gif('BQADBAADpgMAAnMdZAePc-TerW2MSwI', "Party");
	}

	public function oak_oak(){
		return $this->send_voice(FCPATH ."files/te_necesito.ogg", "Oak Oak");
	}

	public function luke_padre(){
		return $this->send_voice(FCPATH ."files/luke_padre.ogg", "Yo soy tu padre");
	}

	public function subnormal(){
		return $this->send_voice(FCPATH ."files/alerta_subnormal.ogg", "Alerta por subnormal");
	}

	public function latigo(){
		return $this->send_gif(FCPATH ."files/whip.gif", "Latigo");
	}

	public function callaos(){
		return $this->send_voice(FCPATH ."files/hipoglucidos.mp3", "Callaos Hipoglúcidos");
	}

	public function buarns(){
		return $this->send_voice(FCPATH ."files/buarns.mp3", "Buarns");
	}

	public function no_llevara_nada(){
		return $this->send_gif(FCPATH ."files/flanders.mp4", "No llevara nada");
	}

	public function pedaso(){
		return $this->send_voice(FCPATH ."files/pedaso.mp3", "Pedaso");
	}

	public function suspense(){
		return $this->send_voice('AwADBAADcyEAAsGgBgx9Qm3d_Dp7lgI', "Suspense");
	}

	public function turn_down($rand = NULL){
		$files = ["tdfw_botella.mp3", "tdfw_turndown.mp3"];
		if($rand === NULL){ $rand = mt_rand(0, count($files) - 1); }
		return $this->send_voice(FCPATH ."files/" .$files[$rand], "Turn Down");
	}

	public function eres_tonto(){
		if($this->telegram->has_reply){ $this->telegram->send->reply_to(FALSE); }
		return $this->send_voice(FCPATH. "files/tonto.ogg", "Tonto");
	}

	public function careless_whisper(){
		if($this->telegram->has_reply){ $this->telegram->send->reply_to(FALSE); }
		return $this->send_voice(FCPATH . "files/careless_whisper.mp3", 'Sexy Saxofon');
	}

	public function old_router(){
		if(mt_rand(1, 4) == 4){
			return $this->telegram->send->file('voice', FCPATH . 'files/modem.ogg', 'ERROR 404 PKGO_FC_CHEATS NOT_FOUND');
		}
	}

	public function soy_una_avioneta(){
		return $this->send_video('BAADBAAD9AgAAjbFNAABxUA6dF63m1YC', "Soy una avioneta");
	}

}
