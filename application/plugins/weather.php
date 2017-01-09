<?php

if($telegram->text_command("tiempo") && $telegram->user->id == $this->config->item('creator')){
	$name = $pokemon->settings($telegram->chat->id, 'name');
	if(strpos($name, "_") !== FALSE){
		$name = NULL;
		$zipcode = $pokemon->settings($telegram->chat->id, 'zipcode');
		if(empty($zipcode)){
			$telegram->send
				->text($telegram->emoji(":times: El grupo no tiene configurada la ubicación correctamente."))
			->send();
			return -1;
		}
		if($zipcode == "08800"){ $name = "vilanova-i-la-geltru"; }

		if(empty($name)){
			$telegram->send
				->text($telegram->emoji(":times: No encuentro esa ciudad :("))
			->send();
			return -1;
		}
	}
	$tmpname = time()."_".mt_rand(1,9);
	$tmpfile = "/tmp/$tmpname";

	$phjs = APPPATH."plugins/phantomjs/tiempo.js";
	$phbin = APPPATH."third_party/phantomjs/bin/phantomjs";

	$cmd = ($phbin ." $phjs $name $tmpfile");
	// $telegram->send->chat($this->config->item('creator'))->text($cmd)->send();

	$telegram->send->chat_action('upload_photo')->send();
	if(!file_exists($phbin)){ return -1; }
	exec($cmd);

	$tmpfile .= ".png";
	if(file_exists($tmpfile)){
		$telegram->send->file('photo', $tmpfile);
		unlink($tmpfile);
	}

}

?>
