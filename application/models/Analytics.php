<?php

define('ANALYTICS_URL', 'http://www.google-analytics.com/collect');

class Analytics extends CI_Model{
	private $content = array();
	private $_version = 1;

	function __construct(){
		parent::__construct();
		$this->content['version'] = $this->_version;
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
		// dh = $host
		// dp = $page
		// dt = $title
		return $this;
	}

	function screenview($appname, $version, $id, $installid, $screen){
		// t = screenview
		// an = $appname
		// av = $version (4.2.0)
		// aid = $id (com.foo.test)
		// aiid = $installid (com.android.vending)
		// cd = $screen (Home)
		return $this;
	}

	function timing($category, $variable, $militime, $label){
		// utc = $category
		// utv = $variable
		// utt = $militime (1000 = 1s)
		// utl = $label
		return $this;
	}

	function event($category, $action, $label = NULL, $value = NULL){
		$this->content['t'] = "event";
		$this->content['ec'] = $category;
		$this->content['ea'] = $action;
		if($label !== NULL){ $this->content['el'] = $label; }
		if($value !== NULL){ $this->content['ev'] = $value; }
		return $this;
	}

	function social($action, $network, $target){
		// t = social
		// sa = action (like)
		// sn = newtork (facebook)
		// st = target (/home)
		return $this;
	}

	function exception($description, $fatal = FALSE){
		// t = exception
		// exd = description
		// exf = fatal error? (boolean 1 - 0)
		return $this;
	}

	function user_override($ip, $useragent){
		// uip = $ip
		// ua = $useragent
		return $this;
	}

	function non_interaction($value = NULL){
		// ni = $value
		return $this;
	}

	function send(){
		if(!isset($this->content['tid']) or empty($this->content['tid'])){
			$tid = $this->config->item("analytics_id");
			if(empty($tid)){ return FALSE; }
			$this->content['tid'] = $tid;
		}
		if(!isset($this->content['cid']) or empty($this->content['cid'])){ $this->content['cid'] = mt_rand(0, 1000000); }
		$data = http_build_query($this->content);

		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, ANALYTICS_URL);
		curl_setopt($ch,CURLOPT_POST, TRUE);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $data);

		//execute post
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

}
