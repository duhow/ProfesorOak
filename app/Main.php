<?php

class Main extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		$this->core->load('Tools');
		$this->core->load('Pokemon');

		$this->chat->setVar('telegram', $this->telegram);
		if($this->chat->load()){
			$this->chat->active_member($this->user->id);
		}

		if($this->chat->settings('forward_interactive')){
			$this->forward_creator();
		}

		if($this->telegram->is_chat_group()){
			$this->core->load('Admin');
			$this->core->load('Group');
			global $Admin, $Group;

			if($this->telegram->data_received("migrate_to_chat_id")){
				$Admin->migrate_settings($this->telegram->migrate_chat, $this->chat->id);
				$this->chat->disable();
				$this->end();
			}

			if($this->chat->settings('forwarding_to')){ $Admin->forward_to_groups(); }
			if($this->chat->settings('antiflood')){ $Admin->antiflood(); }
			if($this->chat->settings('antispam') != FALSE && $this->telegram->text_url()){ $Admin->antispam(); }
			if($this->chat->settings('die') && $this->user->id != CREATOR){ $this->end(); }
			if($this->chat->settings('abandon')){ $Group->abandon(); }

			if($this->user->blocked){ $this->end(); }

			if($this->chat->settings('custom_commands')){ $Group->custom_commands(); }
			if($this->chat->settings('admin_chat')){
				// if($this->chat->settings('blackwords')){ $Admin->blackwords(); }
			}
			if($this->chat->settings('dubs')){ $this->core->load('GameDubs', TRUE); }
		}

		if($this->user->load() !== TRUE){
			// Solo puede registrarse o pedir ayuda por privado.
			$color = Tools::Color($this->telegram->text());
			if(
				($this->telegram->text_has(["Soy", "Equipo", "Team"]) && $color) or
				($color && $this->telegram->words() == 1)
			){
				$this->register($color);
			}elseif(
				$this->telegram->text_command("register") or
				($this->telegram->text_command("start") and
				!$this->telegram->is_chat_group())
			){
				$this->register(NULL);
			}elseif(
				$this->telegram->text_command("help") and
				!$this->telegram->is_chat_group()
			){
				$this->help();
			}
			$this->end();
		}

		// TODO Tiene que pasar TODOS los filtros (aparte de Admin).
		if($this->user->blocked){ $this->end(); }

		parent::run();
	}

	public function ping(){
		return $this->telegram->send
			->text("¡Pong!")
		->send();
	}

	public function help(){
		$this->telegram->send
			->text('¡Aquí tienes la <a href="http://telegra.ph/Ayuda-11-30">ayuda</a>!', 'HTML')
		->send();
	}

	public function register($team = NULL){
		$str = NULL;
		if($this->user->telegramid === NULL){
			if($team === NULL){
				$str = "Hola " .$this->user->telegram->first_name ."! ¿Puedes decirme qué color eres?\n"
				."<b>Di:</b> Soy...";
				if($this->telegram->is_chat_group()){
					$str = "Hola " .$this->user->telegram->first_name ."! Ábreme por privado para registrate! :)";
					$this->telegram->send
					->inline_keyboard()
						->row_button("Registrar", "https://t.me/ProfesorOak_bot")
					->show();
				}
			}elseif($team === FALSE){
				$this->telegram->send->reply_to(TRUE);
				$str = "No te he entendido bien...\n¿Puedes decirme sencillamente <b>soy rojo, soy azul</b> o <b>soy amarillo</b>?";
			}else{
				// Intentar registrar, ignorar si es anonymous.
				if($this->user->register($team) === FALSE){
					$this->telegram->send
						->text("Error general al registrar.")
					->send();
					$this->end();
				}
				if($this->user->load() !== FALSE){
					$this->user->step = "SETNAME";
					$str = "Muchas gracias " .$this->user->telegram->first_name ."! Por cierto, ¿cómo te llamas <b>en el juego</b>? \n<i>(Me llamo...)</i>";
				}
			}
		}elseif($this->user->username === NULL){
			$str = "Oye, ¿cómo te llamas? <b>Di:</b> Me llamo ...";
		}elseif($this->user->verified == FALSE){
			$str = $this->telegram->emoji(":warning:") ."¿Entiendo que quieres <b>validarte</b>?";
			$this->telegram->send
	        ->inline_keyboard()
	            ->row_button("Validar", "quiero validarme", TRUE)
	        ->show();
		}
		if(!empty($str)){
			$this->telegram->send
				->notification(FALSE)
				->text($str, 'HTML')
			->send();
		}
		$this->end();
	}

	private function setname($name, $user = NULL){
		if(empty($user)){ $user = $this->user; }
		if($user->step == "SETNAME"){ $user->step = NULL; }
		try {
			$user->username = $name;
		} catch (Exception $e) {
			$this->telegram->send
				->text("Ya hay alguien que se llama @$name. Habla con @duhow para arreglarlo.")
			->send();
			$this->end();
		}
		$str = "De acuerdo, @$name!\n"
				."¡Recuerda <b>validarte</b> para poder entrar en los grupos de colores!";
		$this->telegram->send
			->text($str, 'HTML')
		->send();
		return TRUE;
	}

	private function forward_creator(){
		return $this->telegram->send
			->notification(FALSE)
			->chat($this->telegram->chat->id)
			->message(TRUE)
			->forward_to(CREATOR)
		->send();
	}

	private function forward_groups($to){
		/* if($this->telegram->user_in_chat($this->telegram->bot->id, $chat_forward)){ // Si el Oak está en el grupo forwarding
			// $forward = new Chat($to);
			$chat_accept = explode(",", $pokemon->settings($chat_forward, 'forwarding_accept'));
			if(in_array($telegram->chat->id, $chat_accept)){ // Si el chat actual se acepta como forwarding...
				$telegram->send
					->message($telegram->message)
					->chat($telegram->chat->id)
					->forward_to($chat_forward)
				->send();
			}
		} */
	}

	public function new_member(){
		// $new = El que entra
		// $this->user = El que le invita (puede ser el mismo)

		$this->chat->load();
		$this->core->load('Admin');
		global $Admin;

		$new = new User($this->telegram->new_user, $this->db);

		if($new->id == $this->telegram->bot->id){
			// A excepción de que lo agregue el creador
			$count = 0;
			if($this->user->id != CREATOR){
				// PROVISIONAL TEMP
				$this->telegram->send->leave_chat();
				$this->end();

				// Si el grupo está muerto, salir.
				if($this->chat->settings('die')){
					$this->telegram->send->leave_chat();
					$this->end();
				}

				$count = $this->telegram->send->get_members_count();
				// Si el grupo tiene <= 5 usuarios, el bot abandona el grupo
				if(is_numeric($count) && $count <= 5){
					// $this->analytics->event('Telegram', 'Join low group');
					$this->telegram->send->text("Nope.")->send();
					$this->telegram->send->leave_chat();
					$this->end();
				}

				// Si el que me agrega está registrado
				if($this->user->load()){
					if(
						$this->user->blocked or
						in_array(['hacks', 'troll', 'ratkid'], $this->user->flags)
					){
						$this->telegram->send->leave_chat();
						$this->end();
					}
				}
			}

			// Avisar al creador de que hay un grupo nuevo
			$text = ":new: ¡Grupo nuevo!\n"
					.":abc: " .$this->chat->title ."\n"
					.":id: " .$this->chat->id ."\n"
					."\ud83d\udec2 " .$count ."\n" // del principio de ejecución.
					.":male: " .$this->user->id ." - " .$this->user->first_name;

			$this->telegram->send
				->chat(CREATOR)
				->text($this->telegram->emoji($text))
			->send();

			// -----------------

			$text = "¡Buenas a todos, entrenadores!\n¡Un placer estar con todos vosotros! :D";
			// $group = $pokemon->group($telegram->chat->id);
			if($this->chat->messages == 0){
				$text .= "\nVeo que este grupo es nuevo, así que voy a buscar cuánta gente conozco.";
				// TODO si el Oak es nuevo en un grupo de más de X personas,
				// Realizar investigate sólo una vez.

				// Esto se puede hacer con el count de mensajes de un grupo, si es > 0.
				// Teniendo en cuenta que el grupo no se borre de la DB para que
				// no vuelva a ejecutarse este método.
			}

			$this->telegram->send
				->text($text, TRUE)
			->send();
			$this->end();
		}

		// $pknew = $pokemon->user($new->id);
		// Si entra el creador
		if($new->id == CREATOR){
			if($this->user->settings('silent_join')){ $this->end(); }
			$this->telegram->send
				->notification(TRUE)
				->reply_to(TRUE)
				->text("Bienvenido, jefe @duhow! Un placer tenerte aquí! :D")
			->send();
			$this->end();
		}

		// Si el grupo no admite más usuarios...
		if(
			$this->chat->settings('limit_join') == TRUE &&
			!$this->chat->is_admin($this->user)
		){
			// $this->analytics->event('Telegram', 'Join limit users');
			$Admin->kick($new->id);
			$Admin->admin_chat_message($new->id ." ha intentado entrar.");
			// $pokemon->user_delgroup($new->id, $telegram->chat->id);
			$this->end();
		}

		// Bot agregado al grupo. Yo no saludo bots :(
		if($new->id != $this->telegram->bot->id && $this->telegram->is_bot($new->username)){ $this->end(); }

		// Cargar información del usuario si está registrado.
		$new->load();

		if($this->chat->settings('team_exclusive')){
			// Si el grupo es exclusivo a un color y el usuario es de otro color
			if($this->chat->settings('team_exclusive') != $new->team){
				$this->telegram->send
					->notification(TRUE)
					->reply_to(TRUE)
					->text("*¡SE CUELA UN TOPO!* @" .$new->username ." " .$new->team, TRUE)
				->send();

				// Kickear (por defecto TRUE)
				if(
					$this->chat->settings('team_exclusive_kick') != FALSE &&
					!$this->chat->is_admin($this->user) // Si NO es admin el que lo mete
				){
					$q = $Admin->kick($new->id);
					if($q !== FALSE){
						$str = ":times: Topo detectado!\n"
								.":id: " .$new->id ."\n"
								.":abc: " .$this->telegram->new_user->first_name ." - @" .$new->username;
						$str = $this->telegram->emoji($str);
						$Admin->admin_chat_message($str);
					}
				}
				$this->end();
			}
		}

		if(
			$this->chat->settings('blacklist') &&
			!$this->chat->is_admin($this->user) && // El que invita no es admin
			!empty($new->flags) // Tiene flags / blacklist?
		){
			$blacklist = explode(",", $this->chat->settings('blacklist'));
			foreach($blacklist as $b){
				if(in_array($b, $new->flags)){
					// $this->analytics->event('Telegram', 'Join blacklist user', $b);
					$q = $Admin->kick($new->id);

					$str = ":times: Usuario en blacklist - $b\n"
					.":id: " .$new->id ."\n"
					.":abc: " .$this->telegram->new_user->first_name ." - @" .$new->username;
					$str = $this->telegram->emoji($str);

					$Admin->admin_chat_message($str);
					// $pokemon->user_delgroup($new->id, $telegram->chat->id);
					$this->end();
				}
			}
		}

		// Si el grupo requiere validados
		if(
			$this->chat->settings('require_verified') &&
			$this->chat->settings('require_verified_kick') &&
			!$new->verified
		){
			// $this->analytics->event('Telegram', 'Kick unverified user');
			$str = "Usuario " . $this->telegram->new_user->first_name ." / " .$new->id ." ";

			if(!$this->chat->is_admin($this->user)){
				$q = $Admin->kick($new->id);
				if($q !== FALSE){
					// $pokemon->user_delgroup($new->id, $telegram->chat->id);
					$str .= "kickeado por no estar verificado.";

					$str2 = ":warning: Usuario no validado.\n"
							.":id: " .$new->id ."\n"
							.":abc: " .$this->telegram->new_user->first_name ." - @" .$new->username;
					$str2 = $this->telegram->emoji($str2);
					$Admin->admin_chat_message($str2);
				}
			}else{
				$str .= "no está verificado.";
			}

			$this->telegram->send
				->text($str)
			->send();
			$this->end();
		}

		// Si un usuario generico se une al grupo
		if($this->chat->settings('announce_welcome') !== FALSE){
			$custom = $this->chat->settings('welcome');
			$text = 'Bienvenido al grupo, $nombre!' ."\n";
			if(!empty($custom)){ $text = json_decode($custom) ."\n"; }
			if(!empty($new->team)){
				$text .= "Oye, ¿podrías decirme el color de tu equipo?\n*Di: *_Soy ..._";
			}else{
				$text .= '$pokemon $nivel $equipo $valido $ingress';

				if(!$new->verified && $this->chat->settings('require_verified')){
					$text .= "\n" ."Para estar en este grupo *debes estar validado.*";

					$this->telegram->send
						->inline_keyboard()
							->row_button("Validar", "quiero validarme", "COMMAND")
						->show();
				}
			}

			// $pokemon->user_addgroup($new->id, $telegram->chat->id);
			// $this->analytics->event('Telegram', 'Join user');

			$ingress = NULL;
			if(in_array('resistance', $new->flags)){ $ingress = ":key:"; }
			elseif(in_array('resistance', $new->flags)){ $ingress = ":frog:"; }

			$emoji = ["Y" => "yellow", "B" => "blue", "R" => "red"];
			$repl = [
				'$nombre' => $this->telegram->new_user->first_name,
				'$apellidos' => $this->telegram->new_user->last_name,
				'$equipo' => ':heart-' .$emoji[$new->team] .':',
				'$team' => ':heart-' .$emoji[$new->team] .':',
				'$usuario' => "@" .$this->telegram->new_user->username,
				'$pokemon' => "@" .$new->username,
				'$nivel' => "L" .$new->lvl,
				'$valido' => $new->verified ? ':green-check:' : ':warning:',
				'$ingress' => $ingress
			];
			$text = str_replace(array_keys($repl), array_values($repl), $text);
			$text = $this->telegram->emoji($text);
			$this->telegram->send
				->notification(FALSE)
				->reply_to(TRUE)
				->text( $text , TRUE)
			->send();
		}

		// Avisar al grupo administrativo
		$str = ":new: Entra al grupo\n"
				.":id: " .$new->id ."\n"
				.":abc: " .$this->telegram->new_user->first_name ." - @" $new->username;
		$str = $this->telegram->emoji($str);
		$Admin->admin_chat_message($str);

		/*
		if(!empty($new->team)){
			$team = $pknew->team;
			$key = $this->chat->settings("pair_team_$team");
			if(!empty($key)){
				$teamchat = $pokemon->group_pair($telegram->chat->id, $team);
				if(!$teamchat){
					$telegram->send
						->chat($this->config->item('creator'))
						->notification(TRUE)
						->text("Problema con pairing $team en " .$this->chat->id ." (" .substr($key, 0, 10) .")")
					->send();
					return -1;
				}
				// Tengo chat, comprobar blacklist
				$black = explode(",", $pokemon->settings($teamchat, 'blacklist'));
				if($pokemon->user_flags($telegram->new_user->id, $black)){ return -1; }

				$link = $pokemon->settings($teamchat, 'link_chat');
				if(empty($link)){
					$telegram->send
						->chat($this->config->item('creator'))
						->notification(TRUE)
						->text("Problema con pair link $team en " .$telegram->chat->id ." (" .substr($key, 0, 10) .")")
					->send();
					return -1;
				}
				// Si es validado
				$color = ['Y' => 'Amarillo', 'R' => 'Rojo', 'B' => 'Azul'];
				$text = "Hola! Veo que eres *" .$color[$pknew->team] ."* y acabas de entrar al grupo " .$telegram->chat->title .".\n"
						."Hay un grupo de tu team asociado, pero no te puedo invitar porque no estás validado " .$telegram->emoji(":warning:") .".\n"
						."Si *quieres validarte*, puedes decirmelo. :)";
				if($pknew->verified){
					$text = "Hola! Te invito al grupo *" .$color[$pknew->team] ."* asociado a " .$telegram->chat->title .". "
							."¡No le pases este enlace a nadie!\n"
							.$telegram->grouplink($link);
				}

				if(!$telegram->user_in_chat($telegram->user->id, $teamchat)){
					$telegram->send
						->notification(TRUE)
						->chat($telegram->user->id)
						->text($text, NULL) // TODO NO Markdown.
					->send();

					if($pknew->verified){
						$telegram->send
							->notification(TRUE)
							->chat($teamchat)
							->text("He invitado a @" .$pknew->username ." a este grupo.")
						->send();
					}
				}
			}
		}
		*/
	}

	public function left_member(){
		// $pokemon->user_delgroup($telegram->user->id, $telegram->chat->id);

		$this->end();
	}

	protected function hooks(){
		// iniciar variables
		$telegram = $this->telegram;
		// $pokemon = $this->pokemon;

		// Cancelar pasos en general.
		if($this->user->step != NULL && $telegram->text_has(["Cancelar", "Desbugear", "/cancel"], TRUE)){
			$this->user->step = NULL;
			$this->user->update();
			$telegram->send
				->notification(FALSE)
				->keyboard()->selective(FALSE)->hide()
				->text("Acción cancelada.")
			->send();
			$this->end();
		}

		if($this->telegram->text_command("register")){ return $this->register(); }
		if($this->user->step == "SETNAME" && $this->telegram->words() == 1){
			$this->setname($this->telegram->last_word(TRUE));
			$this->end();
		}
		if($this->telegram->text_command("info")){ $this->telegram->send->text($this->user->telegramid)->send(); }

		$folder = dirname(__FILE__) .'/';
		foreach(scandir($folder) as $file){
			if(is_readable($folder . $file) && substr($file, -4) == ".php"){
				$name = substr($file, 0, -4);
				if(in_array($name, ["Main", "User", "Chat"])){ continue; }
				$this->core->load($name, TRUE);
			}
		}

		$this->end();

		// Ver los IV o demás viendo stats Pokemon.
		if(
			$telegram->words() >= 4 &&
			($telegram->text_has(["tengo", "me ha salido", "calculame", "calcula iv", "calcular iv", "he conseguido", "he capturado"], TRUE) or
			$telegram->text_command("iv"))
		){
			$pk = Tools::Pokemon($this->telegram->text());
			// TODO contar si faltan polvos o si se han especificado "caramelos" en lugar de polvos, etc.
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
					$levels = $pokemon->stardust($pk['stardust'], $pk['powered']);
					// $telegram->send->text(json_encode($levels))->send();

					// Si tiene HP y CP puesto, calvular IV
					if(isset($pk['hp']) and isset($pk['cp'])){
						$chat = ($telegram->is_chat_group() && $this->is_shutup(TRUE) ? $telegram->user->id : $telegram->chat->id);
						// $pokedex = $pokemon->pokedex($pk['pokemon']);
						$this->analytics->event("Telegram", "Calculate IV", $pokedex->name);

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
						if($this->user->id == $this->config->item('creator') && !$telegram->is_chat_group()){
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
		}elseif($telegram->text_has(["pokédex", "pokémon"], TRUE) or $telegram->text_command("pokedex")){
			// $text = $telegram->text();
			// $chat = ($telegram->text_has("aqui") && !$this->is_shutup() ? $telegram->chat->id : $telegram->user->id);
			/* if($telegram->text_has("aquí")){
				$word = $telegram->words( $telegram->words() - 2 );
			} */
			$this->_pokedex($telegram->text(), $telegram->chat->id);

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
		if($this->telegram->location() && !$this->telegram->is_chat_group()){
		    $loc = implode(",", $this->telegram->location(FALSE));
			$this->user->settings('location', $loc);
			$this->user->step = 'LOCATION';
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
		if($this->telegram->photo() && $this->user->id != CREATOR){
			if($this->user->verified){ return; }
			$this->user->step = 'SCREENSHOT_VERIFY';
			$this->_step();
		}
	}

	function _step(){
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$user = $telegram->user;
		$chat = $telegram->chat;

		$pokeuser = $pokemon->user($this->user->id);
		if(empty($pokeuser)){ return; } // HACK cuidado

		$admins = NULL;
		if($telegram->is_chat_group()){ $admins = $telegram->get_admins(); }
		$admins[] = $this->config->item('creator');

		$step = $pokeuser->step;
		switch ($step) {
			case 'RULES':
				break;
			case 'WELCOME':
				break;
			case 'CHOOSE_POKEMON':
				// $pk = NULL;
				$pk = $this->parse_pokemon();
				$pokemon->step($this->user->id, 'CHOOSE_POKEMON');
				/* if($telegram->text()){
					$pk = trim($telegram->words(0, TRUE));
					// if( preg_match('/^(?)\d{1,3}$/', $word) ){ }
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
						$s = $pokemon->settings($this->user->id, 'step_action');
						$pokemon->step($this->user->id, $s);
						$pokemon->settings($this->user->id, 'pokemon_select', $pk['pokemon']);
						$this->_step(); // HACK relaunch
					}
				}
				exit();
				break;
			case 'POKEMON_SEEN':
				// Tienes que estar en el lugar para poder haber reportado el Pokemon
				// Si tienes flags TROLL, FC u otras, no podrás enviarlo.
				// Solo puedes hacer uno cada minuto.
				$pk = $pokemon->settings($this->user->id, 'pokemon_select');

				$pokemon->settings($this->user->id, 'pokemon_select', 'DELETE');
				$pokemon->settings($this->user->id, 'step_action', 'DELETE');

				$cd = $pokemon->settings($this->user->id, 'pokemon_cooldown');
				if(!empty($cd) && $cd > time()){
					$telegram->send->text("Aún no ha pasado suficiente tiempo. Espera un poco, anda. :)");
					$pokemon->step($this->user->id, NULL);
					exit();
				}

				if($pokemon->user_flags($this->user->id, ['troll', 'rager', 'bot', 'forocoches', 'hacks', 'gps', 'trollmap'])){
					$telegram->send->text("nope.")->send();
					$pokemon->step($this->user->id, NULL);
					exit();
				}
				$loc = explode(",", $pokemon->settings($this->user->id, 'location')); // FIXME cuidado con esto, si reusamos la funcion.
				$pokemon->add_found($pk, $this->user->id, $loc[0], $loc[1]);

				// SELECT uid, SUBSTRING(value, 1, INSTR(value, ",") - 1) AS lat, SUBSTRING(value, INSTR(value, ",") + 1) AS lng FROM `settings` WHERE LEFT(uid, 1) = '-' AND type = "location"

				$pokemon->settings($this->user->id, 'pokemon_cooldown', time() + 60);
				$pokemon->step($this->user->id, NULL);

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
				$pokemon->settings($this->user->id, 'step_action', 'DELETE');

				$cd = $pokemon->settings($this->user->id, 'pokemon_cooldown');
				if(!empty($cd) && $cd > time()){
					$telegram->send->text("Aún no ha pasado suficiente tiempo. Espera un poco, anda. :)");
					$pokemon->step($this->user->id, NULL);
					exit();
				}

				if($pokemon->user_flags($this->user->id, ['troll', 'rager', 'bot', 'forocoches', 'hacks', 'gps', 'trollmap'])){
					$telegram->send->text("nope.")->send();
					$pokemon->step($this->user->id, NULL);
					exit();
				}
				$loc = explode(",", $pokemon->settings($this->user->id, 'location')); // FIXME cuidado con esto, si reusamos la funcion.

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

				$pokemon->add_lure_found($this->user->id, $loc[0], $loc[1]);

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

				$pokemon->settings($this->user->id, 'pokemon_cooldown', time() + 60);
				$pokemon->step($this->user->id, NULL);

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
						->text("Validar " .$this->user->id ." @" .$pokeuser->username ." L" .$pokeuser->lvl ." " .$pokeuser->team)
						->inline_keyboard()
							->row()
								->button($telegram->emoji(":ok:"), "te valido " .$pokeuser->telegramid, "TEXT")
								->button($telegram->emoji(":times:"), "no te valido")
							->end_row()
						->show()
					->send();

					$telegram->send
						->notification(TRUE)
						->chat($this->user->id)
						->keyboard()->hide(TRUE)
						->text("¡Enviado correctamente! El proceso de validar puede tardar un tiempo.")
					->send();

					$pokemon->step($this->user->id, NULL);
					exit();
				}
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

				break;
			default:
			break;
		}
		// exit(); // FIXME molesta. se queda comentado.
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
				if(is_numeric($num) && $num > 0 && $num < 251){ $text = $num; }
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

			$str = "*" .$pokedex->id ."* - " .$pokedex->name ."\n"
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
		$this->user->settings('pokemap_cooldown', time() + $time);
		$telegram->send->keyboard()->hide()->text($str, TRUE)->send();
	}

	function last_command($action){
		$command = $this->user->settings('last_command');
		$amount = 1;
		if($command == $action){
			$count = $this->user->settings('last_command_count');
			$add = ($this->telegram->is_chat_group() ? 0 : 1); // Solo agrega si es grupo
			$amount = (empty($count) ? 1 : ($count + $add));
		}
		$this->user->settings('last_command', $action);
		$this->user->settings('last_command_count', $amount);
	}

	function is_shutup($creator = TRUE){
		$admins = $this->admins($creator);
		$shutup = $this->chat->settings('shutup');
		return ($shutup && !in_array($this->telegram->user->id, $admins));
		// $this->telegram->user->id != $this->config->item('creator')
	}

	function is_shutup_jokes(){
		$can = $this->chat->settings('jokes');
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

	function _update_chat(){
		$chat = $this->telegram->chat;
		$user = $this->telegram->user;

		if(empty($chat->id)){ return; }
		$query = $this->db
			->where('id', $chat->id)
		->get('chats');
		if($this->db->count == 1){
			// UPDATE
			$data = [
				'type' => $chat->type,
				'title' => @$chat->title,
				'last_date' => date("Y-m-d H:i:s"),
				'active' => TRUE,
				'messages' => 'messages + 1',
			];

			$this->db
				->where('id', $chat->id)
			->update('chats', $data);
		}else{
			$data = [
				'id' => $chat->id,
				'type' => $chat->type,
				'title' => $chat->title,
				'active' => TRUE,
				'register_date' => date("Y-m-d H:i:s"),
			];

			$this->db->insert('chats', $data);
		}

		$query = $this->db
			->where('uid', $this->user->id)
			->where('cid', $chat->id)
		->get('user_inchat');

		if($this->db->count == 1){
			// UPDATE
			$data = [
				'messages' => 'messages + 1',
				'last_date' => date("Y-m-d H:i:s")
			];

			$this->db
				->where('uid', $this->user->id)
				->where('cid', $chat->id)
			->update('user_inchat', $data);
		}

		if($this->pokemon->user_exists($this->telegram->user->id)){
			if(isset($this->telegram->user->username) && !empty($this->telegram->user->username)){
				$this->pokemon->update_user_data($this->telegram->user->id, 'telegramuser', $this->telegram->user->username);
			}
		}
	}
}
