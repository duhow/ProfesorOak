<?php

function git_version(){
	$out = exec("git log --pretty=format:'%h %at' -n 1");
	$info = explode(" ", $out);

	if(is_array($info) && count($info) == 2){
		$info['hash'] = $info[0];
		$info['date'] = $info[1];
		return $info;
	}
	return NULL;
}

function git_pull(){

}

if(
	$telegram->text_command("update") &&
	$telegram->user->id == $this->config->item('creator')
){
	$m = $telegram->send
			->text("Ejecutando...")
		->send();

	$out = shell_exec("git pull");
	if(strpos($out, "Already up-to-date") !== FALSE){
		$out = $telegram->emoji(":ok: Ya está actualizado.");
	}

	$telegram->send
		->chat(TRUE)
		->message($m['message_id'])
		->text($out)
	->edit('text');

	return -1;
}

if(
	$telegram->text_command("version") or
	( ($telegram->text_has(["profe", "profesor", "oak"]) or $telegram->text_mention("ProfesorOak_bot") ) && 
	  $telegram->text_has("versión") && $telegram->words() <= 10)
){
	$info = git_version();
	$str = $telegram->emoji(":warning: No se puede cargar la información Git.");

	if($info !== NULL){
		$str = '<a href="https://github.com/duhow/ProfesorOak/commit/' .$info['hash'] .'">' .$info['hash'] .'+</a>, del '
				.date("d/m/Y H:i", $info['date']);
	}

	$telegram->send
		->disable_web_page_preview(TRUE)
		->text($str, 'HTML')
	->send();

	return -1;
}

?>
