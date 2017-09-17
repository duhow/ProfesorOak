<?php

namespace Pokemon;

class Movement {
	public $id;                     // INT
	public $movementId;             // STRING
	public $animationId;            // INT
	public $pokemonType;            // STRING
	public $power;                  // FLOAT
	public $accuracyChance;         // FLOAT PERC
	public $criticalChance;         // FLOAT PERC
	public $staminaLossScalar;      // FLOAT PERC
	public $trainerLevelMin = 1;    // INT
	public $trainerLevelMax = 100;  // INT
	public $vfxName;                // STRING
	public $durationMs;             // INT
	public $damageWindowStartMs;    // INT
	public $damageWindowEndMs;      // INT
	public $energyDelta;            // INT
}
