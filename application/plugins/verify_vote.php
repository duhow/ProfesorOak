<?php

const VERIFY_OK = 1;
const VERIFY_CHECK = 2;
const VERIFY_REJECT = 3;
const VERIFY_REPORT = 4;

const VERIFY_MIN_VOTE_AMOUNT = 5;

function verify_get_randuser($helperid){
	$CI =& get_instance();

	$CI->db
		->select('v.*')
		->from('user_verify v')
		->where('v.status IS NULL')
		->where_not_in("v.id", "(SELECT photo FROM user_verify_vote WHERE telegramid = $helperid)", FALSE)
		->where_not_in("v.id", "(SELECT photo FROM user_verify_vote GROUP BY photo HAVING count(*) >= " .VERIFY_MIN_VOTE_AMOUNT ." )", FALSE)
		->where_not_in("v.telegramid", "(SELECT telegramid FROM user WHERE verified = TRUE)", FALSE)
		->order_by('RAND()')
		// ->order_by('v.id ASC')
		->limit(1);

	/* if($helperid == "3458358"){
		$CI->db->order_by('v.id ASC');
	}else{
		$CI->db->order_by('v.id DESC');
	} */

	$query = $CI->db->get();

	/* $query = $CI->db
		->select(['v.*', 'count(*)'])
		->from('user_verify v')
		->join('user_verify_vote vt', 'v.id = vt.photo')
		->where('v.status IS NULL')
		->where_not_in("v.id", "(SELECT photo FROM user_verify_vote WHERE telegramid = $helperid)", FALSE)
		->where_not_in("v.id", "(SELECT photo FROM user_verify_vote GROUP BY photo HAVING count(*) >= " .VERIFY_MIN_VOTE_AMOUNT ." )", FALSE)
		->group_by('vt.photo')
		->order_by('count(*)', 'DESC')
		->limit(1)
	->get(); */

	if($query->num_rows() == 1){ return $query->row(); }
	return NULL;
}

function verify_get_user($id){
	$CI =& get_instance();

	$query = $CI->db
		->where('id', $id)
		->or_where('telegramid', $id)
		->order_by('id DESC')
	->get('user_verify');

	if($query->num_rows() == 1){ return $query->row(); }
	return NULL;
}

function verify_get_telegramid($photoid){
	$CI =& get_instance();
	$query = $CI->db
		->select('telegramid')
		->where('id', $photoid)
		->or_where('photo', $photoid)
	->get('user_verify');

	if($query->num_rows() == 1){ return (int) $query->row()->telegramid; }
	return NULL;
}

function verify_count_left($helperid = NULL){
	$CI =& get_instance();
	$CI->db
		->from('user_verify')
		->where('status IS NULL')
		->where('date_finish IS NULL');
	if(!empty($helperid)){
		$CI->db->where("id NOT IN (SELECT photo FROM user_verify_vote WHERE telegramid = $helperid)");
	}

	return $CI->db->count_all_results();
}

function verify_vote_profile_count($photoid){
	$CI =& get_instance();
	return $CI->db
		->from('user_verify_vote')
		->where('photo', $photoid)
	->count_all_results();
}

function verify_vote_get_results($photoid, $retpercbase = FALSE){
	$CI =& get_instance();

	$query = $CI->db
		->select(['status', 'count(*) AS count'])
		->from('user_verify_vote')
		->where('photo', $photoid)
		->group_by('status')
	->get();

	$res = [
		VERIFY_OK => 0,
		VERIFY_CHECK => 0,
		VERIFY_REJECT => 0,
		VERIFY_REPORT => 0
	];

	foreach($query->result_array() as $row){
		$res[intval($row['status'])] = intval($row['count']);
	}

	if($retpercbase === FALSE){ return $res; }

	// Percentage base
	$total = array_sum(array_values($res));
	$totalvotes = array();

	foreach($res as $status => $count){
		$totalvotes[$status] = floor(($count / $retpercbase) * 100);
	}
	return $totalvotes;
}

function verify_text_generate($verifydata, $userdata, $left = NULL){
	$str = "";

	// Old time de registro Telegram
	$old = (time() - strtotime($userdata->register_date));
	if($old <= (86400*7)){
		$str .= ":new: ";
	}
	// Old time de la captura
	$old = (time() - strtotime($verifydata->date_add));
	if($old >= (86400*2)){
		$old = round($old / 86400) ." d";
	}elseif($old >= 3600){
		$old = round($old / 3600) ." h";
	}elseif($old >= 60){
		$old = round($old / 60) ." m";
	}else{
		$old .= " s";
	}

	$teamico = [
		'Y' => ":thunder:",
		'R' => ":fire:",
		'B' => ":water:"
	];

	$str .= $old ."  ";
	if(!empty($left)){ $str .= "<code>          </code>$left :triangle-left: "; }
	$str .= "<code>          </code>" .date("H:i", strtotime($verifydata->date_add)) ." :clock:";

	$str .= "\n" .":arrow-up: <b>L" .$userdata->lvl ." </b>"
		.$teamico[$userdata->team] ."<code>          </code>@" .$userdata->username;

	/* if(strtolower($userdata->username) == strtolower($userdata->telegramuser)){
		$str .= " :ok:";
	} */

	return $str;
}

function verify_response_accept($usertelegram, $userverify){
	$telegram = new Telegram();
	$pokemon = new Pokemon();

	verify_vote_set($userverify, VERIFY_OK);

	$telegram->send
		->notification(TRUE)
		->chat($usertelegram)
		->text("Enhorabuena, estás validado! " .$telegram->emoji(":green-check:"))
	->send();

	if($pokemon->step($usertelegram) == "SCREENSHOT_VERIFY"){
		$pokemon->step($usertelegram, NULL);
	}
}

function verify_response_reject($usertelegram, $userverify){
	$telegram = new Telegram();
	$pokemon = new Pokemon();

	verify_vote_set($userverify, VERIFY_REJECT);

	$telegram->send
		->notification(TRUE)
		->chat($usertelegram)
		->text($telegram->emoji(":times: ") ."La validación no es correcta. Revisa la captura de pantalla, y envíala tal y como se pide.")
	->send();

	$pokemon->step($usertelegram, "SCREENSHOT_VERIFY");
}

function verify_vote_set($id, $result){
	$CI =& get_instance();

	return $CI->db
		->where('id', $id)
		->where('status IS NULL')
		->set('status', $result)
		->set('date_finish', date("Y-m-d H:i:s"))
	->update('user_verify');
}

if(
	$this->telegram->text_has(["validar", "comprobar", "verificar"]) and
	$this->telegram->text_has(["perfiles", "perfil", "usuarios", "usuario"]) and
	$this->telegram->words() <= 3 and
	!$this->telegram->is_chat_group()
){
	$userid = $this->telegram->user->id;
	if(!$pokemon->user_flags($userid, 'helper')){
		$this->telegram->send
			->text("Nope.")
		->send();

		return -1;
	}

	// HACK
	/* if($userid != $this->config->item('creator')){
		$this->telegram->send
			->text("Pausado momentáneamente.")
		->send();
		return -1;
	} */

	$times = filter_var($this->telegram->text(), FILTER_SANITIZE_NUMBER_INT);
	if(empty($times) or $times <= 0){ $times = 10; } // Default

	$times = intval($times);
	if($times > 50){
		$this->telegram->send
			->notification(FALSE)
			->text("Hala! Donde vas con tantas!? Te pongo unas pocas menos y si quieres luego repites.")
		->send();
		$times = 50;
	}

	// $pokemon->step($userid, "VERIFY_VOTE");
	$pokemon->settings($userid, 'verify_vote_count', $times);

	$count = verify_count_left($userid);
	if($count <= 0){
		$this->telegram->send
			->text("Tienes suerte, no hay mucho más que validar. Si esperas un rato tal vez...")
		->send();
		return -1;
	}

	$this->telegram->send
		->notification(FALSE)
		->text("Haciendo <b>$times</b> validaciones. Quedan <b>$count</b> pendientes.", "HTML")
	->send();

	// Descansa para la foto.
	usleep(400000);

	// ---------------------------
	$verifydata = verify_get_randuser($userid);
	$userdata = $pokemon->user($verifydata->telegramid);

	// No tendría que pasar, pero...
	if(!$verifydata or !$userdata){
		$this->telegram->send
			->text("Bug! Saliendo.\nPuedes volver a pedir validaciones si quieres.")
		->send();

		$error = (!$verifydata ? "V" : "U");
		$str = "Bug de " .$this->telegram->user->first_name ." - " .$this->telegram->user->id ." <b>al empezar a validar</b> por $error.";
		$this->telegram->send
			->chat("-221103258")
			->text($str, "HTML")
		->send();
		// $pokemon->step($userid, NULL);
		return -1;
	}

	$id = $verifydata->id;
	$str = $this->telegram->emoji(verify_text_generate($verifydata, $userdata, $times));

	$groups = $this->pokemon->group_find_member($verifydata->telegramid, TRUE);
	if($groups){
		$str .= "\n";
		foreach($groups as $g => $d){
			$info = $this->pokemon->group($g);
			$str .= $info->title ."\n";
		}
	}

	if($this->telegram->user->id == $this->config->item('creator')){
		$photo = $pokemon->settings($verifydata->telegramid, 'verify_id');

		if($photo){
			$rp = $this->telegram->send
				->chat($verifydata->telegramid)
				->message($photo)
				->forward_to($this->telegram->chat->id)
			->send();
		}else{
			$rp = $this->telegram->send
				->notification(FALSE)
				->file('photo', $verifydata->photo);
		}

		$res = verify_vote_get_results($id);
		$str .= "\n" .$this->telegram->emoji(":id: ") .$verifydata->telegramid
			."<code>      </code>" .$this->telegram->emoji("\ud83d\udcdd") ." #$id";

		$rt = $this->telegram->send
			->notification(TRUE)
			->inline_keyboard()
				->row()
					->button($this->telegram->emoji(":ok: " .$res[VERIFY_OK]), 				"verivote $id " .VERIFY_OK, "TEXT")
					->button($this->telegram->emoji(":warning: " .$res[VERIFY_CHECK]),		"verivote $id " .VERIFY_CHECK, "TEXT")
					->button($this->telegram->emoji(":times: " .$res[VERIFY_REJECT]),		"verivote $id " .VERIFY_REJECT, "TEXT")
					->button($this->telegram->emoji("\u203c\ufe0f " .$res[VERIFY_REPORT]),	"verivote $id " .VERIFY_REPORT, "TEXT")
				->end_row()
				->row()
					->button($this->telegram->emoji("\ud83d\udcdd"),	"vericount $id", "TEXT")
				->end_row()
			->show()
			->text($str, "HTML")
		->send();
	}else{
		$rp = $this->telegram->send
			->notification(FALSE)
			->file('photo', $verifydata->photo);

		$rt = $this->telegram->send
			->notification(TRUE)
			->inline_keyboard()
				->row()
					->button($this->telegram->emoji(":ok:"), 		"verivote $id " .VERIFY_OK , "TEXT")
					->button($this->telegram->emoji(":warning:"),	"verivote $id " .VERIFY_CHECK , "TEXT")
					->button($this->telegram->emoji(":times:"),		"verivote $id " .VERIFY_REJECT , "TEXT")
					->button($this->telegram->emoji("\u203c\ufe0f"),"verivote $id " .VERIFY_REPORT , "TEXT")
				->end_row()
			->show()
			->text($str, "HTML")
		->send();
	}

	$messages = implode(",", [$rp['message_id'], $rt['message_id']]);
	$pokemon->settings($userid, 'verify_messages', $messages);
	// ---------------------------
}

if($this->telegram->text_command(["vl", "verilist"]) and $this->telegram->user->id == $this->config->item('creator')){
	$query = $this->db
		->select(['username', 'count(*) AS count'])
		->from('user u')
		->join('user_verify_vote v', 'u.telegramid = v.telegramid')
		->group_by('v.telegramid')
		->order_by('count', 'DESC')
	->get();

	$str = "";
	foreach($query->result_array() as $row){
		$str .= "<code>" .$row['count'] ."</code> " .$row['username'] ."\n";
	}

	$this->telegram->send
		->text($str, "HTML")
	->send();

	return -1;
}

if($pokemon->step($telegram->user->id) == "VERIFY_EDIT" and $this->telegram->text()){

}

if($this->telegram->callback and $this->telegram->text_has("verivote", TRUE)){
	$userid = $this->telegram->user->id;
	if(!$pokemon->user_flags($userid, 'helper')){ return -1; }

	// HACK
	/* if($userid != $this->config->item('creator')){
		$this->telegram->send
			->text("Pausado momentáneamente.")
		->send();
		return -1;
	} */

	$id = $this->telegram->words(1);
	$action = $this->telegram->words(2);

	$data = [
		'telegramid' => $userid,
		'photo' => $id,
		'status' => $action
	];

	$insert = $this->db->insert_string('user_verify_vote', $data)
		." ON DUPLICATE KEY UPDATE status = $action";
	$this->db->query($insert);

	// --------------

	$targetid = verify_get_telegramid($id);
	// $target = $pokemon->user($targetid);

	if($userid == $this->config->item('creator')){
		if($action == VERIFY_OK){
			if($pokemon->verify_user($telegram->user->id, $targetid)){
				// Update en nueva tabla.
				verify_response_accept($targetid, $id);
				$pokemon->settings($targetid, 'verify_cooldown', 'DELETE');
		    }
		}elseif($action == VERIFY_REJECT){
			// Update en nueva tabla.
			verify_response_reject($targetid, $id);
			$pokemon->settings($targetid, 'verify_cooldown', 'DELETE');

		}elseif($action == VERIFY_REPORT){
			$pokemon->user_flags($targetid, "fly", TRUE);
			$pokemon->user_flags($targetid, "gps", TRUE);
			$this->telegram->answer_if_callback("Reportado por fly.", TRUE);
			// TODO Ban de grupos con blacklist.
			return -1;
		}
	}else{
		$totalvotes = verify_vote_get_results($id, VERIFY_MIN_VOTE_AMOUNT);
		$total = verify_vote_profile_count($id);

		if($totalvotes[VERIFY_OK] >= 100){
			if($pokemon->verify_user($telegram->user->id, $targetid)){
				verify_response_accept($targetid, $id);
				$pokemon->settings($targetid, 'verify_cooldown', 'DELETE');

				$this->telegram->send
					->notification(FALSE)
					->chat("-221103258")
					->text($this->telegram->emoji(":ok: ") ."Valido a $id / $targetid por " .implode("/", $totalvotes) ." a " .$total .".")
				->send();
			}
		}elseif($totalvotes[VERIFY_REJECT] >= 60){
			verify_response_reject($targetid, $id);
			$pokemon->settings($targetid, 'verify_cooldown', 'DELETE');

			$this->telegram->send
				->notification(FALSE)
				->chat("-221103258")
				->text($this->telegram->emoji(":times: ") ."No valido a $id / $targetid por " .implode("/", $totalvotes) ." a " .$total .".")
			->send();
		}elseif($total >= VERIFY_MIN_VOTE_AMOUNT or $totalvotes[VERIFY_CHECK] >= 60){
			// verify_vote_set($targetid, 0); // TEMP NOT NULL

			if($totalvotes[VERIFY_REPORT] >= 33){
				$this->telegram->send
					->notification(FALSE)
					->chat("-221103258")
					->text($this->telegram->emoji(":fire: ") ."Reportar a $id / $targetid por " .implode("/", $totalvotes) ." a " .$total .".")
				->send();
			}else{
				$this->telegram->send
					->notification(FALSE)
					->chat("-221103258")
					->text($this->telegram->emoji(":warning: ") ."Revisar $id / $targetid por " .implode("/", $totalvotes) ." a " .$total .".")
				->send();
			}

			$verifydata = verify_get_user($id);
			$userdata = $pokemon->user($verifydata->telegramid);
			$photosaveid = $pokemon->settings($verifydata->telegramid, 'verify_id');

			if($photosaveid){
				$this->telegram->send
					->chat($verifydata->telegramid)
					->message($photosaveid)
					->forward_to("-197822813")
				->send();
			}else{
				$this->telegram->send
					->chat("-197822813")
					->file('photo', $verifydata->photo);
			}

			$str = verify_text_generate($verifydata, $userdata);
			$str .= "\n" .":id: " .$userdata->telegramid
				."<code>      </code>\ud83d\udcdd" ." #$id";

			$this->telegram->send
				->notification(TRUE)
				->chat("-197822813")
				->text($this->telegram->emoji($str), "HTML")
				->inline_keyboard()
					->row()
						->button($this->telegram->emoji(":ok:"), "te valido " .$userdata->telegramid, "TEXT")
						->button($this->telegram->emoji(":times:"), "no te valido " .$userdata->telegramid, "TEXT")
						->button($this->telegram->emoji("\u203c\ufe0f"),"verivote $id 4", "TEXT")
					->end_row()
					->row()
						->button($this->telegram->emoji("\ud83d\udcdd"),	"vericount $id", "TEXT")
					->end_row()
				->show()
			->send();
		}
	}

	// --------------

	$deletes = $pokemon->settings($userid, 'verify_messages');
	if(!empty($deletes)){
		$deletes = explode(",", $deletes);
		foreach($deletes as $delid){
			$this->telegram->send->delete($delid);
		}
	}

	// --------------

	$times = $pokemon->settings($userid, 'verify_vote_count');
	$times--;
	$pokemon->settings($userid, 'verify_vote_count', $times);

	$verifydata = verify_get_randuser($userid);

	if($times <= 0 or !$verifydata){
		$pokemon->settings($userid, 'verify_vote_count', 'DELETE');
		$pokemon->settings($userid, 'verify_messages', 'DELETE');

		$this->telegram->send
			->text("¡Ya has acabado!")
		->send();
		return -1;
	}

	// ---------------------------

	$userdata = $pokemon->user($verifydata->telegramid);

	// No tendría que pasar, pero...
	if(!$userdata){
		$this->telegram->send
			->text("Bug! Saliendo.\nPuedes volver a pedir validaciones si quieres.")
		->send();

		$error = (!$verifydata ? "V" : "U");
		$str = "Bug de " .$this->telegram->user->first_name ." - " .$this->telegram->user->id ." <b>después de validar</b> por $error.";
		$this->telegram->send
			->chat("-221103258")
			->text($str, "HTML")
		->send();

		return -1;
	}

	$id = $verifydata->id;
	$str = $this->telegram->emoji(verify_text_generate($verifydata, $userdata, $times));

	$groups = $this->pokemon->group_find_member($verifydata->telegramid, TRUE);
	if($groups){
		$str .= "\n";
		foreach($groups as $g => $d){
			$info = $this->pokemon->group($g);
			$str .= $info->title ."\n";
		}
	}

	if($this->telegram->user->id == $this->config->item('creator')){
		$photo = $pokemon->settings($verifydata->telegramid, 'verify_id');
		if($photo){
			$rp = $this->telegram->send
				->chat($verifydata->telegramid)
				->message($photo)
				->forward_to($this->telegram->chat->id)
			->send();
		}else{
			$rp = $this->telegram->send
				->notification(FALSE)
				->file('photo', $verifydata->photo);
		}

		$res = verify_vote_get_results($id);
		$str .= "\n" .$this->telegram->emoji(":id: ") .$verifydata->telegramid
			."<code>      </code>" .$this->telegram->emoji("\ud83d\udcdd") ." #$id";

		$rt = $this->telegram->send
			->notification(TRUE)
			->inline_keyboard()
				->row()
					->button($this->telegram->emoji(":ok: " .$res[VERIFY_OK]), 				"verivote $id " .VERIFY_OK, "TEXT")
					->button($this->telegram->emoji(":warning: " .$res[VERIFY_CHECK]),		"verivote $id " .VERIFY_CHECK, "TEXT")
					->button($this->telegram->emoji(":times: " .$res[VERIFY_REJECT]),		"verivote $id " .VERIFY_REJECT, "TEXT")
					->button($this->telegram->emoji("\u203c\ufe0f " .$res[VERIFY_REPORT]),	"verivote $id " .VERIFY_REPORT, "TEXT")
				->end_row()
				->row()
					->button($this->telegram->emoji("\ud83d\udcdd"),	"vericount $id", "TEXT")
				->end_row()
			->show()
			->text($str, "HTML")
		->send();
	}else{
		$rp = $this->telegram->send
			->notification(FALSE)
			->file('photo', $verifydata->photo);

		$rt = $this->telegram->send
			->notification(TRUE)
			->inline_keyboard()
				->row()
					->button($this->telegram->emoji(":ok:"), 		"verivote $id " .VERIFY_OK, "TEXT")
					->button($this->telegram->emoji(":warning:"),	"verivote $id " .VERIFY_CHECK, "TEXT")
					->button($this->telegram->emoji(":times:"),		"verivote $id " .VERIFY_REJECT, "TEXT")
					->button($this->telegram->emoji("\u203c\ufe0f"),"verivote $id " .VERIFY_REPORT, "TEXT")
				->end_row()
			->show()
			->text($str, "HTML")
		->send();
	}

	$messages = implode(",", [$rp['message_id'], $rt['message_id']]);
	$pokemon->settings($userid, 'verify_messages', $messages);
	// ---------------------------

}

if($this->telegram->callback and $this->telegram->text_has("vericount", TRUE)){
	if($this->telegram->user->id != $this->config->item('creator')){ return -1; }

	$id = $this->telegram->words(1);
	$str = "\ud83d\udcdd #$id\n";
	$icons = [
		VERIFY_OK		=> ":ok:",
		VERIFY_CHECK	=> ":warning:",
		VERIFY_REJECT	=> ":times:",
		VERIFY_REPORT	=> "\u203c\ufe0f",
	];

	$query = $this->db
		->select(['u.username', 'v.status'])
		->from('user_verify_vote v')
		->join('user u', 'u.telegramid = v.telegramid')
		->where('photo', $id)
	->get();

	foreach($query->result_array() as $vote){
		$str .= $icons[$vote['status']] ." " .$vote['username'] ."\n";
	}

	$req = $this->telegram->send
		->text($this->telegram->emoji($str), "HTML")
	->send();

	$msgs = $pokemon->settings($telegram->user->id, 'verify_messages');
	$msgs .= "," .$req['message_id'];
	$pokemon->settings($telegram->user->id, 'verify_messages', $msgs);

	$this->telegram->answer_if_callback("");
	return -1;
}
?>
