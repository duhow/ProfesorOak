<?php

if($this->telegram->text_command("ocr") && $this->telegram->has_reply && $this->telegram->user->id == $this->config->item('creator')){
	if(!isset($this->telegram->reply->photo)){ return; }
	$photo = array_pop($this->telegram->reply->photo);
	$url = $this->telegram->download($photo['file_id']);

	$temp = tempnam("/tmp", "tgphoto");
	file_put_contents($temp, file_get_contents($url));

	require_once APPPATH .'third_party/tesseract-ocr-for-php/src/TesseractOCR.php';

	$ocr = new TesseractOCR($temp);

	if(is_numeric($this->telegram->last_word())){
		$ocr->psm( intval($this->telegram->last_word()) );
	}

	$this->telegram->send
		->text( $ocr->lang('spa', 'eng')->run() )
	->send();
}

?>
