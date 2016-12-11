<?php
class Plugin extends CI_Model{

	function __construct(){
		parent::__construct();
	}

	private $loaded = array();
	private $base_folder = APPPATH ."plugins/";

	function is_loaded($name){
		$name = strtolower(str_replace(".php", "", $name));
		return in_array(strtolower($name), $this->loaded);
	}

	function dump(){ return $this->loaded; }

	function enable($name){ return $this->chmod_file($name, TRUE); }
	function disable($name){ return $this->chmod_file($name, FALSE); }

	function chmod_file($name, $read){
		$file = $this->file($name);
		if($this->exists($file)){
			$perm = $this->permissions($file);
			$chmod = NULL;
			for($i = 0; $i < strlen($perm); $i++){
				$chmod .= $this->_chmod($perm[$i], $read);
			}
			return chmod($file, octdec($chmod));
		}
		return NULL;
	}

	function _chmod($num, $read = TRUE){
		if($num == 0){ return "0"; }
		if($read){
			if(in_array($num, [1,2,3])){ $num = $num + 4; }
		}else{
			if(in_array($num, [4,5,6,7])){ $num = $num - 4; }
		}
		return $num;
	}

	function permissions($file){
		return substr(sprintf('%o', fileperms($file)), -4);
	}

	function file($name){
		if(substr($name, 0, 1) == "/"){ return $name; }
		$file = $this->base_folder .strtolower($name);
		if(substr($file, -4) != ".php"){ $file .= ".php"; }

		return $file;
	}

	function exists($name){
		return file_exists($this->file($name));
	}

	function load_all($base = FALSE){
		$files = array();

		foreach(scandir($this->base_folder) as $f){
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

		foreach($files as $i => $f){
			if(strpos($f, "last") !== FALSE){
				$files[] = $f;
				unset($files[$i]);
			}
		}

		foreach($files as $f){
			if(!$this->is_loaded($f)){
				$load = $this->load($f);
				if($load === -1){ die(); }
			}
		}
	}

	function load($name){
		$file = $this->file($name);

		if($this->exists($name)){
			if(is_readable($file)){
				$telegram = $this->telegram;
				$pokemon = $this->pokemon;

				$ret = include_once $file;

				$name = strtolower(str_replace(".php", "", $name));
				$this->loaded[] = $name;

				return ($ret === -1 ? -1 : $name);
			}
		}

		return FALSE;
	}

}
