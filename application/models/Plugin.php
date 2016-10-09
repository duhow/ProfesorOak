<?php
class Plugin extends CI_Model{

	function __construct(){
		parent::__construct();
	}

	private $loaded = array();

	function is_loaded($name){
		$name = strtolower(str_replace(".php", "", $name));
		return in_array(strtolower($name), $this->loaded);
	}

	function dump(){
		return $this->loaded;
	}

	function load_all($base = FALSE){
		$folder = APPPATH ."plugins/";
		$files = array();

		foreach(scandir($folder) as $f){
			$f = strtolower($f);
			if(strlen($f) < 4){ continue; }
			if(substr($f, -4) == '.php'){
				if($base === TRUE or strtolower($base) == "base"){
					if(substr($f, 0, 4) == "base"){ $files[] = $f; }
				}else{
					$files[] = $f;
				}
			}
		}

		foreach($files as $f){
			if(!$this->is_loaded($f)){ $this->load($f); }
		}
	}

	function load($name){
		$file = APPPATH ."plugins/" .strtolower($name);
		if(substr($file, -4) != ".php"){ $file .= ".php"; }

		if(file_exists($file)){
			if(is_readable($file)){
				$telegram = $this->telegram;
				$pokemon = $this->pokemon;

				include_once $file;

				$name = strtolower(str_replace(".php", "", $name));
				$this->loaded[] = $name;

				return TRUE;
			}
		}

		return FALSE;
	}

}
