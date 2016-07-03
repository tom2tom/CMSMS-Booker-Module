<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Requestops - functions for processing booking requests
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

class Requestops
{
	const MSGAPPROVE = 1;
	const MSGREJECT = 2;
	const MSGCHANGED = 3;
	const MSGCANCELLED = 4;
	const MSGINFO = 5;
	/*
	GetReqData:
	Get from RequestTable the row(s) of data for @req_id
	@mod: reference to current Booker module
	@req_id: request identifier, or array of them
	*/
	private function GetReqData(&$mod,$req_id)
	{
		if(is_array($req_id))
		{
			$fillers = str_repeat('?,',count($req_id)-1);
			return $mod->dbHandle->GetAssoc('SELECT * FROM '.$mod->RequestTable.' WHERE req_id IN ('.$fillers.'?)',$req_id);
		}
		else
			return $mod->dbHandle->GetAssoc('SELECT * FROM '.$mod->RequestTable.' WHERE req_id=?',array($req_id));
	}

	/* *
	SetupMessage:
	see also - self::MsgParms
	@mod: reference to current Booker module
	@shares: reference to bkrshared object
	@params:
	@mtype: MSG* enum
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	@extra: optional stuff for some types of message, default ''
	Returns: TODO title & bodies for MessageSender::Send
	*/
/*	function SetupMessage(&$mod,&$shares,&$params,$mtype,$custommsg,$extra='')
	{
		//$mtype = $TODO; //what type of message
		$idata = $TODO;
		$what = (isset($params['subgrpcount'])) ?
			sprintf('%d %s',$params['subgrpcount'],$idata['membersname']):
			$shares->GetItemName($mod,$idata);
		$dt = $TODO;
		$dt->setTimestamp($params['slotstart']);
		$on = $shares->IntervalFormat($mod,$dt,'D j M');
		if($overday)
		{
			switch($mtype)
			{
			 default:
				$approvecommon = $mod->Lang('email_approve',$what,$on);
				$rejectcommon = $mod->Lang('email_reject',$what,$on);
				$notifycommon = $mod->Lang('email_changed',$what,$on); //ETC
				$askcommon = $mod->Lang('email_ask',$what,$on);
			}
		}
		else
		{
			$at = $dt->format('g:i A');
			switch($mtype)
			{
			 default:
				$approvecommon = $mod->Lang('email_approveat',$what,$on,$at);
				$rejectcommon = $mod->Lang('email_rejectat',$what,$on,$at);
				$notifycommon = $mod->Lang('email_changedat',$what,$on,$at); //ETC
				$askcommon = $mod->Lang('email_askat',$what,$on,$at);
			}
		}
		//TODO other formats
		//TODO messages =  replace \[.*\] by $params['custom']
	}
*/

	/*
	MsgParms:
	Construct arguments for MessageSender::Send()
	@mod: reference to current Booker module object
	@shares: reference to bkrshared object
	@bdata: reference to array of booking-request data
	@idata: reference to array of booked-item data
	@mtype: MSG* enum
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	@extra: optional stuff for some types of message, default ''
	*/
	private function MsgParms(&$mod,&$shares,&$bdata,&$idata,$mtype,$custommsg,$extra='')
	{
		$overday = ($shares->GetInterval($mod,$idata['item_id'],'slot') >= 84600);
		switch($mtype)
		{
		 case self::MSGAPPROVE:
			$ktitle = 'email_approve_title';
			if($overday)
			{
				$kbody1 = 'email_approve';
				$kbody2 = 'text_approve';
			}
			else
			{
				$kbody1 = 'email_approveat';
				$kbody2 = 'text_approveat';
			}
			break;
		 case self::MSGREJECT:
			$ktitle = 'email_reject_title';
			if($overday)
			{
				$kbody1 = 'email_reject';
				$kbody2 = 'text_reject';
			}
			else
			{
				$kbody1 = 'email_rejectat';
				$kbody2 = 'text_rejectat';
			}
			break;
		 case self::MSGCHANGED:
			$ktitle = 'email_changed_title';
			if($overday)
			{
				$kbody1 = 'email_changed';
				$kbody2 = 'text_change';
			}
			else
			{
				$kbody1 = 'email_changedat';
				$kbody2 = 'text_changeat';
			}
		 	break;
		 case self::MSGCANCELLED:
			$ktitle = 'email_cancelled_title';
			if($overday)
			{
				$kbody1 = 'email_cancel';
				$kbody2 = 'text_cancel';
			}
			else
			{
				$kbody1 = 'email_cancelat';
				$kbody2 = 'text_cancelat';
			}
			break;
		 case self::MSGINFO:
			$ktitle = 'email_ask_title';
			if($overday)
			{
				$kbody1 = 'email_ask';
				$kbody2 = 'text_ask';
			}
			else
			{
				$kbody1 = 'email_askat';
				$kbody2 = 'text_askat';
			}
			break;
		 default:
		 	return FALSE; //error
		}

		$from = FALSE; //always use default sender
		$to = array($bdata['sender']=>$bdata['contact']);
		$what = ($bdata['subgrpcount'] > 1) ?
			sprintf('%d %s',$bdata['subgrpcount'],$idata['membersname']):
			$shares->GetItemName($mod,$idata);
		$dts = new DateTime('1900-1-1',new DateTimeZone('UTC'));
		$dts->setTimestamp($bdata['slotstart']);
		$on = $shares->IntervalFormat($mod,$dts,'D j M');

		$textparms = array('prefix'=>$idata['smsprefix'],'pattern'=>$idata['smspattern']);
		$mailparms = array('subject'=>$mod->Lang($ktitle));
		$tweetparms = array();
		if($overday)
		{
			$msg = $mod->Lang($kbody1,$what,$on);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$mailparms['body'] = $msg;
			$msg = $mod->Lang($kbody2,$what,$on);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$textparms['body'] = $msg;
			$tweetparms['body'] = $msg;
		}
		else
		{
			$at = $dts->format('g:i A');
			$msg = $mod->Lang($kbody1,$what,$on,$at);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$mailparms['body'] = $msg;
			$msg = $mod->Lang($kbody2,$what,$on,$at);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$textparms['body'] = $msg;
			$tweetparms['body'] = $msg;
		}
		return array($from,$to,$textparms,$mailparms,$tweetparms);
	}

	/**
	ApproveReq:
	If possible, record request as approved and do consequent stuff like notify the user.
	Can process intermingled deletion(s) and/or change(s)
	@mod: reference to current Booker module
	@req_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the approval-message 'template'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function ApproveReq(&$mod,$req_id,$custommsg)
	{
		$rows = self::GetReqData($mod,$req_id);
		if($rows)
		{
			$db = $mod->dbHandle;
			$shares = new Booker\Shared();
			$sched = new Booker\Schedule();
			//cluster the requests by id, for specific processing
			krsort($rows,SORT_NUMERIC); //reverse, so groups-first
			$m = -900; //unmatchable
			$collect = array();
			foreach($rows as $id=>&$one)
			{
				switch($one['status'])
				{
				 case Booker::STATDEL:
				 case Booker::STATCHG: //TODO setup replacement
					 $sql = 'DELETE FROM '.$mod->RequestTable.' WHERE req_id=?';
					 $db->Execute($sql,array($id));
					 break;
				 case Booker::STATCANCEL:
				 case Booker::STATTELL:
				 case Booker::STATASK: 
				 case Booker::STATBIG: 
				 case Booker::STATNA:
				 case Booker::STATDUP:
				 case Booker::STATOK:
				 case Booker::STATGONE:
//				 case Booker::STATERR: retry this
					break;
				 default:
					if($id != $m)
					{
						if($collect)
						{
							if($m < Booker::MINGRPID)
								$sched->ScheduleResource($mod,$shares,$m,$collect);
							else
								$sched->ScheduleGroup($mod,$shares,$m,$collect);
							$collect = array();
						}
						$m = $id;
					}
					$collect[] = $one;
					break;
				}
			}
			unset($one);
			if($collect)
			{
				if($m < Booker::MINGRPID)
					$sched->ScheduleResource($mod,$shares,$m,$collect);
				else
					$sched->ScheduleGroup($mod,$shares,$m,$collect);
			}
			//record updated status
			$sql = 'UPDATE '.$mod->RequestTable.' SET status=? WHERE req_id=?';
			$db->StartTrans();
			foreach($rows as $id=>&$one)
				$db->Execute($sql,array($one['status'],$id));
			$db->CompleteTrans(); //ignore any problem e.g. deleted
			unset($one);

			$ob = cms_utils::get_module('Notifier');
			if($ob)
			{
				unset($ob);
				//notify lodger
				$funcs = new MessageSender();
				$fails = array();

				foreach($rows as $id=>&$one)
				{
					switch($one['status'])
					{
					 case Booker::STATNONE:
						$idata = $shares->GetItemProperty($mod,$one['item_id'],'*');
						list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$shares,$one,$idata,self::MSGAPPROVE,$custommsg);
						list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
						if(!$res)
							$fails[] = $msg;
						break;
					 default:
/* TODO relevant advice
					 case Booker::STATASK:
					 case Booker::STATBIG:
					 case Booker::STATCANCEL:
					 case Booker::STATCHG:
					 case Booker::STATDEFER:
					 case Booker::STATDEL:
					 case Booker::STATDUP:
					 case Booker::STATERR:
					 case Booker::STATGONE:
					 case Booker::STATNA:
					 case Booker::STATNEW:
					 case Booker::STATNOPAY:
					 case Booker::STATOK:
					 case Booker::STATTELL:
					 case Booker::STATTEMP:
*/
					 	break;
					}
				}
				unset($one);
				if($fails)
					return array(FALSE,implode('<br />',$fails));
				return aray(TRUE,'');
			}
			else
			{
				//TODO remind user to tell all, manually
			}
		}
		else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	RejectReq:
	@mod: reference to current Booker module
	@req_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the rejection-message 'template'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function RejectReq(&$mod,$req_id,$custommsg)
	{
		$rows = self::GetReqData($mod,$req_id);
		if($rows)
		{
			$ob = cms_utils::get_module('Notifier');
			if($ob)
			{
				unset($ob);
				$funcs = new MessageSender();
				$shares = new Booker\Shared();
				$fails = array();
			}
			else
				$funcs = FALSE;
			$db = $mod->dbHandle;
			$sql = 'UPDATE '.$mod->RequestTable.' SET status='.Booker::STATCANCEL.' WHERE req_id=?';
			foreach($rows as $req_id=>$one)
			{
				if($funcs)
				{
					//notify lodger
					$idata = $shares->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$shares,$one,$idata,self::MSGREJECT,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if(!$res)
						$fails[] = $msg;
				}
				$db->Execute($sql,array($req_id));//update status
			}
			if($fails)
				return array(FALSE,implode('<br />',$fails));
			return array(TRUE,'');
		}
		else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	NotifyReq:
	@mod: reference to current Booker module
	@req_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the notify-message 'template'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function NotifyReq(&$mod,$req_id,$custommsg)
	{
		$rows = self::GetReqData($mod,$req_id);
		if($rows)
		{
			$ob = cms_utils::get_module('Notifier');
			if($ob)
			{
				unset($ob);
				$funcs = new MessageSender();
				$shares = new Booker\Shared();
				$fails = array();
			}
			else
				$funcs = FALSE;
			$db = $mod->dbHandle;
			$sql = 'UPDATE '.$mod->RequestTable.' SET status='.Booker::STATASK.' WHERE req_id=?';
			foreach($rows as $req_id=>$one)
			{
				if($funcs)
				{
					//notify lodger
					$idata = $shares->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$shares,$one,$idata,self::MSGINFO,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if(!$res)
						$fails[] = $msg;
				}
				$db->Execute($sql,array($req_id));//update status
			}
			if($fails)
				return array(FALSE,implode('<br />',$fails));
			return array(TRUE,'');
		}
		else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	DeleteReq:
	@mod: reference to current Booker module
	@req_id: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the delete-message 'template'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function DeleteReq(&$mod,$req_id,$custommsg)
	{
		$rows = self::GetReqData($mod,$req_id);
		if($rows)
		{
			$ob = cms_utils::get_module('Notifier');
			if($ob)
			{
				unset($ob);
				$funcs = new MessageSender();
				$shares = new Booker\Shared();
				$fails = array();
			}
			else
				$funcs = FALSE;
			$db = $mod->dbHandle;
			$sql = 'DELETE FROM '.$mod->RequestTable.' WHERE req_id=?';
			foreach($rows as $req_id=>$one)
			{
				if($funcs && $one['status'] !== Booker::STATOK)
				{
					//notify lodger
					$idata = $shares->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$shares,$one,$idata,self::MSGCANCELLED,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if(!$res)
						$fails[] = $msg;
				}
				$db->Execute($sql,array($req_id));//remove it
			}
			if($fails)
				return array(FALSE,implode('<br />',$fails));
			return array(TRUE,'');
		}
		else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	SaveReq:
	@mod: reference to current Booker module
	@params: reference to parameters array
	@rdata: reference to array of current data for the request
	@is_new: boolean whether to insert or update
	Returns: T/F indicating successful completion
	*/
	public function SaveReq(&$mod,&$params,&$rdata,$is_new)
	{
		$db = $mod->dbHandle;
		$tzone = new DateTimeZone('UTC');
		if($is_new)
		{
			$rid = $db->GenID($mod->RequestTable.'_seq');
			$args = array('req_id'=>$rid,'item_id'=>$params['item_id']);
			$fillers = array('?','?');
			/* TABLE FIELDS UNUSED HERE
			'paid'
			'approved'
			*/
			//from $params[] key to table-field name
			foreach(array(
			 'when'=>'slotstart',
			 'until'=>'slotlen',
			 'user'=>'sender',
			 'contact'=>'contact',
			 'comment'=>'comment',
			 'subgrpcount'=>'subgrpcount',
			 'requesttype'=>'status',
			 'lodged'=>'lodged'
			) as $k=>$field)
			{
				if(!empty($params[$k]))
				{
					if($k == 'when')
					{
						//to support better feedback to user, no period-cleanup until after approval
						$dts = new DateTime($params['when'],$tzone);
						$params[$k] = $dts->getTimestamp();
					}
					elseif($k == 'until')
					{
						$dte = new DateTime($params['until'],$tzone);
						$params[$k] = $dte->getTimestamp() - $params['when'];
					}
					$args[$field] = $params[$k];
					$fillers[] = '?';
				}
				else
				{
					if($k == 'requesttype')
					{
						$args[$field] = Booker::STATNEW;
						$fillers[] = '?';
					}
				}
			}

			$sql = 'INSERT INTO '.$mod->RequestTable.' ('.
				implode(',',array_keys($args)).') VALUES ('.implode(',',$fillers).')';
		}
		else //update
		{
//TODO		X::ConformBookingData($mod,$params,$rdata); //general update where needed
			$dts = new DateTime($params['when'],$tzone);
			$params['when'] = $dts->getTimestamp();
			if(isset($params['until']))
			{
				$dts->modify($params['until']);
				$len = $dts->getTimestamp() - $params['when'];
				$params['until'] = max($len,60);
			}
			$sql2 = 'slotstart=?,sender=?,contact=?,comment=?,paid=?';
			$args = array(
				$params['when'],
				$params['user'],
				$params['contact'],
				$params['comment'],
				empty($params['paid']) ? 0:1
			);
			foreach(array('subgrpcount','until') as $k)
			{
				if(isset($params[$k]))
				{
					$sql2 .= ",$k=?";
					$args[] = (int)$params[$k];
				}
			}
			$args[] = (int)$params['req_id'];
			$sql = 'UPDATE '.$mod->RequestTable.' SET '.$sql2.' WHERE req_id=?';
		}
//		$funcs = new Booker\Shared();
//		return $funcs->SafeExec($sql,$args);
		return ($db->Execute($sql,$args)) != FALSE;
	}
}
?>
