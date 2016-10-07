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
	/*
	GetReqData:
	Get from HistoryTable and BookerTable the row(s) of data for @history_id
	@mod: reference to current Booker module
	@history: request identifier, or array of them
	*/
	private function GetReqData(&$mod, $history)
	{
		$sql = <<<EOS
SELECT H.*,B.name,B.address,B.phone FROM $mod->HistoryTable H
JOIN $mod->BookerTable B ON H.booker_id=B.booker_id
WHERE history_id
EOS;
		if (is_array($history)) {
			$fillers = str_repeat('?,',count($history)-1);
			return $mod->dbHandle->GetAssoc($sql.' IN ('.$fillers.'?)',$history);
		} else
			return $mod->dbHandle->GetAssoc($sql.'=?',array($history));
	}

	/**
	ApproveReq:
	If possible, record request as approved and do consequent stuff like notify the user.
	Can process intermingled deletion(s) and/or change(s)
	@mod: reference to current Booker module
	@history: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the approval-message 'template'
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function ApproveReq(&$mod, $history, $custommsg)
	{
		$rows = self::GetReqData($mod,$history);
		if ($rows) {
			$db = $mod->dbHandle;
			$utils = new Utils();
			$sched = new Schedule();
			//cluster the requests by id, for specific processing
			krsort($rows,SORT_NUMERIC); //reverse, so groups-first
			$m = -900; //unmatchable
			$collect = array();
			foreach ($rows as $history_id=>&$one) {
				switch ($one['status']) {
				 case \Booker::STATDEL:
				 case \Booker::STATCHG: //TODO setup replacement
					 $sql = 'DELETE FROM '.$mod->HistoryTable.' WHERE history_id=?';
			//TODO $utils->SafeExec()
					 $db->Execute($sql,array($history_id));
					 break;
				 case \Booker::STATCANCEL:
//				 case \Booker::STATTELL:
//				 case \Booker::STATASK:
				 case \Booker::STATBIG:
				 case \Booker::STATNA:
				 case \Booker::STATDUP:
				 case \Booker::STATOK:
				 case \Booker::STATGONE:
//				 case \Booker::STATERR: retry this
					break;
				 default:
					if ($one['item_id'] != $m) {
						if ($collect) {
							if ($m < \Booker::MINGRPID) {
								$res = $sched->ScheduleResource($mod,$utils,$m,$collect);
							} else {
								$res = $sched->ScheduleGroup($mod,$utils,$m,$collect);
							}
							$collect = array();
						}
						$m = (int)$one['item_id'];
					}
					$collect[] = &$one;
					break;
				}
			}
			unset($one);
			if ($collect) {
				if ($m < \Booker::MINGRPID) {
					$res = $sched->ScheduleResource($mod,$utils,$m,$collect);
				} else {
					$res = $sched->ScheduleGroup($mod,$utils,$m,$collect);
				}
			}
			if ($res) { //TODO handle collection members
				//record new status etc in HistoryTable
				$sql = array();
				$args = array();
				$sqlbase = 'UPDATE '.$mod->HistoryTable.' SET ';
				foreach ($rows as $history_id=>$one) {
					$sql1 = $sqlbase;
					$args1 = array();
					if (isset($one['approved'])) {
						$sql1 .= 'item_id=?,subgrpcount=?,approved=?,';
						$args1[] = $one['item_id']; //downstream may have changed item_id (for a 1-member group booking)
						$args1[] = $one['subgroupcount']; //ditto for missing subgroupcount
						$args1[] = $one['approved'];
					}
					$sql1 .= 'status=? WHERE history_id=?';
					$sql[] = $sql1;
					$args1[] = $one['status'];
					$args1[] = $history_id;
					$args[] = $args1;
				}
				$utils->SafeExec($sql,$args);
				if ($mod->havenotifier) {
					//notify lodger
					$funcs = new Messager();
					$sndr = new \MessageSender();
					$propstore = array();
					$msgs = array();
					foreach ($rows as $one) {
						$item_id = $one['item_id'];
						if (!isset($propstore[$item_id])) {
							$propstore[$item_id] = $utils->GetItemProperty($mod,$item_id,
								array('item_id','name','membersname','smspattern','smsprefix'));
							$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
						}
						$idata = $propstore[$item_id];
						list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATOK,$custommsg,$sndr);
						if (!$res)
							$msgs[] = $msg1;
					}
					if ($msgs) {
						return array(FALSE,implode('<br />',array_unique($msgs,SORT_STRING)));
					}
					return array(TRUE,'');
				} else {
					return array(TRUE,$mod->Lang('tell_booker'));
				}
			} else {
				return array(FALSE,$mod->Lang('err_na'));
			}
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	RejectReq:
	@mod: reference to current Booker module
	@history: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the rejection-message 'template'
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function RejectReq(&$mod, $history, $custommsg)
	{
		$rows = self::GetReqData($mod,$history);
		if ($rows) {
			$sql = array();
			$args = array();
			$sql1 = 'UPDATE '.$mod->HistoryTable.' SET status='.\Booker::STATCANCEL.' WHERE history_id=?';
			foreach ($rows as $history_id=>$one) {
				$sql[] = $sql1;
				$args[] = array($history_id);
			}
			$utils = new Utils();
			$utils->SafeExec($sql,$args);

			if ($mod->havenotifier) {
				//notify lodgers
				$funcs = new Messager();
				$sndr = new \MessageSender();
				$propstore = array();
				$msgs = array();
				foreach ($rows as $history_id=>$one) {
					$item_id = $one['item_id'];
					if (!isset($propstore[$item_id])) {
						$propstore[$item_id] = $utils->GetItemProperty($mod,$item_id,
							array('item_id','name','membersname','smspattern','smsprefix'));
						$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
					}
					$idata = $propstore[$item_id];
					list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATCANCEL,$custommsg,$sndr);
					if (!$res)
						$msgs[] = $msg1;
				}
				if ($msgs) {
					return array(FALSE,implode('<br />',array_unique($msgs,SORT_STRING)));
				}
				return array(TRUE,'');
			} else {
				return array(TRUE,$mod->Lang('tell_booker'));
			}
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	AskReq:
	@mod: reference to current Booker module
	@history: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the ask-message 'template'
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function AskReq(&$mod, $history, $custommsg)
	{
		$rows = self::GetReqData($mod,$history);
		if ($rows) {
			$sql = array();
			$args = array();
			$sql1 = 'UPDATE '.$mod->HistoryTable.' SET status='.\Booker::STATASK.' WHERE history_id=?';
			foreach ($rows as $history_id=>$one) {
				$sql[] = $sql1;
				$args[] = array($history_id);
			}
			$utils = new Utils();
			$utils->SafeExec($sql,$args);

			if ($mod->havenotifier) {
				//notify lodgers
				$funcs = new Messager();
				$sndr = new \MessageSender();
				$propstore = array();
				$msgs = array();
				foreach ($rows as $history_id=>$one) {
					$item_id = $one['item_id'];
					if (!isset($propstore[$item_id])) {
						$propstore[$item_id] = $utils->GetItemProperty($mod,$item_id,
							array('item_id','name','membersname','smspattern','smsprefix'));
						$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
					}
					$idata = $propstore[$item_id];
					list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATASK,$custommsg,$sndr);
					if (!$res)
						$msgs[] = $msg1;
				}
				if ($msgs) {
					return array(FALSE,implode('<br />',array_unique($msgs,SORT_STRING)));
				}
				return array(TRUE,'');
			} else {
				return array(TRUE,$mod->Lang('tell_booker'));
			}
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	DeleteReq:
	@mod: reference to current Booker module
	@history: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the delete-message 'template'
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function DeleteReq(&$mod, $history, $custommsg)
	{
		$rows = self::GetReqData($mod,$history);
		if ($rows) {
			$sql = array();
			$args = array();
			$sql1 = 'DELETE FROM '.$mod->HistoryTable.' WHERE history_id=?';
			$sql2 = 'UPDATE '.$mod->HistoryTable.' SET status='.\Booker::STATGONE.' WHERE history_id=?';
			foreach ($rows as $history_id=>$one) {
				if (1) { //TODO $one['status'] == ??
					$sql[] = $sql1;
				} else {
					$sql[] = $sql2;
				}
				$args[] = array($history_id);
			}
			$utils = new Utils();
			$utils->SafeExec($sql,$args);

			if ($mod->havenotifier) {
				//notify lodgers
				$funcs = new Messager();
				$sndr = new \MessageSender();
				$propstore = array();
				$msgs = array();
				foreach ($rows as $history_id=>$one) {
					if ($one['status'] !== \Booker::STATOK) { //TODO others too
						//notify lodger
						$item_id = $one['item_id'];
						if (!isset($propstore[$item_id])) {
							$propstore[$item_id] = $utils->GetItemProperty($mod,$item_id,
								array('item_id','name','membersname','smspattern','smsprefix'));
							$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
						}
						$idata = $propstore[$item_id];
						list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATCANCEL,$custommsg,$sndr);
						if (!$res)
							$msgs[] = $msg1;
					}
				}
				if ($msgs) {
					$msgs = array_unique($msgs,SORT_STRING);
					return array(FALSE,implode('<br />',$msgs));
				}
				return array(TRUE,'');
			} else {
				return array(TRUE,$mod->Lang('tell_booker'));
			}
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	FinishReq:
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@params: reference to parameters array, including data for the request
	@success: boolean indicating whether prior processing has been successful
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function FinishReq(&$mod, &$utils, &$params, $success)
	{
		if ($success) { //successful to now
			$cache = Cache::GetCache($mod);
			$cart = $utils->RetrieveCart($cache,$params);
			if (!$cart || !($pending = $cart->getItems())) {
				return array(FALSE,$mod->Lang('err_data'));
			}
			$key = '';
			$rfuncs = new Requestops();
			$sfuncs = new Schedule();
			$ufuncs = new Userops();
			if ($mod->havenotifier) {
				$mfuncs = new Messager();
				$sndr = new \MessageSender();
//				$propstore = array();
				$msg = array();
				$err = array();
			} else {
				$mfuncs = FALSE;
			}
			foreach ($pending as $item) {
				$data = $item->getPackage();
				$reqdata = (array)$data->request; //from self::CartReq()
				$item_id = $reqdata['item_id'];
				$bookerid = $reqdata['booker_id'];
				$is_new = ($reqdata['status'] === \Booker::STATNEW);
				//TODO update $reqdata : ['payment'] etc
				if ($ufuncs->HasRight($mod,$bookerid,'record')) { //booker can record directly
					if ($item_id < \Booker::MINGRPID) {
						$res = $sfuncs->ScheduleResource($mod,$utils,$item_id,$reqdata); //converts $reqdata to array
					} else {
						$res = $sfuncs->ScheduleGroup($mod,$utils,$item_id,$reqdata);
					}
					if ($res) {
						if ($rfuncs->SaveReq($mod,$utils,$reqdata,$is_new)) {
							$recorded = TRUE;
						} else {
							$key = 'err_system';
							break;
						}
					} else {
						$key = 'err_na';
						break;
					}
				} elseif ($rfuncs->SaveReq($mod,$utils,$reqdata,$is_new)) {
					$recorded = FALSE;
				} else {
					$key = 'err_system';
					break;
				}
				if ($mfuncs) {
/*					if (!isset($propstore[$item_id])) {
						$propstore[$item_id] = $utils->GetItemProperty($mod,$item_id,
						array('item_id','name','approver','approvercontact','approvertell','membersname','smspattern','smsprefix'));
					}
					$idata = $propstore[$item_id];
*/
					$status = ($recorded) ? Messager::MSGRECORD : Messager::MSGSUBMIT;
					list($res,$msg1) = $mfuncs->StatusMessage($mod,$utils,(array)$data->itemdata,$reqdata,$status,'',$sndr);
					if ($res) {
						$msg[] = $msg1;
					} else {
						$err[] = $msg1;
					}
				}
				$cart->removeItem($item->id);
			} //end cartitems loop
			$utils->SaveCart($cart,$cache,$params);
		} else { //just continue the 'failed' status
			$key = 'error';
		}
		if (!$key) {
			if ($err) {//comm error
				return array(FALSE,implode('<br />',array_unique($err)));
			}
			if ($msg) {
				return array(TRUE,implode('<br />',array_unique($msg,SORT_STRING)));
			}
			return array(TRUE,'');
		}
		return array(FALSE,$mod->Lang($key));
	}

	/**
	SaveReq:
	Upsert HistoryTable to reflect relevant contents of @params
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@params: reference to parameters array, including new data for the request
	@is_new: boolean whether to insert or update, either could be by user or admin
	Returns: boolean indicating successful completion
	*/
	public function SaveReq(&$mod, &$utils, &$params, $is_new)
	{
/* $params
'booker_id' => int
'item_id' => int
'subgrpcount' => int
'slotstart' => int
'slotlen' => int
'comment' => string
'fee' => int
'status' => int
'payment' => int
OR
'history_id' => string
'task' => string
'custmsg' => string
'when' => string
'until' => string
'name' => string
'conformuser' => string '1'
'comment' => string
'subgrpcount' => int
'submit' => string
'action' => string
'slotstart' => int
'slotlen' => int
*/
		//table fields unused here 'netfee' 'gatetransaction 'gatedata'
 		//date/time $params[] have been verified before calling here
		if (!empty($params['conformuser'])) {
			//general update where needed
			$funcs = new Userops();
			$funcs->ConformUserData($mod,$params); //general update where needed
		}

		$db = $mod->dbHandle;
		if ($is_new) {
			$hid = $db->GenID($mod->HistoryTable.'_seq');
			$bookerid = $params['booker_id'];
			$idata = $utils->GetItemProperty($mod,$params['item_id'],'timezone');
			$now = $utils->GetZoneTime($idata['timezone']);
			$args = array(
				'history_id'=>$hid,
				'booker_id'=>$bookerid,
				'item_id'=>$params['item_id'],
				'lodged'=>$now
			);
			//$params[] key to table-field translates
			foreach (array(
			 'subgrpcount'=>TRUE,
			 'slotstart'=>TRUE,
			 'slotlen'=>TRUE,
			 'comment'=>TRUE,
			 'fee'=>TRUE, //TODO upstream - func(resource(s),times,user)
			 'requesttype'=>'status',
			) as $k=>$field) {
				if (!empty($params[$k])) {
					switch ($k) {
					 case 'subgrpcount':
					 case 'slotstart':
					 case 'slotlen':
						$args[$k] = (int)$params[$k];
						break;
					 case 'requesttype':
						$args[$field] = (int)$params[$k]; //Booker::STATCHG etc
					 	break;
					 default:
					 	if ($field === TRUE) $field = $k;
						$args[$field] = $params[$k];
					}
				} else {
					switch ($k) {
					 case 'requesttype':
						$args[$field] = \Booker::STATNEW;
						break;
					}
				}
			}

			$fillers = str_repeat('?,',count($args)-1);
			$sql = 'INSERT INTO '.$mod->HistoryTable.' ('.
				implode(',',array_keys($args)).') VALUES ('.$fillers.'?)';
		} else { //update
			$args = array();
			$parts = array();
			foreach (array(
			 'subgrpcount'=>TRUE,
			 'slotstart'=>TRUE,
			 'slotlen'=>TRUE,
			 'comment'=>TRUE,
			 'fee'=>TRUE, //TODO upstream - func(resource(s),times,user)
			 'requesttype'=>'status',
			) as $k=>$field) {
				if (!empty($params[$k])) {
					switch ($k) {
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
//		return $utils->SafeExec($sql,$args);
		return ($db->Execute($sql,$args)) != FALSE;
	}

	/**
	CartReq:
	Add request-item to booking cart
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@params: reference to parameters array, including data for the request
	@idata: array of data about the resource being booked
	@cart: cart-object to which the request will be added
	Returns: 2-member array:
	 [0] boolean indicating success
	 [1] error-message or ''
	*/
	public function CartReq(&$mod, &$utils, &$params, $idata, $cart)
	{
		$item_id = (int)$params['item_id'];
		$item = new Cart\BookingCartItem($idata['name'],$item_id,$params['fee'],$idata['taxrate']); //$item_id will be the item 'type'
		$data = $item->getPackage();

		$t = ($params['name']) ? $params['name'] : $params['account'];
		$name = trim($t);
		$ob = \cms_utils::get_module('FrontEndUsers');
		if ($ob) {
			$data->uid = $ob->LoggedInID();
			unset($ob);
		} else {
			$data->uid = FALSE;
		}
		if (isset($params['publicid'])) {
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
		} elseif (isset($params['contact'])) {
			$t = $params['contact'];
		} else {
$this->Crash();
			$t = $mod->Lang('err_data');
		}
		$contact = $t;

		//TODO get real maxlen from table-field size
		$data->maxlen = 0; //max comment length or 0 for unlimited

		$quantity = (!empty($params['subgrpcount'])) ? (int)$params['subgrpcount'] : 1;
		if (isset($params['slotstart'])) {
			$bs = $params['slotstart'];
			$be = $bs + $params['slotlen'];
		} elseif (isset($params['when'])) { //parameter verified before coming here, no chance of fail now
			$dtw = new \DateTime($params['when'],new \DateTimeZone('UTC')); //string e.g. '20 Jul 2016 8:00'
			$bs = $dtw->getTimestamp();
			$dtw->modify($params['until']); //string e.g. '20 Jul 2016 9:00'
			$be = $dtw->getTimestamp();
		} else { //past booking
			$bs = $params['bookat'];
			$be = $bs + 1800; //TODO;
		}
		list($bs,$be) = $utils->TuneBlock($idata['slottype'],$idata['slotcount'],$bs,$be);
		$now = $utils->GetZoneTime($idata['timezone']);

		$fee = (isset($params['fee'])) ? (float)$params['fee'] : 0.0;
		$minpay = 1.0; //TODO support selectable min. payment - single and total
		if ($fee < $minpay) {
			$fee = 0.0;
			$pay = \Booker::STATFREE;
		} else {
			$pay = \Booker::STATPAYABLE; //TODO method to get relevant status
		}
		$stat = (!empty($params['requesttype'])) ? (int)$params['requesttype'] : \Booker::STATNEW; //TODO status method

		//populate request data for later processing
		$data->request = array(
		 'booker_id'=>(int)$params['booker_id'],
		 'name'=>$name,
		 'contact'=>$contact,
		 'item_id'=>$item_id,
		 'subgrpcount'=>$quantity,
		 'slotstart'=>$bs,
		 'slotlen'=>$be-$bs,
		 'lodged'=>$now,
		 'comment'=>trim($params['comment']),
		 'fee'=>$fee,
		 'status'=>$stat,
		 'payment'=>$pay
		);
		$data->itemdata = $idata;

		$cart->addItem($item,$quantity);
		return array(TRUE,'');
	}
}
