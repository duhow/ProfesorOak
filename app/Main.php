<?php

class Main extends TelegramApp\Module {
	protected $runCommands = TRUE;

	public function run(){
		if($this->telegram->left_user){ return $this->left_member(); }
		if($this->telegram->new_user){ return $this->new_member(); }
		$this->core->load('Tools');
		$this->core->load('Pokemon');

		if(
			$this->chat->load() and
			!$this->telegram->callback // Para botones inline de juegos y demás.
		){
			$this->chat->active_member($this->user->id);
		}

		if($this->chat->settings('forward_interactive')){
			$this->forward_creator();
		}

		if($this->telegram->is_chat_group()){
			$this->core->load('Admin');
			$this->core->load('Group');
			global $Admin, $Group;

			if($this->telegram->data_received('migrate_to_chat_id')){
				$Admin->migrate_settings($this->telegram->migrate_chat, $this->chat->id);
				$this->chat->disable();
				$this->end();
			}

			if($this->chat->settings('forwarding_to')){ $Admin->forward_to_groups(); }
			if($this->chat->settings('antiflood')){ $Admin->antiflood(); }
			if($this->chat->settings('antispam') != FALSE && $this->telegram->text_url()){ $Admin->antispam(); }
			if($this->chat->settings('mute_content')){ $Admin->mute_content(); }
			if($this->chat->settings('antiafk') and $this->telegram->key == 'message'){ $Admin->antiafk(); }
			// if($this->user->settings('mute')){ /* TODO Mute User */ }
			// if($this->chat->settings('require_avatar')){ $Admin->antinoavatar(); }
			if($this->chat->settings('die') && $this->user->id != CREATOR){ $this->end(); }
			if($this->chat->settings('abandon')){ $Group->abandon(); }

			if($this->user->blocked){ $this->end(); }

			if($this->chat->settings('custom_commands')){ $Group->custom_commands(); }
			// if($this->chat->settings('blackwords')){ $Admin->blackwords(); }
			if($this->chat->settings('dubs')){ $this->core->load('GameDubs', TRUE); }

			// Cancelar acciones sobre comandos provenientes de mensajes de channels. STOP SPAM.
			if($this->telegram->has_forward && $this->telegram->forward_type("channel")){ $this->end(); }
		}

		if($this->user->load() !== TRUE){
			// Solo puede registrarse o pedir ayuda por privado.
			$this->hooks_newuser();
		}

		// TODO Tiene que pasar TODOS los filtros (aparte de Admin).
		if($this->user->blocked){ $this->end(); }

		// Change language for user.
		if($this->user->settings('language')){
			$this->strings->language = $this->user->settings('language');
			$this->strings->load();
		}

		parent::run();
	}

	public function ping(){
		return $this->telegram->send
			->text("¡Pong!")
		->send();
	}

	public function help(){
		$url = $this->strings->get('help_url');
		$this->telegram->send
			->text_replace($this->strings->get('help_text'), $url, 'HTML')
		->send();
	}

	public function lang($set = NULL){ return $this->language($set); }
	public function language($set = NULL){
		if($this->telegram->is_chat_group()){ $this->end(); }

		if($this->user->telegramid !== NULL){
			if(strlen($set) == 2 and !is_numeric($set)){
				$this->user->settings('language', $set);
				$this->telegram->send->text("Language set to <b>$set</b>!");

				if($this->telegram->callback){
					$this->telegram->send
						->chat(TRUE)
						->message(TRUE)
					->edit('text');
					$this->telegram->answer_if_callback("");
				}else{
					$this->telegram->send->send();
				}

				$this->end();
			}
		}

		$str = "This new version has translations, but currently there is Spanish and a bit of English." ."\n"
			."If you want to contribute or improve them, please contact @duhow. Thank you!";

		$this->telegram->send
			->inline_keyboard()
				->row()
					->button($this->telegram->emoji(':es:'), "language es", "TEXT")
					->button($this->telegram->emoji(':us:'), "language en", "TEXT")
				->end_row()
				->row()
					->button($this->telegram->emoji(':fr:'), "language fr", "TEXT")
					->button($this->telegram->emoji(':it:'), "language it", "TEXT")
					->button($this->telegram->emoji(':de:'), "language de", "TEXT")
				->end_row()
			->show()
			->text($str)
		->send();

		$this->end();
	}

	public function register($team = NULL){
		$str = NULL;
		if($this->user->telegramid === NULL){
			if($team === NULL){
				$str = $this->strings->parse('register_hello_start', $this->telegram->user->first_name);
				if($this->telegram->is_chat_group()){
					$str = $this->strings->parse('register_hello_private', $this->telegram->user->first_name);
					$this->telegram->send
					->inline_keyboard()
						->row_button($this->strings->get('register'), "https://t.me/ProfesorOak_bot")
					->show();
				}
			}elseif($team === FALSE){
				$this->telegram->send->reply_to(TRUE);
				$str = $this->strings->get('error_register');
			}else{
				// Intentar registrar, ignorar si es anonymous.
				if($this->user->register($team) === FALSE){
					$this->telegram->send
						->text($this->strings->get('error_register'))
					->send();
					$this->end();
				}
				if($this->user->load() !== FALSE){
					$this->user->step = "SETNAME";
					if($this->user->settings('language')){
						$this->strings->language = $this->user->settings('language');
						$this->strings->load();
					}
					$str = $this->strings->parse('register_ok_name', $this->telegram->user->first_name);
				}
			}
		}elseif(!$this->user->username){
			$str = $this->strings->get('register_hello_name');
		}elseif(!$this->user->verified){
			$str = $this->telegram->emoji(":warning:") .$this->strings->get('register_hello_verify');
			$this->telegram->send
	        ->inline_keyboard()
	            ->row_button($this->strings->get('verify'), "verify", TRUE)
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

	private function forward_creator(){
		return $this->telegram->send
			->notification(FALSE)
			->chat(TRUE)
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
		$adminchat = $this->chat->settings('admin_chat');

		if($new->id == $this->telegram->bot->id){

			$count = $this->telegram->send->get_members_count();
			// A excepción de que lo agregue el creador
			if($this->user->id != CREATOR){
				// Si el grupo está muerto, salir.
				if($this->chat->settings('die')){
					$this->telegram->send->leave_chat();
					$this->end();
				}

				// Si el grupo tiene <= 5 usuarios, el bot abandona el grupo
				if(is_numeric($count) && $count <= 5){
					// $this->tracking->event('Telegram', 'Join low group');
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
					.":abc: %s\n"
					.":id: %s\n"
					."\ud83d\udec2 %s\n" // del principio de ejecución.
					.":male: %s - %s";
			$text = $this->telegram->emoji($text);

			$repl = [$this->chat->title,
					$this->chat->id,
					$count,
					$this->user->id, $this->user->first_name];

			$this->telegram->send
				->chat(CREATOR)
				->text_replace($text, $repl)
			->send();

			// -----------------

			$text = $this->strings->get('welcome_bot');
			if($this->chat->messages == 0){
				$text .= "\n" .$this->strings->get('welcome_bot_newgroup');
				// TODO si el Oak es nuevo en un grupo de más de X personas,
				// Realizar investigate sólo una vez.

				// Esto se puede hacer con el count de mensajes de un grupo, si es > 0.
				// Teniendo en cuenta que el grupo no se borre de la DB para que
				// no vuelva a ejecutarse este método.
			}

			$this->telegram->send
				->text($text, 'HTML')
			->send();
			$this->end();
		}

		// Si entra el creador
		if($new->id == CREATOR){
			if($new->settings('silent_join')){ $this->end(); }
			$this->telegram->send
				->notification(TRUE)
				->reply_to(TRUE)
				->text($this->strings->get('welcome_group_creator'))
			->send();
			$this->end();
		}

		// Si el grupo no admite más usuarios...
		if(
			$this->chat->settings('limit_join') == TRUE &&
			!$this->chat->is_admin($this->user) // Si el que lo agrega no es Admin
		){
			// $this->tracking->event('Telegram', 'Join limit users');
			$Admin->kick($new->id);
			$Admin->admin_chat_message($this->strings->parse('adminchat_newuser_limit_join', $new->id));
			// $pokemon->user_delgroup($new->id, $telegram->chat->id);
			$this->end();
		}

		// Bot agregado al grupo. Yo no saludo bots :(
		if($new->id != $this->telegram->bot->id && $this->telegram->is_bot($new->username)){ $this->end(); }

		// Cargar información del usuario si está registrado.
		$new->load();

		if($new->settings('follow_join')){
			$str = ":warning: Join detectado\n"
					.":id: " .$new->id ." - " .$this->telegram->new_user->first_name ."\n"
					.":multiuser: " .$this->telegram->chat->id ." - " .$this->telegram->chat->title;
			$str = $this->telegram->emoji($str);
			$this->telegram->send
				->notification(TRUE)
				->chat(CREATOR)
				->text($str)
			->send();
		}

		if($this->chat->settings('team_exclusive')){
			// Si el grupo es exclusivo a un color y el usuario es de otro color
			if($this->chat->settings('team_exclusive') != $new->team){
				$this->telegram->send
					->notification(TRUE)
					->reply_to(TRUE)
					->text_replace($this->strings->get('welcome_group_team_exclusive'), $new->username, 'HTML')
				->send();

				// Kickear (por defecto TRUE)
				if(
					$this->chat->settings('team_exclusive_kick') != FALSE &&
					!$this->chat->is_admin($this->user) // Si NO es admin el que lo mete
				){
					$q = $Admin->kick($new->id);
					if($q !== FALSE){
						$str = ":times: " .$this->strings->get('adminchat_newuser_team_exclusive_invalid') ."\n"
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
					// $this->tracking->event('Telegram', 'Join blacklist user', $b);
					$q = $Admin->kick($new->id);

					$str = ":times: " .$this->strings->get('adminchat_newuser_in_blacklist') ." - $b\n"
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
			// $this->tracking->event('Telegram', 'Kick unverified user');
			$str = $this->strings->get('user') ." " .$this->telegram->new_user->first_name ." / " .$new->id ." ";

			if(!$this->chat->is_admin($this->user)){
				$q = $Admin->kick($new->id);
				if($q !== FALSE){
					// $pokemon->user_delgroup($new->id, $telegram->chat->id);
					$str = $this->strings->get('admin_kicked_unverified');

					$str2 = ":warning: " .$this->strings->get('adminchat_newuser_not_verified') ."\n"
							.":id: " .$new->id ."\n"
							.":abc: " .$this->telegram->new_user->first_name ." - @" .$new->username;
					$str2 = $this->telegram->emoji($str2);
					$Admin->admin_chat_message($str2);
				}
			}else{
				$str = $this->strings->get('welcome_group_unverified');
			}

			$this->telegram->send
				->text($str)
			->send();
			$this->end();
		}

		// Si un usuario generico se une al grupo
		if($this->chat->settings('announce_welcome') !== FALSE){
			$custom = $this->chat->settings('welcome');
			$text = $this->strings->parse('welcome_group', $this->telegram->new_user->first_name) ."\n";
			if(!empty($custom)){ $text = json_decode($custom) ."\n"; }
			if(!empty($new->team)){
				$text .= $this->strings->get('welcome_group_register');
			}else{
				$text .= '$pokemon $nivel $equipo $valido $ingress';

				if(!$new->verified && $this->chat->settings('require_verified')){
					$text .= "\n" .$this->strings->get('welcome_group_require_verified');

					$this->telegram->send
						->inline_keyboard()
							->row_button($this->strings->get("verify"), "verify", "COMMAND")
						->show();
				}
			}

			// $pokemon->user_addgroup($new->id, $telegram->chat->id);
			// $this->tracking->event('Telegram', 'Join user');

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
				->text( $text , 'HTML')
			->send();
		}

		// Avisar al grupo administrativo
		$str = ":new: Entra al grupo\n"
				.":id: " .$new->id ."\n"
				.":abc: " .$this->telegram->new_user->first_name ." - @" .$new->username;
		$str = $this->telegram->emoji($str);
		$Admin->admin_chat_message($str);
	}

	public function left_member(){
		$this->chat->load();
		if($this->telegram->left_user->id == $this->telegram->bot->id){
			$str = ":door: Me han echado :(\n"
					.":id: " .$this->telegram->chat->id ."\n"
					.":abc: " .$this->telegram->chat->title ."\n"
					.":male: " .$this->telegram->user->id . " - " . $this->telegram->user->first_name;
			$str = $this->telegram->emoji($str);

			$this->telegram->send
				->notification(TRUE)
				->chat(CREATOR)
				->text($str)
			->send();
			$this->chat->disable();
		}else{
			// TODO remove user from list
		}
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
				->text($this->strings->get('step_cancel'))
			->send();
			$this->end();
		}

		if($this->telegram->text_command("register")){ return $this->register(); }
		// Registro nombre
		if(
			!$this->telegram->text_command() and
			(
				$this->user->step == "SETNAME" and $this->telegram->words() == 1
			) or (
				$this->telegram->text_regex($this->strings->get('command_register_username')) and
				in_array($this->telegram->words(), [3,4,5,6]) // HACK
			)
		){
			$username = $this->telegram->input->name;
			if($this->telegram->words() == 1){
				$username = $this->telegram->last_word();
			}
			$this->set_username($username)
			$this->end();
		}

		if(
			$this->telegram->text_regex($this->strings->get("command_levelup")) and
			$this->telegram->words() <= 6
		){
			$this->levelup($this->telegram->input->level);
		}

		if($this->telegram->callback and $this->telegram->text_regex("language {lang}")){
			return $this->language($this->telegram->input->lang);
		}

		$folder = dirname(__FILE__) .'/';
		foreach(scandir($folder) as $file){
			if(is_readable($folder . $file) && substr($file, -4) == ".php"){
				$name = substr($file, 0, -4);
				if(in_array($name, ["Main", "User", "Chat"])){ continue; }
				$this->core->load($name, TRUE);
			}
		}

		$this->end();
	}

	private function set_username($name = NULL){
		if(empty($name) or strlen($name) < 4){ $this->end(); }

		$this->user->step = NULL;
		$res = $this->user->register_username($word, FALSE);
		if($res === TRUE){
			$this->tracking->track('Register username');
			$this->telegram->send
				->inline_keyboard()
					->row_button($this->strings->get('verify'), "verify", TRUE)
				->show()
				->reply_to(TRUE)
				->notification(FALSE)
				->text($this->strings->parse("register_successful", $word), "HTML")
			->send();
		}elseif($res === FALSE){
			$this->telegram->send
				->reply_to(TRUE)
				->notification(FALSE)
				->text($this->strings->parse("register_error_duplicated_name", $word), "HTML")
			->send();
		}elseif($res == -1){
			// Name already set.
			$this->end();
		}elseif($res == -2){
			$this->telegram->send
				->reply_to(TRUE)
				->notification(FALSE)
				->text($this->strings->get("register_error_name_shln"), "HTML")
			->send();
		}
	}

	private function levelup($level = NULL){
		if(empty($level) or !is_numeric($level)){
			$this->end();
		}

		if($level == $this->user->lvl){
			$this->telegram->send
				->notification(FALSE)
				->text($this->strings->get('register_levelup_same'))
			->send();
			$this->end();
		}

		$this->tracking->track("Change level $level");
		$this->user->settings('last_command', 'LEVELUP');
		if($level >= 5 && $level <= 35){
			if($level < $this->user->lvl){ $this->end(); } // No volver atrás.
			$old = $this->user->lvl;
			$this->user->lvl = $level;
			$this->user->exp = 0;
			// $pokemon->log($telegram->user->id, 'levelup', $level);

			// Vale. Como me vuelvas a preguntar quien eres, te mando a la mierda. Que lo sepas.
			$str = $this->strings->parse("register_levelup_ok", $level);

			if($this->user->step == "SCREENSHOT_VERIFY" and $old == 1){
				$str = $this->strings->get("register_levelup_verify");
			}

			$this->telegram->send
				->text($str, 'HTML')
				->notification(FALSE)
			->send();
		}elseif(
			($level > 35 and $level <= 40) and
			$level > $this->user->lvl // No volver atrás.
		){
			if($this->user->lvl == 1){
				$this->user->lvl = $level;
				$this->user->exp = 0;

				$str = 'register_levelup_newhigh';
			}elseif($level > $this->user->lvl + 1){
				$str = 'register_levelup_trollhigh';
			}else{
				$this->user->step = "LEVEL_SCREENSHOT";
				$str = 'register_levelup_checkhigh';
			}

			$this->telegram->send
				->text($this->strings->get($str))
			->send();
		}
	}

	private function hooks_newuser(){
		$color = Tools::Color($this->telegram->text());
		if(
			($this->telegram->text_regex("(Soy|Equipo|Team) {color}") && $color) or
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

}
