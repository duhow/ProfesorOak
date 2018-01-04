<?php

class Git extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){
		if(!$this->telegram->text_command()){ return; }

		if(
			$this->telegram->user->id == CREATOR &&
			$this->telegram->text_command("git") &&
			$this->telegram->words() > 1
		){
			return $this->git_command($this->telegram->words(1));
		}

		elseif($this->telegram->text_command("github")){
			$issue = ($this->telegram->words() == 2 ? $this->telegram->last_word() : NULL);
			return $this->github($issue);
		}
	}

	public function git_command($action){
		$action = strtolower($action);
		if($action == "update"){
			$m = $this->telegram->send
					->text("Ejecutando...")
				->send();

			$out = $this->git_pull();
			$out = $this->telegram->emoji($out);

			$this->telegram->send
				->chat(TRUE)
				->message($m['message_id'])
				->text($out)
			->edit('text');
		}elseif($action == "version"){
			$info = $this->git_version();
			$str = $this->telegram->emoji(":warning: No se puede cargar la información Git.");

			if($info !== NULL){
				$str = '<a href="https://github.com/duhow/ProfesorOak/commit/' .$info['hash'] .'">' .$info['hash'] .'+</a>, del '
						.date("d/m/Y H:i", $info['date']);
			}

			$this->telegram->send
				->disable_web_page_preview(TRUE)
				->text($str, 'HTML')
			->send();
		}
		$this->end();
	}

	private function git_version(){
		$out = exec("git log --pretty=format:'%h %at' -n 1");
		$info = explode(" ", $out);

		if(is_array($info) && count($info) == 2){
			$info['hash'] = $info[0];
			$info['date'] = $info[1];
			return $info;
		}
		return NULL;
	}

	private function git_pull(){
		$version = $this->git_version();
		$out = shell_exec("git pull");
		// TODO Check more errors
		$newversion = $this->git_version();
		if(strpos($out, "Already up-to-date") !== FALSE){
			$out = ":ok: Ya está actualizado.";
		// TODO check si el hash coincide o no y validar si hace falta cambio o no de revertir.
		}elseif(empty($out) or $version['hash'] == $newversion['hash']){
			$out = ":times: Problema al actualizar.";
		}

		return $out;
	}

	// TODO
	private function git_revert($hash){
		$out = shell_exec("git revert -i $hash");
		if(strpos($out, "") !== FALSE){ return TRUE; }
		return $out;
	}

	public function github($issue = NULL){
		$help = $this->strings->get('github_info');
		if(!empty($issue) and is_numeric($issue) and $issue > 0){
			$help = "https://github.com/duhow/ProfesorOak/issues/$issue";
		}
		return $this->telegram->send
			->text($help)
			->notification(FALSE)
		->send();
	}

}
