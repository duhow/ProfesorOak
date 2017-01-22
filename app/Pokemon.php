<?php

class Pokemon extends TelegramApp\Module {
	protected $runCommands = FALSE;

	$levels = [
		200 => [
			[1.0 => 0.094],
			[1.5 => 0.135137],
			[2.0 => 0.166398],
			[2.5 => 0.192651]
		],
		400 => [
			[3.0 => 0.215732],
			[3.5 => 0.236573],
			[4.0 => 0.25572],
			[4.5 => 0.27353]
		],
		600 => [
			[5.0 => 0.29025],
			[5.5 => 0.306057],
			[6.0 => 0.321088],
			[6.5 => 0.335445]
		],
		800 => [
			[7.0 => 0.349213],
			[7.5 => 0.362458],
			[8.0 => 0.375236],
			[8.5 => 0.387592]
		],
		1000 => [
			[9.0 => 0.399567],
			[9.5 => 0.411194],
			[10.0 => 0.4225],
			[10.5 => 0.432926]
		],
		1300 => [
			[11.0 => 0.443108],
			[11.5 => 0.45306],
			[12.0 => 0.462798],
			[12.5 => 0.472336]
		],
		1600 => [
			[13.0 => 0.481685],
			[13.5 => 0.490856],
			[14.0 => 0.499858],
			[14.5 => 0.508702]
		],
		1900 => [
			[15.0 => 0.517394],
			[15.5 => 0.525943],
			[16.0 => 0.534354],
			[16.5 => 0.542636]
		],
		2200 => [
			[17.0 => 0.550793],
			[17.5 => 0.558831],
			[18.0 => 0.566755],
			[18.5 => 0.574569]
		],
		2500 => [
			[19.0 => 0.582279],
			[19.5 => 0.589888],
			[20.0 => 0.5974],
			[20.5 => 0.604824]
		],
		3000 => [
			[21.0 => 0.612157],
			[21.5 => 0.619404],
			[22.0 => 0.626567],
			[22.5 => 0.633649]
		],
		3500 => [
			[23.0 => 0.640653],
			[23.5 => 0.647581],
			[24.0 => 0.654436],
			[24.5 => 0.661219]
		],
		4000 => [
			[25.0 => 0.667934],
			[25.5 => 0.674582],
			[26.0 => 0.681165],
			[26.5 => 0.687685]
		],
		4500 => [
			[27.0 => 0.694144],
			[27.5 => 0.700543],
			[28.0 => 0.706884]
		],
		5000 => [
			[28.5 => 0.713169],
			[29.0 => 0.719399],
			[29.5 => 0.725576],
			[30.0 => 0.7317],
			[30.5 => 0.734741]
		],
		6000 => [
			[31.0 => 0.737769],
			[31.5 => 0.740786],
			[32.0 => 0.743789],
			[32.5 => 0.746781]
		],
		7000 => [
			[33.0 => 0.749761],
			[33.5 => 0.752729],
			[34.0 => 0.755686],
			[34.5 => 0.75863]
		],
		8000 => [
			[35.0 => 0.761564],
			[35.5 => 0.764486],
			[36.0 => 0.767397],
			[36.5 => 0.770297]
		],
		9000 => [
			[37.0 => 0.773187],
			[37.5 => 0.776065],
			[38.0 => 0.778933],
			[38.5 => 0.78179]
		],
		10000 => [
			[39.0 => 0.784637],
			[39.5 => 0.787474],
			[40.0 => 0.7903]
		]
	];

	public $items = [
		'POKEBALL',
		'SUPERBALL',
		'ULTRABALL',
		'MASTERBALL',
		'INCENSE',
		'MOD_LURE',
		'RAZZBERRY',
		'INCUBATOR',
		'GIFTBOX'
	];

	public $rewards = [
		1 => [],
		2 => [],
		3 => [],
		4 => [],
		5 => [],
		6 => [],
		7 => [],
		8 => [],
		9 => [],
		10 => [],
		11 => [],
		12 => [],
		13 => [],
		14 => [],
		15 => [],
		16 => [],
		17 => [],
		18 => [],
		19 => [],
		20 => [],
		21 => [],
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
		35 => [],
		36 => [],
		37 => [],
		38 => [],
		39 => [],
		40 => [],
	];

	public function load($search){
		if(is_numeric($search) or is_string($search)){  } // load Pokemon Pokedex.
		return $search;
	}

	public function pokedex($search){

	}

	public function evolution($pokemon){
		$pokemon = $this->load($pokemon);

	}

	public function movements($pokemon){
		$pokemon = $this->load($pokemon);
	}

	public function movement_best($pokemon, $str = FALSE){
		$pokemon = $this->load($pokemon);
	}

	public function movement_worst($pokemon, $str = FALSE){
		$pokemon = $this->load($pokemon);
	}

	public function attack_pokemon($pokemon, $target = 'source'){
		// This redirects to attack_type
		$pokemon = $this->load($pokemon);
	}

	public function attack_type($type, $type2 = 'source', $target = NULL){
		// TODO HACK function 2/3 args.
	}

	public function attack_table($type){

	}

	public function misspell($name, $retnum = FALSE){
		if(strtolower(substr($name, -1)) == 's'){ $name = substr($name, 0, -1); } // Plural

	}

	public function iv($pokemon, $cp = NULL, $hp = NULL, $stardust = NULL, $extra = NULL){
		if(is_array($pokemon)){
			if(is_array($cp)){ $extra = $cp; }
			$stardust = $pokemon[3];
			$hp = $pokemon[2];
			$cp = $pokemon[1];
			$pokemon = $pokemon[0];
		}
		$pokemon = $this->load($pokemon);

	}

	public function hooks(){
		if(
			$this->telegram->text_command("iv") or
			$this->telegram->text_command("ivs")
		){
			if($this->telegram->words() < 5){
				$this->telegram->send
					->notification(FALSE)
					->text('/iv *[Pokémon] [CP] [HP] [Polvos]*', TRUE)
				->send();
				$this->end();
			}else{
				$args = $this->telegram->words(TRUE);
				array_shift($args);
				$ivs = $this->iv($args);
			}
		}
	}
}
