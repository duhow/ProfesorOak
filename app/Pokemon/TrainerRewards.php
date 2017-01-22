<?php

namespace Pokemon;

class TrainerRewards {

	function __construct($level){
		public $rewards = [
			1 => [],
			2 => ['POKE_BALL' => 10],
			3 => ['POKE_BALL' => 15],
			4 => ['POKE_BALL' => 15],
			5 => ['POKE_BALL' => 15, 'POTION' => 10, 'REVIVE' => 10, 'INCENSE_ORDINARY' => 1],
			6 => ['POKE_BALL' => 15, 'POTION' => 10, 'REVIVE' => 5, 'INCUBATOR_BASIC' => 1],
			7 => ['POKE_BALL' => 15, 'POTION' => 10, 'REVIVE' => 5, 'INCENSE_ORDINARY' => 1],
			8 => ['POKE_BALL' => 15, 'POTION' => 10, 'REVIVE' => 5, 'RAZZ_BERRY' => 10, 'TROY_DISK' => 1],
			9 => ['POKE_BALL' => 15, 'POTION' => 10, 'REVIVE' => 5, 'RAZZ_BERRY' => 3, 'LUCKY_EGG' => 1],
			10 => ['POKE_BALL' => 20, 'SUPER_POTION' => 20, 'REVIVE' => 10, 'RAZZ_BERRY' => 1, 'INCENSE_ORDINARY' => 1, 'LUCKY_EGG' => 1, 'INCUBATOR_BASIC' => 1, 'TROY_DISK' => 1],
			11 => ['POKE_BALL' => 15, 'SUPER_POTION' => 10, 'REVIVE' => 3, 'RAZZ_BERRY' => 3],
			12 => ['GREAT_BALL' => 20, 'SUPER_POTION' => 10, 'REVIVE' => 3, 'RAZZ_BERRY' => 3],
			13 => ['GREAT_BALL' => 10, 'SUPER_POTION' => 10, 'REVIVE' => 3, 'RAZZ_BERRY' => 3],
			14 => ['GREAT_BALL' => 10, 'SUPER_POTION' => 10, 'REVIVE' => 3, 'RAZZ_BERRY' => 3],
			15 => ['GREAT_BALL' => 15, 'HYPER_POTION' => 20, 'REVIVE' => 10, 'RAZZ_BERRY' => 10, 'TROY_DISK' => 2, 'LUCKY_EGG' => 1, 'INCUBATOR_BASIC' => 1],
			16 => ['GREAT_BALL' => 10, 'HYPER_POTION' => 10, 'REVIVE' => 5, 'RAZZ_BERRY' => 5],
			17 => ['GREAT_BALL' => 10, 'HYPER_POTION' => 10, 'REVIVE' => 5, 'RAZZ_BERRY' => 5],
			18 => ['GREAT_BALL' => 10, 'HYPER_POTION' => 10, 'REVIVE' => 5, 'RAZZ_BERRY' => 5],
			19 => ['GREAT_BALL' => 15, 'HYPER_POTION' => 10, 'REVIVE' => 5, 'RAZZ_BERRY' => 5],
			20 => ['ULTRA_BALL' => 20, 'HYPER_POTION' => 20, 'REVIVE' => 20, 'RAZZ_BERRY' => 20, 'INCENSE_ORDINARY' => 2, 'INCUBATOR_BASIC' => 2, 'LUCKY_EGG' => 2, 'TROY_DISK' => 2],
			21 => ['ULTRA_BALL' => 10, 'HYPER_POTION' => 10, 'REVIVE' => 10, 'RAZZ_BERRY' => 10],
			22 => [],
			23 => [],
			24 => [],
			25 => [],
			26 => [],
			27 => [],
			28 => [],
			29 => [],
			30 => [],
			31 => [],
			32 => [],
			33 => [],
			34 => [],
			35 => ['ULTRA_BALL' => 30, 'MAX_POTION' => 20, 'MAX_REVIVE' => 20, 'RAZZ_BERRY' => 2, 'INCENSE_ORDINARY' => 2, 'LUCKY_EGG' => 1, 'INCUBATOR_BASIC' => 1, 'TROY_DISK' => 1],
			36 => ['ULTRA_BALL' => 20, 'MAX_POTION' => 20, 'MAX_REVIVE' => 10, 'RAZZ_BERRY' => 20],
			37 => ['ULTRA_BALL' => 20, 'MAX_POTION' => 20, 'MAX_REVIVE' => 10, 'RAZZ_BERRY' => 20],
			38 => ['ULTRA_BALL' => 20, 'MAX_POTION' => 20, 'MAX_REVIVE' => 10, 'RAZZ_BERRY' => 20],
			39 => ['ULTRA_BALL' => 20, 'MAX_POTION' => 20, 'MAX_REVIVE' => 10, 'RAZZ_BERRY' => 20],
			40 => ['MAX_POTION' => 40, 'MAX_REVIVE' => 40, 'RAZZ_BERRY' => 40, 'INCENSE_ORDINARY' => 4, 'LUCKY_EGG' => 4, 'INCUBATOR_BASIC' => 4, 'TROY_DISK' => 4],
		];

		return $rewards[$level];
	}
}

?>
