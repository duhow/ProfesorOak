<?php

define('ANALYTICS_URL', 'http://www.google-analytics.com/collect');

class Analytics extends CI_Model{
	private $content = array();
	private $_version = 1;

	function __construct(){
		parent::__construct();
		$this->_reset();
	}

	function tracking($data = NULL){
		$this->set('tid', $data);
		return $this;
	}

	function client($data = NULL){
		// GET or SET
		/* $r = ["8","9","A","B"];
		$data = sha1($data);
		$data[12] = 4;
		$data[16] = $r[mt_rand(0, count($r) - 1)];
		$data = strtolower($data);
		$hash = substr($data, 0, 8) ."-" .substr($data, 8, 4) ."-" .substr($data, 12, 4) ."-" .substr($data, 16, 4) ."-" .substr($data, 20, 12); */
		$this->set('cid', $data);
		return $this;
	}

	function source($data = NULL){
		$this->set('ds', $data);
		return $this;
	}

	function country($data = NULL){
		$this->set('geoid', $data);
		return $this;
	}

	function campaign($name = NULL, $source = NULL, $medium = NULL, $keyword = NULL, $content = NULL, $id = NULL){
		$this
			->set('cn', $name, TRUE)
			->set('cs', $source, TRUE)
			->set('cm', $medium, TRUE)
			->set('ck', $keyword, TRUE)
			->set('cc', $content, TRUE)
			->set('ci', $id, TRUE);
		return $this;
	}

	function ads($adwords = NULL, $display = NULL){
		$this
			->set('gclid', $adwords, TRUE)
			->set('dclid', $display, TRUE);
		return $this;
	}

	function screen($resolution, $window = NULL, $bits = NULL){
		$this
			->set('sr', $resolution)
			->set('vp', $window, TRUE)
			->set('sd', $bits, TRUE);
		return $this;
	}

	function pageview($host, $page, $title){
		$this
			->set('t', 'pageview')
			->set('dh', $host)
			->set('dp', $page)
			->set('dt', $title);
		return $this->send();
	}

	function screenview($appname, $screen = NULL, $version = NULL, $id = NULL, $installid = NULL){
		$this
			->set('t', "screenview")
			->set('an', $appname)
			->set('av', $version, TRUE) // (4.2.0)
			->set('aid', $id, TRUE) // (com.foo.test)
			->set('aiid', $installid, TRUE) // (com.android.vending)
			->set('cd', $screen); // (Home)
		return $this->send();
	}

	function timing($category, $variable, $militime, $label = NULL){
		$this
			->set('t', "timing")
			->set('utc', $category)
			->set('utv', $variable)
			->set('utt', $militime)
			->set('utl', $label, TRUE);
		return $this->send();
	}

	function event($category, $action, $label = NULL, $value = NULL){
		$this
			->set('t', "event")
			->set('ec', $category)
			->set('ea', $action)
			->set('el', $label, TRUE)
			->set('ev', $value, TRUE);
		return $this->send();
	}

	function social($action, $network, $target){
		$this
			->set('t', "social")
			->set('sa', $action) // (like)
			->set('sn', $network) // (facebook)
			->set('st', $target); // (/home)
		return $this->send();
	}

	function exception($description, $fatal = FALSE){
		if($fatal === TRUE){ $fatal = 1; }
		$this
			->set('t', "exception")
			->set('exd', $description)
			->set('exf', $fatal); // error fatal?
		return $this->send();
	}

	function user_anonymous(){
		$this->set('aip', 1);
		return $this;
	}

	function user_override($ip, $useragent){
		$this
			->set('uip', $ip, TRUE)
			->set('ua', $useragent, TRUE);
		return $this;
	}

	function non_interaction($value = NULL){
		$this->set('ni', $value);
		return $this;
	}

	function set($key, $value, $optional = FALSE){
		if(($optional && $value !== NULL) or !$optional){ $this->content[$key] = $value; }
		return $this;
	}

	function _reset(){
		$this->content['v'] = $this->_version;
		$tid = $this->config->item("analytics_id");
		if(!empty($tid)){ $this->tracking($tid); }
		if(isset($this->telegram->user->id)){ $this->client($this->telegram->user->id); }
		$this
			->user_anonymous()
			->source('app');
	}

	function send(){
		if(!isset($this->content['tid']) or empty($this->content['tid'])){ return FALSE; }
		if(!isset($this->content['cid']) or empty($this->content['cid'])){ $this->client(mt_rand(1000, 1000000)); }
		$data = http_build_query($this->content);

		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, ANALYTICS_URL);
		curl_setopt($ch,CURLOPT_POST, TRUE);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE);

		//execute post
		$result = curl_exec($ch);
		curl_close($ch);
		$this->_reset();
		return $result; // Pixel
	}

}
