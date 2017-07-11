<?php

function load_karps($user){
    $CI =& get_instance();
    $pokes = $CI->db
        ->where('pokemon', 129) // Magikarp
        ->where('disabled', FALSE)
        ->where('owner', $user)
    ->get('pokegame_sightseens');

    if($pokes->num_rows() == 0){ return array(); }
    return $pokes->result_array();
}


if(!$this->telegram->is_chat_group()){ return; }
if($pokemon->settings($this->telegram->chat->id, "play_games") == FALSE){ return; }

if(
	$telegram->text_has("magikarp", ["salta", "jump"]) and
	!$telegram->has_forward and
	$telegram->words() <= 5
){

    // Cargar los Karp Karp del usuario.
    $pokes = load_karps($this->telegram->user->id);

    if(count($pokes) == 0){
        $str = "No tienes Magikarp con los que competir. :(";
        if($telegram->callback){
            $this->telegram->answer_if_callback($str, TRUE);
        }else{
            $q = $this->telegram->send
                ->notification(FALSE)
                ->text($str)
            ->send();

			sleep(4);
			$this->telegram->send->delete(TRUE);
			$this->telegram->send->delete($q);
        }

        return -1;
    }

    // Comprobar que haya pasado tiempo suficiente como para volver a jugar.
    $last = $pokemon->settings($this->telegram->user->id, "magikarp_cooldown");
    if($last && $last >= time() && $telegram->user->id != $this->config->item('creator')){
        $str = "¡Aún es demasiado pronto para volver a competir!";
        if($telegram->callback){
            $this->telegram->answer_if_callback($str, TRUE);
        }else{
            $q = $this->telegram->send
                ->notification(FALSE)
                ->text($str)
            ->send();

			sleep(4);
			$this->telegram->send->delete(TRUE);
			$this->telegram->send->delete($q);
        }

        return -1;
    }

    // Si la persona pulsa en el botón...
    if($this->telegram->callback){
        $playerA = $this->telegram->last_word();
        $playerB = $this->telegram->user->id;

        if($playerA == $playerB){
            $this->telegram->answer_if_callback("¡No puedes autoinvitarte a la partida!", TRUE);
            return -1;
        }

        // Load user data
        $userA = $pokemon->user($playerA);
        $userB = $pokemon->user($playerB);

        $karpA = load_karps($playerA);
        $karpA = (object) $karpA[mt_rand(0, count($karpA) - 1)];

        $karpB = load_karps($playerB);
        $karpB = (object) $karpB[mt_rand(0, count($karpB) - 1)];

        $jump = 13;

        $str = "<b>Magikarp Jump!</b> Compiten:\n"
                ."- Magikarp PC " .$karpA->cp ." de " .$userA->username ." VS\n"
                ."- Magikarp PC " .$karpB->cp ." de " .$userB->username .".\n\n";

        $ivA = (floor(pow($karpA->atk + $karpA->def + $karpA->sta, 1/2)) / 10);
        $ivB = (floor(pow($karpB->atk + $karpB->def + $karpB->sta, 1/2)) / 10);
        $powA = (pow($karpA->lvl, 1/3) / 10);
        $powB = (pow($karpB->lvl, 1/3) / 10);

        $rand = mt_rand(1, 30);
        $extraA = 0;
        $extraB = 0;

        if($rand % 3 == 1){
            $extraA = (mt_rand(1, 3) / 10);
        }elseif($rand % 3 == 2){
            $extraB = (mt_rand(1, 3) / 10);
        }

        $jumpA = round($jump * ($powA + $ivA + $extraA), 2, PHP_ROUND_HALF_DOWN);
        $jumpB = round($jump * ($powB + $ivB + $extraB), 2, PHP_ROUND_HALF_DOWN);

        $str .= "¡El primer Magikarp salta $jumpA m!" .($extraA > 0 ? " <b>JUMP!</b>" : "") ."\n"
                ."¡El segundo Magikarp salta $jumpB m!" .($extraB > 0 ? " <b>JUMP!</b>" : "") ."\n";

        $winner = NULL;
        if($jumpA > $jumpB){
            $winner = $userA;
        }else{
            $winner = $userB;
        }

        $str .= "\n" ."¡Ha ganado " .$winner->username ."!";

        $this->telegram->send
            ->message(TRUE)
            ->chat(TRUE)
            ->text($str, "HTML")
        ->edit('text');

        $telegram->answer_if_callback("");

        $points = $pokemon->settings($winner->telegramid, "magikarp_points");
        if(empty($points)){ $points = 0; }
        $points++;
        $pokemon->settings($winner->telegramid, "magikarp_points", $points);

        // Cooldown de una hora.
        // $pokemon->settings($playerA, "magikarp_cooldown", time() + 1800);
        $pokemon->settings($playerB, "magikarp_cooldown", time() + 1800);

        return -1;
    }

	$pokemon->settings($this->telegram->user->id, "magikarp_cooldown", time() + 1800);

    $emoji = json_decode('"\ud83c\udf8f"');
    $this->telegram->send
        ->inline_keyboard()
            ->row_button("$emoji Competir", "magikarp jump " .$this->telegram->user->id, "TEXT")
        ->show()
        ->text_replace("¡%s quiere competir a <b>Magikarp Jump</b>!\n¿Quieres retarle?", $this->telegram->user->first_name, "HTML")
    ->send();

    return -1;
}
?>
