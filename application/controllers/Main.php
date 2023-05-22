<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {

	public function __construct(){
		  parent::__construct();
	}

	public function index($access = NULL){
		// comprobar IP del host
		// if(strpos($_SERVER['REMOTE_ADDR'], "149.154.167.") === FALSE){ die(); }
		// Kill switch for overloading.

		// -------------
		// $chat = ($this->telegram->chat ? $this->telegram->chat->id : "0");
		// $user = ($this->telegram->user ? $this->telegram->user->id : "0");
		// if($this->telegram->new_user){ $user = $this->telegram->new_user->id; }

		ini_set("log_errors_max_len", 0);

		$key = "telegram.oak.updates";
		$key2 = "telegram.oak.key."; 
		if($this->telegram->callback){
			$key2 .= "callback";
		}elseif($this->telegram->new_user){
			$key2 .= "member.new";
		}elseif(@$this->telegram->left_user){
			$key2 .= "member.left";
		}else{
			$key2 .= "message";
		}

		$data = $this->telegram->update_id;
		// $date = $this->telegram->timestamp;
		$date = time();

		// METRICS - DISABLED 
		//$conn = fsockopen("localhost", 2003);
		//fwrite($conn, "$key $data $date\n");
		//fwrite($conn, "$key2 1 $date\n");
		//fclose($conn);
		// -------------

		$this->load->driver('cache');

		// iniciar variables
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$this->log(json_encode( $telegram->dump() ));

		// Actualizamos datos de chat
		$this->_update_chat();

		/* if($this->telegram->text() and !$this->telegram->callback and mt_rand(0,100) >= 50){
			die();
		} */

		// if(mt_rand(1,3) == 1){ die(); }
		if(!$this->telegram->new_user){
			if(file_exists('die')){ die(); }
			if(file_exists('skip') and unlink('skip')){ die(); }
			if(file_exists('callback') and $this->telegram->callback){ die(); }
		}
		$this->load->model('plugin');
		// PRIO LOAD
		if(date("G") == "0" && intval(date("i")) <= 7 && $this->telegram->words() == 1){
			// $this->plugin->load('game_pole');
			// die();
		}

		$loads = [ (string) $this->telegram->user->id];
		if($this->telegram->is_chat_group()){
			$loads[] = (string) $this->telegram->chat->id;
		}
		$this->pokemon->load_settings($loads);

		$step = NULL;
		if($pokeuser = $pokemon->user_firstload($telegram->user->id)){
			$step = $pokeuser->step;
		}

		$this->plugin->load_all(TRUE); // BASE

		/* $colores_full = [
			'Y' => ['amarillo', 'instinto', 'yellow', 'instinct'],
			'R' => ['rojo', 'valor', 'red'],
			'B' => ['azul', 'sabiduría', 'blue', 'mystic'],
		];
		$colores = array();
		foreach($colores_full as $c){
			foreach($c as $col){ $colores[] = $col; }
		} */

		// Si el usuario no está registrado con las funciones básicas, fuera.
		// Si el usuario está bloqueado, fuera.
		// $pokeuser = $pokemon->user($telegram->user->id);
		if($this->telegram->key != "channel_post" and (empty($pokeuser) or $pokeuser->blocked)){ return; }
		if($this->telegram->key == "channel_post"){ return; }

		// Cancelar pasos en general.
		if($step != NULL && $telegram->text_has(["Cancelar", "Desbugear", "/cancel"], TRUE)){
			$pokemon->step($telegram->user->id, NULL);
			$this->telegram->send
				->notification(FALSE)
				->keyboard()->selective(FALSE)->hide()
				->text("Acción cancelada.")
			->send();
			die();
		}

		if(!empty($step)){ $this->_step(); }

		$this->plugin->load('vote');
		$this->plugin->load('tools');

		$this->plugin->load_all();
		$this->plugin->load('plugin_manager'); // To manage all loaded plugins.

		/*
		##################
		# Comandos admin #
		##################
		 */

		/* if(
			// $telegram->user->id == $this->config->item('creator') or 
			$telegram->key == "message" and
			(intval($telegram->message_id) % 222 == 0) and
			$telegram->is_chat_group()
		){
			$r = mt_rand(1,3);
			$poles = ['la pole', 'la subpole', 'el bronce'];

			$str = $telegram->emoji(":medal-" .$r .": ") .$telegram->user->first_name ." ha conseguido <b>" .$poles[$r - 1] ."</b>!";
			$telegram->send
				->notification(FALSE)
				->text($str, 'HTML')
			->send();
		} */

		// ---------------------
		// Apartado de cuenta
		// ---------------------

		if($telegram->text_has("estoy aquí")){
			// Quien en cac? Que estoy aquí

		// ---------------------
		// Información General Pokemon
		// ---------------------

		}

		// Ver los IV o demás viendo stats Pokemon.
		elseif(
			$telegram->words() >= 4 &&
			($telegram->text_has(["tengo", "me ha salido", "calculame", "calcula iv", "calcular iv", "he conseguido", "he capturado"], TRUE) or
			$telegram->text_command("iv"))
		){
			$pk = $this->parse_pokemon();
			// TODO contar si faltan polvos o si se han especificado "caramelos" en lugar de polvos, etc.
			if($telegram->text_command("iv") && empty($pk['pokemon'])){
				if(is_numeric($telegram->words(1))){ $pk['pokemon'] = (int) $telegram->words(1); }
			}
			if(!empty($pk['pokemon'])){
				if($telegram->text_command("iv")){
					$pk["cp"] = $telegram->words(2);
					$pk["hp"] = $telegram->words(3);
					$pk["stardust"] = $telegram->words(4);
				}
				if(($pk['egg'] == TRUE) && isset($pk['distance']) && !$telegram->text_contains("calcu")){
					if(in_array($pk['distance'], [2000, 5000, 10000])){
						if(!$pokeuser->verified){ return; } // no cheaters plz
						if($pokemon->user_flags($telegram->user->id, ['troll', 'bot', 'gps'])){ return; }
						$pk['distance'] = ($pk['distance'] / 1000);
						if($pokemon->hatch_egg($pk['distance'], $pk['pokemon'], $telegram->user->id)){
							$telegram->send
								->notification(FALSE)
								->text("¡Gracias! Lo apuntaré en mi lista :)")
							->send();
						}
						return;
					}
				}elseif(isset($pk['stardust']) or isset($pk['candy'])){
					if((!isset($pk['stardust']) or empty($pk['stardust'])) and isset($pk['candy'])){
						// HACK confusión de la gente
						$pk['stardust'] = $pk['candy'];
						if(!empty($pk['hp']) and !empty($pk['cp'])){
							$telegram->send->text("¿Caramelos? Querrás decir polvos...")->send();
						}
					}
					// TODO el Pokemon sólo puede ser +1.5 del nivel de entrenador (guardado en la cuenta)
					// Calcular posibles niveles
					if($pk['stardust'] >= 6000){ $pk['powered'] = TRUE; } // A partir de nivel 30+, se habrá mejorado si o si.
					$levels = $pokemon->stardust($pk['stardust'], $pk['powered']);
					// $telegram->send->text(json_encode($levels))->send();

					// Si tiene HP y CP puesto, calvular IV
					if(isset($pk['hp']) and isset($pk['cp'])){
						$chat = ($telegram->is_chat_group() && $this->is_shutup(TRUE) ? $telegram->user->id : $telegram->chat->id);
						$pokedex = $pokemon->pokedex($pk['pokemon']);
						$this->analytics->event("Telegram", "Calculate IV", $pokedex->name);
						// De los niveles que tiene...
						$table = array();
						$low = 100;
						$high = 0; // HACK invertidas
						foreach($levels as $lvl){
							$lvlmp = $pokemon->level($lvl)->multiplier;
							$pow = pow($lvlmp, 2) * 0.1;
							for($IV_STA = 0; $IV_STA < 16; $IV_STA++){
								$hp = max(floor(($pokedex->stamina + $IV_STA) * $lvlmp), 10);
								// Si tenemos el IV de HP y coincide con su vida...
								if($hp == $pk['hp']){
									$lvl_STA = sqrt($pokedex->stamina + $IV_STA) * $pow;
									$cps = array(); // DEBUG
									for($IV_DEF = 0; $IV_DEF < 16; $IV_DEF++){
			                            for($IV_ATK = 0; $IV_ATK < 16; $IV_ATK++){
											$cp = floor( ($pokedex->attack + $IV_ATK) * sqrt($pokedex->defense + $IV_DEF) * $lvl_STA);
											// Si el CP calculado coincide con el nuestro, agregar posibilidad.
											if($cp == $pk['cp']){
												$sum = (($IV_ATK + $IV_DEF + $IV_STA) / 45) * 100;
												if($sum > $high){ $high = $sum; }
												if($sum < $low){ $low = $sum; }
												$table[] = ['level' => $lvl, 'atk' => $IV_ATK, 'def' => $IV_DEF, 'sta' => $IV_STA];
											}
											$cps[] = $cp; // DEBUG
										}
									}
									if($user->id == $this->config->item('creator')){
										// $telegram->send->text(json_encode($cps))->send(); // DEBUG
									}
								}
							}
						}
						if(count($table) > 1 and ($pk['attack'] or $pk['defense'] or $pk['stamina'])){
							// si tiene ATK, DEF O STA, los resultados
							// que lo superen, quedan descartados.
							foreach($table as $i => $r){
								if($pk['attack'] and ( max($r['atk'], $r['def'], $r['sta']) != $r['atk'] )){ unset($table[$i]); continue; }
								if($pk['defense'] and ( max($r['atk'], $r['def'], $r['sta']) != $r['def'] )){ unset($table[$i]); continue; }
								if($pk['stamina'] and ( max($r['atk'], $r['def'], $r['sta']) != $r['sta'] )){ unset($table[$i]); continue; }
								if($pk['attack'] and isset($pk['ivcalc']) and !in_array($r['atk'], $pk['ivcalc'])){ unset($table[$i]); continue; }
								if($pk['defense'] and isset($pk['ivcalc']) and !in_array($r['def'], $pk['ivcalc'])){ unset($table[$i]); continue; }
								if($pk['stamina'] and isset($pk['ivcalc']) and !in_array($r['sta'], $pk['ivcalc'])){ unset($table[$i]); continue; }
								if((!$pk['attack'] or !$pk['defense'] or !$pk['stamina']) and ($r['atk'] + $r['def'] + $r['sta'] == 45)){ unset($table[$i]); continue; }
							}
							$low = 100;
							$high = 0;
							foreach($table as $r){
								$sum = (($r['atk'] + $r['def'] + $r['sta']) / 45) * 100;
								if($sum > $high){ $high = $sum; }
								if($sum < $low){ $low = $sum; }
							}
						}

						$frases = [
							'Es una.... mierda. Si quieres caramelos, ya sabes que hacer.',
							'Bueno, no está mal. :)',
							'Oye, ¡pues mola!',
							'Menuda suerte que tienes, cabrón...'
						];

						if(count($table) == 0){
							$text = "Los cálculos no me salen...\n¿Seguro que me has dicho bien los datos? (¿Lo has *mejorado*?)";
						}elseif(count($table) == 1){
							if($low == $high){ $sum = round($high, 1); }
							reset($table); // HACK Reiniciar posicion
							$r = current($table); // HACK Seleccionar primer resultado
							$frase = 0;
							if($sum <= 50){ $frase = 0; }
							elseif($sum > 50 && $sum <= 66){ $frase = 1; }
							elseif($sum > 66 && $sum <= 80){ $frase = 2; }
							elseif($sum > 80){ $frase = 3; }
							$text = "Pues parece que tienes un *$sum%*!\n"
									.$frases[$frase] ."\n"
									."*L" .round($r['level']) ."* " .$r['atk'] ." ATK, " .$r['def'] ." DEF, " .$r['sta'] ." STA";
						}else{
							$low = round($low, 1);
							$high = round($high, 1);
							$text = "He encontrado *" .count($table) ."* posibilidades, "; // \n
							if($low == $high){ $text .= "con un *$high%*."; }
							else{ $text .= "entre *" .round($low, 1) ."% - " .round($high, 1) ."%*."; }

							if($high <= 50 or ($low <= 60 and $high <= 60) ){ $frase = 0; }
							elseif($low > 75){ $frase = 3; }
							elseif($low > 66){ $frase = 2; }
							elseif($low > 50 or ($high >= 75 and $low <= 65)){ $frase = 1; }

							$text .= "\n" .$frases[$frase] ."\n";

							// Si hay menos de 6 resultados, mostrar.
							if(count($table) <= 6){
								$text .= "\n";
								foreach($table as $r){
									$total = number_format(round((($r['atk'] + $r['def'] + $r['sta']) / 45) * 100, 1), 1);
									$text .= "*L" .$r['level'] ."* - *" .$total ."%*: " .$r['atk'] ."/" .$r['def'] ."/" .$r['sta'] ."\n";
								}
							}
						}

						$telegram->send->chat($chat)->text($text, TRUE)->send();
						if($user->id == $this->config->item('creator') && !$telegram->is_chat_group()){
							// $telegram->send->text(json_encode($table))->send(); // DEBUG
						}
					}
				}
				return;
			}
		}

		// PARTE 2


		// PARTE 3

		if($telegram->text_contains( ["atacando", "atacan"]) && $telegram->text_contains(["gimnasio", "gym"])){

		}elseif($telegram->text_has(["evolución", "evolucionar"]) && $telegram->words() <= 7){
			$chat = ($telegram->text_has("aquí") && !$this->is_shutup() ? $telegram->chat->id : $telegram->user->id);

			$pk = $this->parse_pokemon();
			if(empty($pk['pokemon'])){ return; }

			$search = $pokemon->pokedex($pk['pokemon']);
			$this->analytics->event('Telegram', 'Search Pokemon Evolution', $search->name);

			$evol = $pokemon->evolution($search->id);
			$str = array();
			if(count($evol) == 1){ $str = "No tiene."; }
			else{
				foreach($evol as $i => $p){
					$cur = FALSE;
					if($p['id'] == $search->id){ $cur = TRUE; }

					$frase = ($cur ? $telegram->emoji(":triangle-right:") ." *" .$p['name'] ."*" : $p['name']);
					$frase .= ($p['candy'] != NULL && $p['candy'] > 0 ? " (" .$p['candy'] .$telegram->emoji(" :candy:") .")" : "");

					if(!empty($pk['cp'])){
						if(!$cur && !empty($p['evolved_from'])){ $pk['cp'] = min(floor($pk['cp'] * $p['evolved_from']['cp_multi']), $p['cp_max']); }
						if($cur or !empty($p['evolved_from'])){ $frase .= " *" .$pk['cp'] ." CP*"; }
					}
					$str[] = $frase;
				}
				$str = implode("\n", $str);

			}
			$telegram->send
				->chat( $chat )
				->notification(FALSE)
				// ->reply_to( ($chat == $telegram->chat->id) )
				->text($str, TRUE)
			->send();
		}elseif(($telegram->text_has(["ataque", "habilidad", "skill"], TRUE) or $telegram->text_command("attack")) && $telegram->words() <= 5){
			$chat = ($telegram->text_has("aquí") && !$this->is_shutup() ? $telegram->chat->id : $telegram->user->id);

			$find = $telegram->words(1, 2);
			if($telegram->text_has("aquí")){
				$find = $telegram->words(1, $telegram->words() - 2);
			}
			$skill = $pokemon->skill($find);
			if($skill){
				$types = $pokemon->attack_types();
				$text = "*" .$skill->name_es ."* / _" .$skill->name ."_\n"
						.$types[$skill->type] ." - " .$skill->attack ." ATK / " .$skill->bars ." barras";

				$telegram->send
					->notification(TRUE)
					->chat($chat)
					->text($text, TRUE)
				->send();
				return;
			}
		// ---------------------
		// Utilidades varias
		// ---------------------


		// ---------------------
		// Administrativo
		// ---------------------

		}elseif($telegram->text_has(["team", "equipo"]) && $telegram->text_has(["sóis", "hay aquí", "estáis"])){
			exit();
		}elseif($telegram->text_has(["pokemon", "pokemons", "busca", "buscar", "buscame"]) && $telegram->text_contains("cerca") && $telegram->words() <= 10){
			// $this->_locate_pokemon();
			return;
		}
		// ---------------------
		// Chistes y tonterías
		// ---------------------

		// Recibir ubicación
		if($telegram->location() && !$telegram->is_chat_group()){
		    $loc = implode(",", $telegram->location(FALSE));
		    $pokemon->settings($telegram->user->id, 'location', $loc);
		    $pokemon->step($telegram->user->id, 'LOCATION');
		    $this->_step();
		}

		/* if($telegram->text_has(["agregar"], TRUE) && $telegram->words() == 3 && $telegram->has_reply && isset($telegram->reply->location)){
			$loc = (object) $telegram->reply->location;
			$loc = [$loc->latitude, $loc->longitude];

			$am = $telegram->words(1);
			$dir = $telegram->words(2);
			if(!is_numeric($am)){ exit(); }

			$telegram->send
				->text($pokemon->location_add($loc, $am, $dir))
			->send();
			exit();
		} */

		// NUEVO MOLESTO
		if($telegram->photo() && $telegram->user->id != $this->config->item('creator')){
			if($pokeuser->verified){ return; }
			// $pokemon->step($telegram->user->id, 'SCREENSHOT_VERIFY');
			$this->_step();
		}
	}

	function _step(){
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$user = $telegram->user;
		$chat = $telegram->chat;

		$pokeuser = $pokemon->user($user->id);
		if(empty($pokeuser)){ return; } // HACK cuidado

		$admins = NULL;
		if($telegram->is_chat_group()){ $admins = $telegram->get_admins(); }
		$admins[] = $this->config->item('creator');

		$step = $pokeuser->step;
		switch ($step) {
			case 'POKEMON_PARSE':
				$pokes = $pokemon->pokedex();
				$s = explode(" ", $pokemon->misspell($telegram->text()));
				$data = array();
				$number = NULL;
				$hashtag = FALSE;
				// ---------
				$data['pokemon'] = NULL;
				foreach($s as $w){
					$hashtag = ($w[0] == "#" and strlen($w) > 1);
					$w = $telegram->clean('alphanumeric', $w);
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
				$data['attack'] = ($telegram->text_has(["ataque", "ATQ", "ATK"]));
				$data['defense'] = ($telegram->text_has(["defensa", "DEF"]));
				$data['stamina'] = ($telegram->text_has(["salud", "stamina", "estamina", "STA"]));
				$data['powered'] = ($telegram->text_has(["mejorado", "entrenado", "powered"]) && !$telegram->text_has(["sin", "no"], ["mejorar", "mejorado"]));
				$data['egg'] = ($telegram->text_contains("huevo"));

				if($telegram->text_has(["muy fuerte", "lo mejor", "flipando", "fuera de", "muy fuertes", "muy alto", "muy alta", "muy altas"])){ $data['ivcalc'] = [15]; }
				if($telegram->text_has(["bueno", "bastante bien", "buenas", "normal", "muy bien"])){ $data['ivcalc'] = [8,9,10,11,12]; }
				if($telegram->text_has(["bajo", "muy bajo", "poco que desear", "bien"])){ $data['ivcalc'] = [0,1,2,3,4,5,6,7]; }
				if($telegram->text_has(["fuerte", "fuertes", "excelente", "excelentes", "impresionante", "impresionantes", "alto", "alta"])){ $data['ivcalc'] = [13,14]; }

				if($pokemon->settings($user->id, 'debug')){
					$telegram->send->text(json_encode($data))->send();
				}

				$pokemon->step($user->id, NULL);
				if($pokemon->settings($user->id, 'pokemon_return')){
					$pokemon->settings($user->id, 'pokemon_return', "DELETE");
					return $data;
				}
				break;
			case 'TIME_PARSE':
				$s = explode(" ", $telegram->text());
				$data = array();
				$number = NULL;
				$hashtag = FALSE;
				// ---------
				$days = [
					'lunes' => 'monday', 'martes' => 'tuesday',
					'miercoles' => 'wednesday', 'jueves' => 'thursday',
					'viernes' => 'friday', 'sabado' => 'saturday',
					'domingo' => 'sunday'
				];
				$months = [
					'enero' => 'january', 'febrero' => 'february', 'marzo' => 'march',
					'abril' => 'april', 'mayo' => 'may', 'junio' => 'june',
					'julio' => 'july', 'agosto' => 'august', 'septiembre' => 'september',
					'octubre' => 'october', 'noviembre' => 'november', 'diciembre' => 'december'
				];
				$waiting_month = FALSE;
				$waiting_time = FALSE;
				$waiting_time_add = FALSE;
				$select_week = FALSE;
				$next_week = FALSE;
				$last_week = FALSE;
				$this_week_day = FALSE;
				foreach($s as $w){
					// $w = $telegram->clean('alphanumeric', $w); // HACK filtrar?
					$w = strtolower($w);
					$w = str_replace(["á","é"], ["a","e"], $w);
					$w = str_replace("?", "", $w);

					if($w == "de" && (!isset($data['date']) or empty($data['date']) )){ $waiting_month = TRUE; } // FIXME not working?
					if($w == "la" && !isset($data['hour'])){ $waiting_time = TRUE; }
					if($w == "las" && !isset($data['hour'])){ $waiting_time = TRUE; }
					if($w == "en" && !isset($data['hour'])){ $waiting_time_add = TRUE; }

					if(is_numeric($w)){
						$number = (int) $w;
						if($waiting_time){
							if($number >= 24){ continue; }
							if($number <= 6){ $number = $number + 12; }
							$data['hour'] = $number .":00";
							$waiting_time = FALSE;
						}
						continue;
					}

					if(!isset($data['hour']) && preg_match("/(\d\d?):(\d\d)/", $w, $hour)){
						if($hour[1] >= 24){ $hour[1] = "00"; }
						if($hour[2] >= 60){ $hour[2] = "00"; }
						$data['hour'] = "$hour[1]:$hour[2]";
						continue;
					}

					if($waiting_time && in_array($w, ['tarde']) && !isset($data['hour'])){
						$data['hour'] = "18:00";
						$waiting_time = FALSE;
						continue;
					}
					if($waiting_time && in_array($w, ['mañana', 'maana', 'manana']) && !isset($data['hour'])){
						$data['hour'] = "11:00";
						$waiting_time = FALSE;
						continue;
					}
					if($waiting_time_add && in_array($w, ['hora', 'horas']) && !isset($data['hour'])){
						$hour = date("H") + $number;
						if(date("i") >= 30){ $hour++; } // Si son más de y media, suma una hora.
						$data['hour'] = $hour .":00";
						if(!isset($data['date'])){ $data['date'] = date("Y-m-d"); } // HACK bien?
						$waiting_time_add = FALSE;
						continue;
					}
					if(in_array($w, array_keys($days)) && ($next_week or $last_week or $this_week_day) && !isset($data['date'])){
						$selector = "+1 week next";
						if($this_week_day && date("w") <= date("w", strtotime($days[$w]))){ $selector = "this"; }
						if($this_week_day && date("w") > date("w", strtotime($days[$w]))){ $selector = "next"; }
						if($last_week){ $selector = "last"; } // && date("w") > date("w", strtotime($days[$w]))
						if($next_week && date("w") >= date("w", strtotime($days[$w]))){ $selector = "next"; }
						$data['date'] = date("Y-m-d", strtotime($selector ." " .$days[$w]));
						$next_week = FALSE;
						$last_week = FALSE;
						$this_week_day = FALSE;
						continue;
					}
					if(in_array($w, array_keys($months))){ // FIXME $waiting_month no funciona
						if($number >= 1 && $number <= 31){
							$data['date'] = date("Y-m-d", strtotime($months[$w] ." " .$number));
						}
						$waiting_month = FALSE;
						continue;
					}
					if($w == "semana" && !isset($data['date'])){
						if($next_week){
							$data['date'] = date("Y-m-d", strtotime("next week"));
							$next_week = FALSE;
							continue;
						}
						$select_week = TRUE;
						continue;
					}
					if(in_array($w, ["proximo", "próximo", "proxima", "próxima", "siguiente"])){
						// proximo lunes != ESTE lunes, esta semana
						if($select_week && !isset($data['date'])){
							$data['date'] = date("Y-m-d", strtotime("next week"));
							$select_week = FALSE;
							continue;
						}
						$next_week = TRUE;
						continue;
					}
					if(in_array($w, ["pasado", "pasada"])){
						if(!isset($data['date']) or empty($data['date'])){
							if($this_week_day){ $this_week_day = FALSE; }
							if($select_week){
								// last week = LUNES, marca el dia de hoy!
								$en_days = array_values($days);
								$data['date'] = date("Y-m-d", strtotime("last week " .$en_days[date("N") - 1]));
								$select_week = FALSE;
								continue;
							}
							$last_week = TRUE;
							continue;
						}
						// el pasado martes, el martes pasado.
						$tmp = new DateTime($data['date']);
						$tmp->modify('-1 week');
						$data['date'] = $tmp->format('Y-m-d');
						continue;
					}
					if(in_array($w, ["este", "el"])){
						// este lunes
						$this_week_day = TRUE;
						continue;
					}
					if(in_array($w, ['mañana', 'maana', 'manana']) && !isset($data['date'])){
						// Distinguir mañana de "por la mañana"
						$data['date'] = date("Y-m-d", strtotime("tomorrow"));
						continue;
					}
					if($w == "hoy" && !isset($data['date'])){
						$data['date'] = date("Y-m-d"); // TODAY
						continue;
					}
					if($w == "ayer" && !isset($data['date'])){
						$data['date'] = date("Y-m-d", strtotime("yesterday"));
						continue;
					}
				}

				if(isset($data['date'])){
					$strdate = $data['date'] ." ";
					$strdate .= (isset($data['hour']) ? $data['hour'] : "00:00");
					$strdate = strtotime($strdate);
					$data['left_hours'] = floor(($strdate - time()) / 3600);
					$data['left_minutes'] = floor(($strdate - time()) / 60);
				}
				if($pokemon->settings($user->id, 'debug')){
					$telegram->send->text(json_encode($data))->send();
				}

				$pokemon->step($user->id, NULL);
				if($pokemon->settings($user->id, 'pokemon_return')){
					$pokemon->settings($user->id, 'pokemon_return', "DELETE");
					return $data;
				}
				break;
			case 'CHOOSE_POKEMON':
				// $pk = NULL;
				$pk = $this->parse_pokemon();
				$pokemon->step($user->id, 'CHOOSE_POKEMON');
				/* if($telegram->text()){
					$pk = trim($telegram->words(0, TRUE));
					// if( preg_match('/^(#?)\d{1,3}$/', $word) ){ }
				}elseif($telegram->sticker()){
					// Decode de la lista de stickers cuál es el Pokemon.
				} */
				if(!empty($pk)){
					// $pk = $pokemon->find($pk);
					if(empty($pk['pokemon'])){
						$telegram->send
							->text("El Pokémon mencionado no existe.")
						->send();
					}else{
						$s = $pokemon->settings($user->id, 'step_action');
						$pokemon->step($user->id, $s);
						$pokemon->settings($user->id, 'pokemon_select', $pk['pokemon']);
						$this->_step(); // HACK relaunch
					}
				}
				exit();
				break;
			case 'POKEMON_SEEN':
				// Tienes que estar en el lugar para poder haber reportado el Pokemon
				// Si tienes flags TROLL, FC u otras, no podrás enviarlo.
				// Solo puedes hacer uno cada minuto.
				$pk = $pokemon->settings($user->id, 'pokemon_select');

				$pokemon->settings($user->id, 'pokemon_select', 'DELETE');
				$pokemon->settings($user->id, 'step_action', 'DELETE');

				$cd = $pokemon->settings($user->id, 'pokemon_cooldown');
				if(!empty($cd) && $cd > time()){
					$telegram->send->text("Aún no ha pasado suficiente tiempo. Espera un poco, anda. :)");
					$pokemon->step($user->id, NULL);
					exit();
				}

				if($pokemon->user_flags($user->id, ['troll', 'rager', 'bot', 'forocoches', 'hacks', 'gps', 'trollmap'])){
					$telegram->send->text("nope.")->send();
					$pokemon->step($user->id, NULL);
					exit();
				}
				$loc = explode(",", $pokemon->settings($user->id, 'location')); // FIXME cuidado con esto, si reusamos la funcion.
				$pokemon->add_found($pk, $user->id, $loc[0], $loc[1]);

				// SELECT uid, SUBSTRING(value, 1, INSTR(value, ",") - 1) AS lat, SUBSTRING(value, INSTR(value, ",") + 1) AS lng FROM `settings` WHERE LEFT(uid, 1) = '-' AND type = "location"

				$pokemon->settings($user->id, 'pokemon_cooldown', time() + 60);
				$pokemon->step($user->id, NULL);

				$this->analytics->event("Telegram", "Pokemon Seen", $pk);
				$telegram->send
					->text("Hecho! Gracias por avisar! :D")
					->keyboard()->hide(TRUE)
				->send();
				exit();
				break;
			case 'LURE_SEEN':
				// Tienes que estar en el lugar para poder haber reportado el Pokemon
				// Si tienes flags TROLL, FC u otras, no podrás enviarlo.
				// Solo puedes hacer uno cada minuto.
				$pokemon->settings($user->id, 'step_action', 'DELETE');

				$cd = $pokemon->settings($user->id, 'pokemon_cooldown');
				if(!empty($cd) && $cd > time()){
					$telegram->send->text("Aún no ha pasado suficiente tiempo. Espera un poco, anda. :)");
					$pokemon->step($user->id, NULL);
					exit();
				}

				if($pokemon->user_flags($user->id, ['troll', 'rager', 'bot', 'forocoches', 'hacks', 'gps', 'trollmap'])){
					$telegram->send->text("nope.")->send();
					$pokemon->step($user->id, NULL);
					exit();
				}
				$loc = explode(",", $pokemon->settings($user->id, 'location')); // FIXME cuidado con esto, si reusamos la funcion.

				// Buscar Pokeparada correspondiente o cercana.
				$pkstop = $pokemon->pokestops($loc, 160, 1);
				if(!$pkstop){
					$telegram->send
						->text("No hay Pokeparadas por ahí cerca, o no están registradas. Pregúntalo más adelante.")
					->send();
					$telegram->send
						->chat($this->config->item('creator'))
						->text("*!!* Buscar Poképaradas en *" .json_encode($loc) ."*", TRUE)
					->send();
					return;
				}
				$pkstop = $pkstop[0];
				$loc = [$pkstop['lat'], $pkstop['lng']];

				$pokemon->add_lure_found($user->id, $loc[0], $loc[1]);

				$nearest = $pokemon->group_near($loc);
				foreach($nearest as $g){
					$telegram->send
						->chat($g)
						->location($loc)
					->send();

					$text = "Cebo en *" .$pkstop['title'] ."*!";
					$telegram->send
						->chat($g)
						->text($text, TRUE)
					->send();
				}
				// SELECT uid, SUBSTRING(value, 1, INSTR(value, ",") - 1) AS lat, SUBSTRING(value, INSTR(value, ",") + 1) AS lng FROM `settings` WHERE LEFT(uid, 1) = '-' AND type = "location"

				$pokemon->settings($user->id, 'pokemon_cooldown', time() + 60);
				$pokemon->step($user->id, NULL);

				$this->analytics->event("Telegram", "Lure Seen", $pk);
				$telegram->send
					->text("Cebo en *" .$pkstop['title'] ."*, gracias por avisar! :D", TRUE)
					->keyboard()->hide(TRUE)
				->send();
				exit();
				break;
			case 'MEETING_LOCATION';

				break;
			case 'SCREENSHOT_VERIFY':

				break;
			case 'SPEAK':
				// DEBUG - FIXME
				if(
					$telegram->is_chat_group() or
					$telegram->callback or
					($telegram->text() && substr($telegram->words(0), 0, 1) == "/")
				){ return; }
				$chattalk = $pokemon->settings($telegram->user->id, 'speak');
				if($telegram->user->id != $this->config->item('creator') or $chattalk == NULL){
					$pokemon->step($telegram->user->id, NULL);
					return;
				}
				$telegram->send
					->notification(TRUE)
					->chat($chattalk);

				if($telegram->text()){
					$type = 'Markdown';
					if(strip_tags($telegram->text()) != $telegram->text()){ $type = 'HTML'; }
					$telegram->send->text( $telegram->text(), $type )->send();
				}elseif($telegram->photo()){
					$telegram->send->file('photo', $telegram->photo());
				}elseif($telegram->sticker()){
					$telegram->send->file('sticker', $telegram->sticker());
				}elseif($telegram->voice()){
					$telegram->send->file('voice', $telegram->voice());
				}elseif($telegram->gif()){
					$telegram->send->file('document', $telegram->gif());
				}elseif($telegram->video()){
					$telegram->send->file('video', $telegram->video());
				}
				exit();
				break;
			case 'DUMP':
				$telegram->send->text( $telegram->dump(TRUE) )->send();
				exit();
				break;
			case 'SETNAME':
				// Last word con filtro de escapes.

				break;
			default:
			break;
		}
		// exit(); // FIXME molesta. se queda comentado.
	}

	function cron(){
		$chat = "-221103258";
		if($_SERVER['REMOTE_ADDR'] != getHostByName(getHostName())){
			$this->telegram->send
				->chat($chat)
				->text($_SERVER['REMOTE_ADDR'] ." / " .getHostByName(getHostName()))
			->send();
			die();
		}

		$this->load->driver('cache', array('adapter' => 'memcached', 'backup' => 'file'));
		if($groups = $this->cache->get('pole_groups') and date("H:i") == "23:59"){
			$this->telegram->send
				->chat($chat)
				->text($this->telegram->emoji(":warning: ") ."Hay " .count($groups) ." grupos para la pole.")
			->send();
		}

		$q = $this->telegram->send->Request("getWebhookInfo", array());
		if($q !== FALSE){
			if(strpos(sha1($q['url']), "6a0644e5f2c5d79d") === FALSE){
				$this->telegram->send
					->chat($chat)
					->text($this->telegram->emoji(":warning: ") ."¡Webhook ha variado!")
				->send();
			}
			if($q['pending_update_count'] >= 100){
				touch('skip');
				$str = $this->telegram->emoji(":warning: ") ."¡Hay " .$q['pending_update_count'] ." requests pendientes!";
				if($q['pending_update_count'] >= 300 and (
						!in_array(date("H:i"), ["00:00", "00:01", "00:02", "00:03", "00:04", "00:05"])
					)
				){
					$str .= "\n" .$this->telegram->emoji(":!: ") ."Vale, son demasiados. Frenando...";
				}

				$this->telegram->send
					->chat($chat)
					->text($str)
				->send();

				touch('callback');

				if($q['pending_update_count'] >= 300 and (date("G") != 0 and date("i") > 5)){
					if(touch('die')){
						while($q['pending_update_count'] >= 30){
							$q = $this->telegram->send->Request("getWebhookInfo", array());
							sleep(2);
						}
						unlink('callback');
						unlink('die');
						$this->telegram->send
							->chat($chat)
							->text($this->telegram->emoji(":ok: ") ."Ahora hay " .$q['pending_update_count'] .".")
						->send();
					}
				}
			}elseif(file_exists('callback')){
				unlink('callback');
			}
			if(isset($q['last_error_date']) and $q['last_error_date'] >= time() + 90){
				$time = time() - $q['last_error_date'];
				$this->telegram->send
					->chat($chat)
					->text($this->telegram->emoji(":!: ") ."Error hace $time s: " .$q['last_error_message'])
				->send();
			}
			$cpu = sys_getloadavg();
			if($cpu[0] >= 3.8){
				$this->telegram->send
					->chat($chat)
					->text($this->telegram->emoji(":fire: ") ."¡CPU caliente! " .implode(" / ", $cpu))
				->send();
			}
		}
	}

	function _joke(){
		$this->analytics->event('Telegram', 'Games', 'Jokes');
		$this->last_command("JOKE");

		$jokes = $this->pokemon->settings($this->telegram->chat->id, 'jokes');
		$shut = $this->pokemon->settings($this->telegram->chat->id, 'shutup');

		$admins = array();
		if($this->telegram->is_chat_group()){ $admins = $this->telegram->get_admins(); }
		$admins[] = $this->config->item('creator');

		if(
			$this->telegram->is_chat_group() &&
			!in_array($this->telegram->user->id, $admins) &&
			( $jokes == FALSE or $shut == TRUE )
		){ return; }

		$joke = $this->pokemon->joke();

		if(filter_var($joke, FILTER_VALIDATE_URL) !== FALSE){
			// Foto
			$this->telegram->send
				->notification( !$this->telegram->is_chat_group() )
				->file('photo', $joke);
		}else{
			$this->telegram->send
				->notification( !$this->telegram->is_chat_group() )
				->text($joke, TRUE)
			->send();
		}
	}

	function _locate_pokemon(){
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;

		$distance = 500;
		$limit = 10;

		$this->last_command("POKEMAP");

		// Bloquear a trols y otros.
		if($pokemon->user_flags($telegram->user->id, ['troll', 'rager', 'spam', 'bot', 'gps', 'hacks'])){ return; }
		// Comprobar cooldown.
		if($pokemon->settings($telegram->user->id, 'pokemap_cooldown') > time()){ return; }
		// Desactivar por grupos
		if($pokemon->settings($telegram->chat->id, 'location_disable') && $telegram->user->id != $this->config->item('creator')){ return; }

		// Parsear datos Pokemon
		$pk = $this->parse_pokemon();

		if(isset($pk['distance'])){ $distance = $pk['distance']; }
		if($telegram->is_chat_group() && $pokemon->settings($telegram->chat->id, 'location')){
			// GET location del grupo
			$loc = explode(",", $pokemon->settings($telegram->chat->id, 'location'));
			$dist = $pokemon->settings($telegram->chat->id, 'location_radius');
			// Radio por defecto 5km.
			$distance = (is_numeric($dist) ? $dist : 5000);
		}else{
			// GET location
			$loc = explode(",", $pokemon->settings($telegram->user->id, 'location'));
		}
		// die();
		$list = $pokemon->spawn_near($loc, $distance, $limit, $pk['pokemon']);
		$str = "No se han encontrado Pokemon.";
		if($telegram->user->id == $this->config->item('creator')){
			$telegram->send->text("Calculando especial...")->send();
			if(!function_exists("pokeradar")){
				$telegram->send->text("Función especial no cargada. Mu mal David. u.u")->send();
				return;
			}
			$list = pokeradar($loc, $distance, $limit, $pk['pokemon']);
			$telegram->send->chat($this->config->item('creator'))->text($list)->send();
			return;
			// $list = $pokemon->pokecrew($loc, $distance, $limit, $pk['pokemon']);
		}
		if(!empty($list)){
			$str = "";
			$pokedex = $pokemon->pokedex();
			$pkfind = (empty($pk['pokemon']) ? "All" : $pokedex[$pk['pokemon']]->name);
			$this->analytics->event("Telegram", "Search Pokemon Location", $pkfind);
			if(count($list) > 1){
				foreach($list as $e){
					$met = floor($e['distance']);
					if($met > 1000){ $met = round($met / 1000, 2) ."km"; }
					else{ $met .= "m"; }

					$str .= "*" .$pokedex[$e['pokemon']]->name ."* en $met" ." (" .date("d/m H:i", strtotime($e['last_seen'])) .")" ."\n";
				}
			}else{
				$e = $list[0]; // Seleccionar el primero
				$met = floor($e['distance']);
				if($met > 1000){ $met = round($met / 1000, 2) ."km"; }
				else{ $met .= "m"; }

				$str = "Tienes a *" .$pokedex[$e['pokemon']]->name ."* a $met, ve a por él!\n"
						."(" .date("d/m H:i", strtotime($e['last_seen'])) .")";
				$telegram->send->location($e['lat'], $e['lng'])->send();
			}
		}
		$time = (empty($list) ? 10 : 15); // Cooldown en función de resultado
		$pokemon->settings($telegram->user->id, 'pokemap_cooldown', time() + $time);
		$telegram->send->keyboard()->hide()->text($str, TRUE)->send();
	}

	function parse_pokemon(){
		$pokemon = $this->pokemon;
		$user = $this->telegram->user;

		$pokemon->settings($user->id, 'pokemon_return', TRUE);
		$pokemon->step($user->id, 'POKEMON_PARSE');
		$pk = $this->_step();
		return $pk;
	}

	function last_command($action){
		$user = $this->telegram->user->id;
		$chat = $this->telegram->chat->id;
		$pokemon = $this->pokemon;

		$command = $pokemon->settings($user, 'last_command');
		$amount = 1;
		if($command == $action){
			$count = $pokemon->settings($user, 'last_command_count');
			$add = ($user == $chat ? 0 : 1); // Solo agrega si es grupo
			$amount = (empty($count) ? 1 : ($count + $add));
		}
		$pokemon->settings($user, 'last_command', $action);
		$pokemon->settings($user, 'last_command_count', $amount);
	}

	function is_shutup($creator = TRUE){
		$admins = $this->admins($creator);
		$shutup = $this->pokemon->settings($this->telegram->chat->id, 'shutup');
		return ($shutup && !in_array($this->telegram->user->id, $admins));
		// $this->telegram->user->id != $this->config->item('creator')
	}

	function is_shutup_jokes(){
		$can = $this->pokemon->settings($this->telegram->chat->id, 'jokes');
		return ($this->is_shutup() or ($can != NULL && $can == FALSE));
	}

	function admins($add_creator = TRUE, $custom = NULL){
		$admins = $this->pokemon->group_admins($this->telegram->chat->id);
		if(empty($admins)){
			$admins = $this->telegram->get_admins(); // Del grupo
			$this->pokemon->group_admins($this->telegram->chat->id, $admins);
		}
		if($add_creator){ $admins[] = $this->config->item('creator'); }
		if($custom != NULL){
			if(!is_array($custom)){ $custom = [$custom]; }
			foreach($custom as $c){ $admins[] = $c; }
		}
		return $admins;
	}

	function log($texto){
		$fp = fopen('error.loga', 'a');
		fwrite($fp, $texto ."\n");
		fclose($fp);
	}


	function _update_chat(){
		$chat = $this->telegram->chat;
		$user = $this->telegram->user;

		if(empty($chat->id)){ return; }

		$data = [
			'id' 		=> $chat->id,
			'type' 		=> $chat->type,
			'title' 	=> @$chat->title,
			'register_date' => date("Y-m-d H:i:s"),
			'active' 	=> TRUE,
			'messages' 	=> 0,
		];

		$update = [
			// 'title'	=> '"' .@$chat->title .'"',
			'last_date' => '"' .date("Y-m-d H:i:s") .'"',
			'active'	=> TRUE,
			'messages'	=> 'messages + 1'
		];

		$sql = $this->db->insert_string('chats', $data) ." ON DUPLICATE KEY UPDATE ";
		$upd = array();
		foreach($update as $key => $val){ $upd[] = "$key = $val"; }
		$sql .= implode(", ", $upd);

		$this->db->query($sql);
		// -----------
		if(empty($user->id)){ return; } // Channels

		$data = [
			'uid' => $user->id,
			'cid' => $chat->id,
			'messages' => 0,
			'last_date' => NULL,
			'register_date' => date("Y-m-d H:i:s")
		];

		$update = [
			'messages' => 'messages + 1',
			'last_date' => '"' .date("Y-m-d H:i:s") .'"'
		];

		$sql = $this->db->insert_string('user_inchat', $data) ." ON DUPLICATE KEY UPDATE ";
		$upd = array();
		foreach($update as $key => $val){ $upd[] = "$key = $val"; }
		$sql .= implode(", ", $upd);

		$this->db->query($sql);
		// -----------

		if(isset($user->username) and !empty($user->username)){
			$this->db
				->set('telegramuser', $user->username)
				->where('telegramid', $user->id)
			->update('user');
		}
	}

	function pruebando(){
		set_time_limit (3600);
                $chat = $this->config->item('creator');
                $groups = $this->pokemon->get_groups();
                $groups = array_reverse($groups);
                $c = 0;
                $this->telegram->send->chat($chat)->text("Voy!")->send();
                $total = 0;
                $txt = NULL;
                foreach($groups as $g){
                        // $ret = $this->telegram->send->get_member_info($this->config->item('telegram_bot_id'), $g);
                        $info = $this->pokemon->group($g);
                        $ret = $this->telegram->send->get_members_count($g);
                        if(!$this->telegram->user_in_chat($this->config->item('telegram_bot_id'), $g)){
                                $this->telegram->send
                                        ->chat($chat)
                                        ->text("Marco fuera de " .$info->title .".")
                                ->send();
                                $this->pokemon->group_disable($g);
			}else{
				$info = $this->telegram->send->get_chat($g);
	                        $this->db
	                                ->where('id', $g)
					->set('users', $ret)
					->set('title', $info['title'])
				->update('chats');
			}

			$total = $total + $ret;

			usleep(100000);

		}
                $this->telegram->send
                        ->chat($chat)
                        ->text("En total hay $total")
                ->send();
	}

	function sobrantes(){
	set_time_limit (2700);

	$query = $this->db
		->select(['user_inchat.id', 'user_inchat.cid', 'user_inchat.uid'])
		->from('user_inchat')
		->join('chats', 'chats.id = user_inchat.cid')
		->not_like('type', 'private')
		->where('active', TRUE)
		->order_by('RAND()')
	->get();

	$c = 0;
	foreach($query->result_array() as $u){
		if(!$this->telegram->user_in_chat($u['uid'], $u['cid'])){
			$c++;

			$this->db
				->where('id', $u['id'])
			->delete('user_inchat');

			$this->telegram->send
				->chat("-247195497")
				->notification(FALSE)
				->text_replace("Saco a %s de %s.", [$u['uid'], $u['cid']])
			->send();
		}
	}

	$this->telegram->send
		->chat("-247195497")
		->notification(TRUE)
		->text("$c usuarios limpiados.")
	->send();
	}

	function validacionesextra(){
		set_time_limit (7200);

		$query = $this->db
			->select('user.*')
			->from('user')
			->join('user_flags', 'user.telegramid = user_flags.user')
			->where('value', 'verified_2016')
			->where('verified', FALSE)
			->where('blocked', FALSE)
			->where('anonymous', FALSE)
		->get();

		$c = 0;
		// $prueba = [['telegramid' =>3458358]];
		foreach($query->result_array() as $u){
			$this->pokemon->step($u['telegramid'], "SCREENSHOT_VERIFY");

			$str = "¡Hola entrenador!\n\n"
					."Por temas de limpieza y gestión, tengo que volver a pedirte la validación de usuario.\n"
					."Por favor, mándame una captura de pantalla de tu <b>perfil Pokémon GO</b>, y con <b>una mascota que se llame Oak</b>.\n"
					."Luego le puedes volver a cambiar el nombre, no hay problema.";

			$q = $this->telegram->send
				->chat($u['telegramid'])
				->text($str, 'HTML')
			->send();

			if($q === FALSE){
				$this->pokemon->user_flags($u['telegramid'], 'nocontesta');
			}else{
				$c++;
			}

			usleep(200000);
		}

		$this->telegram->send
			->chat(3458358)
			->text("Acabado con $c.")
		->send();
	}

	function chatget(){
		set_time_limit(2700);
		$query = $this->db
			->select('id')
			->where('type !=', 'private')
			->where('active', TRUE)
		->get('chats');

		$chats = array_column($query->result_array(), 'id');
		foreach($chats as $c){
			$linkid = NULL;
			$chatinfo = $this->telegram->send->get_chat($c);
			if(!$chatinfo){ continue; }
			echo $chatinfo['id'] ."\t" .$chatinfo['title'] ."\t";
			if(array_key_exists('username', $chatinfo)){
				echo "https://t.me/" .$chatinfo['username'];
				$linkid = "@" .$chatinfo['username'];
			}else{
				$link = $this->telegram->send->get_chat_link($c);
				if($link){
					$linkid = str_replace("https://t.me/joinchat/", "", $link);
					echo $link;
				}
			}

			if(!empty($linkid)){
				$data = ['type' => 'link_chat', 'value' => $linkid, 'uid' => $c];
				$sql = $this->db->insert_string('settings', $data) ." ON DUPLICATE KEY UPDATE value='" .$linkid ."'";
				$this->db->query($sql);
			}
			echo "\n";
		}
	}

	function chatsend(){
		set_time_limit(2700);
		$query = $this->db
			->select('id')
			->where_in('type', ['group', 'supergroup'])
			->where('active', TRUE)
		->get('chats');

		$chats = array_column($query->result_array(), 'id');

		foreach($chats as $c){
			$this->telegram->send
				->chat("-1001089222378")
				->message("490")
				->forward_to($c)
			->send();
		}
	}

	function nivelazo(){
		set_time_limit (7200);
		// SELECT * FROM `user` WHERE `lvl` BETWEEN 34 AND 39 AND `verified` = 1 AND `blocked` = 0 AND `anonymous` = 0
		$query = $this->db
			->select('telegramid')
			->where('lvl >=', 34)
			->where('lvl <=', 39)
			->where('blocked', FALSE)
			->where('anonymous', FALSE)
			->where('verified', TRUE)
		->get('user');

		$users = array_column($query->result_array(), 'telegramid');

		$str = "Hola entrenador! Debido al exito del evento, estoy convencido de que habrás subido de nivel, así que en un rato podrás decirme directamente tu nivel actual."
			."\n\n" ."Pero por favor, nada de troleitos. Si no, ya no seré tu amigo. Gracias. <3";

		foreach($users as $user){
			$this->telegram->send
				->text($str)
				->chat($user)
			->send();
			usleep(500000);
		}
	}

	function poletroll($user){
		$this->load->driver('cache');
		$this->load->model('pokemon');
		$pkuser = $this->pokemon->user($user);
		$query = $this->db
			->where('uid', $pkuser->telegramid)
			->where('cid !=', $pkuser->telegramid)
		->get('user_inchat');
		if($query->num_rows() == 0){ die(); }
		$chats = array_column($query->result_array(), 'cid');
		foreach($chats as $chat){
			$this->telegram->send
				->chat($chat)
				->text('¡Estáis de suerte! Hoy podréis hacer la pole en este grupo gracias a @' .$pkuser->username ."!")
			->send();
		}
	}

	function antiguallas(){
		set_time_limit (2700);

		$query = $this->db
			->select('id')
			->where('type !=', 'private')
			->where('active', TRUE)
			->where('last_date <=', "2017-12-02")
		->get('chats');

		$ids = array_column($query->result_array(), 'id');
		foreach($ids as $id){
			$this->telegram->send->leave_chat($id);

			$this->db
				->where('id', $id)
				->set('active', FALSE)
			->update('chats');
			usleep(50000);
		}
	}

	function cachear(){
		$timecache = 58;
		header("Content-Type: text/plain");
		$this->load->driver('cache',  array('adapter' => 'memcached', 'backup' => 'file') );

		echo date("H:i:s") ." begin\n";

		// ----------------

		$groups = $this->db
			->select(['uid', 'value'])
			->where('type', 'admin_chat')
		->get('settings');

		foreach($groups->result() as $group){
			$key = 'group_admin_' .$group->uid;
			$this->cache->save('oak_' .$key, strval($group->value), $timecache);
			// echo $key ."\n";
		}

		echo date("H:i:s") ." groups\n\n";
		unset($groups);
		// ----------------
		
		$flagqu = $this->db
			->select(['user', 'value'])
			->order_by('user')
		->get('user_flags');

		$flags = array();
		foreach($flagqu->result_array() as $flag){
			if(!array_key_exists($flag['user'], $flags)){
				$flags[$flag['user']] = array();
			}
			$flags[$flag['user']][] = $flag['value'];
		}

		foreach($flags as $user => $flagc){
			$flagc = array_unique($flagc);
			$key = 'flags_' .$user;
			// echo $key ."\n";
			$this->cache->save('oak_' .$key, $flagc, $timecache);
		}

		echo date("H:i:s") ." flags\n\n";
		unset($flagqu);
		unset($flags);
		// ----------------

		$adminqu = $this->db
			->select(['gid', 'uid'])
			->where('expires >=', date("Y-m-d H:i:s"))
		->get('user_admins');

		$adminlist = array();
		foreach($adminqu->result_array() as $admin){
			if(!array_key_exists($admin['gid'], $adminlist)){
				$adminlist[$admin['gid']] = array();
			}
			$adminlist[$admin['gid']][] = $admin['uid'];
		}

		foreach($adminlist as $gid => $uids){
			$key = 'useradmins_' .$gid;
			// echo $key ."\n";
			$this->cache->save('oak_' .$key, $uids, $timecache);
		}

		echo date("H:i:s") ." admins\n\n";
		unset($adminqu);
		unset($adminlist);

		// ------------------

		$users = $this->db
			->where('anonymous', FALSE)
		->get('user');

		foreach($users->result() as $user){
			$key = 'user_' .$user->telegramid;
			$this->cache->save('oak_' .$key, $user, 15);
			// echo $key ."\n";
		}

		echo date("H:i:s") ." users\n\n";
		unset($users);
		// var_dump($this->cache->cache_info());

	}

	function validacionesatope(){
		set_time_limit(86400);
		$query = $this->db
			->select('telegramid')
			->where('verified', false)
			->where('blocked', false)
			->where('anonymous', false)
		->get('user');

		$users = array_column($query->result_array(), 'telegramid');
		// $users = [3458358];

		$str = "Todavía no estás validado.\nRecuerda volver a validarte para poder usar todas las funciones de Oak!\nTendrás más información y avisos en @ProfesorOakNews, te recomiendo que sigas al canal :)";
		foreach($users as $user){
			$this->telegram->send
				->chat($user)
				->notification(TRUE)
				->text($str)
				->inline_keyboard()
					->row_button("Quiero validarme", "Quiero validarme", "TEXT")
				->show()
			->send();
			echo $user ."\n";
			sleep(1);
		}
	}


	function adiosmigente(){
		set_time_limit(86400);
		$query = $this->db
			->select('telegramid')
			->order_by('RAND()')
		->get('user');

		$users = array_column($query->result_array(), 'telegramid');
		// $users = [3458358];

		foreach($users as $user){
			$info = $this->telegram->send->get_chat($user);
			$flag = NULL;
			if($info and empty(trim($info['first_name']))){ $flag = "deleted"; }
			elseif(!$info){ $flag = "bot_blocked"; }
			if($flag){
				$data = ['user' => $user, 'value' => $flag];
				$this->db->insert('user_flags', $data);
			}
			usleep(250000);
		}
	}


	function actualiza(){
		die();
		$data = json_decode(file_get_contents('nudata'), TRUE);

		foreach($data as $pk){
			$update = [
				'attack' => $pk[2],
				'defense' => $pk[3],
				'stamina' => $pk[1],
				'candy' => $pk[4]
			];
			$pokemon = $pk[0];

			$this->db
				->where('id', $pokemon)
			->update('pokedex', $update);
		}
	}

	function valista(){
		/*
		 * select u.username, count(*) from user_verify_vote v
		 * join user u on v.telegramid = u.telegramid
		 * where v.date between "2018-01-14 00:00:00" and "2018-01-14 23:59:59"
		 * group by v.telegramid
		 */

		$today = $this->db
			->select('u.username AS username, count(*) AS count')
			->from('user_verify_vote v')
			->join('user u', 'v.telegramid = u.telegramid')
			->where('v.date >=', date("Y-m-d 00:00:00"))
			->where('v.date <=', date("Y-m-d 23:59:59"))
			->group_by('v.telegramid')
		->get();
		
		$total = $this->db
			->select('u.username AS username, count(*) AS count')
			->from('user_verify_vote v')
			->join('user u', 'v.telegramid = u.telegramid')
			->where('v.date >=', "2018-01-12")
			->group_by('v.telegramid')
			->order_by('count', 'DESC')
		->get();

		$cfin = $this->db
			->select('count(*) AS count')
			->where('date_finish >=', date("Y-m-d 00:00:00"))
			->where('date_finish <=', date("Y-m-d 23:59:59"))
		->get('user_verify');

		$cadd = $this->db
			->select('count(*) AS count')
			->where('date_add >=', date("Y-m-d 00:00:00"))
			->where('date_add <=', date("Y-m-d 23:59:59"))
		->get('user_verify');

		$todayArr = array_column($today->result_array(), 'count', 'username');
		$totalArr = array_column($total->result_array(), 'count', 'username');
		$cfin = $cfin->row()->count;
		$cadd = $cadd->row()->count;

		$str = "";
		foreach($totalArr as $user => $atotal){
			$str .= "<code>$atotal</code> ";
			if(array_key_exists($user, $todayArr)){
				$str .= "(+" .$todayArr[$user] .") ";
			}
			$str .= $user ."\n";
		}

		// $str .= "\n" ."#ranking del " .date("m-d");
		$str .= "\n" ."IN: $cadd ($cfin)";

		$this->telegram->send
			->chat("-1001108551764")
			// ->chat($this->config->item('creator'))
			->text($str, 'HTML')
		->send();
	}

	function viguense(){
		set_time_limit(86400);
		$news = $this->db
			->select('uid')
			->where('cid', '-1001291546747')
		->get('user_inchat');
		$news = array_column($news->result_array(), 'uid');

		$users = $this->db
			->select('uid')
			->where('cid', '-1001146489804')
			->where_in('uid', $news)
			->where('uid !=', $this->config->item('telegram_bot_id'))
		->get('user_inchat');

		$users = array_column($users->result_array(), 'uid');

		// $this->telegram->send->text( (count($users)) )->chat('3458358')->send();
		//

		foreach($users as $user){
			$this->telegram->send
				->kick($user, '-1001146489804');
			usleep(300000);
		}

	}

	function fantasiafinal(){
		$url = "https://na.finalfantasyxiv.com/lodestone/worldstatus/";
		$data = file_get_contents($url);
		$pos1 = strpos($data, 'Ragnarok');
		$pos2 = strpos($data, 'Light');
		$ragnarok = substr($data, $pos1, $pos2 - $pos1);

		$status = 'Unknown';
		foreach(['Unavailable', 'Available'] as $stat){
			if(strpos($ragnarok, $stat) !== FALSE){
				$status = $stat;
				break;
			}
		}

		$this->telegram->send
			->text("Status: $status")
			->chat($this->config->item('creator'))
		->send();
	}


	function existe($user){
		$r = $this->telegram->send->get_chat($user);
		$exists = !empty($r['first_name']);
		$this->telegram->send
			->text($user ." " .(!$exists ? "<b>NO </b>" : "") ."existe.", 'HTML')
			->chat("-273852147")
			->notification(!$exists)
		->send();
	}
}
