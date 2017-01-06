<?php

if($telegram->text_command("version")){
	$out = exec("git log --pretty=format:'%h %at' -n 1");
	$telegram->send
		->text($out)
	->send();

	return -1;
}

?>
