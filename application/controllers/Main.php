<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {

	public function __construct(){
		  parent::__construct();
	}

	public function index($access = NULL){
		// comprobar IP del host
		if(strpos($_SERVER['REMOTE_ADDR'], "149.154.167.") === FALSE){ die(); }

		// iniciar variables
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$this->log(json_encode( $telegram->dump() ));

		// Actualizamos datos de chat
		$this->_update_chat();

		// Cancelar acciones sobre comandos provenientes de mensajes de channels. STOP SPAM.
		if($telegram->has_forward && $telegram->forward_type("channel")){ return; }

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

		// si el usuario existe, proceder a interpretar el mensaje
		if($pokemon->user_exists($telegram->user->id)){
			$this->_begin();
			return;
		}
	}

	// interpretar mensajes de usuarios verificados
	function _begin(){
		// TODO hay que reducir la complejidad de esta bestialidad de funcion ^^
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$user = $telegram->user;
		$text = $telegram->text();

		$pokeuser = $pokemon->user($user->id);
		$step = $pokemon->step($user->id);

		// terminar si el usuario no esta verificado o esta en la blacklist
		if($pokemon->user_blocked($user->id)){ return; }

		// Cancelar pasos en general.
		if($step != NULL && $telegram->text_has(["Cancelar", "Desbugear", "/cancel"], TRUE)){
			$pokemon->step($user->id, NULL);
			$this->telegram->send
				->notification(FALSE)
				->keyboard()->selective(FALSE)->hide()
				->text("Acción cancelada.")
			->send();
			die();
		}

		if(!empty($step)){ $this->_step(); }

		$this->plugin->load_all();

		/*
		// Marcar otro usuario (solo creador)
		if($telegram->text_has("Éste", TRUE) && $telegram->has_reply && $telegram->user->id == $this->config->item('creator')){
		    $reply = $telegram->reply_user;
		    $word = $telegram->last_word();


		    // marcar de un color
		    if(in_array(strtolower($word), ["rojo", "azul", "amarillo"])){
		        if( $pokemon->register( $reply->id, $word ) !== FALSE){
		            $name = trim("$reply->first_name $reply->last_name");
		            $telegram->send
		                ->notification(FALSE)
		                ->text("Vale jefe, marco a $name como *$word*!", TRUE)
		            ->send();
		            $pokemon->update_user_data($reply->id, 'fullname', $name);
		        }elseif($pokemon->user_exists( $reply->id )){
		            $telegram->send
		                ->notification(FALSE)
		                ->text("Con que un topo, eh? ¬¬ Bueno, ahora es *$word*.\n_Cuidadín, que te estaré vigilando..._", TRUE)
		            ->send();
		            $pokemon->update_user_data($reply->id, 'team', $pokemon->team_text($word));
		        }
		    }

		    // guardar nombre del user
		    elseif($telegram->text_has("se llama")){

		        if($pokemon->user_exists($word)){
		            $telegram->send
		                ->notification(FALSE)
		                ->reply_to(TRUE)
		                ->text("Oye jefe, que ya hay alguien que se llama así :(")
		            ->send();
		        }else{
		            $pokemon->update_user_data($reply->id, 'username', $word);
		            $this->analytics->event('Telegram', 'Register username');
		            $str = "De acuerdo, *@$word*!\n"
		                    ."¡Recuerda *validarte* para poder entrar en los grupos de colores!";
		            $telegram->send
		                ->notification(FALSE)
		                ->text($str, TRUE)
		            ->send();
		        }
		    }

		    // guardar nivel del user
		    elseif($telegram->text_has("es nivel")){
		        if(is_numeric($word) && $word >= 5 && $word <= 40){
		            $this->analytics->event('Telegram', 'Change level', $word);
		            $pokemon->update_user_data($reply->id, 'lvl', $word);
		        }
		    }

		    return;
		}

		*/




		/*
		##################
		# Comandos admin #
		##################
		*/

		if($telegram->text_contains("mal") && $telegram->words() < 4 && $telegram->has_reply){
			$telegram->send
				->chat($telegram->chat->id)
				->notification(FALSE)
				->message($telegram->reply->message_id)
				->text("Perdon :(")
			->edit('message');
			return;
		}elseif(
			( $telegram->text_has(["link", "enlace"], ["del grupo", "de este grupo", "grupo"]) or
			$telegram->text_has(["/linkgroup", "/grouplink"], TRUE))
		){
			$colores_full = [
				'Y' => ['amarillo', 'instinto', 'yellow', 'instinct'],
				'R' => ['rojo', 'valor', 'red'],
				'B' => ['azul', 'sabiduría', 'blue', 'mystic'],
			];

			if($telegram->text_url()){ return; }

			$team = NULL;
			foreach($colores_full as $k => $colores){
				if($telegram->text_has($colores)){ $team = $k; break; }
			}

			$pokesuer = $pokemon->user($telegram->user->id);

			if($team && ($pokeuser->team == $team or $telegram->user->id == $this->config->item('creator'))){
				$pairteam = $pokemon->settings($telegram->chat->id, 'pair_team_' .$team);
				if(!$pairteam){ return; }
				$teamchat = $pokemon->group_pair($telegram->chat->id, $team);
				if(!$teamchat or $pairteam != sha1($telegram->chat->id .":" .$teamchat)){ // HACK Algoritmo de verificación
					$telegram->send
						->chat($this->config->item('creator'))
						->text("MEEEEEC en " .$telegram->chat->id ." con $team")
					->send();
					return;
				}

				// Tengo chat, comprobar blacklist
				$black = explode(",", $pokemon->settings($teamchat, 'blacklist'));
				if($pokemon->user_flags($telegram->user->id, $black)){ return; }

				$teamlink = $pokemon->settings($teamchat, 'link_chat');

				// Si es validado
				$color = ['Y' => 'Amarillo', 'R' => 'Rojo', 'B' => 'Azul'];
				$text = "Hay un grupo de tu team *" .$color[$team] ."*, pero no te puedo invitar porque no estás validado " .$telegram->emoji(":warning:") .".\n"
						."Si *quieres validarte*, puedes decirmelo. :)";
				if($pokeuser->verified){
					$text = "Te invito al grupo *" .$color[$team] ."* asociado a " .$telegram->chat->title .". "
							."¡No le pases este enlace a nadie!\n"
							.$telegram->grouplink($teamlink);
				}

				$telegram->send
					->notification(TRUE)
					->chat($telegram->user->id)
					->text($text, NULL) // TODO NO Markdown.
				->send();

				if($pokeuser->verified){
					$telegram->send
						->notification(TRUE)
						->chat($teamchat)
						->text("He invitado a @" .$pokeuser->username ." a este grupo.")
					->send();
				}
				return;
			}

			$link = $pokemon->settings($telegram->chat->id, 'link_chat');
			$word = $telegram->last_word(TRUE);

			if(!$team && !is_numeric($word) and strlen($word) >= 4 and !$telegram->text_has("este")){ // XXX comprobar que no dé problemas
				$s = $pokemon->group_link($word);
				if(!empty($s)){ $link = $s; }
			}
			$chatgroup = NULL;
			if(!empty($link)){ $chatgroup = $telegram->grouplink($link); }
			if(!empty($chatgroup)){
				$this->analytics->event('Telegram', 'Group Link');
				$telegram->send
					->notification(FALSE)
					->disable_web_page_preview()
					->text("Link: $chatgroup")
				->send();
			}
			return;
		}

		// ---------------------
		// Apartado de cuenta
		// ---------------------


		// guardar nombre de user
		if($telegram->text_has(["Me llamo", "Mi nombre es", "Mi usuario es"], TRUE) && $telegram->words() <= 4 && $telegram->words() > 2){
			if(!empty($pokeuser->username)){ return; }
			$word = $telegram->last_word(TRUE);
			$this->_set_name($user->id, $word, FALSE);
			return;
		}

		// pedir info sobre uno mismo
		elseif(
			$telegram->text_has(["Quién soy", "Cómo me llamo", "who am i"], TRUE) or
			($telegram->text_has(["profe", "oak"]) && $telegram->text_has("Quién soy") && $telegram->words() <= 5)
		){
			$str = "";
			$team = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];

			$this->analytics->event('Telegram', 'Whois', 'Me');
			if(empty($pokeuser->username)){ $str .= "No sé como te llamas, sólo sé que "; }
			else{ $str .= '$pokemon, '; }

			$str .= 'eres *$team* $nivel. $valido';

			// si el bot no conoce el nick del usuario
			if(empty($pokeuser->username)){ $str .= "\nPor cierto, ¿cómo te llamas *en el juego*? \n_Me llamo..._"; }

			$chat = ($telegram->is_chat_group() && $this->is_shutup() && !in_array($telegram->user->id, $this->admins(TRUE)) ? $telegram->user->id : $telegram->chat->id);

			$repl = [
				'$nombre' => $new->first_name,
				'$apellidos' => $new->last_name,
				'$equipo' => $team[$pokeuser->team],
				'$team' => $team[$pokeuser->team],
				'$usuario' => "@" .$new->username,
				'$pokemon' => "@" .$pokeuser->username,
				'$nivel' => "L" .$pokeuser->lvl,
				'$valido' => ($pokeuser->verified ? ':green-check:' : ':warning:')
			];

			$str = str_replace(array_keys($repl), array_values($repl), $str);
			if($pokemon->settings($user->id, 'last_command') == "LEVELUP"){
				if($chat != $telegram->chat->id){
					/* $telegram->send
						->chat($this->config->item('creator'))
						->text("Me revelo contra " .$pokeuser->username ." " .$user->id ." en " .$telegram->chat->id)
					->send();

					$str = "¿Eres tonto o que? Ya te lo he dicho antes. ¿Puedes parar ya?"; */
				}
			}
			$pokemon->settings($user->id, 'last_command', 'WHOIS');

			$telegram->send
				->chat($chat)
				->reply_to( ($chat == $telegram->chat->id) )
				->notification(FALSE)
				->text($telegram->emoji($str), TRUE)
			->send();
		}
		// Si pregunta por Ash...
		elseif($telegram->text_has("quién es Ash") && $telegram->words() <= 7){
			$this->analytics->event('Telegram', 'Jokes', 'Ash');
			$telegram->send->text("Ah! Ese es un *cheater*, es nivel 100...\nLo que no sé de dónde saca tanto dinero para viajar tanto...", TRUE)->send();
			return;
		}
		// si pregunta por un usuario
		elseif(
			( $telegram->text_has("quién", ["es", "eres"]) or
			$telegram->text_has("Conoces", "a") ) &&
			!$telegram->text_contains(["programa", "esta"]) &&
			$telegram->words() <= 5
		){
			$str = "";
			$teams = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];
			// pregunta usando respuesta
			if($telegram->has_reply){
				$this->analytics->event('Telegram', 'Whois', 'Reply');
				// si el usuario por el que se pregunta es el bot
				if($telegram->reply_user->id == $this->config->item("telegram_bot_id") && !$telegram->reply_is_forward){
					$str = "Pues ese soy yo mismo :)";
				// HACK Un bot no detecta reply de otro bot.
				// }elseif(strtolower(substr($telegram->reply_user->username, -3)) == "bot"){
				//	$str = "Es un bot.";
				}else{
					$user_search = $telegram->reply_user->id;
					if($telegram->reply_is_forward && $telegram->reply_user->id != $telegram->reply->forward_from->id){
						$user_search = $telegram->reply->forward_from['id']; // FIXME -> to object?
					}

					// si el usuario es desconocido
					$info = $pokemon->user( $user_search );
					if(empty($info)){
						$str = "No sé quien es.";
					}else{
						// si no se conoce el nick pero si el equipo
						if(empty($info->username)){ $str .= "No sé como se llama, sólo sé que "; }
						// si se conoce el equipo
						// $telegram->user->id == $this->config->item('creator')
						elseif($pokeuser->authorized){ $str .= "@$info->username, "; } //  FIXME - anti report trolls

						$str .= 'es *$team* $nivel.' ."\n";

						$flags = $pokemon->user_flags($info->telegramid);

						// añadir emoticonos basado en los flags del usuario
						if($info->verified){ $str .= $telegram->emoji(":green-check: "); }
						else{ $str .= $telegram->emoji(":warning: "); }
						// ----------------------
						if($info->blocked){ $str .= $telegram->emoji(":forbid: "); }
						if($info->authorized){ $str .= $telegram->emoji(":star: "); }
						if(in_array("ratkid", $flags)){ $str .= $telegram->emoji(":mouse: "); }
						if(in_array("multiaccount", $flags)){ $str .= $telegram->emoji(":multiuser: "); }
						if(in_array("gps", $flags)){ $str .= $telegram->emoji(":satellite: "); }
						if(in_array("bot", $flags)){ $str .= $telegram->emoji(":robot: "); }
						if(in_array("rager", $flags)){ $str .= $telegram->emoji(":fire: "); }
						if(in_array("troll", $flags)){ $str .= $telegram->emoji(":joker: "); }
						if(in_array("spam", $flags)){ $str .= $telegram->emoji(":spam: "); }
						if(in_array("hacks", $flags)){ $str .= $telegram->emoji(":laptop: "); }
						if(in_array("enlightened", $flags)){ $str .= $telegram->emoji(":frog: "); }
						if(in_array("resistance", $flags)){ $str .= $telegram->emoji(":key: "); }
					}
				}
			}
			// pregunta usando nombre
			elseif(
				// ( ($telegram->words() == 3) or ($telegram->words() == 4 && $telegram->last_word() == "?") ) and
				( $telegram->text_has("quién es") or $telegram->text_has("conoces a") )
			){
				$this->analytics->event('Telegram', 'Whois', 'User');
				if($telegram->text_mention()){ $text = $telegram->text_mention(); if(is_array($text)){ $text = key($text); } } // CHANGED Siempre coger la primera mención
				elseif($telegram->words() == 4){ $text = $telegram->words(2); } // 2+1 = 3 palabra
				else{ $text = $telegram->last_word(); } // Si no hay mención, coger la última palabra
				$text = $telegram->clean('alphanumeric', $text);
				if(strlen($text) < 4){ return; }
				if(in_array(strtolower($text), ["quien", "quién"])){ return; }
				$pk = $this->parse_pokemon();
				if(!empty($pk['pokemon'])){ $this->_pokedex($pk['pokemon']); return; }
				$info = $pokemon->user($text);

				// si es un bot
				if(strtolower(substr($text, -3)) == "bot"){
					$str = "Es un bot."; // Yo no me hablo con los de mi especie.\nSi, queda muy raro, pero nos hicieron así...";
				// si no se conoce
				}elseif(empty($info)){
					$str = "No sé quien es $text.";
					// User offline
					$info = $pokemon->user_offline($text);
					if(!empty($info)){ $str = 'Es *$team* $nivel. :question-red:'; }
				}else{
					$str = 'Es *$team* $nivel. $valido';
				}
			}

			if(!empty($str)){
			$chat = ($telegram->is_chat_group() && $this->is_shutup() && !in_array($telegram->user->id, $this->admins(TRUE)) ? $telegram->user->id : $telegram->chat->id);

			$repl = [
				// '$nombre' => $new->first_name,
				// '$apellidos' => $new->last_name,
				'$equipo' => $teams[$info->team],
				'$team' => $teams[$info->team],
				// '$usuario' => "@" .$new->username,
				'$pokemon' => "@" .$info->username,
				'$nivel' => "L" .$info->lvl,
				'$valido' => ($info->verified ? ':green-check:' : ':warning:')
			];

			$str = str_replace(array_keys($repl), array_values($repl), $str);
			$this->last_command('WHOIS');
			// $pokemon->settings($user->id, 'last_command', 'WHOIS');

			// $telegram->send->chat($this->config->item('creator'))->text($text->emoji($str))->send();

				$telegram->send
					->chat($chat)
					->reply_to( (($chat == $telegram->chat->id && $telegram->has_reply) ? $telegram->reply->message_id : NULL) )
					->notification(FALSE)
					->text($telegram->emoji($str), TRUE)
				->send();
			}
			return;

		}elseif($telegram->text_has("estoy aquí")){
			// Quien en cac? Que estoy aquí

		// ---------------------
		// Información General Pokemon
		// ---------------------

		}elseif($telegram->text_has("Lista de", ["enlaces", "links"], TRUE)){
			$str = "";
			$links = $pokemon->link("ALL");
			$str = implode("\n- ", array_column($links, 'name'));
			$telegram->send
				->notification(FALSE)
				->text("- " .$str)
			->send();
			return;
		}elseif(
			$telegram->text_has(["Enlace", "Link"], TRUE) or
			$telegram->text_has(["/enlace", "/link"], TRUE) and
			!$telegram->text_contains("http") // and
			// $telegram->words() < 6
		){
			$text = $telegram->text();
			$text = explode(" ", $text);
			unset($text[0]);
			$command = trim(strtolower($telegram->last_word(TRUE)));

			if(in_array($command, ["aquí", "aqui"])){
				$chat = $telegram->chat->id;
				unset( $text[end(array_keys($text))] );
			}
			else{ $chat = $telegram->user->id; }

			$text = implode(" ", $text);
			$text = trim(strtolower($text));

			$link = $pokemon->link($text);
			if(!empty($link) && count($link) == 1){
				$telegram->send
					->chat($chat)
					->text($link)
				->send();
			}elseif(is_numeric($link) or count($link) > 1){
				$telegram->send
					->chat($chat)
					->text("Demasiadas coincidencias. Vuelve a probar.")
				->send();
			}

			return;
		}

		// Ver los IV o demás viendo stats Pokemon.
		elseif($telegram->text_has(["tengo", "me ha salido", "calculame", "calcula iv", "calcular iv", "he conseguido", "he capturado"], TRUE) && $telegram->words() >= 4){
			$pk = $this->parse_pokemon();
			// TODO contar si faltan polvos o si se han especificado "caramelos" en lugar de polvos, etc.
			if(!empty($pk['pokemon'])){
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
							$text = "Los cálculos no me salen...\n¿Seguro que me has dicho bien los datos?";
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

		}elseif($telegram->text_has(["debilidad", "debilidades", "fortaleza", "fortalezas"], ["contra", "hacia", "sobre", "de"]) && $telegram->words() <= 6){
			$chat = NULL;
			$filter = (strpos($telegram->text(), "/") === FALSE); // Si no hay barra, filtra.
			if(in_array($telegram->words(), [3,4]) && $telegram->text_has("aquí", FALSE)){
				$text = $telegram->words(2, $filter);
				$chat = ($telegram->is_chat_group() && $this->is_shutup() ? $telegram->user->id : $telegram->chat->id);
			}else{
				$text = $telegram->last_word($filter);
				$chat = $telegram->user->id;
			}
			$pk = $this->parse_pokemon();
			if(!empty($pk['pokemon'])){ $text = $pk['pokemon']; }
			$this->analytics->event('Telegram', 'Search Pokemon Attack', ucwords(strtolower($text)));
			$target = $telegram->text_contains("fortaleza");
			$this->_poke_attack($text, $chat, $target);
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
		}elseif($telegram->text_has("mejor", ["ataque", "habilidad", "skill"])){
			$pk = $this->parse_pokemon();
			if(!empty($pk['pokemon'])){
				$pokedex = $pokemon->pokedex($pk['pokemon']);
				$skills = $pokemon->skill_learn($pk['pokemon']);
				$sel = NULL;
				$min = 0;
				foreach($skills as $k => $skill){
					if($skill->attack > $min){
						$min = $skill->atttack;
						$sel = $k;
					}
				}
				// $chat = ($this->is_shutup() ? $telegram->user->id : $telegram->chat->id);
				$text = "El mejor ataque de *" .$pokedex->name ."* es *" .$skills[$sel]->name_es ."*, con " .$skills[$sel]->attack ." ATK y " .$skills[$sel]->bars ." barras.";
				$telegram->send
					// ->chat($chat)
					->notification(FALSE)
					->text($text, TRUE)
				->send();
			}
			return;
		}elseif($telegram->text_has(["pokédex", "pokémon"], TRUE) or $telegram->text_command("pokedex")){
			$text = $telegram->text();
			$chat = ($telegram->text_has("aqui") && !$this->is_shutup() ? $telegram->chat->id : $telegram->user->id);
			/* if($telegram->text_has("aquí")){
				$word = $telegram->words( $telegram->words() - 2 );
			} */
			$this->_pokedex($text, $chat);

		// ---------------------
		// Utilidades varias
		// ---------------------

		}elseif($telegram->text_has(["crear", "organizar", "hacer una", "hacemos una"], ["quedada", "reunión"])){
			$date = $this->parse_time();
			if(isset($date['date'])){ // && $date['left_hours'] > 1
				$this->analytics->event("Telegram", "Create meeting");
				$private = ($telegram->text_has(["privada", "no pública"]));
				$rand = [
					"¡Me parece genial!",
					"¡Estupendo!",
				];
				$n = mt_rand(0, count($rand) - 1);
				$str = $rand[$n] ." Quedada ";
				$str .= ($private ? "*privada*" : "*pública*") ." ";
				if($date['left_hours'] <= 10 && $date['left_hours'] > 0){ $str .= "dentro de *" .$date['left_hours'] ." horas*.\n"; }
				elseif($date['left_minutes'] <= 60){ $str .= "dentro de *" .$date['left_minutes'] ." minutos*.\n"; }
				else{ $str .= "el *" .date("d/m", strtotime($date['date'])) ."* a las *" .date("H:i", strtotime($date['hour'])) ."*.\n"; }
				$str .= "Dime en qué lugar váis a quedar (escrito o ubicación).";

				$telegram->send
					->force_reply(TRUE)
					->text($str, TRUE)
				->send();

				$pokemon->settings($telegram->user->id, 'meeting_private', $private);
				$pokemon->settings($telegram->user->id, 'meeting_date', serialize($date));
				$pokemon->step($telegram->user->id, 'MEETING_LOCATION');
				return;
			}

		// ---------------------
		// Administrativo
		// ---------------------

		}elseif($telegram->is_chat_group() && $telegram->text_has("dump") && $user->id == $this->config->item('creator') && $telegram->words() == 2){
			$u = $telegram->last_word(TRUE);
			// $data = $pokemon->user($u);
			$str = NULL;
			/* if(empty($data)){
				$str = "nope";
			}else{ */
				$find = $telegram->send->get_member_info($u, $u);
				$str = json_encode($find);
			// }
			$telegram->send
				->notification(FALSE)
				->text($str)
			->send();
			return;
		}elseif($telegram->text_has(["team", "equipo"]) && $telegram->text_has(["sóis", "hay aquí", "estáis"])){
			exit();
		}elseif($telegram->text_has(["busca", "buscar", "buscame"], ["pokeparada", "pokeparadas", "pkstop", "pkstops"])){
			$this->_locate_pokestops();
			return;
		}elseif($telegram->text_has(["pokemon", "pokemons", "busca", "buscar", "buscame"]) && $telegram->text_contains("cerca") && $telegram->words() <= 10){
			$this->_locate_pokemon();
			return;
		}
		// ---------------------
		// Chistes y tonterías
		// ---------------------

		$joke = NULL;

		$this->plugin->load('games');

		if($telegram->text_has("Gracias", ["profesor", "Oak", "profe"]) && !$telegram->text_has("pero", "no")){
			// "el puto amo", "que maquina eres"
			$this->analytics->event('Telegram', 'Jokes', 'Thank you');
			$frases = ["De nada, entrenador! :D", "Nada, para eso estamos! ^^", "Gracias a ti :3"];
			$n = mt_rand(0, count($frases) - 1);

			$joke = $frases[$n];
		}elseif($telegram->text_has(["buenos", "buenas", "bon"], ["días", "día", "tarde", "tarda", "tardes", "noches", "nit"])){
			/* if(
				($telegram->is_chat_group() and $pokemon->settings($telegram->chat->id, 'say_hello') == TRUE) and
				($pokemon->settings($telegram->user->id, 'say_hello') != FALSE or $pokemon->settings($telegram->user->id, 'say_hello') == NULL)
			){*/
			if($this->is_shutup()){ return; }
			$joke = "Buenas a ti también, entrenador! :D";
			if($telegram->text_has(['noches', 'nit'])){
				$joke = "Buenas noches fiera, descansa bien! :)";
			}
		}

		if(!empty($joke)){
			$telegram->send
				->notification(FALSE)
				->text($joke, TRUE)
			->send();

			exit();
		}

		/* if($telegram->text_has("compartir", TRUE)){
			$texto = $telegram->words(1, 10);
			$telegram->send
				->inline_keyboard()
					->row_button("Compartir", $texto, "SHARE")
				->show()
				->text("Compártelo con tus amigos!")
			->send();
			return;
		} */

		// Recibir ubicación
		if($telegram->location() && !$telegram->is_chat_group()){
		    $loc = implode(",", $telegram->location(FALSE));
		    $pokemon->settings($user->id, 'location', $loc);
		    $pokemon->step($user->id, 'LOCATION');
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


		if($telegram->text_has(["aquí hay un", "ahi hay", "hay un"], TRUE) and $telegram->has_reply and $telegram->is_chat_group()){
			// $telegram->send->text("ke dise?")->send();
			if(isset($telegram->reply->location)){
				$loc = $telegram->reply->location['latitude'] ."," .$telegram->reply->location['longitude'];
				$pk = $this->parse_pokemon();
				if(!empty($pk['pokemon'])){
					$pokemon->settings($user->id, 'pokemon_select', $pk['pokemon']);
					$pokemon->settings($user->id, 'location', $loc);
					$pokemon->step($user->id, 'POKEMON_SEEN');
					$this->_step();
				}
				if($telegram->text_contains(["cebo", "lure"])){
					$pokemon->settings($user->id, 'location', $loc);
					$pokemon->step($user->id, 'LURE_SEEN');
					$this->_step();
				}
			}
		}

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
			case 'RULES':
				if(!$telegram->is_chat_group()){ break; }
				if(!in_array($user->id, $admins)){ $pokemon->step($user->id, NULL); break; }

				$text = json_encode($telegram->text());
				if(strlen($text) < 4){ exit(); }
				if(strlen($text) > 4000){
					$telegram->send
						->text("Buah, demasiadas normas. Relájate un poco anda ;)")
					->send();
					exit();
				}
				$this->analytics->event('Telegram', 'Set rules');
				$pokemon->settings($telegram->chat->id, 'rules', $text);
				$telegram->send
					->text("Hecho!")
				->send();
				$pokemon->step($user->id, NULL);
				break;
			case 'WELCOME':
				if(!$telegram->is_chat_group()){ break; }
				if(!in_array($user->id, $admins)){ $pokemon->step($user->id, NULL); break; }

				$text = json_encode($telegram->text());
				if(strlen($text) < 4){ exit(); }
				if(strlen($text) > 4000){
					$telegram->send
						->text("Buah, demasiado texto! Relájate un poco anda ;)")
					->send();
					exit();
				}
				$this->analytics->event('Telegram', 'Set welcome');
				$pokemon->settings($telegram->chat->id, 'welcome', $text);
				$telegram->send
					->text("Hecho!")
				->send();
				$pokemon->step($user->id, NULL);
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
			case 'LOCATION':
				if($telegram->is_chat_group()){ return; }
				if($telegram->location()){
					// Comprobar si el location_now es distinto del location, para mostrar otro keyboard.
					$locnow = explode(",", $pokemon->settings($telegram->user->id, 'location_now'));
					$loccur = $telegram->location(FALSE);
					if(!empty($locnow)){
						$dist = $pokemon->location_distance($locnow, $loccur);
						if($dist <= 80){
							$pokemon->settings($telegram->user->id, 'location_now', implode(",", $loccur)); // HACK es bien?
							$telegram->send
								->notification(FALSE)
								->reply_to(TRUE)
								->text("Veo que sigues cerca de tu sitio. ¿Alguna novedad?")
								->keyboard()
									->row()
										->button($telegram->emoji(":map: Pokémon"))
										->button($telegram->emoji(":candy: PokéParadas"))
									->end_row()
									->row()
										->button($telegram->emoji(":mouse: ¡Un Pokémon!"))
										->button($telegram->emoji(":spiral: ¡Hay cebo!"))
									->end_row()
									->row_button("Cancelar")
								->selective(TRUE)
							->show(TRUE, TRUE)
							->send();
							exit();
						}
					}
					$telegram->send
						->notification(FALSE)
						->reply_to(TRUE)
						->text("¿Qué quieres hacer con esa ubicación?")
						->keyboard()
							// ->row_button($telegram->emoji(":mouse: He encontrado un Pokémon!"))
							->row_button($telegram->emoji(":pin: ¡Estoy aquí!"))
							->row()
								->button($telegram->emoji(":map: Ver Pokémon"))
								->button($telegram->emoji(":candy: Ver PokéParadas"))
							->end_row()
							// ->row_button($telegram->emoji(":home: Vivo aquí."))
							->row_button($telegram->emoji("Cancelar"))
							->selective(TRUE)
						->show(FALSE, TRUE)
					->send();
				}elseif($telegram->text()){
					$text = $telegram->emoji($telegram->words(0), TRUE);
					switch ($text) {
						case ':mouse:': // Pokemon Avistado
							$cd = $pokemon->settings($user->id, 'pokemon_cooldown');
							if(!empty($cd) && $cd > time()){
								$pokemon->step($user->id, NULL);
								$telegram->send
									->text("Es demasiado pronto para informar de otro Pokémon.\nTake it easy bro ;)")
									->keyboard()->hide(TRUE)
								->send();
								exit();
							}
							$pokemon->settings($user->id, 'step_action', 'POKEMON_SEEN');
							$pokemon->step($user->id, 'CHOOSE_POKEMON');
							$telegram->send
								->text("De acuerdo, dime qué Pokémon has visto aquí?")
								->keyboard()->hide(TRUE)
							->send();
							exit();
							break;
						case ':spiral:':
							$cd = $pokemon->settings($user->id, 'pokemon_cooldown');
							if(!empty($cd) && $cd > time()){
								$pokemon->step($user->id, NULL);
								$telegram->send
									->text("Es demasiado pronto para informar de un lure.\nTake it easy bro ;)")
									->keyboard()->hide(TRUE)
								->send();
								exit();
							}
							$pokemon->step($user->id, 'LURE_SEEN');
							$this->_step();
							break;
						case ':home:': // Set home
							$loc = $pokemon->settings($user->id, 'location');
							$pokemon->settings($user->id, 'location_home', $loc);
							$pokemon->step($user->id, NULL);
							$this->analytics->event('Telegram', 'Set home');
							$telegram->send
								->text("Hecho!")
								->keyboard()->hide(TRUE)
							->send();
						break;
						case ':pin:': // Set here
							$loc = $pokemon->settings($user->id, 'location');
							$here = $pokemon->settings($user->id, 'location_now', 'FULLINFO');
							$text = NULL;
							$error = FALSE;
							if(!empty($here)){
								$locs[] = explode(",", $loc);
								$locs[] = explode(",", $here->value);
								$t = time() - strtotime($here->lastupdate);
								$d = $pokemon->location_distance($locs[0], $locs[1]);
								// DEBUG $telegram->send->text($d)->send();
								if(
									($t <= 10) or
									($t <= 30 and $d >= 300) or
									($t <= 300 and $d >= 14000)
									// TODO formula km/h
								){
									$text = "¡No intentes falsificar tu ubicación! ¬¬";
									$error = TRUE;
									$pokemon->step($user->id, NULL);
								}
							}
							if(!$error){
								$this->analytics->event('Telegram', 'Set Current Location');
								$pokemon->settings($user->id, 'location_now', $loc);
								// TODO buscar a gente cercana.
								$loc = explode(",", $loc);
								$near = $pokemon->user_near($loc, 500, 30);

								$text = "¡Hecho! ¿Quieres hacer algo más?";
								if($telegram->user->id == $this->config->item('creator')){
									$telegram->send->text(json_encode($near))->send();
								}
								if(count($near) > 1){
									$text = "¡Tienes a *" .(count($near) - 1) ."* entrenadores por ahi cerca!\n¿Quieres hacer algo más?";
								}
							}

							if($error){ $telegram->send->keyboard()->hide(TRUE); }
							else{
								$telegram->send->keyboard()
									->row_button($telegram->emoji(":map: Ver los Pokémon cercanos"))
									->row()
										->button($telegram->emoji(":mouse: ¡Un Pokémon!"))
										->button($telegram->emoji(":spiral: ¡Hay cebo!"))
									->end_row()
									->row_button("Cancelar")
									->selective(TRUE)
								->show(TRUE, TRUE);
							}
							$telegram->send->text($text, TRUE)->send();
						break;
						case ':map:':
							$pokemon->step($user->id, NULL);
							$this->_locate_pokemon();
							exit();
						case ':candy:':
							$pokemon->step($user->id, NULL);
							$this->_locate_pokestops();
							exit();
						break;
						default:

						break;
					}
					exit();
				}
				break;
			case 'MEETING_LOCATION';
				$loc = NULL;
				// if(!$telegram->has_reply or $telegram->reply_user->id != $this->config->item('telegram_bot_id')){ return; }
				if($telegram->location()){
					$loc = $telegram->location()->latitude ."," .$telegram->location()->longitude;
				}elseif($telegram->text()){
					$loc = $telegram->text_encoded();
				}
				if($loc != NULL){
					$date = $pokemon->settings($telegram->user->id, 'meeting_date');
					$private = $pokemon->settings($telegram->user->id, 'meeting_private');
					if($date === NULL or $private === NULL){
						$telegram->send->text("Error general, cancelando acción.")->send();
					}else{
						$date = unserialize($date);
						$date = date("Y-m-d H:i:s", strtotime($date['date'] ." " .$date['hour']));
						$meeting = $pokemon->meeting_create($telegram->user->id, $date, $loc, $private);
						if($meeting){
							$chat = ($private ? $telegram->user->id : $telegram->chat->id);
							$text = "Para invitar a la gente envíales la siguiente clave:";
							if($private){ $text = "Te envío la clave por privado."; }
							$telegram->send
								->notification(TRUE)
								->text("De acuerdo, ¡quedada creada!\n$text")
							->send();
							$telegram->send
								->notification(TRUE)
								->chat($chat)
								->text("Quedada *$meeting*", TRUE)
							->send();
						}
					}

					$pokemon->settings($telegram->user->id, 'meeting_date', "DELETE");
					$pokemon->settings($telegram->user->id, 'meeting_private', "DELETE");
					$pokemon->step($telegram->user->id, NULL);
					exit();
				}
				break;
			case 'MEETING_JOIN':
				$text = $telegram->emoji($telegram->words(0), TRUE);
				$join = ($text == ":ok:");
				$meeting = $pokemon->settings($telegram->user->id, 'meeting');
				if(!empty($meeting)){
					$pokemon->meeting_join($telegram->user->id, $meeting, $join);
					$str = "Bueno, una pena... ¡Siempre estás a tiempo de volver a apuntarte!";
					if($join){ $str = "¡Genial! ¡Te espero allí! :D"; }

					$telegram->send
						->text($str)
						->keyboard()->hide(TRUE)
					->send();
				}

				$pokemon->settings($telegram->user->id, 'meeting', 'DELETE');
				$pokemon->step($telegram->user->id, NULL);
				exit();
				break;
			case 'SCREENSHOT_VERIFY':
				if(!$telegram->is_chat_group() && $telegram->photo()){
					if(empty($pokeuser->username) or $pokeuser->lvl == 1){
						$text = "Antes de validarte, necesito saber tu *nombre o nivel actual*.\n"
								.":triangle-right: *Me llamo ...*\n"
								.":triangle-right: *Soy nivel ...*";
						$telegram->send
							->notification(TRUE)
							->chat($telegram->user->id)
							->text($telegram->emoji($text), TRUE)
							->keyboard()->hide(TRUE)
						->send();
						exit();
					}

					$telegram->send
						->message(TRUE)
						->chat(TRUE)
						->forward_to($this->config->item('creator'))
					->send();

					$telegram->send
						->notification(TRUE)
						->chat($this->config->item('creator'))
						->text("Validar " .$user->id ." @" .$pokeuser->username ." L" .$pokeuser->lvl ." " .$pokeuser->team)
						->inline_keyboard()
							->row()
								->button($telegram->emoji(":ok:"), "te valido " .$pokeuser->telegramid, "TEXT")
								->button($telegram->emoji(":times:"), "no te valido")
							->end_row()
						->show()
					->send();

					$telegram->send
						->notification(TRUE)
						->chat($user->id)
						->keyboard()->hide(TRUE)
						->text("¡Enviado correctamente! El proceso de validar puede tardar un tiempo.")
					->send();

					$pokemon->step($user->id, NULL);
					exit();
				}
				break;
			case 'CUSTOM_COMMAND':
				if(!$telegram->is_chat_group() or !in_array($telegram->user->id, $this->admins(TRUE))){ return; }
				$command = $pokemon->settings($telegram->user->id, 'command_name');
				if(empty($command)){
					if($telegram->text()){
						$pokemon->settings($telegram->user->id, 'command_name', strtolower($telegram->text()) );
						$telegram->send
							->text("¡De acuerdo! Ahora envíame la respuesta que quieres enviar.")
						->send();
					}
					die(); // HACK
				}
				$cmds = $pokemon->settings($telegram->chat->id, 'custom_commands');
				if($cmds){ $cmds = unserialize($cmds); }
				if(!is_array($cmds) or empty($cmds)){
					$pokemon->settings($telegram->chat->id, 'custom_commands', "DELETE");
					$cmds = array();
				}

				if(isset($cmds[$command])){ unset($cmds[$command]); }
				if($telegram->text()){
					if(strlen(trim($telegram->text())) < 4){ return; }
					$cmds[$command] = ["text" => $telegram->text_encoded()];
				}elseif($telegram->photo()){
					$cmds[$command] = ["photo" => $telegram->photo()];
				}elseif($telegram->voice()){
					$cmds[$command] = ["voice" => $telegram->voice()];
				}elseif($telegram->gif()){
					$cmds[$command] = ["document" => $telegram->gif()];
				}elseif($telegram->sticker()){
					$cmds[$command] = ["sticker" => $telegram->sticker()];
				}

				$cmds = serialize($cmds);
				$pokemon->settings($telegram->chat->id, 'custom_commands', $cmds);
				$pokemon->settings($telegram->user->id, 'command_name', "DELETE");
				$pokemon->step($telegram->user->id, NULL);
				$telegram->send
					->text("¡Comando creado correctamente!")
				->send();
				break;
			case 'SPEAK':
				// DEBUG - FIXME
				if($telegram->is_chat_group()){ return; }
				if($telegram->callback){ return; }
				if($telegram->text() && substr($telegram->words(0), 0, 1) == "/"){ return; }
				$chattalk = $pokemon->settings($telegram->user->id, 'speak');
				if($telegram->user->id != $this->config->item('creator') or $chattalk == NULL){
					$pokemon->step($telegram->user->id, NULL);
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
				if($telegram->words() == 1){ $this->_set_name($user->id, $telegram->last_word(TRUE), TRUE); }
				$pokemon->step($user->id, NULL);
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

	// function _pokedex($chat = NULL){
	function _pokedex($text = NULL, $chat = NULL){
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;

		$this->last_command("POKEDEX");

		$types = $pokemon->attack_types();

		if($chat === NULL){ $chat = $telegram->chat->id; }
		if(!is_numeric($text)){
			$exp = explode(" ", $text);
			if(in_array(count($exp), [2, 3])){ // el aquí también cuenta
				$num = filter_var($exp[1], FILTER_SANITIZE_NUMBER_INT);
				if(is_numeric($num) && $num > 0 && $num < 152){ $text = $num; }
			}
			if(!is_numeric($text)){
				$poke = $this->parse_pokemon();
				$text = (!empty($poke['pokemon']) ? $poke['pokemon'] : NULL);
			}
		}

		if(empty($text)){ return; }
		$pokedex = $pokemon->pokedex($text);
		$str = "";
		if(!empty($pokedex)){
			$skills = $pokemon->skill_learn($pokedex->id);

			$str = "*#" .$pokedex->id ."* - " .$pokedex->name ."\n"
					.$types[$pokedex->type] .($pokedex->type2 ? " / " .$types[$pokedex->type2] : "") ."\n"
					."ATK " .$pokedex->attack ." - DEF " .$pokedex->defense ." - STA " .$pokedex->stamina ."\n\n";

			foreach($skills as $sk){
				$str .= "[" .$sk->attack ."/" .$sk->bars ."] - " .$sk->name_es  ."\n";
			}
		}

		if($pokedex->sticker && ($chat == $telegram->user->id)){
			$telegram->send
				->chat($chat)
				// ->notification(FALSE)
				->file('sticker', $pokedex->sticker);
		}
		if(!empty($str)){
			$telegram->send
				->chat($chat)
				// ->notification(FALSE)
				->text($str, TRUE)
			->send();
		}
	}

	function _poke_attack($text, $chat = NULL, $target_attack = FALSE){
		$telegram = $this->telegram;
		$types = $this->pokemon->attack_types();

		$this->last_command("ATTACK");

		if($chat === NULL){ $chat = $telegram->chat->id; }
		$str = "";
		// $specs = array();

		if(strpos(strtolower($text), "missing") !== FALSE){
			$telegram->send
				->notification(FALSE)
				->reply_to(TRUE)
				->text("Lo siento, no encuentro ese número. Es que me parece que se ha perdido.")
			->send();
			exit();
		}elseif(trim(strtolower($text)) == "mime"){
			$text = "Mr. Mime";
		}elseif($text[0] == "#" && is_numeric(substr($text, 1))){ // Si es número pero con #
			$text = substr($text, 1);
		}

		// $attack contiene el primer tipo del pokemon
		$pokemon = $this->pokemon->find($text);
		if($pokemon !== FALSE){
			$str .= "#" .$pokemon['id'] ." - *" .$pokemon['name'] ."* (*" .$types[$pokemon['type']] ."*" .(!empty($pokemon['type2']) ? " / *" .$types[$pokemon['type2']] ."*" : "") .")\n";
			$primary = $pokemon['type'];
			$secondary = $pokemon['type2'];
		}else{
			$str .= (!$target_attack ? "Debilidad " : "Fortaleza ");
			if(strpos($text, "/") !== FALSE){
				$text = explode("/", $text);
				if(count($text) != 2){ exit(); } // Hay más de uno o algo raro.
				$primary = trim($text[0]);
				$secondary = trim($text[1]);

				$str .= "*" .ucwords($primary) ."* / *" .ucwords($secondary) ."*:\n";
			}else{
				$primary = $text;
				$str .= "*" .ucwords($primary) ."*:\n";
			}

			$primary = $this->pokemon->attack_type($primary); // Attack es toda la fila, céntrate en el ID.
			if(empty($primary)){
				// $this->telegram->send("Eso no existe, ni en el mundo Pokemon ni en la realidad.");
				exit();
			}
			$primary = $primary['id'];

			if(!empty($secondary)){
				$secondary = $this->pokemon->attack_type($secondary);
				if(!empty($secondary)){ $secondary = $secondary['id']; }
			}
		}

		// $table contiene todos las relaciones donde aparezcan alguno de los dos tipos del pokemon
		$table = $this->pokemon->attack_table($primary);
		$target[] = $primary;
		if($secondary != NULL){
			$table = array_merge($table, $this->pokemon->attack_table($secondary));
			$target[] = $secondary;
		}

		// debil, muy fuerte
		// 0.5 = poco eficaz; 2 = muy eficaz
		$list = array();
		$type_target = ($target_attack ? "target" : "source");
		$type_source = ($target_attack ? "source" : "target");
		foreach($table as $t){
			if(in_array(strtolower($t[$type_source]), $target)){
				if($t['attack'] == 0.5){ $list[0][] = $types[$t[$type_target]]; }
				if($t['attack'] == 2){ $list[1][] = $types[$t[$type_target]]; }
			}
		}
		foreach($list as $k => $i){ $list[$k] = array_unique($list[$k]); } // Limpiar debilidades duplicadas
		$idex = 0;
		foreach($list[0] as $i){
			$jdex = 0;
			foreach ($list[1] as $j){
				if($i == $j){
					// $i y $j contienen el mismo tipo, hay contradicción
					unset($list[0][$idex]);
					unset($list[1][$jdex]);
				}
				$jdex++;
			}
			$idex++;
		}

		if(isset($list[0]) && count($list[0]) > 0){ $str .= (!$target_attack ? "Apenas le afecta *" : "Apenas es fuerte contra *") .implode("*, *", $list[0]) ."*.\n"; }
		if(isset($list[1]) && count($list[1]) > 0){ $str .= (!$target_attack ? "Le afecta mucho *" : "Es muy eficaz contra *") .implode("*, *", $list[1]) ."*.\n"; }

		$telegram->send
			->chat($chat)
			->notification( ($chat == $telegram->user->id) ) // Solo si es chat privado
			->text($str, TRUE)
		->send();
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

	function _locate_pokestops(){
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$pk = $this->parse_pokemon();
		$distance = (isset($pk['distance']) ? $pk['distance'] : 160);
		$loc = NULL;
		if($telegram->has_reply && isset($telegram->reply->location)){
			$loc = [$telegram->reply->location['latitude'], $telegram->reply->location['longitude']];
		}elseif($telegram->is_chat_group()){
			$loc = explode(",", $pokemon->settings($telegram->chat->id, 'location'));
			if(!empty($loc)){
				$dist2 = $pokemon->settings($telegram->chat->id, 'location_radius');
				if(!empty($dist2)){ $distance = $dist2; }
			}
		}
		if($loc === NULL){
			$loc = explode(",", $pokemon->settings($telegram->chat->id, 'location'));
			if(empty($loc) or count($loc) != 2){
				$telegram->send
					->notification(FALSE)
					->text("No tengo ninguna ubicación. ¿Me la mandas?")
					->keyboard()
						->row_button($telegram->emoji(":pin: Enviar ubicación"), FALSE)
					->show(TRUE, TRUE)
				->send();
				return;
			}
		}
		$stops = $pokemon->pokestops($loc, $distance, 500);
		$text = "No hay PokéParadas registradas ahí.";
		$found = FALSE;
		if(!empty($stops)){
			$found = TRUE;
			$text = (count($stops) >= 499 ? "¡Hay más de <b>500</b> PokéParadas ahi!" : "Hay unas <b>" .count($stops) ."</b> PokéParadas aproximadamente. Seguramente menos.") ."\n";
			$text .= "Las más cercanas son:\n";
			$lim = (count($stops) < 10 ? count($stops) : 10);
			for($i = 0; $i < $lim; $i++){
				$text .= "A <b>" .floor($stops[$i]['distance']) ."m</b> tienes " .$stops[$i]['title'] .".\n";
			}

			$pkuser = $pokemon->user($telegram->user->id);

			if($pkuser->team == 'Y'){ $color = "0xffee00"; }
			elseif($pkuser->team == 'B'){ $color = "0x0000aa"; }
			else{ $color = "0xff0000"; } // Red

			$url = "http://maps.googleapis.com/maps/api/staticmap?center=" .$loc[0] ."," .$loc[1] ."&zoom=17&scale=2&size=500x400&maptype=terrain&format=png&visual_refresh=true";
			$url .= "&markers=size:mid%7Ccolor:" .$color ."%7Clabel:P%7C" .$loc[0] ."," .$loc[1];
			for($i = 0; $i < $lim; $i++){
				$url .= "&markers=size:mid%7Ccolor:0xdd40ff%7Clabel:" .($i+1) ."%7C" .$stops[$i]['lat'] ."," .$stops[$i]['lng'];
			}
			$text .= '<a href="' .$url .'">IMG</a>';
		}
		$chat = ($telegram->is_chat_group() && $this->is_shutup() ? $telegram->user->id : $telegram->chat->id);
		$telegram->send
			->chat($chat)
			->keyboard()->hide(TRUE)
			->text($text, 'HTML')
		->send();
		if(!$found && $loc){
			$telegram->send
				->chat($this->config->item('creator'))
				->text("*!!* Busca pokeparadas en *" .implode(",", $loc) ."*", TRUE)
			->send();
		}
	}

	function _set_name($user, $name, $force = FALSE){
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;

		$pokeuser = $pokemon->user($user);
		if(empty($pokeuser)){ return; }
		if(!$force && !empty($pokeuser->username)){ return; }
		if($name[0] == "@"){ $name = substr($name, 1); }
		if(strlen($name) < 4 or strlen($name) > 18){ return; }

		// si el nombre ya existe
		if($pokemon->user_exists($name)){
			$telegram->send
				->reply_to(TRUE)
				->notification(FALSE)
				->text("No puede ser, ya hay alguien que se llama *@$name* :(\nHabla con @duhow para arreglarlo.", TRUE)
			->send();
			return FALSE;
		}
		// si no existe el nombre
		else{
			$this->analytics->event('Telegram', 'Register username');
			$pokemon->update_user_data($user, 'username', $name);
			$str = "De acuerdo, *@$name*!\n"
					."¡Recuerda *validarte* para poder entrar en los grupos de colores!";
			$telegram->send
				->inline_keyboard()
					->row_button("Validar perfil", "quiero validarme", TRUE)
				->show()
				->reply_to(TRUE)
				->notification(FALSE)
				->text($str, TRUE)
			->send();
		}
		return TRUE;
	}

	function parse_pokemon(){
		$pokemon = $this->pokemon;
		$user = $this->telegram->user;

		$pokemon->settings($user->id, 'pokemon_return', TRUE);
		$pokemon->step($user->id, 'POKEMON_PARSE');
		$pk = $this->_step();
		return $pk;
	}

	function parse_time(){
		$pokemon = $this->pokemon;
		$user = $this->telegram->user;

		$pokemon->settings($user->id, 'pokemon_return', TRUE);
		$pokemon->step($user->id, 'TIME_PARSE');
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

	function _meeting_text($meeting){
		$text = "Invitación al evento del *" .date("d/m", strtotime($meeting->date_event))  ."* a las *" .date("H:i", strtotime($meeting->date_event)) ."*.\n";
		$text .= "Organizado por @" .$this->pokemon->user($meeting->creator)->username ."\n";
		if(substr($meeting->location, 0, 1) == '"'){
			$text .= "Ubicación: " .json_decode($meeting->location);
		}else{
			$text .= "Ubicación por posición enviada.";
		}
		$count = $this->pokemon->meeting_members_count($meeting->id);
		$text .= "\nVan a ir *$count* personas.\n";
		$text .= "¿Te apuntas?";
		return $text;
	}

	function _blocked(){
		exit();
	}

	function _help(){

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
