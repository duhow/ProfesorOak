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

class __Module_Telegram_Sender extends CI_Model{
	private $content = array();
	private $method = NULL;
	private $_keyboard;

	function __construct(){
		parent::__construct();
		$this->_keyboard = new __Module_Telegram_Keyboard();
	}

	function chat($id = NULL){
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
		$this->content['message_id'] = $id;
		return $this;
	}

	function file($type, $file, $caption = NULL, $keep = FALSE){
		if(!in_array($type, ["photo", "audio", "voice", "document", "sticker", "video"])){ return FALSE; }

		$this->method = "send" .ucfirst($type);
		if(file_exists(realpath($file))){
			$this->content[$type] = new CURLFile(realpath($file));
		}else{
			$this->content[$type] = $file;
		}
		if($caption !== NULL){ $this->content['caption'] = $caption; }

		if(empty($this->content['chat_id'])){ $this->content['chat_id'] = $this->telegram->chat->id; }

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:multipart/form-data"
		));
		curl_setopt($ch, CURLOPT_URL, $this->_url(TRUE));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->content);
		$output = curl_exec($ch);

		if($keep === FALSE){ $this->_reset(); }
		return $output;
		// return $this;
	}

	function location($lat, $lon){
		$this->content['latitude'] = $lat;
		$this->content['longitude'] = $lon;
		$this->method = "sendLocation";
		return $this;
	}

	function dump(){
		echo $this->method; var_dump($this->content);
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
	function inline_keyboard(){
		// TODO
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
		elseif($message_id === FALSE && $this->telegram->has_reply){ $message_id = $this->telegram->reply->message_id; }
		$this->content['reply_to_message_id'] = $message_id;
		return $this;
	}

	function forward_to($chat_id_to){
		if(empty($this->content['chat_id']) or empty($this->content['message_id'])){ return FALSE; }
		$this->content['from_chat_id'] = $this->content['chat_id'];
		$this->content['chat_id'] = $chat_id_to;
		$this->method = "fordwardMessage";

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

	function ban($user = NULL, $chat = NULL, $keep = FALSE){
		return $this->_parse_generic_chatFunctions("kickChatMember", $keep, $chat, $user);
	}

	function unban($user = NULL, $chat = NULL, $keep = FALSE){
		return $this->_parse_generic_chatFunctions("unbanChatMember", $keep, $chat, $user);
	}

	function leave_chat($chat = NULL, $keep = FALSE){
		return $this->_parse_generic_chatFunctions("leaveChat", $keep, $chat);
	}

	function get_chat($chat = NULL, $keep = FALSE){
		return $this->_parse_generic_chatFunctions("getChat", $keep, $chat);
	}

	function get_admins($chat = NULL, $keep = FALSE){
		return $this->_parse_generic_chatFunctions("getChatAdministrators", $keep, $chat);
	}

	function get_member_info($user = NULL, $chat = NULL, $keep = FALSE){
		// stats -> member, left, kicked, administrator, creator
		return $this->_parse_generic_chatFunctions("getChatMember", $keep, $chat, $user);
	}

	function get_members_count($chat = NULL, $keep = FALSE){
		return $this->_parse_generic_chatFunctions("getChatMembersCount", $keep, $chat);
	}

	function edit($type){
		if(!in_array($type, ['text', 'message', 'caption'])){ return FALSE; }
		if(isset($this->content['text']) && in_array($type, ['text', 'message'])){
			$this->method = "editMessageText";
		}elseif(isset($this->content['caption']) && $type == "caption"){
			$this->method = "editMessageCaption";
		}else{
			return FALSE;
		}

		return $this->send();
	}

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

		$content = file_get_contents("php://input");
		// if(empty($content)){ die(); }
		$this->raw = $content;
		$this->data = json_decode($content, TRUE);
		$this->id = $this->data['update_id'];
		$this->message = $this->data['message']['message_id'];
		$this->chat = (object) $this->data['message']['chat'];
		$this->user = (object) $this->data['message']['from'];
		if(isset($this->data['message']['caption'])){
			$this->caption = $this->data['message']['caption'];
		}
		if(isset($this->data['message']['reply_to_message'])){
			$this->has_reply = TRUE;
			$this->reply_is_forward = (isset($this->data['message']['reply_to_message']['forward_from']));
			$this->reply_user = (object) $this->data['message']['reply_to_message']['from'];
			$this->reply = (object) $this->data['message']['reply_to_message'];
		}
		if(isset($this->data['message']['new_chat_participant'])){
			$this->new_user = (object) $this->data['message']['new_chat_participant'];
		}elseif(isset($this->data['message']['left_chat_participant'])){
			$this->new_user = (object) $this->data['message']['left_chat_participant'];
		}
	}

	private $raw;
	private $data = array();
	public $id = NULL;
	public $message = NULL;
	public $chat = NULL;
	public $user = NULL;
	public $reply = NULL;
	public $new_user = NULL;
	public $reply_user = NULL;
	public $has_reply = FALSE;
	public $reply_is_forward = FALSE;
	public $caption = NULL;
	public $send; // Class

	function text($clean = FALSE){
		$text = @$this->data['message']['text'];
		if($clean === TRUE){ $text = $this->clean('alphanumeric-full-spaces', $text); }
		return $text;
	}

	function text_encoded($clean_quotes = FALSE){
		$t = json_encode($this->text(FALSE));
		if($clean_quotes){ $t = substr($t, 1, -1); }
		return $t;
	}

	// DEPRECATED
	function receive($a, $b = NULL, $c = NULL){
		return $this->text_contains($a, $b, $c);
	}

	function text_contains($input, $next_word = NULL, $strpos = NULL){
		if(!is_array($input)){ $input = array($input); }
		// TODO implementar $next_word
		foreach($input as $i){
			if(
				($strpos === NULL and strpos(strtolower($this->text()), strtolower($i)) !== FALSE) or // Buscar cualquier coincidencia
				($strpos === TRUE and strpos(strtolower($this->text()), strtolower($i)) === 0) or // Buscar textualmente eso al principio
				($strpos === FALSE and strpos($this->text(), $i) === 0) or // Buscar textualmente al principio + CASE sensitive
				($strpos !== NULL and strpos(strtolower($this->text()), strtolower($i)) == $strpos) // Buscar por strpos
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
        $input = str_replace("/", "\/", $input); // CHANGED fix para escapar comandos y demás.

		if(is_bool($next_word)){ $position = $next_word; $next_word = NULL; }
		elseif($next_word !== NULL){
			if(!is_array($next_word)){ $next_word = array($next_word); }
			$next_word = implode("|", $next_word);
			$next_word = strtolower($next_word); // HACK
			$next_word = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $next_word); // HACK
            $input = str_replace("/", "\/", $input); // CHANGED
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
		return preg_match("/" .$regex ."/", $text);
	}

	function last_word($clean = FALSE){
		$text = explode(" ", $this->text());
		if($clean === TRUE){ $clean = 'alphanumeric-accent'; }
		return $this->clean($clean, array_pop($text));
	}

	function words($position = NULL, $amount = 1, $filter = FALSE){ // Contar + recibir argumentos
		if($position === NULL){
			return count(explode(" ", $this->text()));
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

	function is_chat_group(){ return in_array($this->chat->type, ["group", "supergroup"]); }
	function data_received($expect = NULL){
		$data = ["new_chat_participant", "left_chat_participant", "new_chat_member", "left_chat_member", "reply_to_message",
			"text", "audio", "document", "photo", "voice", "location", "contact"];
		foreach($data as $t){
			if(isset($this->data["message"][$t])){
				if($expect == NULL or $expect == $t){ return $t; }
			}
		}
		return FALSE;
	}

	function is_bot($user = NULL){
		if($user === NULL){ $user = $this->user->username; }
		return (!empty($user) && substr(strtolower($user), -3) == "bot");
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

	function document(){}

	function photo($retall = FALSE, $id = -1){
		$photos = $this->data['message']['photo'];
		if(empty($photos)){ return FALSE; }
		$photo = NULL;
		if($id == -1 or $id > count($photos) - 1){ $photo = array_pop($photos); }
		else{ $photo = $photos[$id]; }

		if($retall == FALSE){ return $photo['file_id']; }
		elseif($retall == TRUE){ return (object) $photo; }
	}

	function location($object = TRUE){
		$loc = $this->data['message']['location'];
		if(empty($loc)){ return FALSE; }
		if($object == TRUE){ return (object) $loc; }
		return $loc;
	}

	function contact($same = FALSE, $object = TRUE){
		$contact = $this->data['message']['contact'];
		if(empty($contact)){ return FALSE; }
		if(
			$same == FALSE or
			($same == TRUE && $this->user->id == $contact['user_id'])
		){
			if($object == TRUE){ return (object) $contact; }
			return $contact;
		}elseif($same == TRUE){
			return FALSE;
		}
	}

	function sticker($object = FALSE){
		if(!isset($this->data['message']['sticker'])){ return FALSE; }
		if($object === TRUE){ return (object) $this->data['message']['sticker']; }
		return $this->data['message']['sticker']['file_id'];
	}
	function download($file){
		// TODO
	}

	function emoji($text, $reverse = FALSE){
		$emoji = [
			'kiss' => "\ud83d\ude18",
			'heart' => "\u2764\ufe0f",
			'heart-blue' => "\ud83d\udc99",
			'heart-green' => "\ud83d\udc9a",
			'heart-yellow' => "\ud83d\udc9b",
			'laugh' => "\ud83d\ude02",
			'tongue' => "\ud83d\ude1b",
			'smiley' => "\ud83d\ude00",
			'happy' => "\ud83d\ude19",
			'die' => "\ud83d\ude35",
			'cloud' => "\u2601\ufe0f",
			'gun' => "\ud83d\udd2b",
			'green-check' => "\u2705",
			'antenna' => "\ud83d\udce1",
			'spam' => "\ud83d\udce8",
			'laptop' => "\ud83d\udcbb",
			'pin' => "\ud83d\udccd",
			'home' => "\ud83c\udfda",

			'forbid' => "\u26d4\ufe0f",
			'times' => "\u274c",
			'warning' => "\u26a0\ufe0f",
			'banned' => "\ud83d\udeab",
			'star' => "\u2b50\ufe0f",
			'star-shine' => "\ud83c\udf1f",
			'mouse' => "\ud83d\udc2d",
			'multiuser' => "\ud83d\udc65",
			'robot' => "\ud83e\udd16",
			'fire' => "\ud83d\udd25",
			'collision' => "\ud83d\udca5",
			'joker' => "\ud83c\udccf",
			'exclamation-red' => "\u2757\ufe0f",
			'question-red' => "\u2753",
			'exclamation-grey' => "\u2755",
			'question-grey' => "\u2754",

			'triangle-left' => "\u25c0\ufe0f",
			'triangle-up' => "\ud83d\udd3c",
			'triangle-right' => "\u25b6\ufe0f",
			'triangle-down' => "\ud83d\udd3d",
			'arrow-left' => "\u2b05\ufe0f",
			'arrow-up' => "\u2b06\ufe0f",
			'arrow-right' => "\u27a1\ufe0f",
			'arrow-down' => "\u2b07\ufe0f",
			'arrow-up-left' => "\u2196\ufe0f",
			'arrow-up-right' => "\u2197\ufe0f",
			'arrow-down-right' => "\u2198\ufe0f",
			'arrow-down-left ' => "\u2199\ufe0f",

			'minus' => "\u2796",
			'plus' => "\u2795",
			'multiply' => "\u2716\ufe0f",
			'search-left' => "\ud83d\udd0d",
			'search-right' => "\ud83d\udd0e",
		];

		$search = [
			'kiss' => [':kiss:', ':*'],
			'heart' => [':heart-red:', '<3', ':heart:', ':love:'],
			'heart-blue' => [':heart-blue:'],
			'heart-green' => [':heart-green:'],
			'heart-yellow' => [':heart-yellow:'],
			'smiley' => [":smiley:", ":>", "]:D"],
			'happy' => [":happy:", "^^"],
			'laugh' => [':lol:', ":'D"],

			'tongue' => [":tongue:", "=P"],
			'die' => [":die:", ">X"],
			'cloud' => [":cloud:"],
			'gun' => [":gun:"],

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
