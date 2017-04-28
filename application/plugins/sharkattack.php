<?php

$shark = $pokemon->settings($telegram->user->id, 'sharkattack');
if($shark){
	$sharkchat = $pokemon->settings($telegram->chat->id, 'sharkattack');
	$sharkusers = array();
	if(!empty($sharkchat)){
		$sharkchat = explode(";", $sharkchat);
		foreach($sharkchat as $k){
			$k = explode(",", $k);
			$sharkusers[$k[0]] = $k[1];
		}
	}
	if(isset($sharkusers[$telegram->user->id]) and $sharkusers[$telegram->user->id] == date("d")){ return; } // Skip plugin.

	$sharkusers[$telegram->user->id] = date("d");
	$sharkchat = array();
	foreach($sharkusers as $id => $d){ $sharkchat[] = "$id,$d"; }
	$sharkchat = implode(";", $sharkchat);

	$pokemon->settings($telegram->chat->id, 'sharkattack', $sharkchat);

	$this->telegram->send
		->file('voice', "AwADBAADwgADhLIRUJQB4Q4vCdLyAg");

	return -1;
}

?>
