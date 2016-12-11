<?php

if($telegram->user->id != $this->config->item('creator')){ return; }

if($telegram->text_command("sysinfo")){

	$RAM = array();
	foreach(explode("\n", file_get_contents("/proc/meminfo")) as $l){
		$v = explode(":", $l);
		if(count($v) != 2){ continue; }
		$vf = explode(" ", trim($v[1]));
		$RAM[$v[0]] = trim($vf[0]);
	}
	$RAM = (object) $RAM;

	// ------------------

	$load_tmp = array();
	foreach(explode(" ", file_get_contents("/proc/loadavg")) as $l){
		$load_tmp[] = trim($l);
	}
	$load['now'] = (float) $load_tmp[0];
	$load['normal'] = (float) $load_tmp[1];
	$load['long'] = (float) $load_tmp[2];
	unset($load_tmp);
	$load['processors'] = (int) trim(exec("nproc"));
	$load = (object) $load;

	// ------------------

	$uptime = strtotime(exec("uptime -s"));

	// ------------------
	// $proc = file_get_contents("/proc/self/status");

	$mounts = array();
	foreach(explode("\n", shell_exec("df")) as $n => $l){
		if($n == 0){ continue; }
		if(strpos($l, "tmpfs") !== FALSE){ continue; }
		$vars = array();
		foreach(explode(" ", $l) as $v){
			if(empty(trim($v))){ continue; }
			$vars[] = $v;
		}
		$mounts[] = (object) [
			'mount' => $vars[0],
			'size' => $vars[1],
			'used' => $vars[2],
			'available' => $vars[3],
			'percentage' => $vars[4],
			'mountpoint' => $vars[5]
		];
	}

	// ------------------

	$str = "UP " .floor((time() - $uptime)/86400) ."d\n";
	$str .= "CPU: " .$load->now ." " .$load->normal ." " .$load->long ." ("  .floor(($load->now * 100) / $load->processors)   ."%)\n";
	$str .= "RAM: " .floor(($RAM->MemTotal - $RAM->MemAvailable) / 1024) ."/" .floor($RAM->MemTotal / 1024) ."MB (" .floor((($RAM->MemTotal - $RAM->MemAvailable) / $RAM->MemTotal) * 100) ."%)\n";
	$str .= "\n";
	foreach($mounts as $mount){
		$str .= $mount->mountpoint ." - " .$mount->percentage ."\n";
	}

	$telegram->send->text($str)->send();
}

?>
