<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: requestfinish
# Complete booking, after initiation and payment if appropriate
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/*
if (!function_exists('http_response_code')) { //PHP<5.4
//see http://php.net/manual/en/function.http-response-code.php
 function http_response_code($code)
 {
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

//clear all page-content echoed before now
$handlers = ob_list_handlers();
if ($handlers) {
	$t = count($handlers);
	for ($c = 0; $c < $t; $c++)
		ob_end_clean();
}
*/

if (!isset ($utils))
	$utils = new Booker\Utils();
//$utils->RetrieveParameters($cache,$params);
$utils->UnFilterParameters($params);

if (!empty($params['success'])) { //successful completion
	$rfuncs = new Booker\Requestops();
	$sfuncs = new Booker\Schedule();
	$ufuncs = new Booker\Userops();
	$ob = cms_utils::get_module('Notifier');
	if ($ob) {
		unset($ob);
		try {
			$mfuncs = new \MessageSender();
		} catch (Exception $e) {
			$mfuncs = FALSE;
		}
	} else {
		$mfuncs = FALSE;
	}

	$res = TRUE;
	$cache = Booker\Cache::GetCache($this);
	$cart = $utils->RetrieveCart($cache,$params);
	$pending = $cart->getItems();
	foreach ($pending as $item) {
		$data = $item->getPackage();
		$reqdata = (array)$data->request; //etc - from Requestops::CartReq()

		$item_id = $reqdata['item_id'];
		$bookerid = $reqdata['booker_id'];
		$is_new = ($reqdata['status'] === Booker::STATNEW);
		//TODO update $reqdata : ['payment'] etc
		if ($ufuncs->HasRight($this,$bookerid,'record')) { //booker can record directly
			if ($sfuncs->ScheduleResource($this,$utils,$item_id,$reqdata)) {
				if ($rfuncs->SaveReq($this,$utils,$reqdata,$is_new)) {
					$save = TRUE;
				} else {
					$res = FALSE;
					break;
				}
			} else {
				$res = FALSE;
				break;
			}
		} else  //booker must request
			if ($rfuncs->SaveReq($this,$utils,$reqdata,$is_new)) {
				$save = FALSE;
		} else {
			$res = FALSE;
			break;
		}

		if ($mfuncs) {
			$to = array();
			$idata = (array)$data->itemdata; //from Requestops::CartReq()
			if ($idata['approvertell'] && !empty($idata['approvercontact'])) {
				if (preg_match('/\w+@\w+\.\w+/',$idata['approvercontact'])) {
					$to[] = array($idata['approver']=>$idata['approvercontact']);
				} else {//if (preg_match('/^(\+\d{1,4} *)?[\d ]{5,15}$/',$idata['approvercontact']))
					$to[] = $idata['approvercontact'];
				}
				//TODO ignore twitter handle?
			}
			if ($idata['bookertell']) {
				if ($data->contact) {
					if (preg_match('/\w+@\w+\.\w+/',$data->contact)) {
						$to[] = array($data->user=>$data->contact);
					} elseif (preg_match('/^(\+\d{1,4} *)?[\d ]{5,15}$/',$data->contact)) {
						$to[] = $data->contact;
					}
				} else {
					$addrs = $ufuncs->GetContact($this,$bookerid);
					if ($addrs) { //booker contact is known
						//contact via social media not supported
						if ($addrs['address'] &&
						 preg_match('/\w+@\w+\.\w+/',$addrs['address'])) {
							$name = $ufuncs->GetName($bookerid);
							$to[] = array($name=>$addrs['address']);
						} elseif ($addrs['phone'] &&
						 preg_match('/^(\+\d{1,4} *)?[\d ]{5,15}$/',$addrs['phone'])) {
							$to[] = $addrs['phone'];
						}
					}
				}
			}
			if ($to) {
/*				try {
					$localzone = new \DateTimeZone($idata['timezone']);
					$parms = $localzone->getLocation();
					$country = $parms['country_code'];
				} catch (Exception $e) {
					$country = FALSE;
				}
*/
				$from = FALSE; //always use default sender
				$what = $utils->GetItemName($this,$idata);
				if (isset($params['subgrpcount']))
					$what = $this->Lang('countof2',$params['subgrpcount'],$what);
				$dt = new \DateTime('@'.$reqdata['slotstart'],NULL);
				$overday = ($utils->GetInterval($this,$item_id,'slot') >= 84600);
				$on = $utils->IntervalFormat($this,$dt,'D j M');
				if ($overday) {
					$detail = $this->Lang('whatovrday',$what,$on);
				} else {
					$at = $dt->format('g:i A');
					$detail = $this->Lang('whatonday',$what,$on,$at);
				}

				$textparms = array('prefix'=>$idata['smsprefix'],'pattern'=>$idata['smspattern']);

				if ($save) { //recorded
					if ($is_new) {
						$mailparms = array('subject'=>$this->Lang('email_store_title'),
							'body'=>$this->Lang('email_store',$detail));
						$msg = $this->Lang('text_store',$detail);
						$textparms['body'] = $msg;
						$tweetparms = array('body'=>$msg);
					} else { //existing booking
						$mailparms = array('subject'=>$this->Lang('email_stochange_title'),
							'body'=>$this->Lang('email_stochange',$detail));
						$msg = $this->Lang('text_stochange',$detail);
						$textparms['body'] = $msg;
						$tweetparms = array('body'=>$msg);
					}
				} else { //request
					if ($is_new) {
						$mailparms = array('subject'=>$this->Lang('email_request_title'),
							'body'=>$this->Lang('email_request',$detail));
						$msg = $this->Lang('text_request',$detail);
						$textparms['body'] = $msg;
						$tweetparms = array('body'=>$msg);
					} else { //existing booking
						$mailparms = array('subject'=>$this->Lang('email_reqchange_title'),
							'body'=>$this->Lang('email_reqchange',$detail));
						$msg = $this->Lang('text_reqchange',$detail);
						$textparms['body'] = $msg;
						$tweetparms = array('body'=>$msg);
					}
				}
//				list($res,$errmsg) =
				$mfuncs->Send($from,$to,$textparms,$mailparms,$tweetparms);
				//nothing achievable here if message(s) failed
			}
		}

		$cart->removeItem($item->id);
	} //end cartitems loop

	$utils->SaveCart($cart,$cache,$params);
} else { //not completed as expected
	$res = FALSE;
}

/*
http_response_code(200); //always signal success to webhook-source
exit;
*/
