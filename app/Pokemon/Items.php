<?php

namespace Pokemon;

class Items {
	const UNKNOWN          = 0;
	const POKE_BALL        = 1;
	const GREAT_BALL       = 2;
	const ULTRA_BALL       = 3;
	const MASTER_BALL      = 4;
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
	const SPECIAL_CAMERA   = 801;
	const INCUBATOR_BASIC_UNLIMITED = 901;
	const INCUBATOR_BASIC = 902;
	const POKEMON_STORAGE_UPGRADE = 1001;
	const ITEM_STORAGE_UPGRADE = 1002;

	static function toArray(){
		$oClass = new ReflectionClass(__CLASS__);
		$array = $oClass->getConstants();
        return array_flip($array);
	}
}

?>
