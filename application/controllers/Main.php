<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Main extends CI_Controller {

	public function __construct(){
		  parent::__construct();
	}

	public function index($access = NULL){
		if(strpos($_SERVER['REMOTE_ADDR'], "149.154.167.") === FALSE){ die(); }

		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$chat = $telegram->chat;
		$user = $telegram->user;

		if($telegram->receive(["profe", "oak"]) && $telegram->receive(["donde", "dónde"]) && $telegram->receive(["estas", "estás"]) && $telegram->words() <= 5){
			$telegram->send
				->notification(FALSE)
				// ->reply_to(TRUE)
				->text($telegram->emoji("Detrás de ti... :>"))
			->send();
			exit();
		}
		if($telegram->receive(["profe", "profesor", "oak"]) && $telegram->receive(["ping", "me recibe", "estás", "estas", "estas ahi", "estás ahi", "estás ahí"]) && !$telegram->receive("program")){ $telegram->send->text("Pong! :D")->send(); exit(); }
		if($telegram->is_chat_group() && $telegram->data_received() == "new_chat_participant"){
			$set = $pokemon->settings($chat->id, 'announce_welcome');
			$new = $telegram->new_user;

			if($new->id == $this->config->item("telegram_bot_id")){
				$count = $telegram->send->get_members_count();
				if(is_numeric($count) && $count <= 5){
					$telegram->send->leave_chat();
					exit();
				}
			}elseif($telegram->is_bot($new->username)){
				// Bot agregado al grupo. Yo no saludo bots :(
				exit();
			}
			$pknew = $pokemon->user($new->id);
			if($new->id == $this->config->item('creator')){
				$telegram->send
					->notification(TRUE)
					->reply_to(TRUE)
					->text("Bienvenido, jefe @duhow! Un placer tenerte aquí! :D")
				->send();
				exit();
			}elseif(!empty($pknew)){
				$teamonly = $pokemon->settings($chat->id, 'team_exclusive');
				if(!empty($teamonly) && $teamonly != $pknew->team){
					$telegram->send
						->notification(TRUE)
						->reply_to(TRUE)
						->text("*¡SE CUELA UN TOPO!* @$pknew->username $pknew->team", TRUE)
					->send();
					exit();
				}
			}

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

				$telegram->send
					->notification(FALSE)
					->reply_to(TRUE)
					->text( $telegram->emoji($text) , TRUE)
				->send();
			}
			exit();
		}elseif($telegram->is_chat_group() && $telegram->data_received() == "left_chat_participant"){
			//
		}elseif($telegram->receive(["fake GPS", "fake", "fakegps", "nox"])){
			if($telegram->user->id != $this->config->item("creator")){
			$telegram->send
				->text("*(A)* *$chat->title* - $user->first_name @" .$user->username .":\n" .$telegram->text(), TRUE)
				->chat($this->config->item('creator'))
			->send();
			// $this->telegram->sendHTML("*OYE!* Si vas a empezar con esas, deberías dejar el juego. En serio, hacer trampas *NO MOLA*.");
			exit();
			}
		}elseif($telegram->receive("pole", NULL, TRUE) && $telegram->words() == 1){
/*			$telegram->send
				->notification(FALSE)
				->reply_to(TRUE)
				->text("Enhorabuena " .$telegram->user->first_name .", eres *gilipollas!* :D", TRUE)
			->send(); */
		}
		// if($this->telegram->chat_type() == "group"){ die(); }
		if(
			$pokemon->user_exists($user->id) &&
			$pokemon->user_verified($user->id)
		){ $this->_begin(); }

		elseif($telegram->receive(["Soy", "soy", "Yo soy", "Pues soy", "Pues yo soy"], NULL, TRUE) && $telegram->receive(['rojo', 'valor', 'amarillo', 'instinto', 'azul', 'sabiduría', 'sabiduria'])){
			if(!$pokemon->user_exists($user->id)){
				$text = trim(strtolower($telegram->last_word(TRUE)));

				if($pokemon->register($user->id, $text) !== FALSE){
					$name = $user->first_name ." " .$user->last_name;
					$pokemon->update_user_data($user->id, 'fullname', $name);
					$pokemon->update_user_data($user->id, 'verified', TRUE); // XXX Luego habrá que quitarlo
					$telegram->send
						->notification(FALSE)
						->reply_to(TRUE)
						->text("Muchas gracias $user->first_name! Por cierto, ¿cómo te llamas *en el juego*? \n_(Me llamo...)_", TRUE)
					->send();
				}else{
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

	function _begin(){
		$telegram = $this->telegram;
		$pokemon = $this->pokemon;
		$user = $telegram->user;
		$text = $telegram->text();

		$pokeuser = $pokemon->user($user->id);
		$step = $pokemon->step($user->id);

		if(!$pokemon->user_verified($user->id)){ die(); }
		if($pokemon->user_blocked($user->id)){ die(); }

		if(empty($step)){ $pokemon->step($user->id, "MENU"); }

		if($telegram->receive("/broadcast ", TRUE) && $user->id == $this->config->item('creator')){
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
		}elseif($telegram->receive(["/kick", "/ban"], TRUE) && $telegram->is_chat_group()){
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
					if($telegram->receive("kick")){
						$telegram->send->kick($kick, $telegram->chat->id);
					}elseif($telegram->receive("ban")){
						$telegram->send->ban($kick, $telegram->chat->id);
					}
				}
			}
		}elseif($telegram->receive("/autokick", NULL, TRUE) && $telegram->is_chat_group()){
			$telegram->send->kick($telegram->user->id, $telegram->chat->id);
		}elseif($telegram->receive("/adminlist", NULL, TRUE) && $telegram->is_chat_group()){
			$admins = $telegram->get_admins($telegram->chat->id, TRUE);
			$teams = ["Y" => "yellow", "B" => "blue", "R" => "red"];
			$str = "";

			foreach($admins as $a){
				$pk = $pokemon->user($a['user']['id']);
				if(!empty($pk)){ $str .= $telegram->emoji(":heart-" .$teams[$pk->team] .":") ." L" .$pk->lvl ." @" .$pk->username ." - "; }
				$str .= $a['user']['first_name'] ." ";
				if(isset($a['user']['username']) && ($a['user']['username'] != $pk->username) ){ $str .= "( @" .$a['user']['username'] ." )"; }
				$str .= "\n";
			}

			// Reply to private?
			// ->chat( $telegram->user->id )
			$telegram->send
				->notification(FALSE)
				->text($str)
			->send();
		}elseif(
			$telegram->receive("/set ", TRUE) &&
			$telegram->words() == 3 &&
			(
				( $telegram->is_chat_group() && $user->id == $this->config->item('creator') ) or
				( $telegram->is_chat_group() && in_array($user->id, $telegram->get_admins($telegram->chat->id)) ) or
				( !$telegram->is_chat_group() )
			)
		){
			$key = $telegram->words(1);
			$value = $telegram->words(2);

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
		}elseif(
			$telegram->receive("/get ", TRUE) &&
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
			( $telegram->receive(["oak", "profe"]) && $this->telegram->receive("limpia") ) or $telegram->receive("/clean")
		){
			$admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');

			if(in_array($user->id, $admins)){
				$telegram->send
					->notification(FALSE)
					->text(".\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n\n.")
				->send();
			}
		}elseif($telegram->receive(["oak", "profe"]) && $telegram->receive(["sal", "vete"]) && $telegram->is_chat_group() && $telegram->words() < 4){
			$admins = $telegram->get_admins();
			$admins[] = $this->config->item('creator');

			if(in_array($user->id, $admins)){
				$telegram->send
					->notification(FALSE)
					->text("Jo, pensaba que me queríais... :(\nBueno, si me necesitáis, ya sabéis donde estoy.")
				->send();

				$telegram->send->leave_chat();
			}
		}elseif(
			$telegram->receive(["Este ", "Éste "], TRUE) &&
			$telegram->has_reply &&
			$user->id == $this->config->item('creator')
		){
			$reply = $telegram->reply_user;
			$word = $telegram->last_word();
			if(in_array(strtolower($word), ["rojo", "azul", "amarillo"])){
				if( $pokemon->register( $reply->id, $word ) !== FALSE){
					$name = trim("$reply->first_name $reply->last_name");
					$telegram->send
						->notification(FALSE)
						->text("Vale jefe, marco a $name como *$word*!", TRUE)
					->send();
					$pokemon->update_user_data($reply->id, 'fullname', $name);
					$pokemon->update_user_data($reply->id, 'verified', TRUE); // XXX Luego habrá que quitarlo
				}elseif($pokemon->user_exists( $reply->id )){
					$telegram->send
						->notification(FALSE)
						->text("Con que un topo, eh? ¬¬ Bueno, ahora es *$word*.\n_Cuidadín, que te estaré vigilando..._", TRUE)
					->send();
					$pokemon->update_user_data($reply->id, 'team', $pokemon->team_text($word));
				}
			}elseif($telegram->receive("se llama ")){
				if($pokemon->user_exists($word)){
					$telegram->send
						->notification(FALSE)
						->reply_to(TRUE)
						->text("Oye jefe, que ya hay alguien que se llama así :(")
					->send();
				}else{
					$pokemon->update_user_data($reply->id, 'username', $word);
					$telegram->send
						->notification(FALSE)
						->text("De acuerdo, *@$word*!", TRUE)
					->send();
				}
			}elseif($telegram->receive("es nivel ")){
				if(is_numeric($word) && $word >= 5 && $word <= 36){
					$pokemon->update_user_data($reply->id, 'lvl', $word);
				}
			}

			exit();
		}elseif($telegram->receive("/investigate", NULL, TRUE) && $telegram->is_chat_group()){
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
		}elseif($telegram->receive("/count", NULL, TRUE) && $telegram->is_chat_group()){
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

		}elseif($telegram->receive("mal") && $telegram->words() < 4 && $telegram->has_reply){
			$telegram->send
				->chat($telegram->chat->id)
				->notification(FALSE)
				->message($telegram->reply->message_id)
				->text("Perdon :(")
			->edit('message');
		}elseif($telegram->receive(["estadisticas", "estadísticas", "/stats"]) && $telegram->user->id == $this->config->item('creator')){
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
		}

		// ---------------------
		// Apartado de cuenta
		// ---------------------

		if($telegram->receive(["Me llamo", "Mi nombre es", "Mi usuario es"], NULL, TRUE) && $telegram->words() <= 4){
			if(!empty($pokeuser->username)){ exit(); }
			$word = $telegram->last_word(TRUE);
			if($word[0] == "@"){ $word = substr($word, 1); }
			if(strlen($word) < 4){ exit(); }

			if($pokemon->user_exists($word)){
				$telegram->send
					->reply_to(TRUE)
					->notification(FALSE)
					->text("No puede ser, ya hay alguien que se llama *@$word* :(\nHabla con @duhow para arreglarlo.", TRUE)
				->send();
			}else{
				$pokemon->update_user_data($user->id, 'username', $word);
				$telegram->send
					->reply_to(TRUE)
					->notification(FALSE)
					->text("De acuerdo, *@$word*!", TRUE)
				->send();
			}
			exit();
		}elseif($telegram->receive(["Soy nivel", "Ya soy nivel", "Yo soy", "soy lvl", "Soy L", "Soy level", "soy nivel", "Soy lvl", "Si soy lvl"], NULL, TRUE)){
			$level = filter_var($telegram->last_word(), FILTER_SANITIZE_NUMBER_INT);
			if(is_numeric($level) && $level >= 5 && $level <= 35){
				$pokemon->update_user_data($telegram->user->id, 'lvl', $level);
				// $telegram->send
					// ->notification(FALSE)
					// ->text("Guay! A seguir subiendo campeón! :D")
				// ->send();
			}
			exit();
		}elseif($telegram->receive(["Quien soy", "Quién soy", "como me llamo", "Cómo me llamo", "who am i"], NULL, TRUE)){
			$str = "";
			$team = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];
			if(empty($pokeuser->username)){ $str .= "No sé como te llamas, sólo sé que "; }
			else{ $str .= "@$pokeuser->username, "; }

			$str .= "eres *" .$team[$pokeuser->team] ."* L" .$pokeuser->lvl .".";

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
		}elseif($telegram->receive(["quien es miri", "quien es mireia"], NULL, TRUE)){
			$telegram->send
				->text("Es la puta ama y se las tira a todas. ;D")
			->send();
			exit();
		}elseif(
			$telegram->receive(["quien es", "quién es", "quien eres", "quién eres"]) &&
			!$telegram->receive(["tu programador", "esta"]) &&
			$telegram->words() <= 5
		){
			$str = "";
			if($telegram->has_reply){
				if($telegram->reply_user->id == $this->config->item("telegram_bot_id")){
					$str = "Pues ese soy yo mismo :)";
				}else{
					$info = $pokemon->user( $telegram->reply_user->id );
					if(empty($info)){
						$str = "No sé quien es.";
					}else{
						$team = ['Y' => "Amarillo", "B" => "Azul", "R" => "Rojo"];
						if(empty($info->username)){ $str .= "No sé como se llama, sólo sé que "; }
						else{ $str .= "@$info->username, "; }

						$str .= "es *" .$team[$info->team] ."* L" .$info->lvl .".";

						if(!$info->verified){ $str .= "\n*UNTRUSTED*"; }
					}
				}
			}elseif(
				( ($telegram->words() == 3) || ($telegram->words() == 4 && $telegram->last_word() == "?") ) and
				( !$telegram->receive(["quien eres", "quién eres"]) )
			){
				if($telegram->words() == 4){ $text = $telegram->words(2); } // 2+1 = 3 palabra
				else{ $text = $telegram->last_word(); }
				$text = str_replace(["@", "?"], "", $text);
				$text = preg_replace("/[^a-zA-Z0-9]+/", "", $text); // Quitar UTF-8
				if(strlen($text) < 4){ exit(); }
				$data = $pokemon->user($text);

				$teams = ["Y" => "Amarillo", "B" => "Azul", "R" => "Rojo"];

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
					->reply_to( ($chat == $telegram->chat->id) )
					->notification(FALSE)
					->text($str, TRUE)
				->send();
			}
			exit();

		}elseif($telegram->receive("estoy aqui")){
			// Quien en cac? Que estoy aquí

		// ---------------------
		// Información General Pokemon
		// ---------------------

		}elseif(
			!$telegram->receive(["que", "qué", "como", "cómo"]) &&
			$telegram->receive(["Quien", "quién", "oak", "profe"]) &&
			$telegram->receive(["es", "te", "tu", "hizo a", "le"]) &&
			$telegram->receive(["programado", "hizo", "creado", "creador", "dios"]) &&
			$telegram->words() <= 8
		){
			$telegram->send->notification(FALSE)->text("Pues mi creador es @duhow :)")->send();
			exit();
		}elseif($telegram->receive("llama") && $telegram->receive("eevee")){
			$pkmn = "";
			if($telegram->receive("agua")){
				$pkmn = "Vaporeon";
			}elseif($telegram->receive("fuego")){
				$pkmn = "Flareon";
			}elseif($telegram->receive(["eléctrico", "electrico", "electricidad"])){
				$pkmn = "Jolteon";
			}
			if(!empty($pkmn)){ $telegram->send->notification(FALSE)->text("Creo que te refieres a *$pkmn*?", TRUE)->send(); }
			exit();
		}elseif(
			$telegram->receive(["funciona ", "funcionan ", "va ", "caido ", "caer ", "muerto", "caído ", "estado "]) &&
		 	$telegram->receive(["juego", "pokemon", "servidor",  "server", "web", "bien"]) &&
			!$telegram->receive(["ese", "a mi me va", "a mis", "Que alg", "esa", "este", "caza", "su bola", "atacar", "cambi", "futuro", "esto", "para", "mapa", "contando", "va lo de", "llevamos", "a la", "va bastante bien"]) &&
			$telegram->words() < 15 && $telegram->words() > 2
		){
			/* $web = file_get_contents("http://www.mmoserverstatus.com/pokemon_go");
			$web = substr($web, strpos($web, "Spain"), 45);
			if(strpos($web, "red") !== FALSE){
				$str = "*NO funciona, hay problemas en España.*\n(Si bueno, aparte de los políticos.)";
			}elseif(strpos($web, "green") !== FALSE){
				$str = "Pokemon GO funciona correctamente! :)";
			} */
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

			if($pkgo == TRUE && $ptc == TRUE){ $str = "¡Todo está funcionando correctamente!"; }
			elseif($ptc != TRUE){ $str = "El juego funciona, pero parece que el *Club de Entrenadores tiene problemas.*\n_(¿Y cuándo no los tiene?)_"; }
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

		}elseif($telegram->receive("Lista de ", NULL, TRUE) && $telegram->receive(["enlace", "link"]) && $telegram->words() < 5){
			$str = "";
			$links = $pokemon->link("ALL");
			$str = implode("\n- ", array_column($links, 'name'));
			$telegram->send
				->notification(FALSE)
				->text("- " .$str)
			->send();
			exit();
		}elseif(
			$telegram->receive(["Enlace", "Link"], NULL, TRUE) or
			$telegram->receive(["/enlace", "/link"], NULL, TRUE) &&
			!$telegram->receive("http") &&
			$telegram->words() < 6
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

		if($telegram->receive(["añadir", "agreg", "crear", "solicit", "pedir"]) && $telegram->receive(["paradas", "pokeparadas"])){
			$help = "Lo siento, pero por el momento no es posible crear nuevas PokéParadas, tendrás que esperar... :(";
		}elseif($telegram->receive(["poli ", "polis ", "policía ", "policia "]) && $telegram->receive(["juga", "movil", "móvil"])){
			$help = "Recuerda que jugar mientras conduces el coche o vas en bicicleta, está *prohibido*. "
					."Podrías provocar un accidente, así que procura jugar con seguridad! :)";
		}elseif($telegram->receive(["significa", "quiere decir", "que es", "qué es"]) && $telegram->receive(["L1", "L2", "L8"])){
			$help = "Lo del *L1* es *Level 1* (*Nivel*). Si puedes, dime tu nivel y lo guardaré.\n_(Soy nivel ...)_";
		}elseif($telegram->receive("espacio") && $telegram->receive("mochila")){ // $telegram->receive(["como", "cómo"]) &&
			$help = "Tienes una mochila en la Tienda Pokemon, así que tendrás que buscar PokeMonedas si quieres comprarla. Si no, te va a tocar hacer hueco...";
		}elseif($telegram->receive(["cuáles son", "cuales son"]) && $telegram->receive("legendarios")){
			$help = "Pues según la historia, serían *Articuno*, *Zapdos* y *Moltres*. Incluso hay unos Pokemon que se sabe poco de ellos... *Mew* y *Mewtwo*...";
		}elseif($telegram->receive(["presentate", "preséntate"]) && $telegram->receive(["profe", "profesor", "oak"])){
			$help = "¡Buenas a todos! Soy el *Profesor Oak*, programado por @duhow.\n"
					."Mi objetivo es ayudar a todos los entrenadores del mundo, aunque de momento me centro en España.\n\n"
					."Conmigo podréis saber información sobre los Pokémon, cuáles son los tipos de ataques recomendados para debilitarlos rápidamente, y muchas más cosas, "
					."como por ejemplo cómo evolucionar a ciertos Pokémon o ver información de entrenadores, para saber de qué equipo son.\n\n"
					."Para poder hablar conmigo, tengo que saber de qué equipo sois, bastará con que digáis *Soy rojo*, *azul* o *amarillo*. "
					."Pero por favor, sed sinceros y nada de bromas, que yo me lo tomo muy en serio.\n"
					."Una vez hecho, podéis preguntar por ejemplo... *Debilidad contra Pikachu* y os enseñaré como funciona.\n"
					."Espero poder ayudaros en todo lo posible, ¡muchas gracias!";
		}elseif(
			($telegram->receive(["lista", "ayuda", "para que sirve", "para qué sirve"]) && $telegram->receive(["comando", "oak", "profe"]) && !$telegram->receive(["analista"])) ||
			$telegram->receive("/help")){

			// $telegram->send("Aún no tengo una lista de comandos, tengo que hacerla. Ten paciencia, tengo que ir aprendiendo cosas de los Hipsters en el Starbucks. :P");
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
			!$telegram->receive("http") && // Descargas de juegos? No, gracias
			$telegram->receive(["descarga", "enlace", "actualizac"]) &&
			$telegram->receive(["pokemon", "pokémon", "juego"]) &&
			$telegram->words() <= 12
		){
			$help = "Te envío la descarga:\n\n*Android:* https://play.google.com/store/apps/details?id=com.nianticlabs.pokemongo\n"
					."*iPhone:* https://itunes.apple.com/es/app/pokemon-go/id1094591345?mt=8";
		}elseif($telegram->receive("mejorar") && $telegram->receive(["antes", "después", "despues"]) && $telegram->receive(["evolución", "evolucionar", "evolucione"])){
			$help = "En principio es irrelevante, puedes mejorar un Pokemon antes o después de evolucionarlo sin problemas.";
		}elseif($telegram->receive(["calculadora", "calcular", "calculo", "calcula", "tabla", "pagina", "xp ", "experiencia"]) && $telegram->receive(["evolución", "evolucion", "evoluciona", "evolucione", "nivel", "PC", "CP"])){
			$help = "Claro! Te refieres a la Calculadora de Evolución, verdad? http://pogotoolkit.com/";
		}elseif($telegram->receive(["PC", "estadisticas", "estadísticas", "estados", "ataque"]) && $telegram->receive(["pokemon", "pokémon", "máximo", "maximo"]) && !$telegram->receive(["mes"])){
			$help = "Puedes buscar las estadísticas aquí: http://pokemongo.gamepress.gg/pokemon-list";
		}elseif($telegram->receive(["mapa", "página", "pagina"]) && $telegram->receive(["pokemon", "pokémon", "ciudad"]) && !$telegram->receive("evoluci")){
			// $help = "Claro! El mapa para capturar Pokémon: https://pokevision.com/";
		}elseif($telegram->receive(["como", "cómo"]) && $telegram->receive(["conseguir", "consigue"]) && $telegram->receive(["objeto", "incienso", "cebo", "huevo"])){
			$help = "En principio si vas a las PokeParadas y tienes suerte, también deberías de poder conseguirlos.";
		}elseif($telegram->receive(["cambiar", "cambio"]) && $telegram->receive(["facción", "faccion", "equipo", "team"])){
			$help = "Según la página oficial de Niantic, aún no es posible cambiarse de equipo. Tendrás que esperar o hacerte una cuenta nueva, pero *procura no jugar con multicuentas, está prohibido.*";
		}elseif($telegram->receive("datos") && $telegram->receive(["movil", "móvil", "moviles", "móviles"]) && !$telegram->receive("http")){
			$help = "Si te has quedado sin datos, deberías pensar en cambiarte a otra compañía o conseguir una tarifa mejor. "
					."Te recomiendo que tengas al menos 4GB si vas a ponerte a jugar en serio.";
		}elseif($telegram->receive(["no", "os funciona", "no funciona", "no me funciona", "problema"]) && $telegram->receive(["GPS", "ubicacion", "ubicación"]) && !$telegram->receive(["fake", "bueno"])){
			$help = "Si no te funciona el GPS, comprueba los ajustes de GPS. Te recomiendo que lo tengas en modo *sólo GPS*. "
					."Procura también estar en un espacio abierto, el GPS en casa no funciona a no ser que lo tengas en *modo ahorro*. \n"
					."Si sigue sin funcionar, prueba a apagar el móvil por completo, espera un par de minutos y vuelve a probar.";
		}elseif($telegram->receive(["batería", "bateria"]) && $telegram->receive(["externa", "portatil", "portátil", "recargable", "extra"]) && !$telegram->receive(["http", "voy con", "tengo", "del port"])){
			$help = "En función de lo que vayas a jugar a Pokemon GO, puedes coger baterías pequeñas. "
					."La capacidad se mide en mAh, cuanto más tengas, más tiempo podrás jugar.\n\n"
					."Si juegas unas 2-3 horas al día, te recomiendo al menos una de 5.000 mAh. Rondan más o menos *8-12€*. "
					."Pero si quieres jugar más rato, entonces mínimo una de 10.000 o incluso 20.000 mAh. El precio va entre *20-40€*. "
					."Éstas van bien para compartirlas con la gente, por si tu amigo se queda sin batería (o tu mismo si te llega a pasar).\n"
					."Recomiendo las que son de marca *Anker* o *RAVPower*, puedes echarle un vistazo a ésta si te interesa: http://www.amazon.es/dp/B019X8EXJI";
		}elseif(
			$telegram->receive(["evolución", "evolucion", "evolucionar", "evoluciones"]) &
			$telegram->receive(["evee", "eevee", "jolteon", "flareon", "vaporeon"]) &&
			$telegram->receive(["?", "¿"]) &&
			!$telegram->receive(["mejor"])
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

		if($telegram->receive( ["atacando", "atacan"]) && $telegram->receive(["gimnasio", "gym"])){

		}elseif($telegram->receive(["debilidad ", "debilidades ", "luchar ", "atacar "]) && $telegram->receive(["contra ", "hacia ", "sobre ", "de "])){
			// if($this->telegram->is_chat_group()){ die(); }
			if(in_array($telegram->words(), [3,4]) && strpos($telegram->last_word(), "aqu") !== FALSE){
				$text = $telegram->words(2, TRUE);
				$this->_poke_attack($text, $telegram->chat->id);
			}else{
				$text = $telegram->last_word(TRUE);
				$this->_poke_attack($text, $telegram->user->id);
			}
		}elseif($telegram->receive(["evolución", "evolucion"])){
			if(strpos($telegram->last_word(TRUE), "aqu") !== FALSE){
				$chat = $telegram->chat->id;
				$text = $telegram->words( $telegram->words() - 2, TRUE);
			}else{
				$chat = $telegram->user->id;
				$text = $telegram->last_word(TRUE);
			}

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
				( ( $telegram->receive(["está", "esta"]) && $telegram->receive(["aqui", "aquí"]) ) || ( $telegram->receive(["alguno", "alguien"]) && $telegram->receive("es") ) ) &&
				( !$telegram->receive(["alguien es", "alguien ha", "estar", "estamos", "alguno es", "algunos", "alguno como", "alguien está", "alguno esta", "que es"]) ) // Alguien está aquí? - Alguno es....
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
		}elseif($telegram->receive(["team", "equipo"]) && $telegram->receive(["sois", "hay aquí", "hay aqui", "estáis", "estais"])){
			exit();
		}elseif($telegram->receive(["Qué", "Que"], NULL, TRUE) && $telegram->receive(["significa", "es"])){
			exit();
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

		if($telegram->receive(["tira el dado", "lanza el dado", "tira los dados", "tirar los dados", "roll the dice", "/dado"], ["el dado", "los dados", "the dice"], TRUE)){
			$can = $pokemon->settings($telegram->chat->id, 'play_games');
			if($can != FALSE or $can === NULL){
				$joke = "*" .mt_rand(1,6) ."*";
			}
		}elseif($telegram->receive(["piedra papel tijera", "piedra papel o tijera", "piedra, papel, tijera", "/rps"])){
			$rps = ["Piedra", "Papel", "Tijera"];
			if($telegram->receive(["lagarto", "/rpsls"])){ $rps[] = "Lagarto"; }
			if($telegram->receive(["spock", "/rpsls"])){ $rps[] = "Spock"; }
			$n = mt_rand(0, count($rps) - 1);

			$can = $pokemon->settings($telegram->chat->id, 'play_games');
			if($can != FALSE or $can === NULL){
				$joke = "*" .$rps[$n] ."!*";
			}
		}elseif($telegram->receive(["cara o cruz", "/coin", "/flip"], NULL, TRUE)){
			$n = mt_rand(0, 99);
			$flip = ["Cara!", "Cruz!"];

			$joke = "*" .$flip[$n % 2] ."*";
		}elseif($telegram->receive( ["Cuentame", "cuéntame", "cuéntanos", "cuenta"], NULL, TRUE) && $telegram->receive(["otro chiste", "un chiste"]) ){
			$this->_joke();
			exit();
		}elseif($telegram->receive(["A que s"]) && !$telegram->receive("para que s") && $telegram->receive(["profesor", "profe", "oak"])){
			$resp = ["¡Por supuesto que sí!",
				"Mmm... Te equivocas. _(Aunque sea por llevar la contraria)_",
				"Pues ahora me has dejado con la duda..."
			];
			$n = mt_rand(0, count($resp) - 1);
			if($pokemon->settings($telegram->chat->id, 'say_reply') == TRUE or $user->id = $this->config->item('creator')){
				$joke = $resp[$n];
			}
		}elseif($telegram->receive(["Gracias profesor", "Gracias Oak", "Gracias profesor Oak", "Gracias profe", "el puto amo", "que maquina eres"])){
			$frases = ["De nada, entrenador! :D", "Nada, para eso estamos! ^^", "Gracias a ti :3"];
			$n = mt_rand(0, count($frases) - 1);

			$joke = $frases[$n];
		}elseif($telegram->receive(["Ty bro", "ty prof"])){
			if($pokemon->settings($telegram->chat->id, 'jokes') != FALSE){
				$joke = "Yeah ma nigga 8-)";
			}
		}elseif($telegram->receive("oak") && $telegram->receive(["versión", "version"])){
			$date = (time() - filemtime(__FILE__));
			$joke = "Versión de hace " .floor($date / 60) ." minutos.";
		}elseif($telegram->receive(["buenos", "buenas", "bon"]) && $telegram->receive(["días", "día", "dia", "tarde", "tarda", "tardes"])){
			if($pokemon->settings($telegram->chat->id, 'say_hello') == TRUE){
				$joke = "Buenas a ti también, entrenador! :D";
			}
		}elseif($telegram->receive(["oak", "profe"]) && $telegram->receive(["cuantos", "cuántos", "cuando"]) && $telegram->receive(["años", "cumple"])){
			$release = strtotime("2016-07-16 14:27");
			$birthdate = strtotime("now") - $release;
			$joke = "Cumplo " .floor($birthdate / (60*60*24)) ." días. ";
			$joke .= $telegram->emoji(":)");
		}elseif($telegram->receive(["saluda", "saludo"]) && $telegram->receive(["profe", "profesor", "oak"])){
			if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE or $telegram->user->id == $this->config->item('creator')){
				$joke = "Un saludo para todos mis fans! :D";
			}
		}elseif($telegram->receive(["necesitas", "necesitais ", "necesitáis "]) && $telegram->receive(["novio", "novia", "pareja", "follar"])){
			// if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE or $telegram->user->id == $this->config->item('creator')){
				$joke = "¿Novia? Qué es eso, se come?";
			// }
		}elseif($telegram->receive("Team Rocket")){
			$telegram->send->notification(FALSE)->file('photo', FCPATH . "teamrocket.jpg", "¡¡El Team Rocket despega de nuevoooooo...!!");
			$telegram->send->notification(FALSE)->file('audio', FCPATH . "teamrocket.ogg");
			exit();
		}elseif($telegram->receive("sextape")){
			$telegram->send->notification(FALSE)->file('video', FCPATH . "sextape.mp4");
			exit();
		}elseif($telegram->receive("Profesor Oak", TRUE)){
			if($pokemon->settings($telegram->chat->id, 'say_hey') == TRUE){
				$joke = "Dime!";
			}
			// $telegram->send("Dime!");
		}elseif($telegram->receive(["programado", "funcionas"]) && $telegram->receive(["profe", "oak", "bot"])){
			$joke = "Pues yo funciono con *PHP* (_CodeIgniter_) :)";
		}elseif($telegram->receive(["profe", "profesor", "oak"]) && $telegram->receive(["te quiero", "te amo"])){
			if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE){
				$joke = "¡Yo también te quiero! <3";
			}
		}elseif($telegram->receive(["te la com", "te lo com", "un hijo", "me ha dolido"]) && $telegram->receive(["oak", "profe", "bot"])){
			$joke = "Tu sabes lo que es el fiambre? Pues tranquilo, que no vas a pasar hambre... ;)";
			$telegram->send
				->notification(FALSE)
				->file('sticker', 'BQADBAADGgAD9VikAAEvUZ8dGx1_fgI');
		}elseif($telegram->receive(["transferir", "transfiere", "recicla"]) && $telegram->receive(["pokemon", "pokémon"])){
			$telegram->send->notification(FALSE)->file('document', FCPATH . "pidgey.gif", "Espera entrenador, que te voy a transferir un caramelo...");
		}elseif($telegram->receive(["vas ", "hay ", "es una "]) && $telegram->receive("fiesta")){
			if($pokemon->settings($telegram->chat->id, 'shutup') != TRUE){
				$telegram->send
					->notification(FALSE)
					->caption("¿Fiesta? ¡La que te va a dar ésta!")
					->file('document', "BQADBAADpgMAAnMdZAePc-TerW2MSwI");
			}
		}

		if(!empty($joke)){
			$telegram->send
				->notification(FALSE)
				->text($joke, TRUE)
			->send();

			exit();
		}


		if($telegram->receive("@") && !$telegram->receive("@ ") && $telegram->is_chat_group()){
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

		$jokes = $this->pokemon->settings($this->telegram->chat->id, 'jokes');
		$shut = $this->pokemon->settings($this->telegram->chat->id, 'shutup');

		if(
			$this->telegram->user->id != $this->config->item('creator') &&
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

		$pokemon = $this->pokemon->find($text);
		if($pokemon !== FALSE){
			$str .= "#" .$pokemon['id'] ." - *" .$pokemon['name'] ."* (*" .$types[$pokemon['type']] ."*" .(!empty($pokemon['type2']) ? " / *" .$types[$pokemon['type2']] ."*" : "") .")\n";
			$attack = $pokemon['type'];
		}else{
			$attack = $this->pokemon->attack_type($text);
			if(empty($attack)){
				// $this->telegram->send("Eso no existe, ni en el mundo Pokemon ni en la realidad.");
				exit();
			}
			$attack = $attack['id']; // Attack es toda la fila, céntrate en el ID.
		}

		$table = $this->pokemon->attack_table($attack);
		$target[] = $attack;
		if($pokemon !== FALSE && $pokemon['type2'] != NULL){
			$table = array_merge($table, $this->pokemon->attack_table($pokemon['type2']));
			$target[] = $pokemon['type2'];
		}

		// inutil, debil, muy fuerte
		$list = array();
		foreach($table as $t){
			if(in_array(strtolower($t['target']), $target)){
				if($t['attack'] == 0){ $list[0][] = $types[$t['source']]; }
				if($t['attack'] == 0.5){ $list[1][] = $types[$t['source']]; }
				if($t['attack'] == 2){ $list[2][] = $types[$t['source']]; }
			}
		}

		foreach($list as $i => $k){
			$list[$i] = array_unique($list[$i]);
		}

		if(isset($list[0]) && count($list[0]) > 0){ $str .= "Es inútil atacarle con *" .implode("*, *", $list[0]) ."*.\n"; }
		if(isset($list[1]) && count($list[1]) > 0){ $str .= "Apenas le afecta *" .implode("*, *", $list[1]) ."*.\n"; }
		if(isset($list[2]) && count($list[2]) > 0){ $str .= "Le afecta mucho *" .implode("*, *", $list[2]) ."*.\n"; }

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
