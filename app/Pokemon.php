<?php

class Pokemon extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function load($search){
		if(is_numeric($search) or is_string($search)){  } // load Pokemon Pokedex.
		return $search;
	}

	public function pokedex($search){

	}

	public function evolution($pokemon){
		$pokemon = $this->load($pokemon);

	}

	public function movements($pokemon){
		$pokemon = $this->load($pokemon);
	}

	public function movement_best($pokemon, $str = FALSE){
		$pokemon = $this->load($pokemon);
	}

	public function movement_worst($pokemon, $str = FALSE){
		$pokemon = $this->load($pokemon);
	}

	public function attack_pokemon($pokemon, $target = 'source'){
		// This redirects to attack_type
		$pokemon = $this->load($pokemon);
	}

	public function attack_type($type, $type2 = 'source', $target = NULL){
		// TODO HACK function 2/3 args.
	}

	public function attack_table($type){

	}

	public function misspell($name, $retnum = FALSE){
		if(strtolower(substr($name, -1)) == 's'){ $name = substr($name, 0, -1); } // Plural

	}

	public function iv($pokemon, $cp = NULL, $hp = NULL, $stardust = NULL, $extra = NULL){
		if(is_array($pokemon)){
			if(is_array($cp)){ $extra = $cp; }
			$stardust = $pokemon[3];
			$hp = $pokemon[2];
			$cp = $pokemon[1];
			$pokemon = $pokemon[0];
		}
		$pokemon = $this->load($pokemon);

	}

	public function hooks(){
		if(
			$this->telegram->text_command("iv") or
			$this->telegram->text_command("ivs")
		){
			if($this->telegram->words() < 5){
				$this->telegram->send
					->notification(FALSE)
					->text('/iv *[Pokémon] [CP] [HP] [Polvos]*', TRUE)
				->send();
				$this->end();
			}else{
				$args = $this->telegram->words(TRUE);
				array_shift($args);
				$ivs = $this->iv($args);
			}
		}
	}
}
