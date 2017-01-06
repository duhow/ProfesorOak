<?php

if($telegram->chat->id != $telegram->user->id){ return; }

if($telegram->text_has("dame un huevo") && $telegram->words() <= 6){
	$telegram->send
		->text("Nope.")
	->send();
	return -1;
}

?>
