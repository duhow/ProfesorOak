<?php

namespace Pokemon;

class BadgeTypes {
	const UNSET = 0;
	const TRAVEL_KM = 1;
	const POKEDEX_ENTRIES = 2;
	const CAPTURE_TOTAL = 3;
	const DEFEATED_FORT = 4;
	const EVOLVED_TOTAL = 5;
	const HATCHED_TOTAL = 6;
	const ENCOUNTERED_TOTAL = 7;
	const POKESTOPS_VISITED = 8;
	const UNIQUE_POKESTOPS = 9;
	const POKEBALL_THROWN = 10;
	const BIG_MAGIKARP = 11;
	const DEPLOYED_TOTAL = 12;
	const BATTLE_ATTACK_WON = 13;
	const BATTLE_TRAINING_WON = 14;
	const BATTLE_DEFEND_WON = 15;
	const PRESTIGE_RAISED = 16;
	const PRESTIGE_DROPPED = 17;
	const TYPE_NORMAL = 18;
	const TYPE_FIGHTING = 19;
	const TYPE_FLYING = 20;
	const TYPE_POISON = 21;
	const TYPE_GROUND = 22;
	const TYPE_ROCK = 23;
	const TYPE_BUG = 24;
	const TYPE_GHOST = 25;
	const TYPE_STEEL = 26;
	const TYPE_FIRE = 27;
	const TYPE_WATER = 28;
	const TYPE_GRASS = 29;
	const TYPE_ELECTRIC = 30;
	const TYPE_PSYCHIC = 31;
	const TYPE_ICE = 32;
	const TYPE_DRAGON = 33;
	const TYPE_DARK = 34;
	const TYPE_FAIRY = 35;
	const SMALL_RATTATA = 36;
	const PIKACHU = 37;

	static function array(){
		$oClass = new ReflectionClass(__CLASS__);
		$array = $oClass->getConstants();
        return array_flip($array);
	}
}

?>
