<?php

define('TG_API_URL', 'https://api.telegram.org/bot');

class __Module_Telegram_Keyboard extends CI_Model{
	private $rows;
	private $config;

	function __construct(){
		parent::__construct();
		$this->selective(FALSE);
	}

	function row(){ return new __Module_Telegram_Keyboard_Row(); }
	function row_button($text, $request = NULL){ return $this->row()->button($text, $request)->end_row(); }

	function push($data){
		if(!is_array($data)){ return FALSE; }
		$this->rows[] = $data;
		return $this;
	}

	function selective($val = TRUE){
		$this->config['selective'] = $val;
		return $this;
	}

	function show($one_time = FALSE, $resize = FALSE){
		$this->telegram->send->_push('reply_markup', [
			'keyboard' => $this->rows,
			'resize_keyboard' => $resize,
			'one_time_keyboard' => $one_time,
			'selective' => $this->config['selective']
		]);
		$this->_reset();
		return $this->telegram->send;
	}

	function hide($sel = FALSE){
		if($sel === TRUE){ $this->selective(TRUE); }
		$this->telegram->send->_push('reply_markup', [
			'hide_keyboard' => TRUE,
			'selective' => $this->config['selective']
		]);
		$this->_reset();
		return $this->telegram->send;
	}

	function _reset(){
		$this->rows = array();
		return $this;
	}
}

class __Module_Telegram_Keyboard_Row extends CI_Model{
	private $buttons;
	function button($text, $request = NULL){
		$data = array();
		$data['text'] = $text;
		if($request === TRUE or $request == "contact"){ $data['request_contact'] = TRUE; }
		elseif($request === FALSE or $request == "location"){ $data['request_location'] = TRUE; }
		$this->buttons[] = $data;
		return $this;
	}
	function end_row(){
		var_dump($this->buttons);
		$this->telegram->send->keyboard()->push($this->buttons);
		return $this->telegram->send->keyboard();
	}
}

class __Module_Telegram_InlineKeyboard extends CI_Model{
	private $rows;
	private $config;

	function __construct(){
		parent::__construct();
	}

	function row($push = NULL){
		if($push === NULL){ return new __Module_Telegram_InlineKeyboard_Row(); }
		elseif(is_array($push)){
			$buttons = new __Module_Telegram_InlineKeyboard_Row();
			foreach($push as $bt){
				if(count($bt) == 1){ $bt = [current($bt), current($bt)]; } // Si no ha indicado request
				if(count($bt) == 2){ $bt = [$bt[0], $bt[1], NULL]; }
				$buttons->button($bt[0], $bt[1], $bt[2]);
			}
			return $buttons->end_row();
		}
	}

	function row_button($text, $request = NULL, $switch = NULL){ return $this->row()->button($text, $request, $switch)->end_row(); }

	function push($data){
		if(!is_array($data)){ return FALSE; }
		$this->rows[] = $data;
		return $this;
	}

	function show(){
		$this->telegram->send->_push('reply_markup', [
			'inline_keyboard' => $this->rows,
		]);
		$this->_reset();
		return $this->telegram->send;
	}

	function _reset(){
		$this->rows = array();
		return $this;
	}
}

class __Module_Telegram_InlineKeyboard_Row extends CI_Model{
	private $buttons;
	function button($text, $request = NULL, $switch = NULL){
		$data = array();
		$data['text'] = $text;
		if(filter_var($request, FILTER_VALIDATE_URL) !== FALSE){ $data['url'] = $request; }
		elseif($switch === TRUE or (is_string($switch) && strtolower($switch) == "command")){
			// enviar por privado
			$data['url'] = "https://telegram.me/" .$this->config->item('telegram_bot_name') ."?start=" .urlencode($request);
		}elseif(is_string($switch) && strtolower($switch) == "share"){
			$enc = NULL;
			if(is_array($request) && count($request) == 2){
				$enc = ['url' => urlencode($request[0]), 'text' => urldecode($request[1])];
			}else{
				$enc = ['url' => urlencode($request)];
			}
			$data['url'] = "https://telegram.me/share/url?" .http_build_query($enc);
		}
		elseif($switch === FALSE){ $data['switch_inline_query'] = $request; }
		elseif(is_string($switch) && strtolower($switch) == "text"){ $data['callback_data'] = "T:" .$request; }
		elseif($switch === NULL or is_string($switch)){ $data['callback_data'] = $request; }
		if(is_string($switch)){ $data['switch_inline_query'] = $switch; }
		$this->buttons[] = $data;
		return $this;
	}
	function end_row(){
		var_dump($this->buttons);
		$this->telegram->send->inline_keyboard()->push($this->buttons);
		return $this->telegram->send->inline_keyboard();
	}
}

class __Module_Telegram_Sender extends CI_Model{
	private $content = array();
	private $method = NULL;
	private $_keyboard;
	private $_inline;

	function __construct(){
		parent::__construct();
		$this->_keyboard = new __Module_Telegram_Keyboard();
		$this->_inline = new __Module_Telegram_InlineKeyboard();
	}

	function chat($id = NULL){
		if($id === TRUE){ $id = $this->telegram->chat->id; }
		$this->content['chat_id'] = $id;
		return $this;
	}

	function user($id = NULL){
		if(empty($id)){ return $this->content['user_id']; }
		$this->content['user_id'] = $id;
		return $this;
	}

	function message($id = NULL){
		if(empty($id)){ return $this->content['message_id']; }
		if($id === TRUE){ $id = $this->telegram->message; }
		$this->content['message_id'] = $id;
		return $this;
	}

	function get_file($id){
		$this->method = "getFile";
		$this->content['file_id'] = $id;
		return $this->send();
	}

	function file($type, $file, $caption = NULL, $keep = FALSE){
		if(!in_array($type, ["photo", "audio", "voice", "document", "sticker", "video"])){ return FALSE; }

		$url = FALSE;
		if(filter_var($file, FILTER_VALIDATE_URL) !== FALSE){
			// ES URL, descargar y enviar.
			$url = TRUE;
			$tmp = tempnam("/tmp", "telegram") .substr($file, -4); // .jpg
			file_put_contents($tmp, fopen($file, 'r'));
			$file = $tmp;
		}

		$this->method = "send" .ucfirst($type);
		if(file_exists(realpath($file))){
			$this->content[$type] = new CURLFile(realpath($file));
		}else{
			$this->content[$type] = $file;
		}
		if($caption !== NULL){
			$key = "caption";
			if($type == "audio"){ $key = "title"; }
			$this->content[$key] = $caption;
		}

		if(empty($this->content['chat_id'])){ $this->content['chat_id'] = $this->telegram->chat->id; }

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:multipart/form-data"
		));
		curl_setopt($ch, CURLOPT_URL, $this->_url(TRUE));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->content);
		$output = curl_exec($ch);

		if($url === TRUE){ unlink($file); }
		if($keep === FALSE){ $this->_reset(); }
		return $output;
		// return $this;
	}

	function location($lat, $lon = NULL){
		if(is_array($lat) && $lon == NULL){ $lon = $lat[1]; $lat = $lat[0]; }
		$this->content['latitude'] = $lat;
		$this->content['longitude'] = $lon;
		$this->method = "sendLocation";
		return $this;
	}

	function dump(){
		var_dump($this->method); var_dump($this->content);
		$bm = $this->method;
		$bc = $this->content;

		$this->_reset();
		$this
			->chat($this->config->item('creator'))
			->text(json_encode($bc))
		->send();
		$this->method = $bm;
		$this->content = $bc;
		return $this;
	}

	function contact($phone, $first_name, $last_name = NULL){
		$this->content['phone_number'] = $phone;
		$this->content['first_name'] = $first_name;
		if(!empty($last_name)){ $this->content['last_name'] = $last_name; }
		$this->method = "sendContact";
		return $this;
	}

	function text($text, $type = NULL){
		$this->content['text'] = $text;
		$this->method = "sendMessage";
		if($type === TRUE){ $this->content['parse_mode'] = 'Markdown'; }
		elseif(in_array($type, ['Markdown', 'HTML'])){ $this->content['parse_mode'] = $type; }

		return $this;
	}

	function keyboard(){ return $this->_keyboard; }
	function inline_keyboard(){ return $this->_inline; }

	function force_reply($selective = TRUE){
		$this->content['reply_markup'] = ['force_reply' => TRUE, 'selective' => $selective];
		return $this;
	}

	function caption($text){
		$this->content['caption'] = $text;
		return $this;
	}

	function disable_web_page_preview($value = FALSE){
		if($value === TRUE){ $this->content['disable_web_page_preview'] = TRUE; }
		return $this;
	}

	function notification($value = TRUE){
		if($value === FALSE){ $this->content['disable_notification'] = TRUE; }
		else{ if(isset($this->content['disable_notification'])){ unset($this->content['disable_notification']); } }
		return $this;
	}

	function reply_to($message_id = NULL){
		if($message_id === TRUE or ($message_id === FALSE && !$this->telegram->has_reply)){ $message_id = $this->telegram->message; }
		elseif($message_id === FALSE){
			if(!$this->telegram->has_reply){ return; }
			$message_id = $this->telegram->reply->message_id;
		}
		$this->content['reply_to_message_id'] = $message_id;
		return $this;
	}

	function forward_to($chat_id_to){
		if(empty($this->content['chat_id']) or empty($this->content['message_id'])){ return $this; }
		$this->content['from_chat_id'] = $this->content['chat_id'];
		$this->content['chat_id'] = $chat_id_to;
		$this->method = "forwardMessage";

		return $this;
	}

	function chat_action($type){
		$actions = ['typing', 'upload_photo', 'record_video', 'upload_video', 'record_audio', 'upload_audio', 'upload_document', 'find_location'];
		if(!in_array($type, $actions)){ $type = $actions[0]; } // Default is typing
		$this->content['action'] = $type;
		$this->method = "sendChatAction";
		return $this;
	}

	function kick($user = NULL, $chat = NULL, $keep = FALSE){
				$this->ban($user, $chat, $keep);
		return  $this->unban($user, $chat, $keep);
	}

	function ban($user = NULL, $chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("kickChatMember", $keep, $chat, $user); }
	function unban($user = NULL, $chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("unbanChatMember", $keep, $chat, $user); }
	function leave_chat($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("leaveChat", $keep, $chat); }
	function get_chat($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChat", $keep, $chat); }
	function get_admins($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChatAdministrators", $keep, $chat); }
	function get_member_info($user = NULL, $chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChatMember", $keep, $chat, $user); }
	function get_members_count($chat = NULL, $keep = FALSE){ return $this->_parse_generic_chatFunctions("getChatMembersCount", $keep, $chat); }

	// DEBUG
	/* function get_message($message, $chat = NULL){
		$this->method = 'getMessage';
		if(empty($chat) && !isset($this->context['chat_id'])){
			$this->context['chat_id'] = $this->telegram->chat->id;
		}

		return $this->send();
	} */

	function answer_callback($alert = FALSE, $text = NULL, $id = NULL){
		// Function overload :>
		// $this->text can be empty. (Answer callback with empty response to finish request.)
		if($text == NULL && $id == NULL){
			$text = $this->content['text'];
			if($this->telegram->key == "callback_query"){
				$id = $this->telegram->id;
			}
			if(empty($id)){ return $this; } // HACK
			$this->content['callback_query_id'] = $id;
			$this->content['text'] = $text;
			$this->content['show_alert'] = $alert;
			$this->method = "answerCallbackQuery";
		}

		return $this->send();
	}

	function edit($type){
		if(!in_array($type, ['text', 'message', 'caption', 'keyboard', 'inline', 'markup'])){ return FALSE; }
		if(isset($this->content['text']) && in_array($type, ['text', 'message'])){
			$this->method = "editMessageText";
		}elseif(isset($this->content['caption']) && $type == "caption"){
			$this->method = "editMessageCaption";
		}elseif(isset($this->content['inline_keyboard']) && in_array($type, ['keyboard', 'inline', 'markup'])){
			$this->method = "editMessageReplyMarkup";
		}else{
			return FALSE;
		}

		return $this->send();
	}

	// TODO
	/* function delete($message = NULL, $chat = NULL){
		if(empty($chat) && !isset($this->context['chat_id'])){
			$this->context['chat_id'] = $this->telegram->chat->id;
		}
		if(empty($message) && !isset($this->context['message_id'])){
			$this->context['message_id'] = $this->telegram->id;
		}

		$this->method = "deleteMessage";
		return $this->send();
	} */

	function _push($key, $val){
		$this->content[$key] = $val;
		return $this;
	}

	function _reset(){
		$this->method = NULL;
		$this->content = array();
	}

	private function _url($with_method = FALSE){
		$url = (TG_API_URL .$this->config->item('telegram_bot_id') .':' .$this->config->item('telegram_bot_key') .'/');
		if($with_method){ $url .= $this->method; }
		return $url;
	}

	function send($keep = FALSE){
		if(empty($this->method)){ return FALSE; }
		if(empty($this->content['chat_id'])){ $this->content['chat_id'] = $this->telegram->chat->id; }

		$result = $this->Request($this->method, $this->content);
		if($keep === FALSE){ $this->_reset(); }
		return $result;
	}

	function _parse_generic_chatFunctions($action, $keep, $chat, $user = FALSE){
		$this->method = $action;
		if($user === FALSE){ // No hay user.
			if(empty($chat) && empty($this->chat())){ return FALSE; }
		}else{
			if(empty($user) && empty($chat) && (empty($this->chat()) or empty($this->user()))){ return FALSE; }
		}
		if(!empty($chat)){ $this->content['chat_id'] = $chat; }
		if(!empty($user)){ $this->content['user_id'] = $user; }
		return $this->send($keep);
		// return $this;
	}

	function RequestWebhook($method, $parameters) {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = array();
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		$parameters["method"] = $method;

		header("Content-Type: application/json");
		echo json_encode($parameters);
		return true;
	}

	function exec_curl_request($handle) {
		$response = curl_exec($handle);

		if ($response === false) {
			$errno = curl_errno($handle);
			$error = curl_error($handle);
			error_log("Curl returned error $errno: $error\n");
			curl_close($handle);
			return false;
		}

		$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
		curl_close($handle);

		if ($http_code >= 500) {
		// do not wat to DDOS server if something goes wrong
			sleep(10);
			return false;
		} else if ($http_code != 200) {
			$response = json_decode($response, true);
			error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
			if ($http_code == 401) {
				throw new Exception('Invalid access token provided');
			}
			return false;
		} else {
			$response = json_decode($response, true);
			if (isset($response['description'])) {
				error_log("Request was successfull: {$response['description']}\n");
			}
			$response = $response['result'];
		}

		return $response;
	}

	function Request($method, $parameters) {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = array();
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		foreach ($parameters as $key => &$val) {
		// encoding to JSON array parameters, for example reply_markup
			if (!is_numeric($val) && !is_string($val)) {
				$val = json_encode($val);
			}
		}
		$url = $this->_url() .$method.'?'.http_build_query($parameters);

		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);

		return $this->exec_curl_request($handle);
	}

	function RequestJson($method, $parameters) {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = array();
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		$parameters["method"] = $method;

		$handle = curl_init($this->_url());
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
		curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

		return $this->exec_curl_request($handle);
	}
}

class Telegram extends CI_Model{

	function __construct(){
		parent::__construct();
		$this->send = new __Module_Telegram_Sender();

		$this->user = (object) ['id' => NULL, 'username' => NULL];
		$this->chat = (object) ['id' => NULL, 'username' => NULL];

		$content = file_get_contents("php://input");
		// if(empty($content)){ die(); }
		$this->raw = $content;
		$this->data = json_decode($content, TRUE);
		$this->id = $this->data['update_id'];
		if(isset($this->data['message']) or isset($this->data['edited_message'])){
			$this->key = (isset($this->data['edited_message']) ? "edited_message" : "message");
			if($this->key == "edited_message"){
				$this->is_edit = TRUE;
				$this->edit_date = $this->data[$this->key]['edit_date'];
			}
			$this->message = $this->data[$this->key]['message_id'];
			$this->chat = (object) $this->data[$this->key]['chat'];
			$this->user = (object) $this->data[$this->key]['from'];
			if(isset($this->data[$this->key]['caption'])){
				$this->caption = $this->data[$this->key]['caption'];
			}
			if(isset($this->data[$this->key]['reply_to_message'])){
				$this->has_reply = TRUE;
				$this->reply_is_forward = (isset($this->data[$this->key]['reply_to_message']['forward_from']));
				$this->reply_user = (object) $this->data[$this->key]['reply_to_message']['from'];
				$this->reply = (object) $this->data[$this->key]['reply_to_message'];
			}
			if(isset($this->data[$this->key]['forward_from_chat'])){
				$this->has_forward = TRUE;
			}
			if(isset($this->data[$this->key]['new_chat_participant'])){
				$this->new_user = (object) $this->data[$this->key]['new_chat_participant'];
			}elseif(isset($this->data[$this->key]['left_chat_participant'])){
				$this->new_user = (object) $this->data[$this->key]['left_chat_participant'];
			}
		}elseif(isset($this->data['callback_query'])){
			$this->key = "callback_query";
			$this->id = $this->data[$this->key]['id'];
			$this->message = $this->data[$this->key]['message']['message_id'];
			$this->chat = (object) $this->data[$this->key]['message']['chat'];
			$this->user = (object) $this->data[$this->key]['from'];
			$this->callback = $this->data[$this->key]['data'];
		}
	}

	private $raw;
	private $data = array();
	public $key = NULL;
	public $id = NULL;
	public $message = NULL;
	public $chat = NULL;
	public $user = NULL;
	public $reply = NULL;
	public $new_user = NULL;
	public $reply_user = NULL;
	public $has_reply = FALSE;
	public $has_forward = FALSE;
	public $is_edit = FALSE;
	public $edit_date = NULL;
	public $reply_is_forward = FALSE;
	public $caption = NULL;
	public $callback = FALSE;
	public $send; // Class

	function text_message(){
		if($this->key == "callback_query"){ return $this->data[$this->key]['message']['text']; }
		elseif($this->has_reply){ return $this->data[$this->key]['reply_to_message']['text']; }
		return NULL;
	}

	function text($clean = FALSE){
		$text = @$this->data[$this->key]['text'];
		if($this->key == "callback_query"){
			$text = @$this->data[$this->key]['data'];
			$text = (substr($text, 0, 2) == "T:" ? substr($text, 2) : NULL);
		}
		if($clean === TRUE){ $text = $this->clean('alphanumeric-full-spaces', $text); }
		return $text;
	}

	function text_encoded($clean_quotes = FALSE){
		$t = json_encode($this->text(FALSE));
		if($clean_quotes){ $t = substr($t, 1, -1); }
		return $t;
	}

	// DEPRECATED
	function receive($a, $b = NULL){ return $this->text_contains($a, $b); }

	function text_contains($input, $strpos = NULL){
		if(!is_array($input)){ $input = array($input); }
		$text = strtolower($this->text());
		$text = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $text); // HACK
		foreach($input as $i){
			$j = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $i); // HACK
			if(
				($strpos === NULL and strpos($text, strtolower($j)) !== FALSE) or // Buscar cualquier coincidencia
				($strpos === TRUE and strpos($text, strtolower($j)) === 0) or // Buscar textualmente eso al principio
				($strpos === FALSE and strpos($this->text(), $i) === 0) or // Buscar textualmente al principio + CASE sensitive
				($strpos !== NULL and strpos($text, strtolower($j)) == $strpos) // Buscar por strpos
			){
				return TRUE;
			}
		}
		return FALSE;
	}

	function text_has($input, $next_word = NULL, $position = NULL){
		// A diferencia de text_contains, esto no será valido si la palabra no es la misma.
		// ($input = "fanta") -> fanta OK , fanta! OK , fantasma KO
		if(!is_array($input)){ $input = array($input); }
		// FIXME si algun input contiene un PIPE | , ya me ha jodio. Controlarlo.

		$input = implode("|", $input);
		$input = strtolower($input); // HACK util o molesto en segun que casos?
		$input = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $input); // HACK mas de lo mismo, ayuda o molesta?
		$input = str_replace(["Á", "É", "Í", "Ó", "Ú"], ["A", "E", "I", "O", "U"], $input); // HACK
		$input = str_replace("%20", " ", $input); // HACK web
		$input = strtolower($input);
        $input = str_replace("/", "\/", $input); // CHANGED fix para escapar comandos y demás.

		if(is_bool($next_word)){ $position = $next_word; $next_word = NULL; }
		elseif($next_word !== NULL){
			if(!is_array($next_word)){ $next_word = array($next_word); }
			$next_word = implode("|", $next_word);
			$next_word = strtolower($next_word); // HACK
			$next_word = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $next_word); // HACK
			$next_word = str_replace(["Á", "É", "Í", "Ó", "Ú"], ["A", "E", "I", "O", "U"], $next_word); // HACK
			$next_word = strtolower($next_word); // HACK
            $next_word = str_replace("/", "\/", $next_word); // CHANGED
		}

		if($position === TRUE){
			if($next_word === NULL){ $regex = "^(" .$input .')([\s!.,"]?)'; }
			else{ $regex = "^(" .$input .')([\s!.,"]?)\s(' .$next_word .')([\s!?.,"]?)'; }
		}elseif($position === FALSE){
			if($next_word === NULL){ $regex = "(" .$input .')([!?,."]?)$'; }
			else{ $regex = "(" .$input .')([\s!.,"]?)\s(' .$next_word .')([?!.,"]?)$'; }
		}else{
			if($next_word === NULL){ $regex = "(" .$input .')([\s!?.,"])|(' .$input .')$'; }
			else{ $regex = "(" .$input .')([\s!.,"]?)\s(' .$next_word .')([\s!?.,"])|(' .$input .')([\s!.,"]?)\s(' .$next_word .')([!?.,"]?)$'; }
		}

		$text = strtolower($this->text());
		$text = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $text); // HACK
		$text = str_replace(["Á", "É", "Í", "Ó", "Ú"], ["A", "E", "I", "O", "U"], $text); // HACK
		$text = strtolower($text);
		return preg_match("/" .$regex ."/", $text);
	}

	function text_mention($user = NULL){
		// Incluye users registrados y anónimos.
		// NULL -> decir si hay usuarios mencionados o no (T/F)
		// TRUE -> array [ID => @nombre o nombre]
		// NUM -> decir si el NUM ID usuario está mencionado o no, y si es @nombre, parsear para validar NUM ID.
		// STR -> decir si nombre o @nombre está mencionado o no.
		if(!isset($this->data['message']['entities'])){ return FALSE; }
		$users = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->data['message']['entities'] as $e){
			if($e['type'] == 'text_mention'){
				$users[] = [$e['user']['id'] => substr($text, $e['offset'], $e['length'])];
			}elseif($e['type'] == 'mention'){
				$u = trim(substr($this->text(TRUE), $e['offset'], $e['length'])); // @username
				// $d = $this->send->get_member_info($u); HACK
				$d = FALSE;
				$users[] = ($d === FALSE ? $u : [$d['user']['id'] => $u] );
			}
		}
		if($user == NULL){ return (count($users) > 0 ? $users[0] : FALSE); }
		if($user === TRUE){ return $users; }
		if(is_numeric($user)){
			if($user < count($users)){
				$k = array_keys($users);
				$v = array_values($users);
				return [ $k[$user] => $v[$user] ];
			}
			return in_array($user, array_keys($users));
		}
		if(is_string($user)){ return in_array($user, array_values($users)); }
		return FALSE;
	}

	function text_email($email = NULL){
		// NULL -> saca el primer mail o FALSE.
		// TRUE -> array [emails]
		// STR -> email definido.
		if(!isset($this->data['message']['entities'])){ return FALSE; }
		$emails = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->data['message']['entities'] as $e){
			if($e['type'] == 'email'){ $emails[] = strtolower(substr($text, $e['offset'], $e['length'])); }
		}
		if($email == NULL){ return (count($emails) > 0 ? $emails[0] : FALSE); }
		if($email === TRUE){ return $emails; }
		if(is_string($email)){ return in_array(strtolower($email), $emails); }
		return FALSE;
	}

	function text_command($cmd = NULL, $begin = TRUE){
		// NULL -> saca el primer comando o FALSE.
		// TRUE -> array [comandos]
		// STR -> comando definido.
		// $begin = si es comando inicial
		if(!isset($this->data['message']['entities'])){ return FALSE; }
		$cmds = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		$initbegin = FALSE;
		foreach($this->data['message']['entities'] as $e){
			if($e['type'] == 'bot_command'){ $cmds[] = strtolower(substr($text, $e['offset'], $e['length'])); }
			if($initbegin == FALSE && $e['offset'] == 0){ $initbegin = TRUE; }
		}
		if($cmd == NULL){ return (count($cmds) > 0 ? $cmds[0] : FALSE); }
		if($cmd === TRUE){ return $cmds; }
		if(is_string($cmd)){
			if($cmd[0] != "/"){ $cmd = "/" .$cmd; }
			if(in_array(strtolower($cmd), $cmds) && strpos($cmd, "@") === FALSE){ return TRUE; }
			$name = $this->config->item('telegram_bot_name');
			if($name){
				if($name[0] != "@"){ $name = "@" .$name; }
				$cmd = $cmd.$name;
			}
			return in_array(strtolower($cmd), $cmds);
		}
		return FALSE;
	}

	function text_hashtag($tag = NULL){
		// NULL -> saca el primer hashtag o FALSE.
		// TRUE -> array [hashtags]
		// STR -> hashtag definido.
		if(!isset($this->data['message']['entities'])){ return FALSE; }
		$hgs = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->data['message']['entities'] as $e){
			if($e['type'] == 'hashtag'){ $hgs[] = strtolower(substr($text, $e['offset'], $e['length'])); }
		}
		if($tag == NULL){ return (count($hgs) > 0 ? $hgs[0] : FALSE); }
		if($tag === TRUE){ return $hgs; }
		if(is_string($tag)){
			if($tag[0] != "#"){ $tag = "#" .$tag; }
			return in_array(strtolower($tag), $hgs);
		}
		return FALSE;
	}

	function text_url($cmd = NULL){
		// NULL -> saca la primera URL o FALSE.
		// TRUE -> array [URLs]
		if(!isset($this->data['message']['entities'])){ return FALSE; }
		$cmds = array();
		$text = $this->text(FALSE); // No UTF-8 clean
		foreach($this->data['message']['entities'] as $e){
			if($e['type'] == 'url'){ $cmds[] = substr($text, $e['offset'], $e['length']); }
		}
		if($cmd == NULL){ return (count($cmds) > 0 ? $cmds[0] : FALSE); }
		if($cmd === TRUE){ return $cmds; }
		return FALSE;
	}

	function last_word($clean = FALSE){
		$text = explode(" ", $this->text());
		if($clean === TRUE){ $clean = 'alphanumeric-accent'; }
		return $this->clean($clean, array_pop($text));
	}

	function words($position = NULL, $amount = 1, $filter = FALSE){ // Contar + recibir argumentos
		if($position === NULL){
			return count(explode(" ", $this->text()));
		}elseif($position === TRUE){
			return explode(" ", $this->text());
		}elseif(is_numeric($position)){
			if($amount === TRUE){ $filter = 'alphanumeric'; $amount = 1; }
			elseif(is_string($amount)){ $filter = $amount; $amount = 1; }
			$t = explode(" ", $this->text());
			$a = $position + $amount;
			$str = '';
			for($i = $position; $i < $a; $i++){
				$str .= $t[$i] .' ';
			}
			if($filter !== FALSE){ $str = $this->clean($filter, $str); }
			return trim($str);
		}
	}

	function clean($pattern = 'alphanumeric-full', $text = NULL){
		$pats = [
			'number' => '/^[0-9]+/',
			'number-calc' => '/^([+-]?)\d+(([\.,]?)\d+?)/',
			'alphanumeric' => '/[^a-zA-Z0-9]+/',
			'alphanumeric-accent' => '/[^a-zA-Z0-9áéíóúÁÉÍÓÚ]+/',
			'alphanumeric-symbols-basic' => '/[^a-zA-Z0-9\._\-]+/',
			'alphanumeric-full' => '/[^a-zA-Z0-9áéíóúÁÉÍÓÚ\._\-]+/',
			'alphanumeric-full-spaces' => '/[^a-zA-Z0-9áéíóúÁÉÍÓÚ\.\s_\-]+/',
		];
		if(empty($text)){ $text = $this->text(); }
		if($pattern == FALSE){ return $text; }
		if(!isset($pats[$pattern])){ return FALSE; }
		return preg_replace($pats[$pattern], "", $text);
	}

	function is_chat_group(){ return isset($this->chat->type) && in_array($this->chat->type, ["group", "supergroup"]); }
	function data_received($expect = NULL){
		$data = ["migrate_to_chat_id", "migrate_from_chat_id", "new_chat_participant", "left_chat_participant", "new_chat_member", "left_chat_member", "reply_to_message",
			"text", "audio", "document", "photo", "voice", "location", "contact"];
		foreach($data as $t){
			if(isset($this->data["message"][$t])){
				if($expect == NULL or $expect == $t){ return $t; }
			}
		}
		return FALSE;
	}

	function forward_type($expect = NULL){
		if(!$this->has_forward){ return FALSE; }
		$type = $this->data['message']['forward_from_chat']['type'];
		if($expect !== NULL){ return (strtolower($expect) == $type); }
		return $type;
	}

	function is_bot($user = NULL){
		if($user === NULL){ $user = $this->user->username; }
		elseif($user === TRUE && $this->has_reply){ $user = $this->reply_user->username; }
		return (!empty($user) && substr(strtolower($user), -3) == "bot");
	}

	// NOTE: Solo funcionará si el bot está en el grupo.
	function user_in_chat($user, $chat = NULL, $object = FALSE){
		if($chat === TRUE){ $object = TRUE; $chat = NULL; }
		if(empty($chat)){ $chat = $this->chat->id; }
		$info = $this->send->get_member_info($user, $chat);
		$ret = ($object ? (object) $info : $info);
		return ( ($info === FALSE or in_array($info['status'], ['left', 'kicked'])) ? FALSE : $ret );
	}

	function grouplink($text, $url = FALSE){
		$link = "https://telegram.me/";
		if($text[0] != "@" and strlen($text) == 22){
			$link .= "joinchat/$text";
		}else{
			if($url && $text[0] == "@"){ $link .= substr($text, 1); }
			else{ $link = $text; }
		}
		return $link;
	}

	function answer_if_callback($text = "", $alert = FALSE){
		if($this->key != "callback_query"){ return FALSE; }
		return $this->send
			->text($text)
		->answer_callback($alert);
	}

	function dump($json = FALSE){ return($json ? json_encode($this->data) : $this->data); }

	function get_admins($chat = NULL, $full = FALSE){
		$ret = array();
		$admins = $this->send->get_admins($chat);
		if(!empty($admins)){
			foreach($admins as $a){	$ret[] = $a['user']['id']; }
		}
		return ($full == TRUE ? $admins : $ret);
	}

	function data($type, $object = TRUE){
		$accept = ["text", "audio", "video", "document", "photo", "voice", "location", "contact"];
		$type = strtolower($type);
		if(in_array($type, $accept) && isset($this->data['message'][$type])){
			if($object){ return (object) $this->data['message'][$type]; }
			return $this->data['message'][$type];
		}
		return FALSE;
	}

	function _generic_content($key, $object = NULL, $rkey = 'file_id'){
		if(!isset($this->data['message'][$key])){ return FALSE; }
		$data = $this->data['message'][$key];
		if(empty($data)){ return FALSE; }
		if($object === TRUE){ return (object) $data; }
		elseif($object === FALSE){ return array_values($data); }

		if(in_array($key, ["document", "location"])){ return $data; }
		return $data[$rkey];
	}

	function document($object = TRUE){ return $this->_generic_content('document', $object); }
	function location($object = TRUE){ return $this->_generic_content('location', $object); }
	function voice($object = NULL){ return $this->_generic_content('voice', $object); }
 	function video($object = NULL){ return $this->_generic_content('video', $object); }
	function sticker($object = NULL){ return $this->_generic_content('sticker', $object); }

	function gif(){
		$gif = $this->document(TRUE);
		if(!$gif or !in_array($gif->mime_type, ["video/mp4"])){ return FALSE; }
		// TODO gif viene por size?
		return $gif->file_id;
	}

	function photo($retall = FALSE, $sel = -1){
		if(!isset($this->data['message']['photo'])){ return FALSE; }
		$photos = $this->data['message']['photo'];
		if(empty($photos)){ return FALSE; }
		// Select last file or $sel_id
		$sel = ($sel == -1 or ($sel > count($photos) - 1) ? (count($photos) - 1) : $sel);
		if($retall === FALSE){ return $photos[$sel]['file_id']; }
		elseif($retall === TRUE){ return (object) $photos[$sel]; }
	}

	function contact($self = FALSE, $object = TRUE){
		$contact = $this->data['message']['contact'];
		if(empty($contact)){ return FALSE; }
		if(
			$self == FALSE or
			($self == TRUE && $this->user->id == $contact['user_id'])
		){
			if($object == TRUE){ return (object) $contact; }
			return $contact;
		}elseif($self == TRUE){
			return FALSE;
		}
	}

	function pinned_message($content = NULL){
		if(!isset($this->data['message']['pinned_message'])){ return FALSE; }
		$pin = $this->data['message']['pinned_message'];
		if($content === NULL){
			$user = (object) $pin['from'];
			$chat = (object) $pin['chat'];
			$data = $pin['text'];
			return (object) array(
				'user' => $user,
				'chat' => $chat,
				'data' => $data
			);
		}
		elseif($content === TRUE){ return $pin['text']; }
		elseif($content === FALSE){  }
	}

	function download($file_id){
		$data = $this->send->get_file($file_id);
		$url = "https://api.telegram.org/file/bot" .$this->config->item('telegram_bot_id') .":" .$this->config->item('telegram_bot_key') ."/";
		$file = $url .$data['file_path'];
		return $file;
	}

	function emoji($text, $reverse = FALSE){
		$emoji = [
			'kiss' => '\ud83d\ude18',
			'heart' => '\u2764\ufe0f',
			'heart-blue' => '\ud83d\udc99',
			'heart-green' => '\ud83d\udc9a',
			'heart-yellow' => '\ud83d\udc9b',
			'laugh' => '\ud83d\ude02',
			'tongue' => '\ud83d\ude1b',
			'smiley' => '\ud83d\ude00',
			'happy' => '\ud83d\ude19',
			'die' => '\ud83d\ude35',
			'cloud' => '\u2601\ufe0f',
			'gun' => '\ud83d\udd2b',
			'green-check' => '\u2705',
			'antenna' => '\ud83d\udce1',
			'spam' => '\ud83d\udce8',
			'laptop' => '\ud83d\udcbb',
			'pin' => '\ud83d\udccd',
			'home' => '\ud83c\udfda',
			'map' => '\ud83d\uddfa',
			'candy' => '\ud83c\udf6c',
			'spiral' => '\ud83c\udf00',
			'tennis' => '\ud83c\udfbe',
			'key' => '\ud83d\udddd',
			'door' => '\ud83d\udeaa',
			'frog' => '\ud83d\udc38',

			'forbid' => '\u26d4\ufe0f',
			'times' => '\u274c',
			'warning' => '\u26a0\ufe0f',
			'banned' => '\ud83d\udeab',
			'star' => '\u2b50\ufe0f',
			'star-shine' => '\ud83c\udf1f',
			'mouse' => '\ud83d\udc2d',
			'multiuser' => '\ud83d\udc65',
			'robot' => '\ud83e\udd16',
			'fire' => '\ud83d\udd25',
			'collision' => '\ud83d\udca5',
			'joker' => '\ud83c\udccf',
			'exclamation-red' => '\u2757\ufe0f',
			'question-red' => '\u2753',
			'exclamation-grey' => '\u2755',
			'question-grey' => '\u2754',

			'1' => '1\u20e3',
			'2' => '2\u20e3',
			'3' => '3\u20e3',
			'4' => '4\u20e3',
			'5' => '5\u20e3',
			'6' => '6\u20e3',
			'7' => '7\u20e3',
			'8' => '8\u20e3',
			'9' => '9\u20e3',
			'0' => '0\u20e3',

			'triangle-left' => '\u25c0\ufe0f',
			'triangle-up' => '\ud83d\udd3c',
			'triangle-right' => '\u25b6\ufe0f',
			'triangle-down' => '\ud83d\udd3d',
			'arrow-left' => '\u2b05\ufe0f',
			'arrow-up' => '\u2b06\ufe0f',
			'arrow-right' => '\u27a1\ufe0f',
			'arrow-down' => '\u2b07\ufe0f',
			'arrow-up-left' => '\u2196\ufe0f',
			'arrow-up-right' => '\u2197\ufe0f',
			'arrow-down-right' => '\u2198\ufe0f',
			'arrow-down-left ' => '\u2199\ufe0f',

			'minus' => '\u2796',
			'plus' => '\u2795',
			'multiply' => '\u2716\ufe0f',
			'search-left' => '\ud83d\udd0d',
			'search-right' => '\ud83d\udd0e',
		];

		$search = [
			'kiss' => [':kiss:', ':*'],
			'heart' => [':heart-red:', '<3', ':heart:', ':love:'],
			'heart-blue' => [':heart-blue:'],
			'heart-green' => [':heart-green:'],
			'heart-yellow' => [':heart-yellow:'],
			'smiley' => [':smiley:', ':>', ']:D'],
			'happy' => [':happy:', '^^'],
			'laugh' => [':lol:', ":'D"],

			'tongue' => [':tongue:', '=P'],
			'die' => [':die:', '>X'],
			'cloud' => [':cloud:'],
			'gun' => [':gun:'],
			'door' => [':door:'],

			'1' => [':1:'],
			'2' => [':2:'],
			'3' => [':3:'],
			'4' => [':4:'],
			'5' => [':5:'],
			'6' => [':6:'],
			'7' => [':7:'],
			'8' => [':8:'],
			'9' => [':9:'],
			'0' => [':0:'],

			'forbid' => [':forbid:'],
			'times' => [':times:'],
			'banned' => [':banned:'],
			'star' => [':star:'],
			'star-shine' => [':star-shine:'],
			'mouse' => [':mouse:'],
			'robot' => [':robot:'],
			'multiuser' => [':multiuser:'],
			'fire' => [':fire:'],
			'collision' => [':collision:'],
			'joker' => [':joker:'],
			'antenna' => [':antenna:'],
			'laptop' => [':laptop:'],
			'spam' => [':spam:'],
			'pin' => [':pin:'],
			'home' => [':home:'],
			'map' => [':map:'],
			'candy' => [':candy:'],
			'spiral' => [':spiral:'],
			'tennis' => [':tennis:'],
			'key' => [':key:'],
			'frog' => [':frog:'],
			'green-check' => [':ok:', ':green-check:'],
			'warning' => [':warning:'],
			'exclamation-red' => [':exclamation-red:'],
			'question-red' => [':question-red:'],
			'exclamation-grey' => [':exclamation-grey:'],
			'question-grey' => [':question-grey:'],
			'triangle-left' => [':triangle-left:'],
			'triangle-up' => [':triangle-up:'],
			'triangle-right' => [':triangle-right:'],
			'triangle-down' => [':triangle-down:'],
			'arrow-left' => [':arrow-left:'],
			'arrow-up' => [':arrow-up:'],
			'arrow-right' => [':arrow-right:'],
			'arrow-down' => [':arrow-down:'],
			'arrow-up-left' => [':arrow-up-left:'],
			'arrow-up-right' => [':arrow-up-right:'],
			'arrow-down-right' => [':arrow-down-right:'],
			'arrow-down-left ' => [':arrow-down-left :'],
			'minus' => [':minus:'],
			'plus' => [':plus:'],
			'multiply' => [':multiply:'],
			'search-left' => [':search-left:'],
			'search-right' => [':search-right:'],
		];

		if(!$reverse){
			foreach($search as $n => $k){
				$text = str_replace($k, $emoji[$n], $text);
			}
			$text = str_replace("\n", '\n', $text); // FIX testing
			return json_decode('"' . $text .'"', TRUE);
		}
		$text = json_encode($text); // HACK decode UTF -> ASCII para buscar y reemplazar
		foreach($emoji as $n => $u){
			$text = str_replace($u, $search[$n][0], $text);
		}
		$text = json_decode($text);
		return substr(json_encode($text), 1, -1); // No comas
	}
}

?>
