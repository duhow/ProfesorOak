<?php

if(
	$this->telegram->text_contains("spotify:track") or
	$this->telegram->text_contains("open.spotify.com/track/")
){
	$track = $this->telegram->text();

	if($this->telegram->text_contains("open.spotify.com")){
		$track = substr($track, strpos($track, "open.spotify.com") + strlen("open.spotify.com/track/"), 22);
	}else{
		$track = substr($track, strpos($track, "spotify:track") + strlen("spotify:track:"), 22);
	}

	$track = trim($track);

	$info = json_decode(file_get_contents("https://api.spotify.com/v1/tracks/$track"));

	if(isset($info->error)){ return; }
	if(isset($info->preview_url) and !empty($info->preview_url)){
		$this->telegram->send->chat_action('upload_audio')->send();
		$this->telegram->send
			->notification(FALSE)
			->file('audio', $info->preview_url);
	}
}

?>
