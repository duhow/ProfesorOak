<?php

class Pokemon extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function __construct($name = NULL){
		$folder = dirname(__FILE__) ."/Pokemon/";
		foreach(scandir($folder) as $file){
			if(substr($file, -4) == ".php"){ require_once $folder .$file; }
		}
		if($name !== NULL){
			$this->load($name);
		}
	}

	public function load($search, $misspell = FALSE){
		if(is_string($search) && !is_numeric($search)){
			if($misspell){
				$m = $this->misspell($search);

				if($m !== FALSE){ $this->db->where('name', $search); }
				else{ $search = $m; }
			}else{
				$this->db->where('name', $search);
			}
		}

		if(is_numeric($search)){
			$this->db->where('id', $search);
		}

		$res = $this->db->get('pokedex');
		if($this->db->count == 0){ return FALSE; }

		// TODO Load data to this object.

		return TRUE;
	}

	public function name($id){
		$res = $this->db
			->where('id', $id)
		->get('pokedex');
		if($this->db->count == 1){ return $res[0]['name']; }
		return FALSE;
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
		// TODO Join Pokémon real name
		$res = $this->db
			->where('word', $name)
		->get('pokemon_misspell');
		if($this->db->count == 0){ return FALSE; } // Not found.

		// Increase view.
		$this->db
			->where('word', $name)
		->update('pokemon_misspell', ['visits' => 'visits + 1']);

		if($retnum){ return $res[0]['pokemon']; } // Return Pokémon ID.
		return $this->name($res[0]['pokemon']); // ELSE Return Pokémon Name.
	}

	// TODO Return what?
	public function misspell_multi($str){
		if(is_string($str)){ $str = explode(" ", $str); }
		// TODO Join Pokémon real name
		$res = $this->db
			->where('word', $str, 'IN')
		->get('pokemon_misspell');
		if($this->db->count == 0){ return FALSE; } // All OK.

		$str = implode(" ", $str);
		// $str = str_ireplace()
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
		$levels = new Pokemon\Levels($stardust);
		$table = array();
		$low = 100; $high = 0; // HACK
		foreach($levels as $lvl => $mul){
			$pow = pow($mul, 2) * 0.1;
			for($IV_STA = 0; $IV_STA < 16; $IV_STA++){
				$cal['hp'] = max(floor(($pokemon->stamina + $IV_STA) * $mul), 10);
				if($cal['hp'] == $hp){
					$lvl_STA = sqrt($pokemon->stamina + $IV_STA) * $pow;
					// $cps = array(); // DEBUG
					for($IV_DEF = 0; $IV_DEF < 16; $IV_DEF++){
						for($IV_ATK = 0; $IV_ATK < 16; $IV_ATK++){
							$cal['cp'] = floor( ($pokemon->attack + $IV_ATK) * sqrt($pokemon->defense + $IV_DEF) * $lvl_STA);
							// Si el CP calculado coincide con el nuestro, agregar posibilidad.
							if($cal['cp'] == $cp){
								$sum = (($IV_ATK + $IV_DEF + $IV_STA) / 45) * 100;
								if($sum > $high){ $high = $sum; }
								if($sum < $low){ $low = $sum; }
								$table[] = ['level' => $lvl, 'atk' => $IV_ATK, 'def' => $IV_DEF, 'sta' => $IV_STA];
							}
							// $cps[] = $cp; // DEBUG
						}
					}
				}
			}
		}

		if(count($table) > 1 and ($pk['attack'] or $pk['defense'] or $pk['stamina'])){
			// si tiene ATK, DEF O STA, los resultados
			// que lo superen, quedan descartados.
			foreach($table as $i => $r){
				if
				(
					( $pk['attack'] and (
						( max($r['atk'], $r['def'], $r['sta']) != $r['atk'] ) or
						( isset($pk['ivcalc']) and !in_array($r['atk'], $pk['ivcalc']) )
					) ) or (
					$pk['defense'] and (
						( max($r['atk'], $r['def'], $r['sta']) != $r['def'] ) or
						( isset($pk['ivcalc']) and !in_array($r['def'], $pk['ivcalc']) )
					) ) or (
					$pk['stamina'] and (
						( max($r['atk'], $r['def'], $r['sta']) != $r['sta'] ) or
						( isset($pk['ivcalc']) and !in_array($r['sta'], $pk['ivcalc']) )
					) ) or (
						(!$pk['attack'] or !$pk['defense'] or !$pk['stamina']) and
						($r['atk'] + $r['def'] + $r['sta'] == 45)
					)
				){ unset($table[$i]); continue; }
			}
			$low = 100;
			$high = 0;
			foreach($table as $r){
				$sum = (($r['atk'] + $r['def'] + $r['sta']) / 45) * 100;
				if($sum > $high){ $high = $sum; }
				if($sum < $low){ $low = $sum; }
			}
		}

		return $table;
	}

	protected function hooks(){
		if(
			$this->telegram->text_command("iv") or
			$this->telegram->text_command("ivs")
		){
			if($this->telegram->words() < 5){
				$this->telegram->send
					->notification(FALSE)
					->text('/iv *[Pokémon] [CP] [HP] [Polvos] <Mejorado> <Ataque | Defensa | Salud>*', TRUE)
				->send();
				$this->end();
			}else{
				$args = $this->telegram->words(TRUE);
				array_shift($args); // Remove command
				$ivs = $this->iv($args);
			}
		}
	}
}
