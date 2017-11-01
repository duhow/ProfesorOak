<?php

class Pokemon extends TelegramApp\Module {
	protected $runCommands = FALSE;
	private $misspells = array();

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
		$name = strtolower($name);
		if(substr($name, -1) == 's'){ $name = substr($name, 0, -1); } // Plural

		if(empty($this->misspells)){ $this->load_misspells(); }

		if(array_key_exists($name, $this->misspells)){
			if($retnum){ return $this->misspells[$name]; } // Return Pokémon ID.
			return $this->name($this->misspells[$name]); // ELSE Return Pokémon Name.
		}
	}

	private function load_misspells(){
		$load = array();
		$file = dirname(__FILE__) ."/Pokemon/misspells.csv";
		if(file_exists($file) and is_readable($file)){
			$fp = fopen($file, "r");
			while(!feof($fp)){
				$row = fgetcsv($fp, 24);
				$load[trim($row[0])] = intval($row[1]);
			}
			fclose($fp);
		}
		$this->misspells = $load;
		return $load;
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

	public function parse_text($string = NULL, $first = TRUE, $get_pokemon = FALSE){
		// $first es si solo habrá un Pokémon en la frase.
		// Si hay "mime", busca "Mr. Mime" y sus variantes.
		// Y lo mismo con los Nidoran.
		if(empty($string)){ $string = $this->telegram->text(); }
		// $string = strtolower($string);
		$string = explode(" ", $string);
		$findings = array();

		// Load misspells
		if(empty($this->misspells)){ $this->load_misspells(); }
		$misnames = array_keys($this->misspells);
		foreach($misnames as $k => $v){
			$misnames[$k] = str_replace(".", '\.', $v);
		}
		$misnames = implode("|", $misnames);

		$r = preg_match_all("/(#(?P<id>\d{1,3})\b|(?P<name>$misnames)\b)/i", $string, $matches);
		if($r){
			foreach($matches["id"] as $k => $n){
				if(!empty($n)){
					$findings[$k] = (int) $n;
				}
			}
			foreach($matches["name"] as $k => $n){
				if(!empty($n)){
					if(array_key_exists($n, $this->misspells)){
						$findings[$k] = $this->misspells[$n];
					}
				}
			}
		}

		if($first){
			if(!isset($findings[0])){ return NULL; }
			if($get_pokemon and is_numeric($findings[0])){
				return $this->load($findings[0]);
			}
			return $findings[0];
		}

		return $findings;
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

	public function parser($text){
		$pokes = array(); // $pokemon->pokedex();
		// $s = explode(" ", $pokemon->misspell($telegram->text()));
		$data = array();
		$number = NULL;
		$hashtag = FALSE;
		// ---------
		$data['pokemon'] = NULL;
		foreach($s as $w){
			$hashtag = ($w[0] == "#" and strlen($w) > 1);
			$w = preg_replace("/[^a-zA-Z0-9]+/", "", $w);
			$w = strtolower($w);

			if($data['pokemon'] === NULL){
				foreach($pokes as $pk){
					if($w == strtolower($pk->name)){ $data['pokemon'] = $pk->id; break; }
				}
			}

			if(is_numeric($w)){
				// tengo un número pero no se de qué. se supone que la siguiente palabra me lo dirá.
				// a no ser que la palabra sea un "DE", en cuyo caso paso a la siguiente.
				if($hashtag == TRUE and $data['pokemon'] === NULL){
					$data['pokemon'] = (int) $w;
				}else{
					$number = (int) $w;
				}
			}

			// Buscar distancia
			if(substr($w, -1) == "m"){ // Metros
				$n = substr($w, 0, -1);
				if(!is_numeric($n) && substr($n, -1) == "k"){ // Kilometros
					$n = substr($n, 0, -1);
					if(is_numeric($n)){ $n = $n * 1000; }
				}
				if(is_numeric($n)){
					$data['distance'] = $n;
				}
			}

			// Si se escribe numero junto a palabra, separar
			$conj = ['cp', 'pc', 'hp', 'ps'];
			foreach($conj as $wf){
				if(substr($w, -2) == $wf){
					$n = substr($w, 0, -2);
					if(is_numeric($n)){
						$number = $n;
						$w = $wf;
					}
				}
			}


			$search = ['cp', 'pc', 'hp', 'ps', 'polvo', 'polvos', 'caramelo', 'polvoestelar', 'stardust', 'm', 'metro', 'km'];
			$enter = FALSE;
			foreach($search as $q){
				if(strpos($w, $q) !== FALSE){ $enter = TRUE; break; }
			}
			if($enter){
				$action = NULL;
				if(strpos($w, 'cp') !== FALSE or strpos($w, 'pc') !== FALSE){ $action = 'cp'; }
				if(strpos($w, 'hp') !== FALSE or strpos($w, 'ps') !== FALSE){ $action = 'hp'; }
				if(strpos($w, 'polvo') !== FALSE or strpos($w, 'stardust') !== FALSE or strpos($w, 'polvoestelar') !== FALSE){ $action = 'stardust'; }
				if(strpos($w, 'm') !== FALSE && strlen($w) == 1){ $action = 'distance'; }
				if(strpos($w, 'caramelo') !== FALSE){ $action = 'candy'; }
				if(strpos($w, 'metro') !== FALSE){ $action = 'distance'; }
				if(strpos($w, 'km') !== FALSE && strlen($w) == 2){ $action = 'distance'; $number = $number * 1000; }

				if(strlen($w) > 2 && $number === NULL){
					// Creo que me lo ha puesto junto. Voy a sacar números...
					$number = filter_var($w, FILTER_SANITIZE_NUMBER_INT);
				}

				if(
					(!empty($number) && !empty($action)) and
					( ($action == 'hp' && $number > 5 && $number < 300) or
					($action == 'stardust' && $number > 200 && $number <= 10000) or
					($action == 'distance') or
					($number > 5 && $number < 4000) )
				){
					$data[$action] = $number;
					$number = NULL;
				}
			}
		}

		// TODO CHECK
		/* $data['attack'] = ($telegram->text_has(["ataque", "ATQ", "ATK"]));
		$data['defense'] = ($telegram->text_has(["defensa", "DEF"]));
		$data['stamina'] = ($telegram->text_has(["salud", "stamina", "estamina", "STA"]));
		$data['powered'] = ($telegram->text_has(["mejorado", "entrenado", "powered"]) && !$telegram->text_has(["sin", "no"], ["mejorar", "mejorado"]));
		$data['egg'] = ($telegram->text_contains("huevo"));

		if($telegram->text_has(["muy fuerte", "lo mejor", "flipando", "fuera de", "muy fuertes", "muy alto", "muy alta", "muy altas"])){ $data['ivcalc'] = [15]; }
		if($telegram->text_has(["bueno", "bastante bien", "buenas", "normal", "muy bien"])){ $data['ivcalc'] = [8,9,10,11,12]; }
		if($telegram->text_has(["bajo", "muy bajo", "poco que desear", "bien"])){ $data['ivcalc'] = [0,1,2,3,4,5,6,7]; }
		if($telegram->text_has(["fuerte", "fuertes", "excelente", "excelentes", "impresionante", "impresionantes", "alto", "alta"])){ $data['ivcalc'] = [13,14]; } */

		return $data;
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
