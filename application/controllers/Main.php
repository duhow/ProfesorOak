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
		$chat = $telegram->chat;
		$user = $telegram->user;

		/*
		####################
		# Funciones de Oak #
		####################
		*/

		if($telegram->text_contains(["profe", "oak"]) && $telegram->text_has("dónde estás") && $telegram->words() <= 5){
			$telegram->send
				->notification(FALSE)
				// ->reply_to(TRUE)
				->text($telegram->emoji("Detrás de ti... :>"))
			->send();
			exit();
		}

		// comprobar estado del bot
		if($telegram->text_contains(["profe", "oak"]) && $telegram->text_has(["ping", "pong", "me recibe", "estás", "estás ahí"]) && $telegram->words() <= 4){
			$this->analytics->event('Telegram', 'Ping');
			if($telegram->is_chat_group() && $telegram->user->id != $this->config->item('creator')){
				$can = $pokemon->settings($telegram->chat->id, 'shut_ping');
				if($can == TRUE){ exit(); }
			}
			if($telegram->text_contains("pong")){
				$telegram->send->file('sticker', "BQADBAAD7AADoOj0Bx8Btm77I1V5Ag");
			}else{
				$telegram->send->text("Pong! :D")->send();
			}
			exit();
		}

		// Oak o otro usuario es añadido a una conversación
		if($telegram->is_chat_group() && $telegram->data_received() == "new_chat_participant"){
			$set = $pokemon->settings($chat->id, 'announce_welcome');
			$new = $telegram->new_user;

			if($new->id == $this->config->item("telegram_bot_id")){
				$count = $telegram->send->get_members_count();
				// Si el grupo tiene <= 5 usuarios, el bot abandona el grupo
				if(is_numeric($count) && $count <= 5){
					$this->analytics->event('Telegram', 'Join low group');
					$telegram->send->leave_chat();
					exit();
				}
			}elseif($telegram->is_bot($new->username)){
				// Bot agregado al grupo. Yo no saludo bots :(
				exit();
			}

			$pknew = $pokemon->user($new->id);
			// El usuario nuevo es creador
			if($new->id == $this->config->item('creator')){
				$telegram->send
					->notification(TRUE)
					->reply_to(TRUE)
					->text("Bienvenido, jefe @duhow! Un placer tenerte aquí! :D")
				->send();
				exit();
			}elseif(!empty($pknew)){
				// Si el grupo es exclusivo a un color y el usuario es de otro color
				$teamonly = $pokemon->settings($chat->id, 'team_exclusive');
				if(!empty($teamonly) && $teamonly != $pknew->team){
					$this->analytics->event('Telegram', 'Spy enter group');
					$telegram->send
						->notification(TRUE)
						->reply_to(TRUE)
						->text("*¡SE CUELA UN TOPO!* @$pknew->username $pknew->team", TRUE)
					->send();

					// Kickear (por defecto TRUE)
					$kick = $pokemon->settings($chat->id, 'team_exclusive_kick');
					if($kick != FALSE){
						$telegram->send->kick($user->id, $chat->id);
					}
					exit();
				}

				$blacklist = $pokemon->settings($chat->id, 'blacklist');
				if(!empty($blacklist)){
					$blacklist = explode(",", $blacklist);
					$pknew_flags = $pokemon->user_flags($pknew->telegramid);
					foreach($blacklist as $b){
						if(in_array($b, $pknew_flags)){
							$this->analytics->event('Telegram', 'Join blacklist user', $b);
							$telegram->send->kick($user->id, $chat->id);
							exit();
						}
					}
				}
			}

			// Si un usuario generico se une al grupo
			if($set != FALSE or $set === NULL){
				$text = "Bienvenido al grupo, " .$new->first_name ."!\n";
				if(empty($pknew)){
					$text .= "¿Podrías decirme de que color eres? _(Soy ...)_";
				}else{
					$emoji = ["Y" => "yellow", "B" => "blue", "R" => "red"];
					$text .= "@$pknew->username L$pknew->lvl :heart-" .$emoji[$pknew->team] .":";
				}

				if($new->id == $this->config->item("telegram_bot_id")){
					$text = "Buenas a todos, entrenadores! Un placer estar con todos vosotros! :D";
				}

				$this->analytics->event('Telegram', 'Join user');
				$telegram->send
					->notification(FALSE)
					->reply_to(TRUE)
					->text( $telegram->emoji($text) , TRUE)
				->send();
			}
			exit();
		}
		// mensaje de despedida
		elseif($telegram->is_chat_group() && $telegram->data_received() == "left_chat_participant"){
			//
		}

		// pillando a los h4k0rs
		elseif($telegram->text_contains(["fake GPS", "fake", "fakegps", "nox"])){
			if($telegram->user->id != $this->config->item("creator")){
			$this->analytics->event('Telegram', 'Talk cheating');
			$telegram->send
				->text("*(A)* *$chat->title* - $user->first_name @" .$user->username .":\n" .$telegram->text(), TRUE)
				->chat($this->config->item('creator'))
			->send();
			// $this->telegram->sendHTML("*OYE!* Si vas a empezar con esas, deberías dejar el juego. En serio, hacer trampas *NO MOLA*.");
			exit();
			}
		}elseif($telegram->text_has("pole", TRUE)){
/*			$telegram->send
				->notification(FALSE)
				->reply_to(TRUE)
				->text("Enhorabuena " .$telegram->user->first_name .", eres *gilipollas!* :D", TRUE)
			->send(); */
		}
		// if($this->telegram->chat_type() == "group"){ die(); }

		// si el usuario existe, proceder a interpretar el mensaje
		if($pokemon->user_exists($user->id)){
			$this->_begin();
		}

		// Comando de información de registro para la gente que tanto lo spamea...
		elseif($telegram->text_has("/register", TRUE)){
			$this->analytics->event('Telegram', 'Register', 'command');
			$str = "Hola " .$telegram->user->first_name ."! Me podrías decir tu color?\n"
					."(*Soy* ...)";
			$telegram->send
				->notification(FALSE)
				->text($str, TRUE)
			->send();
			exit();
		}

		// guardar color de user
		elseif($telegram->text_has("Soy", ['rojo', 'valor', 'amarillo', 'instinto', 'azul', 'sabiduría'])){
			if(!$pokemon->user_exists($user->id)){
				$text = trim(strtolower($telegram->last_word('alphanumeric-accent')));

				// Registrar al usuario si es del color correcto
				if($pokemon->register($user->id, $text) !== FALSE){
					$this->analytics->event('Telegram', 'Register', $text);

					$name = $user->first_name ." " .$user->last_name;
					$pokemon->update_user_data($user->id, 'fullname', $name);
					// enviar mensaje al usuario
					$telegram->send
						->notification(FALSE)
						->reply_to(TRUE)
						->text("Muchas gracias $user->first_name! Por cierto, ¿cómo te llamas *en el juego*? \n_(Me llamo...)_", TRUE)
					->send();
				}else{
					$this->analytics->event('Telegram', 'Register', 'wrong', $text);
					$telegram->send
						->notification(FALSE)
						->reply_to(TRUE)
						->text("Lo siento, pero no te he entendido bien por culpa de los iconos. ¿Puedes decirme sencillamente *Soy rojo*, *soy azul* o *soy amarillo* ?", TRUE)
					->send();
				}
			}
		}else{
			// ---------
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
		if($pokemon->user_blocked($user->id)){ die(); }

		// if(empty($step)){ $pokemon->step($user->id, "MENU"); }

		/*
		##################
		# Comandos admin #
		##################
		*/

		// enviar broadcast a todos los grupos (solo creador)
		if($telegram->text_has("/broadcast", TRUE) && $user->id == $this->config->item('creator')){
			exit();
			$text = substr($text, strlen("/broadcast "));
			foreach($pokemon->get_groups() as $g){
				$res = $telegram->send
					->chat($g)
					->notification(TRUE)
					->text($text, TRUE)
				->send();
				var_dump($res);
			}
			exit();
		}
		elseif($telegram->text_has("/usercast", TRUE) && $user->id == $this->config->item('creator')){
			exit(); // TODO temporal
			$text = substr($text, strlen("/usercast "));
			// Cada 100 usuarios, enviar un mensaje de confirmación del progreso.
			$users = $pokemon->get_users(TRUE);
			$c = 0;
			foreach($users as $u){
				if($c % 100 == 0){
					$telegram->send
						->chat( $this->config->item('creator') )
						->notification(FALSE)
						->text("Enviados $c de " .count($users) ." (" .floor(($c / count($users)) * 100) .")")
					->send();
				}
				$telegram->send
					->chat($u)
					->notification(TRUE)
					->text($text, TRUE)
				->send();
				$c++;
			}
		}elseif($telegram->text_contains("/block", TRUE) && $user->id == $this->config->item('creator') && $telegram->has_reply){
			$pokemon->update_user_data($telegram->reply_user->id, 'blocked', TRUE);
		}elseif($telegram->text_contains("/unblock", TRUE) && $user->id == $this->config->item('creator') && $telegram->has_reply){
			$pokemon->update_user_data($telegram->reply_user->id, 'blocked', FALSE);
		}
		// echar usuario del grupo
		elseif($telegram->text_has(["/kick", "/ban"], TRUE) && $telegram->is_chat_group()){
			$admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');
			$admins[] = $this->config->item('telegram_bot_id');

			if(in_array($telegram->user->id, $admins)){
				$kick = NULL;
				if($telegram->has_reply){
					$kick = $telegram->reply_user->id;
				}elseif($telegram->words() == 2){
					$kick = $telegram->last_word();
					$telegram->send
						->text($kick)
					->send();
					exit();
					// Buscar usuario.
				}
				if(($telegram->user->id == $this->config->item('creator')) or !in_array($kick, $admins)){
					if($telegram->text_contains("kick")){
						$this->analytics->event('Telegram', 'Kick');
						$telegram->send->kick($kick, $telegram->chat->id);
					}elseif($telegram->text_contains("ban")){
						$this->analytics->event('Telegram', 'Ban');
						$telegram->send->ban($kick, $telegram->chat->id);
					}
				}
			}
		}
		// el bot explusa al emisor del mensaje
		elseif($telegram->text_has("/autokick", TRUE) && $telegram->is_chat_group()){
			$this->analytics->event('Telegram', 'AutoKick');
			$telegram->send->kick($telegram->user->id, $telegram->chat->id);
		}
		// enviar lista de admins
		elseif($telegram->text_has("/adminlist", TRUE) && $telegram->is_chat_group()){
			$admins = $telegram->get_admins($telegram->chat->id, TRUE);
			$teams = ["Y" => "yellow", "B" => "blue", "R" => "red"];
			$str = "";

			foreach($admins as $k => $a){
				if($a['status'] == 'creator'){
					unset($admins[$k]);
					array_unshift($admins, $a);
				}elseif($a['user']['id'] == $this->config->item('telegram_bot_id')){
					unset($admins[$k]);
					array_push($admins, $a);
				}
			}
			foreach($admins as $k => $a){
				if($a['user']['id'] == $this->config->item('telegram_bot_id')){
					$str .= "Y yo, el Profesor Oak :)";
					continue;
				}
				$pk = $pokemon->user($a['user']['id']);
				if(!empty($pk)){ $str .= $telegram->emoji(":heart-" .$teams[$pk->team] .":") ." L" .$pk->lvl ." @" .$pk->username ." - "; }
				$str .= $a['user']['first_name'] ." ";
				if(isset($a['user']['username']) && ($a['user']['username'] != $pk->username) ){ $str .= "( @" .$a['user']['username'] ." )"; }
				if($k == 0){ $str .= "\n"; } // - Creator
				$str .= "\n";
			}

			// Reply to private?
			// ->chat( $telegram->user->id )
			$this->analytics->event('Telegram', 'Admin List');
			$telegram->send
				->notification(FALSE)
				->text($str)
			->send();
		}

		// configurar el bot (solo creador/admin/chat privado)
		elseif(
			$telegram->text_has("/set", TRUE) &&
			$telegram->words() == 3 &&
			(
				( $telegram->is_chat_group() && $user->id == $this->config->item('creator') ) or
				( $telegram->is_chat_group() && in_array($user->id, $telegram->get_admins($telegram->chat->id)) ) or
				( !$telegram->is_chat_group() )
			)
		){
			$key = $telegram->words(1);
			$value = $telegram->words(2);

			$this->analytics->event('Telegram', 'Set config', $key);
			$set = $pokemon->settings($telegram->chat->id, $key, $value);
			$announce = $pokemon->settings($telegram->chat->id, 'announce_settings');
			$telegram->send
				->chat( $this->config->item('creator') )
				->text("CONFIG: $key *" .json_encode($set) ." -> " .json_encode($value) ."*", TRUE)
			->send();

			if( ($set !== FALSE or $set > 0) && ($announce == TRUE) ){
				$telegram->send
					->notification(FALSE)
					->reply_to(TRUE)
					->text("Configuración establecida: *$value*", TRUE)
				->send();
			}
			exit();
		}
		// establecer flag del usuario
		elseif(
			$telegram->text_has("/setflag", TRUE) &&
			(in_array($telegram->words(), [2,3])) &&
			$telegram->user->id == $this->config->item('creator')
		){
			if($telegram->words() == 2 and $telegram->has_reply){
				$f_user = $telegram->reply_user->id;
			}elseif($telegram->words() == 3){
				$f_user = $pokemon->user($telegram->words(1));
				if(empty($f_user)){ exit(); }
				$f_user = $f_user->telegramid;
			}
			$flag = $telegram->last_word();
			$pokemon->user_flags($f_user, $flag, TRUE);
		}

		elseif(
			$telegram->text_has("/get", TRUE) &&
			$telegram->words() == 2
		){
			if($telegram->is_chat_group()){
				$admins = $telegram->get_admins();
				$admins[] = $this->config->item('creator');
				if(!in_array($user->id, $admins)){ return; }
			}

			$word = $telegram->words(1);
			if(strpos($word, "+private") !== FALSE){
				$chat = $telegram->user->id;
				$word = str_replace("+private", "", $word);
			}else{
				$chat = $telegram->chat->id;
			}
			if(strtolower($word) == "all"){ $word = "*" ; } // ['say_hello', 'say_hey', 'play_games', 'announce_welcome', 'announce_settings', 'shutup']; }
			$value = $pokemon->settings($telegram->chat->id, $word);
			$text = "";
			if(is_array($value)){
				foreach($value as $k => $v){
					$text .= "$k: $v\n";
				}
			}else{
				$text = "*" .json_encode($value) ."*";
			}
			$telegram->send
				->chat($chat)
				->notification( ($chat != $telegram->chat->id) )
				->reply_to( ($chat == $telegram->chat->id) )
				->text($text, (!is_array($value)))
			->send();
			exit();
		}elseif(
			($telegram->text_has(["oak", "profe"], "limpia")) or $telegram->text_has("/clean", TRUE)
		){
			$admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');

			if(in_array($user->id, $admins)){
				$this->analytics->event('Telegram', 'Clean');
				$telegram->send
					->notification(FALSE)
					->text(".\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n.")
				->send();
			}
		}
		// echar al bot del grupo
		elseif($telegram->text_has(["oak", "profe"], ["sal", "vete"], TRUE) && $telegram->is_chat_group() && $telegram->words() < 4){
			$admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');

			if(in_array($user->id, $admins)){
				$this->analytics->event('Telegram', 'Leave group');
				$telegram->send
					->notification(FALSE)
					->text("Jo, pensaba que me queríais... :(\nBueno, si me necesitáis, ya sabéis donde estoy.")
				->send();

				$telegram->send->leave_chat();
			}
		}
		// marcar otro usuario (solo creador)
		elseif(
			$telegram->text_has(["Éste", "este"], TRUE) &&
			$telegram->has_reply &&
			$user->id == $this->config->item('creator')
		){
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
					$telegram->send
						->notification(FALSE)
						->text("De acuerdo, *@$word*!", TRUE)
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

			exit();
		}elseif($telegram->text_has("Te valido", TRUE) && $telegram->words() <= 3){
			if(!$pokeuser->authorized){ exit(); }
			$target = NULL;
			if($telegram->words() == 2 && $telegram->has_reply){
				$target = $telegram->reply_user->id;
			}elseif($telegram->words() == 3){
				$target = $telegram->last_word(TRUE);
				if($target[0] == "@"){ $target = substr($target, 1); }
				$target = $pokemon->find_users($target);
				if($target == FALSE or count($target) > 1){ exit(); }
				$target = $target[0]['telegramid'];
			}

			if($pokemon->user_verified($target)){ exit(); } // Ya es válido.
			if($pokemon->verify_user($telegram->user->id, $target)){
				$telegram->send
					->notification(FALSE)
					->text( $telegram->emoji(":green-check:") )
				->send();
			}
		}elseif($telegram->text_has("/investigate", TRUE) && $telegram->is_chat_group()){
			$admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');

			if(!in_array($telegram->user->id, $admins)){ die(); }

			$team = $pokemon->settings($telegram->chat->id, 'team_exclusive');
			if($team !== NULL){

				$run = $pokemon->settings($telegram->chat->id, 'investigation');
				if($run !== NULL){
					if(time() <= ($run + 3600)){ exit(); }
				}
				$run = $pokemon->settings($telegram->chat->id, 'investigation', time());

				$this->analytics->event('Telegram', 'Investigation', $team);
				$teams = ["Y", "B", "R"];
				unset( $teams[ array_search($team, $teams) ] );
				$users = $pokemon->get_users($teams);
				$c = 0;
				$dot = 0;
				$topos = array();

				$updates = $telegram->send
					->notification(FALSE)
					->text("*Progreso:* ", TRUE)
				->send();
				foreach($users as $u){
					if(($c % 100 == 0) or ($c % 100 == 50) or ($c >= count($users))){
						$msg = "*Progreso:* " .floor(($c / count($users) * 100)) ."%";
						if($dot++ > 3){ $dot = 0; }
						for($i = 0; $i < $dot; $i++){ $msg .= "."; }
						$msg .= " ($c)";

						$run = $pokemon->settings($telegram->chat->id, 'investigation');
						if($run === NULL){ $msg = "Cancelado. $c comprobados."; }

						$telegram->send
							->message($updates['message_id'])
							->text($msg, TRUE)
						->edit('text');

						if($run === NULL){ die(); }
					}
					$c++;

					$q = $telegram->send
						->chat($telegram->chat->id)
					->get_member_info($u);

					if($q == FALSE or $q['status'] == "left"){ continue; }
					else{
						$topos[] = $q;
						$telegram->send
							->notification(TRUE)
							->text("*TOPO!* " .$q['user']['first_name'] .(isset($q['user']['username']) ? " @" .$q['user']['username'] : "" ), TRUE)
						->send();
					}
				}

				$str = "*Lista final:*\n";
				foreach($topos as $t){
					$str .= $t['user']['first_name'] .(isset($t['user']['username']) ? " @" .$t['user']['username'] : "" ) ."\n";
				}

				$telegram->send
					->notification(FALSE)
					->text($str . "\nFinalizado.", TRUE)
				->send();
			}else{
				$telegram->send
					->notification(FALSE)
					->text("No es un grupo cerrado.")
				->send();
			}
			exit();
		}

		// contar miembros de cada color
		elseif($telegram->text_has("/count", TRUE) && $telegram->is_chat_group()){
			// $admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');

			if(!in_array($telegram->user->id, $admins)){ exit(); }

			$run = $pokemon->settings($telegram->chat->id, 'investigation');
			if($run !== NULL){
				if(time() <= ($run + 3600)){ exit(); }
			}
			$run = $pokemon->settings($telegram->chat->id, 'investigation', time());

			$teams = ["Y", "B", "R"];
			// unset( $teams[ array_search($team, $teams) ] );
			$users = $pokemon->get_users($teams);
			$c = 0;
			$dot = 0;
			$pks = array();
			$current_chat = $telegram->send->get_members_count();

			$updates = $telegram->send
				->notification(FALSE)
				->text("*Progreso:* ", TRUE)
			->send();
			foreach($users as $u){
				if(($c % 100 == 0) or ($c % 100 == 50) or ($c >= count($users))){
					$msg = "*Progreso:* " .floor(($c / count($users) * 100)) ."%";
					$msg .= " (" .count($pks["Y"]) ." / " .count($pks["R"]) ." / " .count($pks["B"]) .") ";
					$msg .= "de " .$current_chat;
					if($dot++ >= 3){ $dot = 0; }
					for($i = 0; $i < $dot; $i++){ $msg .= "."; }
					$msg .= " ($c)";

					$run = $pokemon->settings($telegram->chat->id, 'investigation');
					if($run === NULL){ $msg = "Cancelado. $c comprobados."; }

					$telegram->send
						->message($updates['message_id'])
						->text($msg, TRUE)
					->edit('text');

					if($run === NULL){ die(); }
				}
				$c++;

				$q = $telegram->send
					->chat($telegram->chat->id)
				->get_member_info($u);

				if($q == FALSE or $q['status'] == "left"){ continue; }
				else{
					$pk = $pokemon->user($u);
					if(!empty($pk)){
						$pks[$pk->team][] = $u;
					}
				}
			}

			$str = "*Lista final:*\n\n";
			$str .= ":heart-yellow: " .count($pks["Y"]) ."\n";
			$str .= ":heart-red: " .count($pks["R"]) ."\n";
			$str .= ":heart-blue: " .count($pks["B"]) ."\n";
			$str .= "Faltan: " .($current_chat - count($pks["Y"]) - count($pks["R"]) - count($pks["B"]));
			$str = $telegram->emoji($str);

			$telegram->send
				->notification(FALSE)
				->text($str, TRUE)
			->send();

		}elseif($telegram->text_contains("mal") && $telegram->words() < 4 && $telegram->has_reply){
			$telegram->send
				->chat($telegram->chat->id)
				->notification(FALSE)
				->message($telegram->reply->message_id)
				->text("Perdon :(")
			->edit('message');
		}elseif($telegram->text_has(["/stats"], TRUE)){
			$stats = $pokemon->count_teams();
			$text = "";
			$equipos = ["Y" => "yellow", "B" => "blue", "R" => "red"];
			foreach($stats as $s => $v){
				$text .= $telegram->emoji(":heart-" .$equipos[$s] .":") ." $v\n";
			}
			$text .= "*TOTAL:* " .array_sum($stats);
			$telegram->send
				->notification(FALSE)
				->reply_to(TRUE)
				->text($text, TRUE)
			->send();
			exit();
		}elseif($telegram->text_has(["grupo offtopic", "/offtopic"]) && $telegram->is_chat_group()){
			$offtopic = $pokemon->settings($telegram->chat->id, 'offtopic_chat');
			$chatgroup = NULL;
			if(!empty($offtopic)){
				if($offtopic[0] != "@" and strlen($offtopic) == 22){
					$chatgroup = "https://telegram.me/joinchat/" .$offtopic;
				}else{
					$chatgroup = $offtopic;
				}
			}
			if(!empty($chatgroup)){
				$this->analytics->event('Telegram', 'Offtopic Link');
				$telegram->send
					->notification(FALSE)
					->text("Offtopic: $chatgroup")
				->send();
			}
			exit();
		}elseif(
			( $telegram->text_has(["link", "enlace"], ["del grupo", "de este grupo", "grupo"]) or
			$telegram->text_has(["/linkgroup", "/grouplink"], TRUE)) and
			$telegram->is_chat_group()
		){
			$link = $pokemon->settings($telegram->chat->id, 'link_chat');
			$chatgroup = NULL;
			if(!empty($link)){
				if($link[0] != "@" and strlen($offtopic) == 22){
					$chatgroup = "https://telegram.me/joinchat/" .$link;
				}else{
					$chatgroup = $link;
				}
			}
			if(!empty($chatgroup)){
				$this->analytics->event('Telegram', 'Group Link');
				$telegram->send
					->notification(FALSE)
					->disable_web_page_preview()
					->text("Link: $chatgroup")
				->send();
			}
			exit();
		}

		// ---------------------
		// Apartado de cuenta
		// ---------------------

		// guardar nombre de user
		if($telegram->text_has(["Me llamo", "Mi nombre es", "Mi usuario es"], TRUE) && $telegram->words() <= 4 && $telegram->words() > 2){
			if(!empty($pokeuser->username)){ exit(); }
			$word = $telegram->last_word(TRUE);
			if($word[0] == "@"){ $word = substr($word, 1); }
			if(strlen($word) < 4){ exit(); }

			// si el nombre ya existe
			if($pokemon->user_exists($word)){
				$telegram->send
					->reply_to(TRUE)
					->notification(FALSE)
					->text("No puede ser, ya hay alguien que se llama *@$word* :(\nHabla con @duhow para arreglarlo.", TRUE)
				->send();
			}
			// si no existe el nombre
			else{
				$this->analytics->event('Telegram', 'Register username');
				$pokemon->update_user_data($user->id, 'username', $word);
				$telegram->send
					->reply_to(TRUE)
					->notification(FALSE)
					->text("De acuerdo, *@$word*!", TRUE)
				->send();
			}
			exit();
		}
		// guardar nivel del user
		elseif(
			$telegram->text_has("Soy", ["lvl", "nivel", "L", "level"]) or
			$telegram->text_has("Soy L", TRUE) // HACK L junta
		){
			$level = filter_var($telegram->last_word(), FILTER_SANITIZE_NUMBER_INT);
			if(is_numeric($level) && $level >= 5 && $level <= 35){
				$this->analytics->event('Telegram', 'Change level', $level);
				$pokemon->update_user_data($telegram->user->id, 'lvl', $level);
				// $telegram->send
					// ->notification(FALSE)
					// ->text("Guay! A seguir subiendo campeón! :D")
				// ->send();
			}
			exit();
		}

		// pedir info sobre uno mismo
		elseif($telegram->text_has(["Quién soy", "Cómo me llamo", "who am i"], TRUE)){
			$str = "";
			$this->analytics->event('Telegram', 'Whois', 'Me');
			$team = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];
			if(empty($pokeuser->username)){ $str .= "No sé como te llamas, sólo sé que "; }
			else{ $str .= "@$pokeuser->username, "; }

			$str .= "eres *" .$team[$pokeuser->team] ."* L" .$pokeuser->lvl .".";

			// si el bot no conoce el nick del usuario
			if(empty($pokeuser->username)){ $str .= "\nPor cierto, ¿cómo te llamas *en el juego*? \n_Me llamo..._"; }

			$send = $pokemon->settings($telegram->chat->id, 'shutup');
			if($send == TRUE && $user->id != $this->config->item('creator')){ $chat = $telegram->user->id; }
			else{ $chat = $telegram->chat->id; }

			$telegram->send
				->chat($chat)
				->reply_to( ($chat == $telegram->chat->id) )
				->notification(FALSE)
				->text($str, TRUE)
			->send();
		}
		// Si pregunta por Ash...
		elseif($telegram->text_has("quién es Ash") && $telegram->words() <= 7){
			$this->analytics->event('Telegram', 'Jokes', 'Ash');
			$telegram->send
				->text("Ah! Ese es un *cheater*, es nivel 100...\nLo que no sé de dónde saca tanto dinero para viajar tanto...", TRUE)
			->send();
			exit();
		}
		// si pregunta por un usuario
		elseif(
			$telegram->text_has("quién", ["es", "eres"]) &&
			!$telegram->text_contains(["programa", "esta"]) &&
			$telegram->words() <= 5
		){
			$str = "";
			// pregunta usando respuesta
			if($telegram->has_reply){
				$this->analytics->event('Telegram', 'Whois', 'Reply');
				// si el usuario por el que se pregunta es el bot
				if($telegram->reply_user->id == $this->config->item("telegram_bot_id")){
					$str = "Pues ese soy yo mismo :)";
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
						$team = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];
						// si no se conoce el nick pero si el equipo
						if(empty($info->username)){ $str .= "No sé como se llama, sólo sé que "; }
						// si se conoce el equipo
						else{ $str .= "@$info->username, "; }

						$str .= "es *" .$team[$info->team] ."* L" .$info->lvl .".\n";

						$flags = $pokemon->user_flags($info->telegramid);

						// añadir emoticonos basado en los flags del usuario
						if($info->verified){ $str .= $telegram->emoji(":green-check: "); }
						else{ $str .= $telegram->emoji(":warning: "); }
						// ----------------------
						if($info->blocked){ $str .= $telegram->emoji(":forbid: "); }
						if($info->authorized){ $str .= $telegram->emoji(":star: "); }
						if(in_array("ratkid", $flags)){ $str .= $telegram->emoji(":mouse: "); }
						if(in_array("multiaccount", $flags)){ $str .= $telegram->emoji(":multiuser: "); }
						if(in_array("bot", $flags)){ $str .= $telegram->emoji(":robot: "); }
						if(in_array("rager", $flags)){ $str .= $telegram->emoji(":fire: "); }
						if(in_array("troll", $flags)){ $str .= $telegram->emoji(":joker: "); }
					}
				}
			}
			// pregunta usando nombre
			elseif(
				( ($telegram->words() == 3) or ($telegram->words() == 4 && $telegram->last_word() == "?") ) and
				( $telegram->text_has("quién es") )
			){
				$this->analytics->event('Telegram', 'Whois', 'User');
				if($telegram->words() == 4){ $text = $telegram->words(2); } // 2+1 = 3 palabra
				else{ $text = $telegram->last_word(); }
				$text = $telegram->clean('alphanumeric', $text);
				if(strlen($text) < 4){ exit(); }
				$data = $pokemon->user($text);

				$teams = ["Y" => "Amarillo", "B" => "Azul", "R" => "Rojo"];

				// si no se conoce
				if(empty($data)){
					$str = "No sé quien es $text.";
				}else{
					$str = "Es *" .$teams[$data->team] ."* L" .$data->lvl .".";
				}
			}

			if(!empty($str)){
			$send = $pokemon->settings($telegram->chat->id, 'shutup');
			$admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');

			if($send == TRUE && !in_array($telegram->user->id, $admins)){ $chat = $telegram->user->id; }
			else{ $chat = $telegram->chat->id; }

				$telegram->send
					->chat($chat)
					->reply_to( (($chat == $telegram->chat->id && $telegram->has_reply) ? $telegram->reply->message_id : FALSE) )
					->notification(FALSE)
					->text($str, TRUE)
				->send();
			}
			exit();

		}elseif($telegram->text_has("estoy aquí")){
			// Quien en cac? Que estoy aquí

		// ---------------------
		// Información General Pokemon
		// ---------------------

		}
		// pregunta sobre el creador de Oak
		elseif(
			!$telegram->text_has(["qué", "cómo"]) &&
			$telegram->text_has(["quién", "oak", "profe"]) &&
			$telegram->text_has(["es", "te", "tu", "hizo a", "le"]) &&
			$telegram->text_has(["programado", "hecho", "hizo", "creado", "creador"]) &&
			$telegram->words() <= 8
		){
			$telegram->send->notification(FALSE)->text("Pues mi creador es @duhow :)")->send();
			exit();
		}
		// pregunta sobre Eevee
		elseif($telegram->text_has("llama") && $telegram->text_has("eevee")){
			$pkmn = "";
			if($telegram->text_has("agua")){
				$pkmn = "Vaporeon";
			}elseif($telegram->text_has("fuego")){
				$pkmn = "Flareon";
			}elseif($telegram->text_has(["eléctrico", "electricidad"])){
				$pkmn = "Jolteon";
			}
			if(!empty($pkmn)){ $telegram->send->notification(FALSE)->text("Creo que te refieres a *$pkmn*?", TRUE)->send(); }
			exit();
		}
		// Estado Pokemon Go
		elseif(
			$telegram->text_has(["funciona", "funcionan", "va", "caído", "caer", "muerto", "estado"]) &&
		 	$telegram->text_has(["juego", "pokémon", "servidor",  "server"]) &&
			!$telegram->text_contains(["ese", "a mi me va", "a mis", "Que alg", "esa", "este", "caza", "su bola", "atacar", "cambi", "futuro", "esto", "para", "mapa", "contando", "va lo de", "llevamos", "a la", "va bastante bien"]) &&
			$telegram->words() < 15 && $telegram->words() > 2
		){
			/* $web = file_get_contents("http://www.mmoserverstatus.com/pokemon_go");
			$web = substr($web, strpos($web, "Spain"), 45);
			if(strpos($web, "red") !== FALSE){
				$str = "*NO funciona, hay problemas en España.*\n(Si bueno, aparte de los políticos.)";
			}elseif(strpos($web, "green") !== FALSE){
				$str = "Pokemon GO funciona correctamente! :)";
			} */

			// Conseguir estado mediante API JSON
			$web = file_get_contents("https://go.jooas.com/status");
			$web = json_decode($web);

			$pkgo = ($web->go_online == TRUE ? ':green-check:' : ':times:');
			$ptc = ($web->ptc_online == TRUE ? ':green-check:' : ':times:');

			$pkgo_t = $web->go_idle;
			$pkgo = ($pkgo == ":green-check:" && $pkgo_t <= 45 ? ':warning:' : ':green-check:');
			$pkgo_t = ($pkgo_t > 120 ? floor($pkgo_t / 60) ."h" : $pkgo_t ."m" );

			$ptc_t = $web->ptc_idle;
			$ptc = ($ptc == ":green-check:" && $ptc_t <= 45 ? ':warning:' : ':green-check:');
			$ptc_t = ($ptc_t > 120 ? floor($ptc_t / 60) ."h" : $ptc_t ."m" );
			// Todo funciona bien
			if($pkgo == TRUE && $ptc == TRUE){ $str = "¡Todo está funcionando correctamente!"; }
			// Problemas con PTC
			elseif($ptc != TRUE){ $str = "El juego funciona, pero parece que el *Club de Entrenadores tiene problemas.*\n_(¿Y cuándo no los tiene?)_"; }
			// Esto no va ni a la de tres
			else{ $str = "Parece que *hay problemas con el juego.*"; }

			$str .= "\n\n$pkgo PKMN ($pkgo_t)\n" ."$ptc PTC ($ptc_t)\n";
			// $str .= "_powered by https://go.jooas.com/ _";
			$str = $telegram->emoji($str);

			$telegram->send
				->notification(TRUE)
				// ->reply_to(TRUE)
				->text($str, TRUE)
			->send();
			exit();

		}elseif($telegram->text_has("Lista de", ["enlaces", "links"], TRUE)){
			$str = "";
			$links = $pokemon->link("ALL");
			$str = implode("\n- ", array_column($links, 'name'));
			$telegram->send
				->notification(FALSE)
				->text("- " .$str)
			->send();
			exit();
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

			exit();
		}

		// PARTE 2
		$help = NULL;

		if($telegram->text_contains(["añadir", "agreg", "crear", "solicit", "pedir"]) && $telegram->text_contains(["paradas", "pokeparadas"])){
			$help = "Lo siento, pero por el momento no es posible crear nuevas PokéParadas, tendrás que esperar... :(";
		}elseif($telegram->text_contains(["poli ", "polis ", "policía ", "policia "]) && $telegram->text_contains(["juga", "movil", "móvil"])){
			$help = "Recuerda que jugar mientras conduces el coche o vas en bicicleta, está *prohibido*. "
					."Podrías provocar un accidente, así que procura jugar con seguridad! :)";
		}elseif($telegram->text_contains(["significa", "quiere decir", "que es", "qué es"]) && $telegram->text_contains(["L1", "L2", "L8"])){
			$help = "Lo del *L1* es *Level 1* (*Nivel*). Si puedes, dime tu nivel y lo guardaré.\n_(Soy nivel ...)_";
		}elseif($telegram->text_contains("espacio") && $telegram->text_contains("mochila")){ // $telegram->text_contains(["como", "cómo"]) &&
			$help = "Tienes una mochila en la Tienda Pokemon, así que tendrás que buscar PokeMonedas si quieres comprarla. Si no, te va a tocar hacer hueco...";
		}elseif($telegram->text_contains(["cuáles son", "cuales son"]) && $telegram->text_contains("legendarios")){
			$help = "Pues según la historia, serían *Articuno*, *Zapdos* y *Moltres*. Incluso hay unos Pokemon que se sabe poco de ellos... *Mew* y *Mewtwo*...";
		}elseif($telegram->text_contains(["presentate", "preséntate"]) && $telegram->text_contains(["profe", "profesor", "oak"])){
			$help = "¡Buenas a todos! Soy el *Profesor Oak*, programado por @duhow.\n"
					."Mi objetivo es ayudar a todos los entrenadores del mundo, aunque de momento me centro en España.\n\n"
					."Conmigo podréis saber información sobre los Pokémon, cuáles son los tipos de ataques recomendados para debilitarlos rápidamente, y muchas más cosas, "
					."como por ejemplo cómo evolucionar a ciertos Pokémon o ver información de entrenadores, para saber de qué equipo son.\n\n"
					."Para poder hablar conmigo, tengo que saber de qué equipo sois, bastará con que digáis *Soy rojo*, *azul* o *amarillo*. "
					."Pero por favor, sed sinceros y nada de bromas, que yo me lo tomo muy en serio.\n"
					."Una vez hecho, podéis preguntar por ejemplo... *Debilidad contra Pikachu* y os enseñaré como funciona.\n"
					."Espero poder ayudaros en todo lo posible, ¡muchas gracias!";
		}elseif(
			($telegram->text_has(["lista", "ayuda", "ayúdame", "para qué sirve"]) && $telegram->text_has(["comando", "oak", "profe"])) or
			$telegram->text_has("/help", TRUE)
		){
			if($telegram->is_chat_group() && $telegram->user->id != $this->config->item('creator')){
				$telegram->send
					->notification(FALSE)
					->text("Te la envío por privado, " .$telegram->user->first_name .$telegram->emoji("! :happy:"))
				->send();
				$telegram->send->chat( $telegram->user->id );
			}
			$this->analytics->event('Telegram', 'Help');
			$help = "- Puedes preguntarme sobre la *Debilidad de Pikachu* y te responderé por privado.\n"
			 		."O si me pides que diga la *Debilidad de Pidgey aquí*, lo haré en el chat donde estés.\n"
					."También puedes preguntar *Evolución de Charizard* y te diré las fases por las que pasa.\n"
					."- Para juegos de azar, puedo *tirar los dados* o jugar al *piedra papel tijera*!\n"
					."- Si os va lento el juego o no conseguís ver o capturar Pokemon, podéis preguntar *Funciona el Pokemon?*\n"
					."- Podéis ver la *Calculadora de evolución* para saber los CP que necesitáis o tendréis para las evoluciones Pokemon.\n"
					."- También tenéis el *Mapa Pokemon* con los sitios indicados para capturar los distintos Pokemon.\n"
					."- Podéis preguntar *Quien es @usuario* (de Pokemon) para saber su equipo.\n"
					."- Si mencionáis a *@usuario* (de Pokemon), le enviaréis un mensaje directo - por si tiene el grupo silenciado, para darle un toque.\n\n"
					."¡Y muchas más cosas que vendrán próximamente!\n"
					."Cualquier duda, consulta, sugerencia o reporte de problemas podéis contactar con *mi creador*. :)";
		}elseif(
			!$telegram->text_contains("http") && // Descargas de juegos? No, gracias
			$telegram->text_has(["descarga", "enlace", "actualización"], ["de pokémon", "pokémon", "del juego"]) &&
			$telegram->words() <= 12
		){
			$google['web'] = "https://play.google.com/store/apps/details?id=com.nianticlabs.pokemongo";
			$apple['web'] =  "https://itunes.apple.com/es/app/pokemon-go/id1094591345";
			$web = file_get_contents($google['web']);
			$google['date'] = substr($web, strpos($web, "datePublished") - 32, 100);
			$google['version'] = substr($web, strpos($web, "softwareVersion") - 32, 100);
			foreach($google as $k => $v){
				$google[$k] = substr($google[$k], 0, strpos($google[$k], "</div>") + strlen("</div>"));
				$google[$k] = trim(strip_tags($google[$k]));
			}

			$web = file_get_contents($apple['web']);
			$apple['date'] = substr($web, strpos($web, "datePublished") - 16, 100);
			$apple['version'] = substr($web, strpos($web, "softwareVersion") - 16, 100);
			foreach($apple as $k => $v){
				$apple[$k] = substr($apple[$k], 0, strpos($apple[$k], "</span>") + strlen("</span>"));
				$apple[$k] = trim(strip_tags($apple[$k]));
			}

			$google['date'] = date("Y-m-d", strtotime($google['date']));
			$apple['date'] = date("Y-d-m", strtotime($apple['date'])); // HACK DMY -> YMD
			$apple['date'] = date("Y-m-d", strtotime($apple['date']));

			$google['days'] = floor((time() - strtotime($google['date'])) / 86400); // -> Days
			$apple['days'] = floor((time() - strtotime($apple['date'])) / 86400); // -> Days

			$google['new'] = ($google['days'] <= 1);
			$apple['new'] = ($apple['days'] <= 1);

			$dates = [0 => "hoy", 1 => "ayer"];

			$google['web'] = "https://play.google.com/store/apps/details?id=com.nianticlabs.pokemongo";
			$apple['web'] =  "https://itunes.apple.com/es/app/pokemon-go/id1094591345";

			$str = "[iOS](" .$apple['web'] ."): ";
			if($apple['new']){ $str .= "*NUEVA* de " .$dates[$apple['days']] ."! "; }
			else{ $str .= "de hace " .$apple['days'] ." dias "; }
			$str .= "(" .$apple['version'] .")\n";

			$str .= "[Android](" .$google['web'] ."): ";
			if($google['new']){ $str .= "*NUEVA* de " .$dates[$google['days']] ."! "; }
			else{ $str .= "de hace " .$google['days'] ." dias "; }
			$str .= "(" .$google['version'] .")";

			$telegram->send->disable_web_page_preview(TRUE);
			$help = $str;
		}elseif($telegram->text_contains("mejorar") && $telegram->text_contains(["antes", "después", "despues"]) && $telegram->text_contains(["evolución", "evolucionar", "evolucione"])){
			$help = "En principio es irrelevante, puedes mejorar un Pokemon antes o después de evolucionarlo sin problemas.";
		}elseif($telegram->text_contains(["calculadora", "calcular", "calculo", "calcula", "tabla", "pagina", "xp ", "experiencia"]) && $telegram->text_contains(["evolución", "evolucion", "evoluciona", "evolucione", "nivel", "PC", "CP"])){
			$help = "Claro! Te refieres a la Calculadora de Evolución, verdad? http://pogotoolkit.com/";
		}elseif($telegram->text_contains(["PC", "estadisticas", "estadísticas", "estados", "ataque"]) && $telegram->text_contains(["pokemon", "pokémon", "máximo", "maximo"]) && !$telegram->text_contains(["mes"])){
			$help = "Puedes buscar las estadísticas aquí: http://pokemongo.gamepress.gg/pokemon-list";
		}elseif($telegram->text_contains(["mapa", "página", "pagina"]) && $telegram->text_contains(["pokemon", "pokémon", "ciudad"]) && !$telegram->text_contains("evoluci")){
			$this->analytics->event('Telegram', 'Map Pokemon');
			$help = "https://goo.gl/GZb5hd";
		}elseif($telegram->text_contains(["como", "cómo"]) && $telegram->text_contains(["conseguir", "consigue"]) && $telegram->text_contains(["objeto", "incienso", "cebo", "huevo"])){
			$help = "En principio si vas a las PokeParadas y tienes suerte, también deberías de poder conseguirlos.";
		}elseif($telegram->text_contains(["tabla", "lista"]) && $telegram->text_contains(["ataque", "tipos de ataque", "debilidad"]) && $telegram->words() < 10){
			$this->analytics->event('Telegram', 'Attack Table');
			$telegram->send
				->notification(FALSE)
				->file('photo', FCPATH .'files/attack_types.png');
			exit();
		}elseif($telegram->text_contains(["tabla", "lista"]) && $telegram->text_contains(["huevos"]) && $telegram->words() < 10){
			$this->analytics->event('Telegram', 'Egg Table');
			$telegram->send
				->notification(FALSE)
				->file('photo', FCPATH .'files/egg_list.png');
			exit();
		}elseif($telegram->text_contains(["tabla", "lista"]) && $telegram->text_contains(["nivel", "recompensa"]) && $telegram->words() < 10){
			$this->analytics->event('Telegram', 'Level Table');
			$telegram->send
				->notification(FALSE)
				->file('photo', FCPATH .'files/egg_list.png');
			exit();
		}elseif($telegram->text_contains(["cambiar", "cambio"]) && $telegram->text_contains(["facción", "color", "faccion", "equipo", "team"]) && $telegram->words() <= 12){
			$help = "Según la página oficial de Niantic, aún no es posible cambiarse de equipo. Tendrás que esperar o hacerte una cuenta nueva, pero *procura no jugar con multicuentas, está prohibido.*";
		}elseif($telegram->text_contains(["cambiar", "cambio"]) && $telegram->text_contains(["usuario", "nombre", "llamo"]) && $telegram->words() <= 15){
			$help = "Si quieres cambiarte de nombre, puedes hacerlo en los *Ajustes de Pokemon GO.*\nUna vez hecho, habla con @duhow para que pueda cambiarte el nombre aquí!";
		}elseif($telegram->text_contains("datos") && $telegram->text_contains(["movil", "móvil", "moviles", "móviles"]) && !$telegram->text_contains("http")){
			$help = "Si te has quedado sin datos, deberías pensar en cambiarte a otra compañía o conseguir una tarifa mejor. "
					."Te recomiendo que tengas al menos 4GB si vas a ponerte a jugar en serio.";
		}elseif($telegram->text_contains(["no", "os funciona", "no funciona", "no me funciona", "problema"]) && $telegram->text_contains(["GPS", "ubicacion", "ubicación"]) && !$telegram->text_contains(["fake", "bueno"])){
			$help = "Si no te funciona el GPS, comprueba los ajustes de GPS. Te recomiendo que lo tengas en modo *sólo GPS*. "
					."Procura también estar en un espacio abierto, el GPS en casa no funciona a no ser que lo tengas en *modo ahorro*. \n"
					."Si sigue sin funcionar, prueba a apagar el móvil por completo, espera un par de minutos y vuelve a probar.";
		}elseif($telegram->text_contains(["batería", "bateria"]) && $telegram->text_contains(["externa", "portatil", "portátil", "recargable", "extra"]) && !$telegram->text_contains(["http", "voy con", "tengo", "del port"])){
			$help = "En función de lo que vayas a jugar a Pokemon GO, puedes coger baterías pequeñas. "
					."La capacidad se mide en mAh, cuanto más tengas, más tiempo podrás jugar.\n\n"
					."Si juegas unas 2-3 horas al día, te recomiendo al menos una de 5.000 mAh. Rondan más o menos *8-12€*. "
					."Pero si quieres jugar más rato, entonces mínimo una de 10.000 o incluso 20.000 mAh. El precio va entre *20-40€*. "
					."Éstas van bien para compartirlas con la gente, por si tu amigo se queda sin batería (o tu mismo si te llega a pasar).\n"
					."Recomiendo las que son de marca *Anker* o *RAVPower*, puedes echarle un vistazo a ésta si te interesa: http://www.amazon.es/dp/B019X8EXJI";
		}elseif(
			$telegram->text_has(["evolución", "evolucionar", "evoluciones"]) &&
			$telegram->text_contains(["evee", "eevee", "jolteon", "flareon", "vaporeon"]) &&
			$telegram->text_contains(["?", "¿"]) &&
			!$telegram->text_contains(["mejor"])
		){
			$help = "Tan sólo hay que *cambiar el nombre de Eevee antes de evolucionarlo* en función del que quieras conseguir.\n\n"
					."*El truco*\n"
					."- Si quieres a *Vaporeon* (Agua), llámalo *Rainer*.\n"
					."- Si quieres a *Jolteon* (Eléctrico), llámalo *Sparky*.\n"
					."- Si quieres a *Flareon* (Fuego), llámalo *Pyro*.\n\n"
					."Pero ten en cuenta que este truco *sólo funciona una vez* por cada nombre, así que elige sabiamente...";
					// ."Estos nombres tienen una historia detrás, aunque hay que remontarse a la serie original. "
					// ."En uno de los capítulos, Ash y sus compañeros de viaje se topaban con los hermanos Eeeve, "
					// ."y cada uno de ellos tenía una de las tres evoluciones.\n_¿A que no adivinas como se llamaban los hermanos?_\n";
					// ."https://youtu.be/uZE3CwmCYcY";
		}

		if(!empty($help)){
			$telegram->send
				->notification(FALSE)
				->text($help, TRUE)
			->send();
			exit();
		}

		// PARTE 3

		if($telegram->text_contains( ["atacando", "atacan"]) && $telegram->text_contains(["gimnasio", "gym"])){

		}elseif($telegram->text_has(["debilidad", "debilidades", "luchar", "atacar"], ["contra", "hacia", "sobre", "de"]) && $telegram->words() <= 6){
			$chat = NULL;
			$filter = (strpos($telegram->text(), "/") === FALSE); // Si no hay barra, filtra.
			if(in_array($telegram->words(), [3,4]) && $telegram->text_has("aquí", FALSE)){
				$text = $telegram->words(2, $filter);
				$chat = $telegram->chat->id;
			}else{
				$text = $telegram->last_word($filter);
				$chat = $telegram->user->id;
			}
			$this->analytics->event('Telegram', 'Search Pokemon Attack', $text);
			$this->_poke_attack($text, $chat);
		}elseif($telegram->text_has("evolución")){
			if($telegram->text_has("aquí", FALSE)){
				$chat = $telegram->chat->id;
				$text = $telegram->words( $telegram->words() - 2, TRUE);
			}else{
				$chat = $telegram->user->id;
				$text = $telegram->last_word(TRUE);
			}

			$this->analytics->event('Telegram', 'Search Pokemon Evolution', $text);
			$search = $pokemon->find($text);
			if($search !== FALSE){
				$evol = $pokemon->evolution($search['id']);
				$str = array();
				if(count($evol) == 1){ $str = "No tiene."; }
				else{
					foreach($evol as $i => $p){
						$cur = FALSE;
						if($p['name'] == $search['name']){ $cur = TRUE; }
						/* $ci = $p['id'];
						foreach($evol as $q){
						if($ci != $q['id'] && $q['id'] < $ci){ $ci = $q['id']; }
					} */
						$str[] = ($cur ? "*" .$p['name'] ."*" : $p['name']) .($p['candy'] != NULL && $p['candy'] > 0 ? " (_" .$p['candy'] ."_)" : "") ;
					}
					$str = implode(" > ", $str);
				}
				$telegram->send
					->chat( $chat )
					->notification(FALSE)
					->reply_to( ($chat == $telegram->chat->id) )
					->text($str, TRUE)
				->send();
			}

		// ---------------------
		// Utilidades varias
		// ---------------------

		//  TODO

		// ---------------------
		// Administrativo
		// ---------------------

		}elseif(
			$telegram->is_chat_group() && $telegram->words() <= 6 &&
			(
				( $telegram->text_has("está aquí") ) and
				// ( ( $telegram->receive(["está", "esta"]) && $telegram->receive(["aqui", "aquí"]) ) || ( $telegram->receive(["alguno", "alguien"]) && $telegram->receive("es") ) ) &&
				( !$telegram->text_contains(["alguien es", "alguien ha", "estar", "estamos", "alguno es", "algunos", "alguno como", "alguien está", "alguno esta", "que es"]) ) // Alguien está aquí? - Alguno es....
			)
		){
			if($telegram->words() > 3){
				$find = $telegram->last_word(TRUE);
			}else{
				if(strpos($telegram->last_word(), "aqu") !== FALSE){
					$find = $telegram->words(1, TRUE);
				}else{
					$find = $telegram->words(2, TRUE);
				}
			}

			$str = "";
			$find = str_replace(["@", "?"], "", $find);
			if(empty($find)){ exit(); }
			$this->analytics->event('Telegram', 'Search User', $find);
			$data = $pokemon->user($find);
			if(empty($data)){
				$str = "No sé quien es. ($find)";
			}else{
				$find = $telegram->send->get_member_info($data->telegramid);
				if($find === FALSE || $find['status'] == 'left'){
					$str = "No, no está.";
				}else{
					$str = "Si, " .$find['user']['first_name'] ." está aquí.";
				}
			}

			if(!empty($str)){
				$telegram->send
					->notification(FALSE)
					->reply_to(TRUE)
					->text($str)
				->send();
			}

			exit();
		}elseif($telegram->text_has(["team", "equipo"]) && $telegram->text_has(["sóis", "hay aquí", "estáis"])){
			exit();
		}elseif($telegram->text_has("Qué", ["significa", "es"], TRUE)){
			$word = trim(strtolower($telegram->last_word(TRUE)));
			$help = $pokemon->meaning($word);

			// Buscar si contiene EL/LA si no ha encontrado el $help, y repetir proceso.

			if(!empty($help) && !is_numeric($help)){
				$telegram->send
					->notification(FALSE)
					->reply_to(TRUE)
					->text($help, TRUE)
				->send();
			}
			exit();
		}
		// ---------------------
		// Chistes y tonterías
		// ---------------------

		$joke = NULL;

		if($telegram->text_has(["tira", "lanza", "tirar", "roll"], ["el dado", "los dados", "the dice"], TRUE) or $telegram->text_has("/dado", TRUE)){
			$this->analytics->event('Telegram', 'Games', 'Dice');
			$can = $pokemon->settings($telegram->chat->id, 'play_games');
			if($can != FALSE or $can === NULL){
				$joke = "*" .mt_rand(1,6) ."*";
			}
		}elseif(
			( $telegram->text_has("piedra") and
			$telegram->text_has("papel") and
			$telegram->text_has(["tijera", "tijeras"]) ) or
			$telegram->text_has(["/rps", "/rpsls"], TRUE)
		){
			$this->analytics->event('Telegram', 'Games', 'RPS');
			$rps = ["Piedra", "Papel", "Tijera"];
			if($telegram->text_contains(["lagarto", "/rpsls"])){ $rps[] = "Lagarto"; }
			if($telegram->text_contains(["spock", "/rpsls"])){ $rps[] = "Spock"; }
			$n = mt_rand(0, count($rps) - 1);

			$can = $pokemon->settings($telegram->chat->id, 'play_games');
			if($can != FALSE or $can === NULL){
				$joke = "*" .$rps[$n] ."!*";
			}
		}elseif($telegram->text_has(["cara o cruz", "/coin", "/flip"])){
			$this->analytics->event('Telegram', 'Games', 'Coin');
			$n = mt_rand(0, 99);
			$flip = ["Cara!", "Cruz!"];

			$can = $pokemon->settings($telegram->chat->id, 'play_games');
			if($can != FALSE or $can === NULL){
				$joke = "*" .$flip[$n % 2] ."*";
			}
		}elseif($telegram->text_has(["Recarga", "/recarga"], TRUE) && $telegram->words() <= 3){
			$can = $pokemon->settings($telegram->chat->id, 'play_games');
			if($can == FALSE){ exit(); }

			$shot = $pokemon->settings($telegram->chat->id, 'russian_roulette');
			$text = NULL;
			if(empty($shot)){
				$this->analytics->event('Telegram', 'Games', 'Roulette Reload');
				$shot = mt_rand(1, 6);
				$pokemon->settings($telegram->chat->id, 'russian_roulette', $shot);
				$text = "Bala puesta.";
			}else{
				if($telegram->user->id == $this->config->item('creator')){
					$pokemon->settings($telegram->chat->id, 'russian_roulette', 'DELETE');
					$this->_begin(); // HACK vigilar
				}
				$text = "Ya hay una bala. ¡*Dispara* si te atreves!";
			}
			$telegram->send
				->notification(FALSE)
				->text($text, TRUE)
			->send();
			exit();
		}elseif($telegram->text_has(["Dispara", "Disparo", "/dispara"], TRUE) && $telegram->words() <= 3){
			$can = $pokemon->settings($telegram->chat->id, 'play_games');
			if($can == FALSE){ exit(); }

			$shot = $pokemon->settings($telegram->chat->id, 'russian_roulette');
			$text = NULL;
			$last = NULL; // Ultimo en disparar
			if(empty($shot)){
				$text = "No hay bala. *Recarga* antes de disparar.";
			}else{
				if($telegram->is_chat_group()){
					$last = $pokemon->settings($telegram->chat->id, 'russian_roulette_last');
					if($last == $telegram->user->id){
						$last = -1;
						$text = "Tu ya has disparado, ¡pásale el arma a otra persona!";
					}else{
						$pokemon->settings($telegram->chat->id, 'russian_roulette_last', $telegram->user->id);
					}
				}
				if($shot == 6 && $last != -1){
					$this->analytics->event('Telegram', 'Games', 'Roulette Shot');
					$pokemon->settings($telegram->chat->id, 'russian_roulette', 'DELETE');
					$text = ":die: :collision::gun:";
				}elseif($last != -1){
					$this->analytics->event('Telegram', 'Games', 'Roulette Shot');
					$pokemon->settings($telegram->chat->id, 'russian_roulette', $shot + 1);
					$faces = ["happy", "tongue", "smiley"];
					$r = mt_rand(0, count($faces) - 1);
					$text = ":" .$faces[$r] .": :cloud::gun:";
				}
				$telegram->send
					->notification(FALSE)
					->reply_to(TRUE)
					->text( $telegram->emoji($text) )
				->send();

				if($shot == 6 && $last != -1 && $telegram->is_chat_group()){
					// Implementar modo light o hard (ban)
					// Avisar al admin?
					$telegram->send->ban( $telegram->user->id );
					$pokemon->settings($telegram->chat->id, 'russian_roulette_last', 'DELETE');
				}
			}
		}elseif($telegram->text_has(["Cuéntame", "cuéntanos", "cuenta"], ["otro chiste", "un chiste"])){
			$this->_joke();
			exit();
		}elseif($telegram->text_has(["a que sí"], ["profe", "oak", "profesor"])){
			$this->analytics->event('Telegram', 'Jokes', 'Reply yes or no');
			$resp = ["¡Por supuesto que sí!",
				"Mmm... Te equivocas. _(Aunque sea por llevar la contraria)_",
				"Pues ahora me has dejado con la duda..."
			];
			$n = mt_rand(0, count($resp) - 1);
			if($pokemon->settings($telegram->chat->id, 'say_reply') == TRUE or $user->id = $this->config->item('creator')){
				$joke = $resp[$n];
			}
		}elseif($telegram->text_has("Gracias", ["profesor", "Oak", "profe"])){
			// "el puto amo", "que maquina eres"
			$this->analytics->event('Telegram', 'Jokes', 'Thank you');
			$frases = ["De nada, entrenador! :D", "Nada, para eso estamos! ^^", "Gracias a ti :3"];
			$n = mt_rand(0, count($frases) - 1);

			$joke = $frases[$n];
		}elseif($telegram->text_has(["Ty bro", "ty prof"])){
			if($pokemon->settings($telegram->chat->id, 'jokes') != FALSE){
				$joke = "Yeah ma nigga 8-)";
			}
		}elseif($telegram->text_has("oak", "versión")){
			$date = (time() - filemtime(__FILE__));
			$joke = "Versión de hace " .floor($date / 60) ." minutos.";
		}elseif($telegram->text_has(["oak", "profe"], "dónde estoy") && $telegram->words() <= 4){
			// DEBUG
			if($telegram->is_chat_group()){
				$joke = "Estás en *" .$telegram->chat->title ."* ";
				if(isset($telegram->chat->username)){ $joke .= "@" .$telegram->chat->username ." "; }
				$joke .= "(" .$telegram->chat->id .").";
			}else{
				$joke = "Estás hablando por privado conmigo :)\n";
				if(isset($telegram->chat->username)){ $joke .= "@" .$telegram->chat->username ." "; }
				$joke .= "(" .$telegram->chat->id .").";
			}
		}elseif($telegram->text_has(["buenos", "buenas", "bon"], ["días", "día", "tarde", "tarda", "tardes", "noches", "nit"])){
			if($pokemon->settings($telegram->chat->id, 'say_hello') == TRUE){
				$joke = "Buenas a ti también, entrenador! :D";
				if($telegram->text_has(['noches', 'nit'])){
					$joke = "Buenas noches fiera, descansa bien! :)";
				}
			}
		}elseif(
			$telegram->text_contains(["oak", "profe"]) &&
			$telegram->text_has(["cuántos", "cuándo", "qué"]) &&
			$telegram->text_contains(["años", "edad", "cumple"])
		){
			$release = strtotime("2016-07-16 14:27");
			$birthdate = strtotime("now") - $release;
			$joke = "Cumplo " .floor($birthdate / (60*60*24)) ." días. ";
			$joke .= $telegram->emoji(":)");
		}elseif($telegram->text_has(["saluda", "saludo"]) && $telegram->text_has(["profe", "profesor", "oak"])){
			if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE or $telegram->user->id == $this->config->item('creator')){
				$joke = "Un saludo para todos mis fans! :D";
			}
		}elseif($telegram->text_has("/me", TRUE) && $telegram->words() >= 2){
			$text = substr($telegram->text(), strlen("/me "));
			if(strpos($text, "/") !== FALSE){ exit(); }
			$joke = trim("*" .$telegram->user->first_name ."* " .$text);
		}elseif($telegram->text_has(["necesitas", "necesitáis"], ["novio", "un novio", "novia", "una novia", "pareja", "una pareja", "follar"])){
			// if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE or $telegram->user->id == $this->config->item('creator')){
				$joke = "¿Novia? Qué es eso, se come?";
			// }
		}elseif($telegram->text_has("Team Rocket")){
			$this->analytics->event('Telegram', 'Jokes', 'Team Rocket');
			$telegram->send->notification(FALSE)->file('photo', FCPATH . "files/teamrocket.jpg", "¡¡El Team Rocket despega de nuevoooooo...!!");
			$telegram->send->notification(FALSE)->file('audio', FCPATH . "files/teamrocket.ogg");
			exit();
		}elseif($telegram->text_contains("sextape")){
			$telegram->send->notification(FALSE)->file('video', FCPATH . "files/sextape.mp4");
			exit();
		}elseif($telegram->text_has(["GTFO", "vale adiós"], TRUE)){
			// puerta revisar
			$this->analytics->event('Telegram', 'Jokes', 'GTFO');
			$telegram->send->notification(FALSE)->file('document', "BQADBAADHgEAAuK9EgOeCEDKa3fsFgI"); // Puerta
			exit();
		}elseif($telegram->text_contains(["badumtss", "ba dum tss"])){
			$this->analytics->event('Telegram', 'Jokes', 'Ba Dum Tss');
			$telegram->send->notification(FALSE)->file('document', "BQADBAADHgMAAo-zWQOHtZAjTKJW2QI");
			exit();
		}elseif($telegram->text_has("seguro dental")){
			$this->analytics->event('Telegram', 'Jokes', 'Seguro dental');
			$telegram->send->notification(FALSE)->file('video', FCPATH . "files/seguro_dental.mp4");
			exit();
		}elseif($telegram->text_has("no paras") && $telegram->words() < 10){
			$this->analytics->event('Telegram', 'Jokes', 'Paras');
			$telegram->send->notification(FALSE)->file('photo', FCPATH . "files/paras.png");
			exit();
		}elseif($telegram->text_contains("JOHN CENA") && $telegram->words() < 10){
			$this->analytics->event('Telegram', 'Jokes', 'John Cena');
			$telegram->send->notification(FALSE)->file('voice', FCPATH . "files/john_cena.ogg");
			exit();
		}elseif($telegram->text_has(["qué", "la"], "hora") && $telegram->text_contains("?") && $telegram->words() <= 5){
			$this->analytics->event('Telegram', 'Jokes', 'Time');
			$joke = "Son las " .date("H:i") .", una hora menos en Canarias. :)";
		}elseif($telegram->text_has("Profesor Oak", TRUE)){
			if($pokemon->settings($telegram->chat->id, 'say_hey') == TRUE){
				$joke = "Dime!";
			}
		}elseif($telegram->text_has(["programado", "funcionas"]) && $telegram->text_has(["profe", "oak", "bot"])){
			$joke = "Pues yo funciono con *PHP* (_CodeIgniter_) :)";
		}elseif($telegram->text_has(["profe", "profesor", "oak"]) && $telegram->text_has("te", ["quiero", "amo", "adoro"])){
			if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE){
				$joke = "¡Yo también te quiero! <3";
			}
		}elseif($telegram->text_contains(["te la com", "te lo com", "un hijo", "me ha dolido"]) && $telegram->text_has(["oak", "profe", "bot"])){
			$joke = "Tu sabes lo que es el fiambre? Pues tranquilo, que no vas a pasar hambre... ;)";
			$telegram->send
				->notification(FALSE)
				->file('sticker', 'BQADBAADGgAD9VikAAEvUZ8dGx1_fgI');
		}elseif($telegram->text_has(["transferir", "transfiere", "recicla"]) && $telegram->text_has(["pokémon"])){
			$this->analytics->event('Telegram', 'Jokes', 'Transfer Pokemon');
			$telegram->send->notification(FALSE)->file('document', FCPATH . "pidgey.gif", "Espera entrenador, que te voy a transferir un caramelo...");
		}elseif($telegram->text_has(["vas a la", "hay una", "es una"], "fiesta")){
			$this->analytics->event('Telegram', 'Jokes', 'Party');
			if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE){
				$telegram->send
					->notification(FALSE)
					->caption("¿Fiesta? ¡La que te va a dar ésta!")
					->file('document', "BQADBAADpgMAAnMdZAePc-TerW2MSwI");
			}
		}elseif($telegram->text_has("fanta") && $telegram->words() > 3){
			$this->analytics->event('Telegram', 'Jokes', 'Fanta');
			$fantas = [
				"BQADBAADLwEAAjSYQgABe8eWP7cgn9gC", // Naranja
				"BQADBAADQwEAAjSYQgABVgn9h2J6NfsC", // Limon
				"BQADBAADRQEAAjSYQgABsDEEUjdh0w8C", // Uva
				"BQADBAADRwEAAjSYQgABu1UlOqU2-8IC", // Fresa
			];
			$n = mt_rand(0, count($fantas) - 1);

			if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE or $telegram->user->id == $this->config->item('creator')){
				$telegram->send->notification(FALSE)->file('sticker', $fantas[$n]);
			}
		}

		if(!empty($joke)){
			$telegram->send
				->notification(FALSE)
				->text($joke, TRUE)
			->send();

			exit();
		}


		if($telegram->text_contains("@") && !$telegram->text_contains("@ ") && $telegram->is_chat_group()){
			$users = array();
			preg_match_all("/[@]\w+/", $telegram->text(), $users, PREG_SET_ORDER);
			foreach($users as $i => $u){ $users[$i] = substr($u[0], 1); } // Quitamos la @

			if(!empty($users)){
				if(in_array("admin", $users)){
					// FIXME Cambiar function get_admins por la integrada + array merge
					$admins = $telegram->send->get_admins();
					if(!empty($admins)){
						foreach($admins as $a){	$users[] = $a['user']['id']; }
					}
				}
				$find = $pokemon->find_users($users);
				if(!empty($find)){
					foreach($find as $u){
						// Valida que el entrenador esté en el grupo
						$chat = $telegram->send->get_member_info($u['telegramid'], $telegram->chat->id);
						if($chat !== FALSE && $chat['status'] != 'left'){
							// $str = "@" .$agent->username ." te ha mencionado en el grupo *" .$this->telegram->chat('title') ."*.";
							$str = "@" .$pokeuser->username ." - *" .$telegram->chat->title ."*:\n" .$telegram->text();
							$telegram->send
								->chat($u['telegramid'])
								->notification(TRUE)
								->text($str, TRUE)
							->send();
						}
					}
				}
			}
		}

		// Buscar coordenadas
		$loc = NULL;
		if(preg_match("/(\d+.\d+)[,;]\s?(\d+.\d+)/", $telegram->text(), $loc)){
			$loc = $loc[0];
			if(strpos($loc, ";") !== FALSE){ $loc = explode(";", $loc); }
			elseif(strpos($loc, ",") !== FALSE){ $loc = explode(",", $loc); }

			if(count($loc) == 2){
				$this->analytics->event('Telegram', 'Parse coords');
				$telegram->send
					->location($loc[0], $loc[1])
				->send();
			}
		}
	}

	function _joke(){
		$chistes = [
			// 'http://k45.kn3.net/taringa/2/D/1/D/A/2/FacundoandrsCamp/8B0.jpg',
			// 'http://k45.kn3.net/taringa/B/B/E/7/8/5/FacundoandrsCamp/A3A.png',
			// 'http://k37.kn3.net/taringa/7/E/1/5/F/1/FacundoandrsCamp/D2D.jpg',
			'http://k43.kn3.net/taringa/A/6/C/A/6/A/FacundoandrsCamp/201.jpg',
			'http://k43.kn3.net/taringa/8/0/4/E/C/F/FacundoandrsCamp/D8C.jpg',
			'http://k38.kn3.net/taringa/9/5/6/0/3/5/FacundoandrsCamp/93C.jpg',
			'http://k32.kn3.net/taringa/1/5/8/0/4/E/FacundoandrsCamp/6BA.jpg',
			'http://i777.photobucket.com/albums/yy56/Felikis/Chistes%20Pokemon%20Espaniol/giarados.jpg',
			'http://i777.photobucket.com/albums/yy56/Felikis/Chistes%20Pokemon%20Espaniol/lavendertaun.jpg',
			'http://i777.photobucket.com/albums/yy56/Felikis/Chistes%20Pokemon%20Espaniol/objeto.jpg',
			'https://lh6.googleusercontent.com/-3nZvdz5JbFA/TW1u0xudl-I/AAAAAAAAAHY/XQ70Pa0xq5s/s1600/meme+pokemon+missigno.jpg',
			'https://i.imgur.com/vl6QSQ3.jpg', // Fat Pidgeotto
			'¿Que le dice Pikachu a un Cyndaquil? -¡¡¡¡Pika!!!!!! -Pos rascate ',
			'¿Sabes que le dice un Charmander loco a un Squirtle?  Porfa, apágame la cola con tu Pistola de Agua.',
			'Un Ursaring se cayó de un árbol de 50 metros. ¿Te gustó el chiste? A Ursaring tampoco.',
			'¿Sabes por qué no sale Mew? ¡Porque tiene mewdo!',
			'Cuando un Charizard se tira un pedo, tiene el ataque furia.',
			'¿Cuál es el Pokémon que come más chorizo? Chorizard',
			'¿Cuál es el Pokémon que tiene dudas en geografía? Geodude',
			'¿Cuál es el Pokémon al que le gusta el té? Dragonite',
			'¿Cuál es el Pokémon que tiene la ametralladora en el nombre?  - RATATATATATATA',
			'¿Cuál es la canción que canta Digglet? "<¡Soy minero...!>"... ',
			'¿Cuál es el Pokémon más multifacético? Eevee, porque no se queda contento con ninguna evolución...',
			'¿Cuál es el colmo de un Magikarp? Que lo echen al mar.',
			'¿Cuál es el colmo de un Oddish? Que lo dejen plantado.',
			'¿Cuál es el Pokémon que le gusta el atún? Lickiatúng.',
			'¿Cuál es el grupo favorito de Eevee? Los Rolling STONES!',
			'¿Qué le dice Exeggcute a Exeggutor? *No tienes huevos.*',
			'¿Qué Pokémon viaja en tren? Bagon.',
			'¿Qué le dice Darkrai a Arceus? - Me tienes NEGRO.',
			'¿Qué dice un Caterpie cuando tiene frío? Metapod.',
			'¿Qué pasa cuando a un Pokémon le da ébola? Eboluciona.',
			'Esto era un entrenador tan loco que quería todos los Pokémon del mundo, y llego un día en el que se capturó a sí mismo.',
			'¿Por qué Arceus formó el universo? Para hacer un trabajo de plástica.',
			'Van dos travestis en una moto, y se cae Jynx.',
			'¿Qué es una Pokeparada? Una Pokechacha sin un Poketrabajo.',
			"¿Has visto lo que han dado en las notícias? http://www.elmundotoday.com/2016/07/barack-obama-admite-ahora-que-visito-espana-para-capturar-a-snorlax-en-pokemon-go/",
			'¿Sabes por qué un Sandslash tiene tantas espinas? Porque está en la etapa de la pubertad.',
			'Había una vez dos Zubats que estaban hambrientos por sangre, tenían mucho tiempo sin comer. De repente llega otro Zubat con la boca bañada en sangre,
			y los otros Zubats se quedan asombrados y le preguntan: ¿Oye, dónde conseguiste tanta sangre? Y el Zubat le responde: ¿Véis esa pared que está allí?
			Y los Zubats responden: ¡Sí! - Bueno, yo no la vi.',
		];
		$n = mt_rand(0, count($chistes) - 1);

		$this->analytics->event('Telegram', 'Games', 'Jokes');
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

		if(filter_var($chistes[$n], FILTER_VALIDATE_URL) !== FALSE){
			// Foto
			$f = tempnam("/tmp", "pkmn.") .".jpg";
			file_put_contents($f, fopen($chistes[$n], 'r'));
			$t = $this->telegram->send
				->notification( !$this->telegram->is_chat_group() )
				->file('photo', $f);
			unlink($f);
		}else{
			$this->telegram->send
				->notification( !$this->telegram->is_chat_group() )
				->text($chistes[$n], TRUE)
			->send();
		}
	}

	function _poke_attack($text, $chat = NULL){
		$telegram = $this->telegram;
		$types = $this->pokemon->attack_types();


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
			$str .= "Debilidad ";
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
		foreach($table as $t){
			if(in_array(strtolower($t['target']), $target)){
				if($t['attack'] == 0.5){ $list[0][] = $types[$t['source']]; }
				if($t['attack'] == 2){ $list[1][] = $types[$t['source']]; }
			}
		}
		foreach($list as $i){
			$list[$i] = array_unique($list[$i]);
		}
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

		if(isset($list[0]) && count($list[0]) > 0){ $str .= "Apenas le afecta *" .implode("*, *", $list[0]) ."*.\n"; }
		if(isset($list[1]) && count($list[1]) > 0){ $str .= "Le afecta mucho *" .implode("*, *", $list[1]) ."*.\n"; }

		$telegram->send
			->chat($chat)
			->notification( ($chat == $telegram->user->id) ) // Solo si es chat privado
			->text($str, TRUE)
		->send();
	}

	function _blocked(){
		exit();
	}

	function _help(){
		exit();
	}

}
