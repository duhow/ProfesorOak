<?php

namespace Pokemon;

class Pokemon {
	public $id;
	public $name;
	public $class = "normal";
	public $types = array();
	public $movements = array();
	public $evolution_next = array();
	public $evolution_prev = array();
	public $egg = FALSE; // or number of KM

	public function is_legendary(){
		return ($this->class != "normal");
	}
}

?>
