<?php

class Report extends TelegramApp\Module {
	protected $runCommands = FALSE;

	protected function hooks(){

	}

	public function get($name, $retall = FALSE, $valid = TRUE){
		if($valid){ $this->db->where('valid', TRUE); }
		$query = $this->db
			->where('reported', $name)
		->get('reports');

		if($this->db->count == 0){ return NULL; }
		if(!$retall){ return $this->db->count; }
		return $query;
	}

	public function multiaccount_exists($names, $retall = FALSE){
		if(!is_array($names)){ $names = [$names]; }
		$query = $this->db
			->where('username', $names, 'IN')
		->get('user_multiaccount');

		if($this->db->count == 0){ return FALSE; }
		if(!$retall){ return $query[0]['grouping']; }

		$gid = $query[0]['grouping'];
		return $this->multiaccount_grouping($gid);
	}

	public function multiaccount_grouping($group, $onlynames = FALSE){
		$query = $this->db
			->where('grouping', $group)
		->get('user_multiaccount');

		if($this->db->count == 0){ return array(); }
		$final = ['grouping' => $group];
		$final['usernames'] = array_column($query, 'username');

		if($onlynames){ return $final['usernames']; }
		return $final;
	}
}
