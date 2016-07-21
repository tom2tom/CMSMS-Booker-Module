<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: requestfinish
# Complete booking, after intiation and payment if appropriate
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
# This is a 'do' action, no output but sets html response 200 etc

if (!function_exists('http_response_code')) { //PHP<5.4
 function http_response_code($code)
 {
	//see http://php.net/manual/en/function.http-response-code.php
	switch ($code) {
	 case 200: $text = 'OK'; break;
	 case 401: $text = 'Unauthorized'; break;
	 case 403: $text = 'Forbidden'; break;
	 case 404: $text = 'Not Found'; break;
	 case 405: $text = 'Method Not Allowed'; break;
	 case 406: $text = 'Not Acceptable'; break;
	 default: $code = NULL; break;
	}
	if ($code !== NULL) {
		$protocol = ((!empty($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
		header($protocol.' '.$code.' '.$text);
		$GLOBALS['http_response_code'] = $code;
	}
 }
}

//clear all page content echoed before now
$handlers = ob_list_handlers();
if ($handlers) {
	$l = count($handlers);
	for ($c = 0; $c < $l; $c++)
		ob_end_clean();
}

$this->Crash();

if (1) { //booker is authorised to record
	$save = TRUE;
} else {
	$save = FALSE;
	$ob = cms_utils::get_module('FrontEndUsers');
	if ($ob) {
		$uid = $ob->LoggedInID();
		if ($uid !== FALSE) {
			$t = (int)$idata['feugroup'];
			if ($t == -1) //any group
				$save = TRUE;
			elseif ($t != 0) { //none
				$gid = $ob->GetGroupID($t);
				$save = $ob->MemberOfGroup($uid,$gid);
			}
			if ($save)
				$by = $ob->GetUserName($uid); //default
		}
		unset($ob);
	}
}

if ($save) {
	if (1) { //some verify? Booker\Verify::VerifyAdmin($this,$utils,$params,$item_id,$is_new)) { //??? upstream?
		$ares = Booker\Bookingops::SaveBkg($this,$params,$is_new); //include: update history-table
	} else {
		http_response_code(401);
		exit;
	}
} else { //log the booking-request
	//localise 'now'
	$params['lodged'] = $utils->GetZoneTime($idata['timezone']);
	$rdata = FALSE; //passed-by-ref
	$funcs2 = new Booker\Requestops();
//	$ares =
	$funcs2->SaveReq($this,$params,$rdata,TRUE);
}

if (1) { //$idata['approvertell'] && !empty($idata['approvercontact'])) {
	try {
		$funcs2 = new MessageSender();
	} catch (Exception $e) {
		$funcs2 = FALSE;
	}
	if ($funcs2) {
/*		try {
			$localzone = new DateTimeZone($idata['timezone']);
			$parms = $localzone->getLocation();
			$country = $parms['country_code'];
		} catch (Exception $e) {
			$country = FALSE;
		}
*/
		$from = FALSE; //always use default sender
		if (preg_match('/\w+@\w+/',$idata['approvercontact']))
			$to = array($idata['approver']=>$idata['approvercontact']);
		else
			$to = $idata['approvercontact'];
		$textparms = array('prefix'=>$idata['smsprefix'],'pattern'=>$idata['smspattern']);
		$tweetparms = array();
		$what = (isset($params['subgrpcount'])) ?
			sprintf('%d %s',$params['subgrpcount'],$idata['membersname']):
			$utils->GetItemName($this,$idata);
		$dt = new DateTime($bdata['slotstart'],$tzone);
		$on = $utils->IntervalFormat($this,$dt,'D j M');
		if (!$overday)
			$at = $dt->format('g:i A');

		if ($save) {
$this->Crash();
//TODO
			$mailparms = array('subject'=>$X,'body'=>$Y);
			$msg = $Z;
			$textparms['body'] = $msg;
			$tweetparms['body'] = $msg;
		} else {
			if (isset($params['slotid'])) { //existing booking
				$mailparms = array('subject'=>$this->Lang('email_reqchange_title'));
				if ($overday) {
					$mailparms['body'] = $this->Lang('email_reqchange',$what,$on);
					$msg = $this->Lang('text_reqchange',$what,$on);
					$textparms['body'] = $msg;
					$tweetparms['body'] = $msg;
				} else {
					$mailparms['body'] = $this->Lang('email_reqchangeat',$what,$on,$at);
					$msg = $this->Lang('text_reqchangeat',$what,$on,$at);
					$textparms['body'] = $msg;
					$tweetparms['body'] = $msg;
				}
			} else { //new
				$mailparms = array('subject'=>$this->Lang('email_request_title'));
				if ($overday) {
					$mailparms['body'] = $this->Lang('email_request',$what,$on);
					$msg = $this->Lang('text_request',$what,$on);
					$textparms['body'] = $msg;
					$tweetparms['body'] = $msg;
				} else {
					$mailparms['body'] = $this->Lang('email_requestat',$what,$on,$at);
					$msg = $this->Lang('text_requestat',$what,$on,$at);
					$textparms['body'] = $msg;
					$tweetparms['body'] = $msg;
				}
			}
		}
//			list($res,$errmsg) =
		$funcs2->Send($from,$to,$textparms,$mailparms,$tweetparms);
	}
}
if (1) { //$idata['bookertell'] && booker contact is known
	if (!isset($funcs2)) {
		try {
			$funcs2 = new MessageSender();
		} catch (Exception $e) {
			$funcs2 = FALSE;
		}
	}
	if ($funcs2) {
	//send message to booker
	}
}

http_response_code(200); //signal success
exit;
