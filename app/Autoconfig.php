<?php

class Autoconfig extends TelegramApp\Module {
	protected $runCommands = FALSE;
	private $setupSave = TRUE;

	const TYPE_GENERAL	= 1;
	const TYPE_COLOR	= 2;
	const TYPE_ADMIN	= 3;
	const TYPE_RAID		= 4;
	const TYPE_CATCH	= 5;
	const TYPE_OFFTOPIC	= 6;

	public function run(){
		if(
			$this->user->step != "AUTOCONFIG" or
			$this->chat->is_group()
		){ return; }
		parent::run();
	}

	// Command must be started from group or channel as admin.
	// Disabled to run by itself.
	// Call @Admin ?
	protected function hooks(){

	}

	// When running Autoconfig command
	public function start(){
		if($this->user->settings('autoconfig')){
			// Ya esta en config
			// o se ha bugeado y quiere volver a empezar.
			$this->end();
		}

		if(
			$this->telegram->chat->type == 'private' or
			(
				$this->chat->is_group() and
				!$this->chat->is_admin($this->user)
			)
		){
			$this->end();
		}

		$key = md5($this->chat->id . time());
		$this->chat->settings('autoconfig_key', $key);
		$this->user->settings('autoconfig', $key);
		$this->user->settings('autoconfig_chat_type', $this->telegram->chat->type);

		// TODO DEBUG
		$this->Admin->admin_chat_message("Ejecutando autoconfig");
		$this->telegram->send
			->chat(CREATOR)
			->notification(FALSE)
			->text("Ejecutando autoconfig")
		->send();

		// TODO Tell user to config in private
		$this->telegram->send
			->chat($this->user->id)
			->notification(TRUE)
			->text("configuramos aqui")
		->send();

		// Avoid saving setting first time
		$this->setupSave = FALSE;
		// Prepare to send next message via private.
		$this->telegram->send->chat($this->user->id);
		return $this->setup_type();
	}

	public function setup_name(){
		// Nombre del grupo, en plan ciudad.
		// Si no, nada.
	}

	public function setup_location(){
		// Si es por texto, buscar en GMaps
		// Si es location, poner directamente.
		// En caso de que location GMaps ofrezca un geofence o similar, sugerir en radius.
	}

	public function setup_location_radius(){
		// Radio que ocupa el grupo en base a la posición inicial.
	}

	public function setup_type(){
		// Configurar de qué tipo será el grupo/canal.
		// Grupo: Administrativo, General, Color, Avistamientos, Raids, Offtopic (otros)
		// Canal: Avistamientos, Raids
		if($this->setupSave){
			// Expect answer
			//
			// Si no es lo que esperaba...
			if(
				!$this->telegram->text() or
				$this->telegram->callback or
				!in_array($this->telegram->text(), $this->strings->get('autoconfig_chat_type_options'))
			){
				$this->setupSave = FALSE;
				return $this->setup_type();
			}
		}

		if($this->user->settings('autoconfig_chat_type') == 'channel'){
			$this->telegram->send
				->keyboard()
					->row()
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_RAIDS))
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_CATCH))
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_OFFTOPIC))
					->end_row()
				->show(TRUE, TRUE);
		}else{
			$this->telegram->send
				->keyboard()
					->row()
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_GENERAL))
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_COLOR))
					->end_row()
					->row()
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_RAIDS))
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_CATCH))
					->end_row()
					->row()
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_ADMIN))
						->button($this->strings->get_multi('autoconfig_chat_type_options', self::TYPE_OFFTOPIC))
					->end_row()
				->show(TRUE, TRUE);
		}

		$this->telegram->send
			->text($this->strings->get('autoconfig_chat_type_question'))
		->send();

		$this->end();
	}

	public function setup_team_exclusive(){
		// Poner qué color/es son exclusivos para el grupo.
		// En caso de grupo de color, es obligatorio, si no, opcional (skip >>).
		// Si son varios, obligo a usar inline...
	}

	public function setup_team_exclusive_kick(){
		// Sólo si se ha configurado color exclusivo,
		// Botones toggle para kickear o banear según si es topo.
		// Opcional (skip >>)

		if($this->setupSave){
			$this->setupSave = FALSE;
			if($this->telegram->text_has($this->strings->get('autoconfig_skip'))){

			}
		}

		$this->telegram->send
			->keyboard()
				->row()
					->button($this->telegram->emoji(':x: '). $this->strings->get_multi('autoconfig_team_exclusive_kick_options', 0))
					->button($this->telegram->emoji(':no_entry_sign: '). $this->strings->get_multi('autoconfig_team_exclusive_kick_options', 1))
				->end_row()
				->row_button($this->telegram->emoji(':fast_forward: ') .$this->strings->get('autoconfig_skip'))
			->show(TRUE, TRUE)
			->text($this->strings->get('autoconfig_team_exclusive_kick_question'))
		->send();
	}

	public function setup_require_conditions(){
		// Multiples requisitos:
		// [x] Estar validado?
		// [ ] Tener alias de Telegram?
		// [ ] Tener foto de perfil?
	}

	public function setup_antiafk(){
		// Valor numerico o skip.
	}

	public function setup_rules(){
		// Escribir las normas del grupo
		// Skip si es offtopic
	}

	public function setup_welcome(){
		// Keyboard de Si o No
		// En caso de que quiera un mensaje personalizado, escribir directamente.
	}

	public function setup_tcc(){
		// Catalogar segun el tipo de grupo que sea.
		// Si se configura, la blacklist estará obligada a no alojar tramposos.
		// Y Oak deberá ser administrador.
	}

	public function setup_games(){
		// [ ] Activar juegos ?
		// [ ] Activar chistes
		// [ ] Activar pole (si es que llega a haber
		// [ ] Activar Pokegram
	}

	public function setup_autoconfig_key(){
		// Si tienes un codigo, puedes introducirlo para configurar directamente
		// las mismas opciones. Puede no aplicar a todos los grupos.
	}

	public function read_config($key){
		// Leer la config en forma de texto + i18n.
	}

	public function parse_config($key){
		// Parsear key config y devolver array con settings especificadas.
	}

}
