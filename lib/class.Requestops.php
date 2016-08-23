<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Requestops - functions for processing booking requests
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Requestops
{
	const MSGAPPROVE = 1;
	const MSGREJECT = 2;
	const MSGCHANGED = 3;
	const MSGCANCELLED = 4;
	const MSGINFO = 5;
	/*
	GetReqData:
	Get from HistoryTable the row(s) of data for @history_id
	@mod: reference to current Booker module
	@history_id: request identifier, or array of them
	*/
	private function GetReqData(&$mod, $history_id)
	{
		if (is_array($history_id)) {
			$fillers = str_repeat('?,',count($history_id)-1);
			return $mod->dbHandle->GetAssoc('SELECT * FROM '.$mod->HistoryTable.' WHERE history_id IN ('.$fillers.'?)',$history_id);
		} else
			return $mod->dbHandle->GetAssoc('SELECT * FROM '.$mod->HistoryTable.' WHERE history_id=?',array($history_id));
	}

	/* *
	SetupMessage:
	see also - self::MsgParms
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
		$what = (isset($params['subgrpcount'])) ?
			sprintf('%d %s',$params['subgrpcount'],$idata['membersname']):
			$utils->GetItemName($mod,$idata);
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
			$notifycommon = $mod->Lang('email_changed',$detail); //ETC
			$askcommon = $mod->Lang('email_ask',$detail);
		}
		//TODO other formats
		//TODO messages =  replace \[.*\] by $params['custom']
	}
*/

	/*
	MsgParms:
	Construct arguments for 'outward' message using MessageSender::Send()
	see also Booker\Bookingops::MsgParms()
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@bdata: reference to array of booking-request data
	@idata: reference to array of booked-item data
	@mtype: MSG* enum
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	@extra: optional stuff for some types of message, default ''
	Returns: array or FALSE
	*/
	private function MsgParms(&$mod, &$utils, &$bdata, &$idata, $mtype, $custommsg, $extra='')
	{
		switch ($mtype) {
		 case self::MSGAPPROVE:
			$ktitle = 'email_approve_title';
			$kbody1 = 'email_approve';
			$kbody2 = 'text_approve';
			break;
		 case self::MSGREJECT:
			$ktitle = 'email_reject_title';
			$kbody1 = 'email_reject';
			$kbody2 = 'text_reject';
			break;
		 case self::MSGCHANGED:
			$ktitle = 'email_changed_title';
			$kbody1 = 'email_changed';
			$kbody2 = 'text_change';
		 	break;
		 case self::MSGCANCELLED:
			$ktitle = 'email_cancelled_title';
			$kbody1 = 'email_cancel';
			$kbody2 = 'text_cancel';
			break;
		 case self::MSGINFO:
			$ktitle = 'email_ask_title';
			$kbody1 = 'email_ask';
			$kbody2 = 'text_ask';
			break;
		 default:
		 	return FALSE; //error
		}

		$from = FALSE; //always use default sender
		$to = ($bdata['address']) ? array($bdata['name']=>$bdata['address']):$bdata['phone'];

		$what = ($bdata['subgrpcount'] > 1) ?
			sprintf('%d %s',$bdata['subgrpcount'],$idata['membersname']):
			$utils->GetItemName($mod,$idata);
		$dts = new \DateTime('@'.$bdata['slotstart'],new \DateTimeZone('UTC'));
		$on = $utils->IntervalFormat($mod,$dts,'D j M');
		if ($utils->GetInterval($mod,$idata['item_id'],'slot') >= 84600) {
			$detail = $mod->Lang('whatovrday',$what,$on);
		} else {
			$at = $dts->format('g:i A');
			$detail = $mod->Lang('whatonday',$what,$on,$at);
		}

		$msg = $mod->Lang($kbody1,$detail);
		$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
		$mailparms = array('subject'=>$mod->Lang($ktitle),'body'=>$msg);
		$msg = $mod->Lang($kbody2,$detail);
		$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
		$textparms = array('prefix'=>$idata['smsprefix'],
			'pattern'=>$idata['smspattern'],'body'=>$msg);
		$tweetparms = array('body'=>$msg);
		return array($from,$to,$textparms,$mailparms,$tweetparms);
	}

	/**
	ApproveReq:
	If possible, record request as approved and do consequent stuff like notify the user.
	Can process intermingled deletion(s) and/or change(s)
	@mod: reference to current Booker module
	@history_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the approval-message 'template'
	Returns: 2-member array, 1st is boolean indicating success, 2nd '' or error message
	*/
	public function ApproveReq(&$mod, $history_id, $custommsg)
	{
		$rows = self::GetReqData($mod,$history_id);
		if ($rows) {
			$db = $mod->dbHandle;
			$utils = new Utils();
			$sched = new Schedule();
			//cluster the requests by id, for specific processing
			krsort($rows,SORT_NUMERIC); //reverse, so groups-first
			$m = -900; //unmatchable
			$collect = array();
			foreach ($rows as $id=>&$one) {
				switch ($one['status']) {
				 case \Booker::STATDEL:
				 case \Booker::STATCHG: //TODO setup replacement
					 $sql = 'DELETE FROM '.$mod->HistoryTable.' WHERE history_id=?';
			//TODO $utils->SafeExec()
					 $db->Execute($sql,array($id));
					 break;
				 case \Booker::STATCANCEL:
				 case \Booker::STATTELL:
				 case \Booker::STATASK:
				 case \Booker::STATBIG:
				 case \Booker::STATNA:
				 case \Booker::STATDUP:
				 case \Booker::STATOK:
				 case \Booker::STATGONE:
//				 case \Booker::STATERR: retry this
					break;
				 default:
					if ($id != $m) {
						if ($collect) {
							if ($m < \Booker::MINGRPID)
								$sched->ScheduleResource($mod,$utils,$m,$collect);
							else
								$sched->ScheduleGroup($mod,$utils,$m,$collect);
							$collect = array();
						}
						$m = $id;
					}
					$collect[] = $one;
					break;
				}
			}
			unset($one);
			if ($collect) {
				if ($m < \Booker::MINGRPID)
					$sched->ScheduleResource($mod,$utils,$m,$collect);
				else
					$sched->ScheduleGroup($mod,$utils,$m,$collect);
			}
			//record updated status
			$sql = 'UPDATE '.$mod->HistoryTable.' SET status=? WHERE history_id=?';
			$db->StartTrans();
			foreach ($rows as $id=>&$one) {
			//TODO $utils->SafeExec()
				$db->Execute($sql,array($one['status'],$id));
			}
			$db->CompleteTrans(); //ignore any problem e.g. deleted
			unset($one);

			$ob = \cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				//notify lodger
				$funcs = new \MessageSender();
				$fails = array();

				foreach ($rows as $id=>&$one) {
					switch ($one['status']) {
					 case \Booker::STATNONE:
						$idata = $utils->GetItemProperty($mod,$one['item_id'],'*');
						list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$utils,$one,$idata,self::MSGAPPROVE,$custommsg);
						list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
						if (!$res)
							$fails[] = $msg;
						break;
					 default:
/* TODO relevant advice
					 case \Booker::STATASK:
					 case \Booker::STATBIG:
					 case \Booker::STATCANCEL:
					 case \Booker::STATCHG:
					 case \Booker::STATDEFER:
					 case \Booker::STATDEL:
					 case \Booker::STATDUP:
					 case \Booker::STATERR:
					 case \Booker::STATGONE:
					 case \Booker::STATNA:
					 case \Booker::STATNEW:
					 case \Booker::STATNOTPAID:
					 case \Booker::STATOK:
					 case \Booker::STATTELL:
					 case \Booker::STATTEMP:
*/
					 	break;
					}
				}
				unset($one);
				if ($fails)
					return array(FALSE,implode('<br />',$fails));
				return aray(TRUE,'');
			} else {
				//TODO remind user to tell all, manually
			}
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	RejectReq:
	@mod: reference to current Booker module
	@history_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the rejection-message 'template'
	Returns: 2-member array, 1st is boolean indicating success, 2nd '' or error message
	*/
	public function RejectReq(&$mod, $history_id, $custommsg)
	{
		$rows = self::GetReqData($mod,$history_id);
		if ($rows) {
			$ob = \cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				$funcs = new \MessageSender();
				$utils = new Utils();
				$fails = array();
			} else
				$funcs = FALSE;
			$db = $mod->dbHandle;
			$sql = 'UPDATE '.$mod->HistoryTable.' SET status='.\Booker::STATCANCEL.' WHERE history_id=?';
			foreach ($rows as $history_id=>$one) {
				if ($funcs) {
					//notify lodger
					$idata = $utils->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$utils,$one,$idata,self::MSGREJECT,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if (!$res)
						$fails[] = $msg;
				}
			//TODO $utils->SafeExec()
				$db->Execute($sql,array($history_id));//update status
			}
			if ($fails)
				return array(FALSE,implode('<br />',$fails));
			return array(TRUE,'');
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	NotifyReq:
	@mod: reference to current Booker module
	@history_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the notify-message 'template'
	Returns: 2-member array, 1st is boolean indicating success, 2nd '' or error message
	*/
	public function NotifyReq(&$mod, $history_id, $custommsg)
	{
		$rows = self::GetReqData($mod,$history_id);
		if ($rows) {
			$ob = \cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				$funcs = new \MessageSender();
				$utils = new Utils();
				$fails = array();
			} else
				$funcs = FALSE;
			$db = $mod->dbHandle;
			$sql = 'UPDATE '.$mod->HistoryTable.' SET status='.\Booker::STATASK.' WHERE history_id=?';
			foreach ($rows as $history_id=>$one) {
				if ($funcs) {
					//notify lodger
					$idata = $utils->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$utils,$one,$idata,self::MSGINFO,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if (!$res)
						$fails[] = $msg;
				}
			//TODO $utils->SafeExec()
				$db->Execute($sql,array($history_id));//update status
			}
			if ($fails)
				return array(FALSE,implode('<br />',$fails));
			return array(TRUE,'');
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	DeleteReq:
	@mod: reference to current Booker module
	@history_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the delete-message 'template'
	Returns: 2-member array, 1st is boolean indicating success, 2nd '' or error message
	*/
	public function DeleteReq(&$mod, $history_id, $custommsg)
	{
		$rows = self::GetReqData($mod,$history_id);
		if ($rows) {
			$ob = \cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				$funcs = new \MessageSender();
				$utils = new Utils();
				$fails = array();
			} else
				$funcs = FALSE;
			$db = $mod->dbHandle;
			$sql = 'DELETE FROM '.$mod->HistoryTable.' WHERE history_id=?';
			foreach ($rows as $history_id=>$one) {
				if ($funcs && $one['status'] !== \Booker::STATOK) {
					//notify lodger
					$idata = $utils->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$utils,$one,$idata,self::MSGCANCELLED,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if (!$res)
						$fails[] = $msg;
				}
			//TODO $utils->SafeExec()
				$db->Execute($sql,array($history_id));//remove it
			}
			if ($fails)
				return array(FALSE,implode('<br />',$fails));
			return array(TRUE,'');
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	SaveReq:
	Upsert HistoryTable to reflect relevant contents of @params
	@mod: reference to current Booker module
	@params: reference to parameters array, including new data for the request
	@is_new: boolean whether to insert or update, either could be by user or admin
	Returns: boolean indicating successful completion
	*/
	public function SaveReq(&$mod, &$params, $is_new)
	{
		$booker_id = (int)$params['booker_id']; //TODO upstream must supply this
		//table fields unused here 'netfee' 'gatetransaction 'gatedata'
 		//date/time $params[] have been verified before calling here
		if (isset($params['slotstart'])) {
			//already translated into history-table format
			$dt = new \DateTime('@0',new \DateTimeZone('UTC'));
			$t1 = $params['slotstart'];
			$t2 = $params['slotlen'];
		} else {
			$dt = new \DateTime($params['when'],new \DateTimeZone('UTC'));
			$t1 = $dt->getTimestamp();
			$dt->modify($params['until']);
			$t2 = $dt->getTimestamp() - $t1;
			if ($t2 < 120) $t2 = 120; //2-minutes min
			$t2 --; //TODO iff at start of new slot
		}

		$db = $mod->dbHandle;
		if ($is_new) {
			$hid = $db->GenID($mod->HistoryTable.'_seq');
			$args = array('history_id'=>$hid,'booker_id'=>$booker_id,'item_id'=>$params['item_id']);
			//$params[] key to table-field translates
			foreach (array(
			 'subgrpcount'=>TRUE,
			 'lodged'=>TRUE,
			 'slotstart'=>TRUE,
			 'slotlen'=>TRUE,
			 'when'=>'slotstart',
			 'until'=>'slotlen',
			 'comment'=>TRUE,
			 'fee'=>TRUE, //TODO upstream - func(resource(s),times,user)
			 'requesttype'=>'status',
			) as $k=>$field) {
				if (!empty($params[$k])) {
					switch ($k) {
					 case 'lodged':
						if ($is_new) {
							$dt->modify('now');
							$args[$k] = $dt->getTimestamp();
						}
						break;
					 case 'slotstart':
						$field = $k;
						//no break here
					 case 'when':
						$args[$field] = $t1;
						break;
					 case 'slotlen':
						$field = $k;
						//no break here
					 case 'until':
						$args[$field] = $t2;
						break;
					 case 'requesttype':
						$args[$field] = (int)$params[$k]; //Booker::STATCHG etc
					 	break;
					 default:
					 	if ($field === TRUE) $field = $k;
						$args[$field] = $params[$k];
					}
				} elseif ($k == 'requesttype') { //??
					$args[$field] = \Booker::STATNEW;
				}
			}

			$fillers = str_repeat('?,',count($args)-1);
			$sql = 'INSERT INTO '.$mod->HistoryTable.' ('.
				implode(',',array_keys($args)).') VALUES ('.$fillers.'?)';
		} else { //update
			$funcs = new Bookingops();
			$funcs->ConformBookingData($mod,$params); //general update where needed
			$args = array();
			$parts = array();
			foreach (array(
			 'subgrpcount'=>TRUE,
			 'when'=>'slotstart',
			 'until'=>'slotlen',
			 'comment'=>TRUE,
			 'fee'=>TRUE, //TODO upstream - func(resource(s),times,user)
			 'requesttype'=>'status',
			) as $k=>$field) {
				if (!empty($params[$k])) {
					switch ($k) {
					 case 'when':
						$args[$field] = $t1;
						break;
					 case 'until':
						$args[$field] = $t2;
						break;
					 case 'requesttype':
						$args[$field] = (int)$params[$k]; //Booker::STATCHG etc
					 	break;
					 default:
					 	if ($field === TRUE) $field = $k;
						$args[$field] = $params[$k];
					}
					$parts[] = $field.'=?';
				}
			}
			$fillers = implode(',',$parts);
			$args[] = (int)$params['history_id'];
			$sql = 'UPDATE '.$mod->HistoryTable.' SET '.$fillers.' WHERE history_id=?';
		}
//		$utils = new Utils();
//		return $utils->SafeExec($sql,$args);
		//TODO $utils->SafeExec()
		return ($db->Execute($sql,$args)) != FALSE;
	}

	/**
	CartReq:
	Add request-item to booking cart
	@mod: reference to current Booker module
	@params: reference to parameters array, including data for the request
	@idata: array of data about the resource being booked
	@cart: cart-object to which the request will be added
	Returns: 2-member array, 1st is boolean indicating success, 2nd is '' or error message
	*/
	public function CartReq(&$mod, &$params, $idata, $cart)
	{
		try {
			$dt = new \DateTime($params['when'],new \DateTimeZone('UTC')); //string e.g. '20 Jul 2016 8:00'
		} catch (Exception $e) {
			return array(FALSE,$e->getMessage());
		}

		$item_id = (int)$params['item_id'];
		$fee = (isset($params['fee'])) ? (float)$params['fee'] : 0.0;
		if ($fee < 1.0) //TODO support selectable min. payment
			$fee = 0.0;
		$item = new Cart\BookingCartItem('',$item_id,$fee);

		$data = $item->getPackage();
		$data->user = ($params['user']) ? $params['user'] : $params['account']; 
		$ob = cms_utils::get_module('FrontEndUsers');
		if ($ob) {
			$data->uid = $ob->LoggedInID();
			unset($ob);
		} else {
			$data->uid = FALSE;
		}
		if ($params['publicid']) {
			if ($params['contactnew']) {
				$t = $params['contactnew'];
			} else {
				$funcs = new Userops();
				$row = $funcs->GetContact($mod,$params['booker_id']); //get current contact for account
				if ($row) {
					$t = ($row['address']) ? $row['address'] : $row['phone'];
				} else {
					$t = $mod->Lang('err_data');
				}
			}
		} elseif ($params['contactuser']) {
			$t = $params['contactuser'];
		} else {
			$t = $mod->Lang('err_data');
		}
		$data->contact = $t;
		$data->start = $dt->GetTimestamp();
		$dt->modify($parms['until']); //string e.g. '20 Jul 2016 9:00'
		$t = $dt->GetTimestamp() - $data->start;
		if ($t < 120) { //TODO relevant default c.f. $utils->GetInterval($mod,$item_id,'slot');
			$t = 120;
		}
		$data->slen = $t - 1; //TODO -1 iff ends at a slotstart
		$data->comment = trim($params['comment']);
		//TODO get real maxlen from table-field size
		$data->maxlen = 0; //max comment length or 0 for unlimited
		$quantity = (!empty($params['subgrpcount'])) ? (int)$params['subgrpcount'] : 1;
		$stat = (!empty($params['requesttype'])) ? (int)$params['requesttype'] : \Booker::STATNEW;
		$pay = ($fee < 1.0) ? \Booker::STATFREE : \Booker::STATPAYABLE;
		//partial request data for action.requestfinish
		$data->request = array(
		 'booker_id'=>(int)$params['booker_id'],
		 'item_id'=>$item_id,
		 'subgrpcount'=>$quantity,
		 'slotstart'=>$data->start,
		 'slotlen'=>$data->slen,
		 'comment'=>$data->comment,
		 'fee'=>$fee,
		 'status'=>stat,
		 'payment'=>$pay
		);
		$data->itemdata = $idata;

		$cart->addItem($item,$quantity);
		return array(TRUE,'');
	}
}
