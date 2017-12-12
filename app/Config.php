<?php

class Config extends TelegramApp\Module {
	protected $runCommands = FALSE;

	public function run(){
		if($this->telegram->is_chat_group() and !$this->chat->is_admin($this->user)){ return; }
		if(in_array("set_abuse", $this->user->flags)){ return; }
		parent::run();
	}

	protected function hooks(){
		if($this->telegram->text_command("get") && $this->telegram->words() > 1){
			if($this->telegram->text_has(["private", "p"])){
				$this->telegram->send->chat($this->user->id);
			}
			$target = NULL;
			if($this->user->id == CREATOR){
				if($this->telegram->has_reply){
					$target = $this->telegram->reply_target('forward')->id;
				}elseif($this->telegram->words() >= 3){
					$target = $this->telegram->last_word();
					if(!is_numeric($target) and is_string($target)){
						$target = $this->Group->search_by_name($target);
					}
				}
			}
			$v = $this->get($this->telegram->words(1), $target);
			$this->telegram->send
				->notification(FALSE)
				->text($v)
			->send();
			$this->end();
		}elseif($this->telegram->text_command("set") && in_array($this->telegram->words(), [3,4])){
			if(
				$this->telegram->words() == 4 and
				$this->user->id != CREATOR
			){ $this->end(); }

			$this->end();
		}elseif(
			$this->telegram->text_has(["oak", "profe", "profesor"]) and
			$this->telegram->text_has("qué", ["puedes hacer", "se puede hacer", "tienes activo", "tienes activado"]) and
			$this->telegram->text_contains("?") and
			$this->telegram->words() <= 7 and
			$this->chat->command_limit('settingsview', 7) == FALSE
		){
			$this->telegram->send
				->text($this->display_settings($this->chat->id))
			->send();

			$this->end();
		}
	}

	public function set($key, $value, $chat = NULL){
		if(empty($key) or empty($value)){ return FALSE; }
		if(empty($chat)){ $chat = $this->chat->id; }

		// $this->tracking->track('Set config', $key);
		$target = new Chat($chat);
		if($target->load()){
			$value = $this->parse_value($value);
			$target->settings($key, $value);

			$str = ":floppy_disk: Config\n"
					.":id: " .$this->telegram->user->id ." - @" .$this->telegram->user->username . " " .$this->telegram->user->first_name ."\n"
					.":multiuser: " .$this->telegram->chat->id ." - " .(@$this->telegram->chat->title ?: @$this->telegram->chat->first_name) ."\n"
					.":ok: $key -";
			$str = $this->telegram->emoji($str);
			$str .= " " .json_encode($value);
			$r = $this->telegram->send
				->chat(CREATOR)
				->text($str)
			->send();
			$this->Main->message_assign_set($r, $this->user->id);
		}
	}

	public function get($key, $chat = NULL){
		if(empty($chat)){ $chat = $this->chat->id; }

		// Si pide todos
		if(in_array(strtolower($key), ["all", "*"])){
			// Y no es el creador, fuera.
			if($this->user->id != CREATOR){ return NULL; }
		}else{
			// Si sólo pide uno
			$this->db->where('type', $key);
		}

		$res = $this->db
			->where('uid', $chat)
		->get('settings');

		if($this->db->count == 0){ return NULL; }
		elseif($this->db->count == 1){
			$v = $this->parse_value($res[0]['value']);
			if(is_array($v) or is_bool($v)){ $v = json_encode($v); }
			return $v;
		}

		$str = "";
		foreach($res as $k => $v){
			$v = $this->parse_value($v);
			if(is_array($v) or is_bool($v)){ $v = json_encode($v); }

			$str .= $k .": " .$v ."\n";
		}
		return $str;
	}

	public function display_settings($chat = NULL){
		if(empty($chat)){ $chat = $this->chat->id; }

		$str = "";
		$opts = array();
		$chat = new Chat($chat);
		$chat->load();
		$admin = (in_array($this->config->item('telegram_bot_id'), telegram_admins()));

		// ----------------
		$str = "";
		$s = $chat->settings('announce_welcome');
		if($s != NULL and $s == FALSE){ $str = $this->strings->get('config_announce_welcome_off'); }
		$str = $this->strings->get('config_announce_welcome_on');

		if($chat->settings('welcome')){
			$str .= $this->strings->get('config_announce_welcome_custom');
		}
		$str .= ".";
		$opts[] = $str;
		// ----------------
		$str = "";
		$s = $chat->settings('blacklist');
		if($s){
			$s = implode(", ", explode(",", $s));
			$str = $this->strings->parse('config_blacklist', $s);
			if(!$admin){ $str = $this->strings->parse('config_blacklist_noadmin', $s); }
		}
		$opts[] = $str;
		// ----------------
		$str = "";
		$s = $chat->settings('team_exclusive');
		if(!empty($s)){
			$col = ['R' => 'valor', 'B' => 'mystic', 'Y' => 'instinct'];
			$color = 'team_' .$col[$s] .'_color';
			$str = $this->strings->parse('config_team_exclusive', $this->strings->get_multi($color, 1));
			if($chat->settings('team_exclusive_kick')){
				if($admin){ $str .= " " .$this->strings->get('config_team_exclusive_kick_on'); }
				else{ $str .= " " .$this->strings->get('config_team_exclusive_kick_off'); }
			}
		}
		$opts[] = $str;
		// ----------------
		$str = "";
		if($chat->settings('require_verified')){
			$str = $this->strings->get('config_require_verified');
			if($chat->settings('require_verified_kick')){
				if($admin){ $str .= " " .$this->strings->get('config_require_verified_kick_on'); }
				else{ $str .= " " .$this->strings->get('config_require_verified_kick_off'); }
			}
		}
		$opts[] = $str;
		// ----------------
		$str = "";
		$can = array();
		$cant = array();

		foreach(['jokes', 'play_games', 'pokegram'] as $set){
			$s = $chat->settings($set);
			if($s != NULL and $s == FALSE){ $cant[] = $this->strings->get('config_' .$set); }
			else{ $can[] = $this->strings->get('config_' .$set); }
		}

		if(empty($can)){
			$last = array_pop($cant);
			$str = $this->strings->parse('config_cant_all', [implode(", ", $cant), $last]);
		}elseif(empty($cant)){
			$last = array_pop($can);
			$str = $this->strings->parse('config_can_all', [implode(", ", $can), $last]);
		}else{
			$last = NULL;
			if(count($can) > 1){ $last = array_pop($can); }
			$str = "Se puede " .implode(", ", $can) .($last ? " y $last, " : ", ");

			$last = NULL;
			if(count($cant) > 1){ $last = array_pop($cant); }
			$str .= "pero no " .implode(", ", $cant) .($last ? " ni $last." : ".");
		}

		$opts[] = $str;
		// ----------------
		$s = $chat->settings('antispam');
		if($s === NULL){ $s = TRUE; }
		$str = $this->strings->get('config_antispam_' .($s ? "on" : "off"));

		$s = $chat->settings('antiflood');
		if($s > 0){
			$str .= $this->strings->parse('config_antiflood', $s);
			if($chat->settings('antiflood_ban') and $admin){
				$str .= $this->strings->parse('config_antiflood_ban');
			}
		}

		$str .= ".";

		$opts[] = $str;
		// ----------------
		$s = $chat->settings('custom_commands');
		$str = $this->strings->get('config_custom_commands_off');

		if($s){
			$s = unserialize($s);
			$str = $this->strings->parse('config_custom_commands_on', count($s));
			if(count($s) > 9){
				$str .= $this->strings->get('config_custom_commands_too_much');
			}
		}

		$opts[] = $str;
		// ----------------
		$s = $chat->settings('blackword');
		$str = $this->strings->get('config_blackword_off');

		if($s){
			$s = explode(",", $s);
			$str = $this->strings->parse('config_blackword_on', count($s));
		}

		$opts[] = $str;
		// ----------------
		$str = "";

		if($chat->settings('shutup')){
			$str = $this->strings->get('config_shutup');
		}
		$opts[] = $str;
		// ----------------
		$str = $this->strings->get('config_pole_off');

		if($chat->settings('pole')){
			// $members = $pokemon->group_users_active($chat, TRUE);
			$members = 11; // TODO
			$str = $this->strings->get('config_pole_unavailable');
			if($members > 6){
				$str = $this->strings->get('config_pole_on');
			}
		}
		$opts[] = $str;
		// ----------------
		$str = $this->strings->get('config_related_groups_none');
		$can = array();

		if($chat->settings('admin_chat')){ $can[] = "administrativo"; }
		if($chat->settings('offtopic_chat')){ $can[] = "offtopic"; }
		if($chat->settings('pair_team_Y')){ $can[] = $this->strings->get('team_instinct_color'); }
		if($chat->settings('pair_team_R')){ $can[] = $this->strings->get('team_valor_color'); }
		if($chat->settings('pair_team_B')){ $can[] = $this->strings->get('team_mystic_color'); }

		if(count($can) == 1){
			$str = $this->strings->parse('config_related_groups_one', $can[0]);
		}elseif(count($can) > 1){
			$last = array_pop($can);
			for($i = 0; $i < count($can); $i++){ $can[$i] = $this->strings->parse('config_related_group', $can[$i]); }

			$str = $this->strings->parse('config_related_groups_many', [implode(", ", $can), $last]);
		}

		$opts[] = $str;
		// ----------------
		$str = "";

		$link = ($chat->settings('link_chat') ? "1" : "0");
		$loc = ($chat->settings('location') ? "1" : "0");

		$str = $this->strings->get('config_linkloc_' .$link.$loc);

		$opts[] = $str;
		// ----------------


		$str = "";
		foreach($opts as $t){
			if(empty(trim($t))){ continue; }
			$str .= "$t\n";
		}

		return $str;
	}

	public function parse_value($value){
		if(in_array(strtolower($value), ["yes", "true", "on"]) or $value == 1){ $value = TRUE; }
		elseif(in_array(strtolower($value), ["no", "false", "off"]) or $value == 0){ $value = FALSE; }

		// Array Type converter
		elseif(is_array($value)){ $value = serialize($value); }
		elseif(@unserialize($value) !== FALSE){ $value = unserialize($value); }

		// TODO detect \d+,\d+ and unserialize

		return $value;
	}


}
