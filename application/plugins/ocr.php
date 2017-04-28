<?php

if($this->telegram->text_command("ocr") && $this->telegram->has_reply && $this->telegram->user->id == $this->config->item('creator')){
	if(isset($this->telegram->reply->photo)){
		$photo = array_pop($this->telegram->reply->photo);
		$photo = $photo['file_id'];
	}elseif(isset($this->telegram->reply->document)){
		$doc = $this->telegram->reply->document;
		if(strpos($doc['mime_type'], "image") === FALSE){
			$this->telegram->send
				->text($this->telegram->emoji(":warning: ") ."No es imagen. " .$doc['mime_type'])
			->send();
			return -1;
		}
		$photo = $doc['file_id'];
	}else{
		return;
	}

	$temp = tempnam("/tmp", "tgphoto");
	$this->telegram->download($photo, $temp);

	require_once APPPATH .'third_party/tesseract-ocr-for-php/src/TesseractOCR.php';

	$ocr = new TesseractOCR($temp);

	if(is_numeric($this->telegram->last_word())){
		$ocr->psm( intval($this->telegram->last_word()) );
	}

	$this->telegram->send
		->text( $ocr->lang('spa', 'eng')->run() )
	->send();

	unlink($temp);
}

?>
