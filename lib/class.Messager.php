<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Messager - functions for sending messages
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Messager
{
	const MSGREJECT = -1;
	const MSGSUBMIT = -2;
	const MSGRECORD = -3;
	const MSGCHANGE = -4;

	/*
	MsgKeys:
	@mtype: enum, one of Booker::STAT* or self::MSG*
	Returns: 3-member array or FALSE
	*/
	private function MsgKeys($mtype)
	{
		switch ($mtype) {
		 case \Booker::STATOK:
			$ktitle = 'email_approve_title';
			$kbody1 = 'email_approve';
			$kbody2 = 'text_approve';
			break;
		 case self::MSGSUBMIT:
			$ktitle = 'email_request_title'; //TODO or email_reqchange_title
			$kbody1 = 'email_request';
			$kbody2 = 'text_request';
			break;
		 case self::MSGRECORD:
			$ktitle = 'email_store_title'; //TODO or email_stochange_title
			$kbody1 = 'email_store';
			$kbody2 = 'text_store';
			break;
		 case self::MSGREJECT:
			$ktitle = 'email_reject_title';
			$kbody1 = 'email_reject';
			$kbody2 = 'text_reject';
			break;
		 case \Booker::STATASK:
			$ktitle = 'email_ask_title';
			$kbody1 = 'email_ask';
			$kbody2 = 'text_ask';
			break;
		 case \Booker::STATCANCEL:
			$ktitle = 'email_cancelled_title';
			$kbody1 = 'email_cancel';
			$kbody2 = 'text_cancel';
			break;
		 case \Booker::STATCHG:
			$ktitle = 'email_changed_title';
			$kbody1 = 'email_change';
			$kbody2 = 'text_change';
		 	break;
		 default:
		 	return FALSE; //error
		}
		return array($ktitle,$kbody1,$kbody2);
	}

	/*
	MsgParms:
	Construct arguments for 'outward' message using MessageSender::Send()
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@idata: reference to array of booked-item data
	@reqdata: reference to array of booking-request data
	@custommsg: text entered by user, to replace square-bracketed content of the
		message 'template', or some other message, or FALSE
	@etitlekey: lang key for email title or FALSE
	@ebodykey: lang key for email body or FALSE if sending @custommsg
	@tbodykey: lang key for text/tweet body or FALSE if sending @custommsg
	Returns: array
	*/
	private function MsgParms(&$mod, &$utils, &$idata, &$reqdata, $custommsg, $etitlekey, $ebodykey, $tbodykey)
	{
		$from = FALSE; //always use default sender
		$to = array();
		if (isset($reqdata['contact'])) {
			$val = trim($reqdata['contact']);
			if ($val && preg_match(\Booker::PATNADDRESS,$val)) {
				$to[] = array($val);
			} elseif ($val) {
				$to[] = $val;
			}
		} else {
			$to[] = ($reqdata['address']) ? array($reqdata['name']=>$reqdata['address']):$reqdata['phone'];
		}
		if (!empty($idata['approvertell'])) {
			$val = trim($idata['approvercontact']);
			if ($val && preg_match(\Booker::PATNADDRESS,$val)) {
				$to[] = array($idata['approver']=>$val);
			} elseif ($val) {
				$to[] = $val;
			}
		}

		$what = $utils->GetItemName($mod,$idata);
		if ($idata['item_id'] >= \Booker::MINGRPID)
			$what = $mod->Lang('countof2',$reqdata['subgrpcount'],$what);
		$dts = new \DateTime('@'.$reqdata['slotstart'],NULL);
		$on = $utils->IntervalFormat($mod,$dts,'D j M');
		if ($utils->GetInterval($mod,$idata['item_id'],'slot') >= 84600) {
			$detail = $mod->Lang('whatovrday',$what,$on);
		} else {
			$at = $dts->format('g:i A');
			$detail = $mod->Lang('whatonday',$what,$on,$at);
		}

		if ($ebodykey) {
			$msg = $mod->Lang($ebodykey,$detail);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg); //ok if no custommsg
			$mailparms = array('subject'=>$mod->Lang($etitlekey),'body'=>$msg);
			$msg = $mod->Lang($tbodykey,$detail);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$textparms = array('prefix'=>$idata['smsprefix'],
				'pattern'=>$idata['smspattern'],'body'=>$msg);
			$tweetparms = array('body'=>$msg);
		} else {
			if (!$etitlekey)
				$etitlekey = 'title_bookings';
			$msg = sprintf($custommsg,$what);
			$mailparms = array('subject'=>$mod->Lang($etitlekey),'body'=>$msg);
			$textparms = array('prefix'=>$idata['smsprefix'],
				'pattern'=>$idata['smspattern'],'body'=>$msg);
			$tweetparms = array('body'=>$msg);
		}
		return array($from,$to,$textparms,$mailparms,$tweetparms);
	}

	/* *
	SetupMessage:
	@mod: reference to current Booker module
	@utils: reference to Booker\Utils object
	@params: reference to parameters array
	@mtype: MSG* enum
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	@extra: optional stuff for some types of message, default ''
	Returns: TODO title & bodies for MessageSender::Send
	*/
/*	public function SetupMessage(&$mod, &$utils, &$params, $mtype, $custommsg, $extra='')
	{
		//$mtype = $TODO; //what type of message
		$idata = $TODO;
		$overday = ($utils->GetInterval($mod,$item_id,'slot') >= 84600);

		$what = $utils->GetItemName($mod,$idata);
		if ($idata['item_id'] >= \Booker::MINGRPID)
			$what = $mod->Lang('countof2',$params['subgrpcount'],$what);
		$dt = $TODO;
		$dt->setTimestamp($params['slotstart']);
		$on = $utils->IntervalFormat($mod,$dt,'D j M');
		if ($overday) {
			$detail = $mod->Lang('whatovrday',$what,$on);
		} else {
			$at = $dt->format('g:i A');
			$detail = $mod->Lang('whatonday',$what,$on,$at);
		}
		switch ($mtype) {
		 default:
			$approvecommon = $mod->Lang('email_approve',$detail);
			$rejectcommon = $mod->Lang('email_reject',$detail);
			$notifycommon = $mod->Lang('email_change',$detail); //ETC
			$askcommon = $mod->Lang('email_ask',$detail);
		}
		//TODO other formats
		//TODO messages =  replace \[.*\] by $params['custom']
	}
*/
/*
				//always construct the messages, one of them is needed for reporting upstream
		$idata = (array)$data->itemdata; //from Requestops::CartReq()
/ *				try {
			$localzone = new \DateTimeZone($idata['timezone']);
			$parms = $localzone->getLocation();
			$country = $parms['country_code'];
		} catch (\Exception $e) {
			$country = FALSE;
		}
* /
		$overday = ($utils->GetInterval($mod,$item_id,'slot') >= 84600);
		$what = $utils->GetItemName($mod,$idata);
		if ($item_id >= \Booker::MINGRPID)
			$what = $mod->Lang('countof2',$params['subgrpcount'],$what);
		$dt = new \DateTime('@'.$params['slotstart'],NULL);
		$on = $utils->IntervalFormat($mod,$dt,'D j M');
		if ($overday) {
			$detail = $mod->Lang('whatovrday',$what,$on);
		} else {
			$at = $dt->format('g:i A');
			$detail = $mod->Lang('whatonday',$what,$on,$at);
		}

		$textparms = array('prefix'=>$idata['smsprefix'],'pattern'=>$idata['smspattern']);

		if ($recorded) {
			if ($is_new) {
				$mailparms = array('subject'=>$mod->Lang('email_store_title'),
					'body'=>$mod->Lang('email_store',$detail));
				$msg = $mod->Lang('text_store',$detail);
				$textparms['body'] = $msg;
				$tweetparms = array('body'=>$msg);
			} else { //existing booking
				$mailparms = array('subject'=>$mod->Lang('email_stochange_title'),
					'body'=>$mod->Lang('email_stochange',$detail));
				$msg = $mod->Lang('text_stochange',$detail);
				$textparms['body'] = $msg;
				$tweetparms = array('body'=>$msg);
			}
		} else { //request
			if ($is_new) {
				$mailparms = array('subject'=>$mod->Lang('email_request_title'),
					'body'=>$mod->Lang('email_request',$detail));
				$msg = $mod->Lang('text_request',$detail);
				$textparms['body'] = $msg;
				$tweetparms = array('body'=>$msg);
			} else { //existing booking
				$mailparms = array('subject'=>$mod->Lang('email_reqchange_title'),
					'body'=>$mod->Lang('email_reqchange',$detail));
				$msg = $mod->Lang('text_reqchange',$detail);
				$textparms['body'] = $msg;
				$tweetparms = array('body'=>$msg);
			}
		}

		if (!$report)
			$report = array($mailparms['body']);
		else
			$report[] = $mailparms['body'];

		if ($mfuncs) {
			$to = array();
			if ($idata['approvertell'] && !empty($idata['approvercontact'])) {
				if (preg_match(\Booker::PATNADDRESS,$idata['approvercontact'])) {
					$to[] = array($idata['approver']=>$idata['approvercontact']);
				} else {//if (preg_match(\Booker::PATNPHONE,$idata['approvercontact']))
					$to[] = $idata['approvercontact'];
				}
				//TODO ignore twitter handle?
			}
			if ($idata['bookertell']) {
				if ($data->contact) {
					if (preg_match(\Booker::PATNADDRESS,$data->contact)) {
						$to[] = array($data->name=>$data->contact);
					} elseif (preg_match(\Booker::PATNPHONE,$data->contact)) {
						$to[] = $data->contact;
					}
				} else {
					$addrs = $ufuncs->GetContact($mod,$bookerid);
					if ($addrs) { //booker contact is known
						//contact via social media not supported
						if ($addrs['address'] &&
						 preg_match(\Booker::PATNADDRESS,$addrs['address'])) {
							$name = $ufuncs->GetName($bookerid);
							$to[] = array($name=>$addrs['address']);
						} elseif ($addrs['phone'] &&
						 preg_match(\Booker::PATNPHONE,$addrs['phone'])) {
							$to[] = $addrs['phone'];
						}
					}
				}
			}
			if ($to) {
				$from = FALSE; //always use default sender
				list($res,$msg) = $mfuncs->Send($from,$to,$textparms,$mailparms,$tweetparms);
				if (!$res) {
					if (!$err)
						$err = array($msg);
					else
						$err[] = $msg;
				}
			}
*/
	/**
	StatusMessage:
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@idata: array of item parameters for constructing message
	@reqdata: array of request data for helping construct the message
	@status: actual request/booking status enum, one of \Booker::STAT* or self::MSG*
	@custommsg: message body 'template', or ''
	@sender: optional reference to \MessageSender-class object, default NULL
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function StatusMessage(&$mod, &$utils, $idata, $reqdata, $status, $custommsg, $sender=NULL)
	{
		if (!$sender) {
			$sender = new \Notifier\MessageSender();
		}
		$sent = FALSE;
		$fail = FALSE;
	 	$res = self::MsgKeys($status);
		if ($res) {
			list($etitlekey,$ebodykey,$tbodykey) = $res;
			list($from,$to,$textparms,$mailparms,$tweetparms) =
				self::MsgParms($mod,$utils,$idata,$reqdata,$custommsg,$etitlekey,$ebodykey,$tbodykey);
			list($res,$msg) = $sender->Send($from,$to,$textparms,$mailparms,$tweetparms);
			if ($res)
				$sent = $mailparms['body'];
			else
				$fail = $msg;
		} else {
/* TODO status-specific advice
		 case \Booker::STATBIG:
		 case \Booker::STATDEFER:
		 case \Booker::STATDEL:
		 case \Booker::STATDUP:
		 case \Booker::STATERR:
		 case \Booker::STATGONE:
		 case \Booker::STATNA:
		 case \Booker::STATNEW:
		 case \Booker::STATNOTPAID:
		 case \Booker::STATTELL:
		 case \Booker::STATTEMP:
*/
			$fail = 'NOT YET SUPPORTED'; //TODO
		}
		if ($fail) {
			return array(FALSE,$fail);
		}
		return array(TRUE,$sent);
	}

	/**
	NotifyBooker:
	Send message to 'user' of one or more bookings
	@mod: reference to current Booker module
	@bkgid: booking identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function NotifyBooker(&$mod, $bkgid, $custommsg)
	{
		if ($mod->havenotifier) {
			$funcs = new Bookingops();
			$rows = $funcs->GetBkgData($mod,$bkgid);
			if ($rows) {
				list($etitlekey,$ebodykey,$tbodykey) = self::MsgKeys(\Booker::STATCHG);
				$funcs = new \Notifier\MessageSender();
				$utils = new Utils();
				$propstore = array();
				$msg = array();
				foreach ($rows as $bid=>$one) {
					$item_id = $one['item_id'];
					if (!isset($propstore[$item_id])) {
						$propstore[$item_id] = $utils->GetItemProperty($mod,$item_id,
							array('item_id','name','membersname','smspattern','smsprefix'));
					}
					$idata = $propstore[$item_id];
					list($from,$to,$textparms,$mailparms,$tweetparms) =
						self::MsgParms($mod,$utils,$idata,$one,$custommsg,$etitlekey,$ebodykey,$tbodykey);
					list($res,$msg1) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if (!$res)
						$msg[] = $msg1;
				}
				if ($msg) {
					return array(FALSE,implode('<br />',array_unique($msg,SORT_STRING)));
				}
				return array(TRUE,'');
			} else {
				return array(FALSE,$mod->Lang('err_data'));
			}
		}
		return array(FALSE,$mod->Lang('tell_booker'));
	}
}
