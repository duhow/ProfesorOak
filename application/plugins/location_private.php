<?php

function menu_call_location($user, $tg){
	// Comprobar si el location_now es distinto del location, para mostrar otro keyboard.
	$dist = user_distance($user, $tg->location(FALSE));
	$tg->send
		->chat($user)
		->notification(FALSE)
		->reply_to(TRUE);

	if($dist <= 80){
		user_distance($user, $tg->location(FALSE), TRUE); // SET
		$tg->send
			->text("Veo que sigues cerca de tu sitio. ¿Alguna novedad?")
			->keyboard()
				->row()
					->button($tg->emoji(":map: Pokémon"))
					->button($tg->emoji(":candy: PokéParadas"))
				->end_row()
				->row()
					->button($tg->emoji(":mouse: ¡Un Pokémon!"))
					->button($tg->emoji(":spiral: ¡Hay cebo!"))
				->end_row()
				->row_button("Cancelar")
			->selective(TRUE)
		->show(TRUE, TRUE)
		->send();
		return -1;
	}
	$tg->send
		->text("¿Qué quieres hacer con esa ubicación?")
		->keyboard()
			// ->row_button($tg->emoji(":mouse: He encontrado un Pokémon!"))
			->row_button($tg->emoji(":pin: ¡Estoy aquí!"))
			->row()
				->button($tg->emoji(":map: Ver Pokémon"))
				->button($tg->emoji(":candy: Ver PokéParadas"))
			->end_row()
			// ->row_button($tg->emoji(":home: Vivo aquí."))
			->row_button($tg->emoji("Cancelar"))
			->selective(TRUE)
		->show(FALSE, TRUE)
	->send();
	return -1;
}

function user_distance($user, $loc, $set = FALSE){
	// $loc as array
	$pokemon = new Pokemon();

	$locnow = explode(",", $pokemon->settings($user, 'location_now'));
	$dist = 0;
	if(!empty($locnow)){
		if(function_exists('location_distance')){
			$dist = location_distance($loc, $locnow);
		}
	}

	if(empty($locnow) or $set == TRUE){
		$pokemon->settings($user, 'location_now', implode(",", $loc));
	}

	return $dist;
}

function user_has_cooldown($user, $cooldown = 60, $set = FALSE){
	// return TRUE si no puede avanzar.
	$pokemon = new Pokemon();
	$cd = $pokemon->settings($user, 'pokemon_cooldown');
	if(empty($cd){ $cd = (time() + 1); }
	$has_cooldown = ($cd < time());

	if($set === TRUE){
		$pokemon->settings($user, 'pokemon_cooldown', time() + $cooldown);
	}
	return $has_cooldown;
}

if($telegram->is_chat_group()){ return; }
$step = $pokemon->step($telegram->user->id);

if(!empty($step)){
	switch ($step) {
		case 'LOCATION':
		if($telegram->location()){
			return menu_call_location($telegram->user->id, $telegram);
		}elseif($telegram->text()){
			$text = $telegram->emoji($telegram->words(0), TRUE);
			switch ($text) {
				case ':mouse:': // Pokemon Avistado
					if(user_has_cooldown($telegram->user->id)){
						$pokemon->step($telegram->user->id, NULL);
						$telegram->send
							->text("Es demasiado pronto para informar de otro Pokémon.\nTake it easy bro ;)")
							->keyboard()->hide(TRUE)
						->send();
						return -1;
					}
					$pokemon->settings($telegram->user->id, 'step_action', 'POKEMON_SEEN');
					$pokemon->step($telegram->user->id, 'CHOOSE_POKEMON');
					$telegram->send
						->text("De acuerdo, dime qué Pokémon has visto aquí?")
						->keyboard()->hide(TRUE)
					->send();
					return -1;
					break;
				case ':spiral:':
					if(user_has_cooldown($telegram->user->id)){
						$pokemon->step($telegram->user->id, NULL);
						$telegram->send
							->text("Es demasiado pronto para informar de un lure.\nTake it easy bro ;)")
							->keyboard()->hide(TRUE)
						->send();
						return -1;
					}
					$pokemon->step($telegram->user->id, 'LURE_SEEN');
					$this->_step();
					break;
				case ':home:': // Set home
					$loc = $pokemon->settings($telegram->user->id, 'location');
					$pokemon->settings($telegram->user->id, 'location_home', $loc);
					$pokemon->step($telegram->user->id, NULL);
					$this->analytics->event('Telegram', 'Set home');
					$telegram->send
						->text("Hecho!")
						->keyboard()->hide(TRUE)
					->send();
					break;
				case ':pin:': // Set here
					$loc = $pokemon->settings($telegram->user->id, 'location');
					$here = $pokemon->settings($telegram->user->id, 'location_now', 'FULLINFO');
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
							$pokemon->step($telegram->user->id, NULL);
						}
					}
					if(!$error){
						$this->analytics->event('Telegram', 'Set Current Location');
						$pokemon->settings($telegram->user->id, 'location_now', $loc);
						// TODO buscar a gente cercana.
						$loc = explode(",", $loc);
						$near = $pokemon->user_near($loc, 500, 30);

						$text = "¡Hecho! ¿Quieres hacer algo más?";
						if($telegram->user->id == $this->config->item('creator')){
							// DEBUG
							// $telegram->send->text(json_encode($near))->send();
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
					$pokemon->step($telegram->user->id, NULL);
					// $this->_locate_pokemon();
					exit();
					break;
				case ':candy:':
					$pokemon->step($telegram->user->id, NULL);
					// $this->_locate_pokestops();
					exit();
					break;
				default:

					break;
			}
		}
		break; // End LOCATION
	}

	return -1; // HACK ?
}

if($telegram->location()){
	$loc = implode(",", $telegram->location(FALSE));
	$pokemon->settings($telegram->user->id, 'location', $loc);
	$pokemon->step($telegram->user->id, 'LOCATION');
}

?>
