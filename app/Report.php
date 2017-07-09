<?php

class Report extends TelegramApp\Module {

	protected function hooks(){

	}

	public function get_reports($name, $getAll = FALSE, $valid = TRUE){
		if($valid){ $this->db->where('valid', TRUE); }
		$query = $this->db
			->where('reported', $name)
		->get('reports');

		if($this->db->count == 0){ return NULL; }
		if(!$retall){ return $this->db->count; }
		return $query;
	}
}
