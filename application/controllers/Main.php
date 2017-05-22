<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {

	public function __construct(){
		  parent::__construct();
	}

	public function index($access = NULL){
		// comprobar IP del host
		if(strpos($_SERVER['REMOTE_ADDR'], "149.154.167.") === FALSE){ die(); }
		// Kill switch for overloading.
		if(file_exists('die')){ die(); }

		// iniciar variables
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$this->log(json_encode( $telegram->dump() ));

		// Actualizamos datos de chat
		$this->_update_chat();

		if($this->telegram->is_chat_group()){
			$this->pokemon->load_settings($telegram->chat->id);
		}

		$this->load->model('plugin');
		$this->plugin->load_all(TRUE); // BASE

		$colores_full = [
			'Y' => ['amarillo', 'instinto', 'yellow', 'instinct'],
			'R' => ['rojo', 'valor', 'red'],
			'B' => ['azul', 'sabiduría', 'blue', 'mystic'],
		];
		$colores = array();
		foreach($colores_full as $c){
			foreach($c as $col){ $colores[] = $col; }
		}

		// Si el usuario no está registrado con las funciones básicas, fuera.
		if(!$pokemon->user_exists($telegram->user->id)){ return; }

		// Si el usuario está bloqueado, fuera.
		if($pokemon->user_blocked($telegram->user->id)){ return; }

		$this->pokemon->load_settings($telegram->user->id);

		$pokeuser = $pokemon->user($telegram->user->id);
		$step = $pokemon->step($telegram->user->id);

		if(date("G") == "0" && intval(date("i")) <= 7 && $telegram->words() == 1){
			$this->plugin->load('game_pole');
		}

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

		}elseif($telegram->text_has(["evolución", "evolucionar"])){
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
			$this->_locate_pokemon();
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
			$pokemon->step($telegram->user->id, 'SCREENSHOT_VERIFY');
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
					$telegram->send->text( $telegram->text(), 'Markdown' )->send();
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
		$query = $this->db
			->where('id', $chat->id)
		->get('chats');
		if($query->num_rows() == 1){
			// UPDATE
			$this->db
				->set('type', $chat->type)
				->set('title', @$chat->title)
				->set('last_date', date("Y-m-d H:i:s"))
				->set('active', TRUE)
				->set('messages', 'messages + 1', FALSE)
				->where('id', $chat->id)
			->update('chats');
		}else{
			$this->db
				->set('id', $chat->id)
				->set('type', $chat->type)
				->set('title', $chat->title)
				->set('active', TRUE)
				->set('register_date', date("Y-m-d H:i:s"))
			->insert('chats');
		}

		$query = $this->db
			->where('uid', $user->id)
			->where('cid', $chat->id)
		->get('user_inchat');
		if($query->num_rows() == 1){
			// UPDATE
			$this->db
				->where('uid', $user->id)
				->where('cid', $chat->id)
				->set('messages', 'messages + 1', FALSE)
				->set('last_date', date("Y-m-d H:i:s"))
			->update('user_inchat');
		}

		if($this->pokemon->user_exists($this->telegram->user->id)){
			if(isset($this->telegram->user->username) && !empty($this->telegram->user->username)){
				$this->pokemon->update_user_data($this->telegram->user->id, 'telegramuser', $this->telegram->user->username);
			}
		}
	}
}
