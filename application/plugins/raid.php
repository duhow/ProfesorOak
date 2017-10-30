<?php

// 7:00 -> 21:59
if(!in_array(date("H"), range(7, 21))){
	if(in_array($this->telegram->callback, ["raid apuntar", "raid estoy", "raid rewrite"])){
		$this->telegram->answer_if_callback("");
		// Remove button
		$this->telegram->send
			->text($this->telegram->text_message())
		->edit('text');
		return -1;
	}
	return;
}

if($this->telegram->is_chat_group() or $this->telegram->key == "channel_post"){

	if(
		$this->telegram->text_has(["montar", "monta", "crear", "organizar", "organiza", "nueva"], ["raid", "incursión", "#raid"]) and
		$this->telegram->words() <= 20
	){
		$place = NULL;
		if($this->telegram->text_has("en")){
			$pos = strpos($this->telegram->text(), " en ") + strlen(" en ");
			$place = substr($this->telegram->text(), $pos);
			if(!$this->telegram->text_has(["termina", "acaba"], ["a las"])){
				$place = preg_replace("/ a las \d\d[:.]\d\d$/", "", $place);
			}
		}
		if(empty($place) and $this->telegram->words() > 5){ return; }

		$poke = pokemon_parse($this->telegram->text());
		$time = time_parse($this->telegram->text());

		$str = "Nueva #raid";

		if(!empty($poke) and isset($poke['pokemon'])){
			$pokedex = $pokemon->pokedex($poke['pokemon']);
			$str .= " de " .$pokedex->name;
		}

		if(!empty($time) and isset($time['hour'])){
			$str .= " a las " .$time['hour'];
		}

		if(!empty($place)){
			$str .= " en $place";
		}

		$str .= "!\n";

		/* Desactivado por canal y gente que crea y no va.
		$user = $pokemon->user($this->telegram->user->id);
		$team = ['R' => 'red', 'B' => 'blue', 'Y' => 'yellow'];
		$str .= "- " . $this->telegram->emoji(":heart-" .$team[$user->team] .":") ." L" .$user->lvl ." @" .$user->username ."\n";
		*/

		$this->telegram->send
			->text($str)
			->inline_keyboard()
				->row_button("¡Me apunto!", "raid apuntar")
			->show()
		->send();

		$this->telegram->send->delete(TRUE);

		return -1;
	}
}

if($this->telegram->callback){
	$timeout = $this->pokemon->settings($this->telegram->chat->id, 'raid_timeout');
	if($timeout and $timeout > time()){ return -1; }
	$this->pokemon->settings($this->telegram->chat->id, 'raid_timeout', time() + 2);
	if($this->telegram->callback == "raid apuntar"){
		$str = $this->telegram->text_message();
		$user = $pokemon->user($this->telegram->user->id);

		if(empty($user->username) or $user->lvl < 5){
			$this->telegram->answer_if_callback("Deberías validarte antes de apuntarte a una raid.", TRUE);
			return -1;
		}

		$team = ['R' => 'red', 'B' => 'blue', 'Y' => 'yellow'];

		$str = explode("\n", $str);
		$str[1] = ""; // RESERVED
		$found = FALSE;

		foreach($str as $k => $s){
			if(strpos($s, $user->username) !== FALSE && strpos($s, $user->username) < 10){
				$found = TRUE;
				unset($str[$k]);
			}
		}
		// Agregar
		if(!$found){
			$str[] = "- " . $this->telegram->emoji(":heart-" .$team[$user->team] .":") ." L" .$user->lvl ." " .$user->username;
		}

		$str[1] = "Hay " .(count($str) - 2) ." entrenadores:";

		$str = implode("\n", $str);

		$this->telegram->answer_if_callback();
		$this->telegram->send
			->chat(TRUE)
			->message(TRUE)
			->text($str)
			->inline_keyboard()
				->row()
					->button("¡Me apunto!", "raid apuntar")
					->button("¡Ya estoy!", "raid estoy")
				->end_row()
				->row()
					->button("Reflotar", "raid rewrite")
				->end_row()
			->show()
		->edit('text');

		return -1;
	}elseif($this->telegram->callback == "raid estoy"){
		$str = $this->telegram->text_message();
		$user = $pokemon->user($this->telegram->user->id);

		$team = ['R' => 'red', 'B' => 'blue', 'Y' => 'yellow'];

		if(strpos($str, $user->username) !== FALSE){
			// $this->telegram->answer_if_callback("¡Ya estás apuntado en la lista!", TRUE);
			// return -1;
			$str = explode("\n", $str);
			foreach($str as $k => $s){
				if(strpos($s, $user->username) !== FALSE){
					if(strpos($s, $this->telegram->emoji(":ok:")) !== FALSE){
						$str[$k] = "- " . $this->telegram->emoji(":heart-" .$team[$user->team] .":") ." L" .$user->lvl ." " .$user->username;
					}else{
						$str[$k] = "- " .$this->telegram->emoji(":ok: ")  .$this->telegram->emoji(":heart-" .$team[$user->team] .":") ." L" .$user->lvl ." " .$user->username;
					}
				}
			}
			$str = implode("\n", $str);
		}

		$this->telegram->answer_if_callback();
		$this->telegram->send
			->chat(TRUE)
			->message(TRUE)
			->text($str)
			->inline_keyboard()
				->row()
					->button("¡Me apunto!", "raid apuntar")
					->button("¡Ya estoy!", "raid estoy")
				->end_row()
				->row()
					->button("Reflotar", "raid rewrite")
				->end_row()
			->show()
		->edit('text');

		return -1;
	}elseif($this->telegram->callback == "raid rewrite"){
		$this->telegram->answer_if_callback("");
		if(!in_array($telegram->user->id, telegram_admins(TRUE))){ return -1; }

		$this->telegram->send
			->text($this->telegram->text_message())
			->inline_keyboard()
				->row()
					->button("¡Me apunto!", "raid apuntar")
					->button("¡Ya estoy!", "raid estoy")
				->end_row()
				->row()
					->button("Reflotar", "raid rewrite")
				->end_row()
			->show()
		->send();
	}
}



?>
