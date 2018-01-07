<?php

namespace Pokemon;

class Items {
	const UNKNOWN          = 0;
	const POKE_BALL        = 1;
	const GREAT_BALL       = 2;
	const ULTRA_BALL       = 3;
	const MASTER_BALL      = 4;
	const PREMIER_BALL     = 5;
	const POTION           = 101;
	const SUPER_POTION     = 102;
	const HYPER_POTION     = 103;
	const MAX_POTION       = 104;
	const REVIVE           = 201;
	const MAX_REVIVE       = 202;
	const LUCKY_EGG        = 301;
	const INCENSE_ORDINARY = 401;
	const INCENSE_SPICY    = 402;
	const INCENSE_COOL     = 403;
	const INCENSE_FLORAL   = 404;
	const TROY_DISK        = 501;
	const X_ATTACK         = 602;
	const X_DEFENSE        = 603;
	const X_MIRACLE        = 604;
	const RAZZ_BERRY       = 701;
	const BLUK_BERRY       = 702;
	const NANAB_BERRY      = 703;
	const WEPAR_BERRY      = 704;
	const PINAP_BERRY      = 705;
	const GOLDEN_RAZZ_BERRY = 706;
	const GOLDEN_NANAB_BERRY = 707;
	const GOLDEN_PINAP_BERRY = 708;
	const SPECIAL_CAMERA   = 801;

	const INCUBATOR_BASIC_UNLIMITED = 901;
	const INCUBATOR_BASIC = 902;
	const INCUBATOR_SUPER = 903;

	const POKEMON_STORAGE_UPGRADE = 1001;
	const ITEM_STORAGE_UPGRADE = 1002;

	const SUN_STONE        = 1101;
	const KINGS_ROCK       = 1102;
	const METAL_COAT       = 1103;
	const DRAGON_SCALE     = 1104;
	const UP_GRADE         = 1105;
	const MOVE_REROLL_FAST_ATTACK    = 1201;
	const MOVE_REROLL_SPECIAL_ATTACK = 1202;
	const RARE_CANDY = 1301;
	const FREE_RAID_TICKET = 1401;
	const PAID_RAID_TICKET = 1402;
	const LEGENDARY_RAID_TICKET = 1403;

	static function toArray(){
		$oClass = new ReflectionClass(__CLASS__);
		$array = $oClass->getConstants();
        return array_flip($array);
	}
}

class ItemType {
	const NONE                  = 0;
	const POKEBALL              = 1;
	const POTION                = 2;
	const REVIVE                = 3;
	const MAP                   = 4;
	const BATTLE                = 5;
	const FOOD                  = 6;
	const CAMERA                = 7;
	const DISK                  = 8;
	const INCUBATOR             = 9;
	const INCENSE               = 10;
	const XP_BOOST              = 11;
	const INVENTORY_UPGRADE     = 12;
	const EVOLUTION_REQUIREMENT = 13;
	const MOVE_REROLL           = 14;
	const CANDY                 = 15;
	const RAID_TICKET           = 16;

	static function toArray(){
		$oClass = new ReflectionClass(__CLASS__);
		$array = $oClass->getConstants();
        return array_flip($array);
	}
}

class ItemCategory {
	const NONE                  = 0;
	const POKEBALL              = 1;
	const FOOD                  = 2;
	const MEDICINE              = 3;
	const BOOST                 = 4;
	const UTILITES              = 5;
	const CAMERA                = 6;
	const DISK                  = 7;
	const INCUBATOR             = 8;
	const INCENSE               = 9;
	const XP_BOOST              = 10;
	const INVENTORY_UPGRADE     = 11;
	const EVOLUTION_REQUIREMENT = 12;
	const MOVE_REROLL           = 13;
	const CANDY                 = 14;
	const RAID_TICKET           = 15;

	static function toArray(){
		$oClass = new ReflectionClass(__CLASS__);
		$array = $oClass->getConstants();
        return array_flip($array);
	}
}

?>
