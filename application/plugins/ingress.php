<?php

$teams = [
    "RES" => ["resistencia", "resistance", "res"],
    "ENL" => ["iluminados", "enlightened", "enl"],
];

$teams_text = array();
foreach($teams as $k){ foreach($k as $t){ $teams_text[] = $t; } }

if($telegram->text_command("ingress") && $telegram->is_chat_group()){
    $ingress = ['resistance' => 0, 'enlightened' => 0];
    $users = $pokemon->group_get_members($telegram->chat->id);
    foreach($users as $u){
        foreach(array_keys($ingress) as $team){
            if($pokemon->user_flags($u, $team)){ $ingress[$team]++; }
        }
    }

    $str = ":key: " .$ingress['resistance'] ." "
            .":frog: " .$ingress['enlightened'];

    $telegram->send->text($telegram->emoji($str))->send();
    return;
}elseif($telegram->text_command("/recingress")){
    $telegram->send
        ->inline_keyboard()
            ->row()
                ->button($telegram->emoji(":heart-blue:"), "soy res", "TEXT")
                ->button($telegram->emoji(":heart-green:"), "soy enl", "TEXT")
            ->end_row()
        ->show()
        ->text("Si juegas a Ingress, ¿eres de la Resistencia o Iluminado?")
    ->send();
    return;
}elseif($telegram->text_has(["soy", "soy de la"], $teams_text)){
    if($pokemon->user_flags($telegram->user->id, ["enlightened", "resistance"])){ return; }
    $text = "Bienvenido, agente! ";
    if($telegram->text_has($teams["RES"])){
        $pokemon->user_flags($telegram->user->id, 'resistance', TRUE);
        $text .= $telegram->emoji(":key:");
    }elseif($telegram->text_has($teams["ENL"])){
        $pokemon->user_flags($telegram->user->id, 'enlightened', TRUE);
        $text .= $telegram->emoji(":frog:");
    }

    $telegram->send
        ->chat($telegram->user->id)
        ->text($text)
    ->send();
    return;
}

 ?>
