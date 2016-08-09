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
		$this->content['tid'] = $data;
		return $this;
	}

	function client($data = NULL){
		// GET or SET
		$this->content['cid'] = $data;
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

	function screenview($appname, $version, $id, $installid, $screen = NULL){
		$this
			->set('t', "screenview")
			->set('an', $appname)
			->set('av', $version) // (4.2.0)
			->set('aid', $id) // (com.foo.test)
			->set('aiid', $installid) // (com.android.vending)
			->set('cd', $screen, TRUE); // (Home)
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
		if(!empty($tid)){ $this->content['tid'] = $tid; }
		if(isset($this->telegram->user->id)){ $this->content['cid'] = $this->telegram->user->id; }
	}

	function send(){
		if(!isset($this->content['tid']) or empty($this->content['tid'])){ return FALSE; }
		if(!isset($this->content['cid']) or empty($this->content['cid'])){ $this->content['cid'] = mt_rand(0, 1000000); }
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
