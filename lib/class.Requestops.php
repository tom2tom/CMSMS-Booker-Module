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
	Get from OnceTable and BookerTable/AuthTable the row(s) of data for $reqid
	@mod: reference to current Booker module
	$reqid: request identifier, or array of them
	Returns: associative array, or FALSE
	*/
	private function GetReqData(&$mod, $reqid)
	{
		//CHECKME don't need OnceTable */whole-rows?
		$sql = <<<EOS
SELECT O.*,COALESCE(A.name,B.name,'') AS name,COALESCE(A.address,B.address,'') AS address,B.phone,A.publicid
FROM $mod->OnceTable O
JOIN $mod->BookerTable B ON O.booker_id=B.booker_id
LEFT JOIN $mod->AuthTable A ON B.auth_id=A.id
WHERE O.bkg_id
EOS;
		if (is_array($reqid)) {
			$sql .= ' IN ('.str_repeat('?,',count($reqid)-1).'?)';
			$args = $reqid;
		} else {
			$sql .= '=?';
			$args = [$reqid];
		}

		$utils = new Utils();
		return $utils->PlainGet($mod,$sql,$args,'assoc');
	}

	/**
	ApproveReq:
	If possible, record request as approved and do consequent stuff like notify the user.
	Can process intermingled deletion(s) and/or change(s)
	@mod: reference to current Booker module
	@reqid: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the approval-message 'template'
	Returns: 2-member array,
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function ApproveReq(&$mod, $reqid, $custommsg)
	{
		$rows = self::GetReqData($mod,$reqid);
		if ($rows) {
			$db = $mod->dbHandle;
			$utils = new Utils();
			$sfuncs = new Schedule();
			//cluster the requests by id, for specific processing
			krsort($rows,SORT_NUMERIC); //reverse, so groups-first
			$m = -900; //unmatchable
			$collect = [];
			foreach ($rows as $bkg_id=>&$one) {
				switch ($one['status']) {
				 case \Booker::STATDEL:
				 case \Booker::STATCHG: //TODO setup replacement
//CHECKME func('feepaid','statpay' etc) hence credit booker
					$sql = 'DELETE FROM '.$mod->OnceTable.' WHERE bkg_id=?';
//TODO $utils->SafeExec()
					$db->Execute($sql,[$bkg_id]);
					break;
				 case \Booker::STATCANCEL:
				 case \Booker::STATBIG:
				 case \Booker::STATNA:
				 case \Booker::STATDUP:
				 case \Booker::STATGONE:
//				 case \Booker::STATERR: retry this
//CHECKME func('feepaid','statpay' etc) hence credit booker
					break;
//				 case \Booker::STATTELL:
//				 case \Booker::STATASK:
				 case \Booker::STATOK:
					break;
				 default:
					if ($one['item_id'] != $m) {
						if ($collect) {
							if ($m < \Booker::MINGRPID) {
								$res = $sfuncs->ScheduleResource($mod,$utils,$m,$collect);
							} else {
								$res = $sfuncs->ScheduleGroup($mod,$utils,$m,$collect);
							}
							$collect = [];
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
					$res = $sfuncs->ScheduleResource($mod,$utils,$m,$collect);
				} else {
					$res = $sfuncs->ScheduleGroup($mod,$utils,$m,$collect);
				}
			}
			if ($res) { //TODO handle collection members
				//record new status etc in OnceTable
				$sql = [];
				$args = [];
				$sqlbase = 'UPDATE '.$mod->OnceTable.' SET ';
				foreach ($rows as $bkg_id=>$one) {
					$sql1 = $sqlbase;
					$args1 = [];
					if (isset($one['approved'])) {
						$sql1 .= 'item_id=?,subgrpcount=?,approved=?,';
						$args1[] = $one['item_id']; //downstream may have changed item_id (for a 1-member group booking)
						$args1[] = $one['subgrpcount']; //ditto for missing subgrpcount
						$args1[] = $one['approved'];
					}
//CHECKME 'statpay' field unchanged? maybe some credit due now
					$sql1 .= 'status=? WHERE bkg_id=?';
					$sql[] = $sql1;
					$args1[] = $one['status'];
					$args1[] = $bkg_id;
					$args[] = $args1;
				}
				$utils->SafeExec($sql,$args);
				if ($mod->havenotifier) {
					//notify lodger
					$funcs = new Messager();
					$sndr = new \Notifier\MessageSender();
					$propstore = [];
					$msgs = [];
					foreach ($rows as $one) {
						$item_id = $one['item_id'];
						if (!isset($propstore[$item_id])) {
							$propstore[$item_id] = $utils->GetItemProperties($mod,$item_id,
								['item_id','name','membersname','smspattern','smsprefix']);
							$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
						}
						$idata = $propstore[$item_id];
						list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATOK,$custommsg,$sndr);
						if (!$res)
							$msgs[] = $msg1;
					}
					if ($msgs) {
						return [FALSE,implode('<br />',array_unique($msgs,SORT_STRING))];
					}
					return [TRUE,''];
				} else {
					return [TRUE,$mod->Lang('tell_booker')];
				}
			} else {
				return [FALSE,$mod->Lang('err_na')];
			}
		} else
			return [FALSE,$mod->Lang('err_data')];
	}

	/**
	RejectReq:
	@mod: reference to current Booker module
	@reqid: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the rejection-message 'template'
	Returns: 2-member array,
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function RejectReq(&$mod, $reqid, $custommsg)
	{
		$rows = self::GetReqData($mod,$reqid);
		if ($rows) {
			$sql = [];
			$args = [];
			$sql1 = 'UPDATE '.$mod->OnceTable.' SET status='.\Booker::STATCANCEL.' WHERE bkg_id=?';
			foreach ($rows as $bkg_id=>$one) {
//CHECKME func('feepaid','statpay' etc) hence credit booker
//then 'statpay' field to STATCREDITED
				$sql[] = $sql1;
				$args[] = [$bkg_id];
			}
			$utils = new Utils();
			$utils->SafeExec($sql,$args);

			if ($mod->havenotifier) {
				//notify lodgers
				$funcs = new Messager();
				$sndr = new \Notifier\MessageSender();
				$propstore = [];
				$msgs = [];
				foreach ($rows as $bkg_id=>$one) {
					$item_id = $one['item_id'];
					if (!isset($propstore[$item_id])) {
						$propstore[$item_id] = $utils->GetItemProperties($mod,$item_id,
							['item_id','name','membersname','smspattern','smsprefix']);
						$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
					}
					$idata = $propstore[$item_id];
					list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATCANCEL,$custommsg,$sndr);
					if (!$res)
						$msgs[] = $msg1;
				}
				if ($msgs) {
					return [FALSE,implode('<br />',array_unique($msgs,SORT_STRING))];
				}
				return [TRUE,''];
			} else {
				return [TRUE,$mod->Lang('tell_booker')];
			}
		} else
			return [FALSE,$mod->Lang('err_data')];
	}

	/**
	AskReq:
	@mod: reference to current Booker module
	@reqid: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the ask-message 'template'
	Returns: 2-member array,
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function AskReq(&$mod, $reqid, $custommsg)
	{
		$rows = self::GetReqData($mod,$reqid);
		if ($rows) {
			$sql = [];
			$args = [];
			$sql1 = 'UPDATE '.$mod->OnceTable.' SET status='.\Booker::STATASK.' WHERE bkg_id=?';
			foreach ($rows as $bkg_id=>$one) {
				$sql[] = $sql1;
				$args[] = [$bkg_id];
			}
			$utils = new Utils();
			$utils->SafeExec($sql,$args);

			if ($mod->havenotifier) {
				//notify lodgers
				$funcs = new Messager();
				$sndr = new \Notifier\MessageSender();
				$propstore = [];
				$msgs = [];
				foreach ($rows as $bkg_id=>$one) {
					$item_id = $one['item_id'];
					if (!isset($propstore[$item_id])) {
						$propstore[$item_id] = $utils->GetItemProperties($mod,$item_id,
							['item_id','name','membersname','smspattern','smsprefix']);
						$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
					}
					$idata = $propstore[$item_id];
					list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATASK,$custommsg,$sndr);
					if (!$res)
						$msgs[] = $msg1;
				}
				if ($msgs) {
					return [FALSE,implode('<br />',array_unique($msgs,SORT_STRING))];
				}
				return [TRUE,''];
			} else {
				return [TRUE,$mod->Lang('tell_booker')];
			}
		} else
			return [FALSE,$mod->Lang('err_data')];
	}

	/**
	DeleteReq:
	@mod: reference to current Booker module
	@reqid: request identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the delete-message 'template'
	Returns: 2-member array,
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function DeleteReq(&$mod, $reqid, $custommsg)
	{
		$rows = self::GetReqData($mod,$reqid);
		if ($rows) {
			$sql = [];
			$args = [];
			$sql1 = 'DELETE FROM '.$mod->OnceTable.' WHERE bkg_id=?';
			$sql2 = 'UPDATE '.$mod->OnceTable.' SET status='.\Booker::STATGONE.' WHERE bkg_id=?';
			foreach ($rows as $bkg_id=>$one) {
				if (1) { //TODO $one['status'] == ??
					$sql[] = $sql1;
				} else {
					$sql[] = $sql2;
				}
				$args[] = [$bkg_id];
			}
			$utils = new Utils();
			$utils->SafeExec($sql,$args);

			if ($mod->havenotifier) {
				//notify lodgers
				$funcs = new Messager();
				$sndr = new \Notifier\MessageSender();
				$propstore = [];
				$msgs = [];
				foreach ($rows as $bkg_id=>$one) {
					if ($one['status'] !== \Booker::STATOK) { //TODO others too
						//notify lodger
						$item_id = $one['item_id'];
						if (!isset($propstore[$item_id])) {
							$propstore[$item_id] = $utils->GetItemProperties($mod,$item_id,
								['item_id','name','membersname','smspattern','smsprefix']);
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
					return [FALSE,implode('<br />',$msgs)];
				}
				return [TRUE,''];
			} else {
				return [TRUE,$mod->Lang('tell_booker')];
			}
		} else
			return [FALSE,$mod->Lang('err_data')];
	}

	/**
	FinishReq:
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@params: reference to parameters array, including data for the request
	@success: boolean indicating whether prior processing has been successful
	Returns: 2-member array,
	 [0] boolean indicating success
	 [1] success- or error-message or ''
	*/
	public function FinishReq(&$mod, &$utils, &$params, $success)
	{
		$cache = Cache::GetCache($mod);
		$cart = $utils->RetrieveCart($cache,$params);
		if ($success) { //successful to now
			if (!$cart || !($pending = $cart->getItems())) {
				return [FALSE,$mod->Lang('err_data')];
			}
			$key = '';
			$pfuncs = new Payment();
			$sfuncs = new Schedule();
			$ufuncs = new Userops($mod);
			if ($mod->havenotifier) {
				$mfuncs = new Messager();
				$sndr = new \Notifier\MessageSender(); //CHECKME relevance
//				$propstore = array();
				$msg = [];
				$err = [];
			} else {
				$mfuncs = FALSE;
			}
			$minpay = $mod->GetPreference('minpay');
			$bucket = $params['amount']; //running payment total, starts at nominal amount paid (ignoring any adjustment(s))
			foreach ($pending as $item) {
				$data = $item->getPackage();
				$data->request = (array)$data->request; //from self::CartReq()
				$reqdata = &$data->request;
				$item_id = $reqdata['item_id'];
				$bookerid = $reqdata['booker_id']; //maybe not same for whole batch
				$poster = $ufuncs->HasRight($mod,$bookerid,'postpay');
				$is_new = ($reqdata['status'] === \Booker::STATNEW);
				if (!$is_new) {
					$reqdata['bkg_id'] = $params['bkg_id']; //TODO
				}

				$itempay = $cart->getItemPrice($item);
				if ($itempay > $minpay || ($minpay > 0.0 && $minpay == $itempay)) {
					$bucket -= $itempay;
					if ($bucket >= 0.0) {
						$actual = $itempay;
					} else {
						$actual = -$bucket;
						$bucket = 0.0;
					}
					//calc TODO tax etc
					$reqdata['feepaid'] = $actual;
					$reqdata['statpay'] = $pfuncs->PayUpdate($mod,$bookerid,$poster,$itempay,$actual);
				} else {
					$actual = 0.0;
					$reqdata['statpay'] = \Booker::STATFREE;
				}
				if (!empty($params['transaction'])) {
					$reqdata['gatetransaction'] = $params['transaction'];
				}
/*				if (!empty($params['gatedata'])) {
					$reqdata['gatedata'] = $params['gatedata'];
				}
*/
				if (!$this->SaveOnce($mod,$utils,$reqdata,$is_new)) {
					$actual += $bucket; //revert
					if ($minpay < $actual || ($minpay > 0.0 && $minpay == $actual)) {
						$pfuncs->AddCredit($mod,$bookerid,$actual);
					}
/*					if ($reqdata['statpay'] != \Booker::STATFREE) {
						$reqdata['statpay'] = \Booker::STATCREDITED; //probably no use
					}
*/
					$cart->clear();
					$key = 'err_system';
					break;
				}

				if ($ufuncs->HasRight($mod,$bookerid,'record')) { //booker can record directly
					$save = $reqdata;
					if ($item_id < \Booker::MINGRPID) {
						$recorded = $sfuncs->ScheduleResource($mod,$utils,$item_id,$reqdata);
					} else {
						$recorded = $sfuncs->ScheduleGroup($mod,$utils,$item_id,$reqdata);
					}
					if ($recorded) {
						if ($reqdata != $save) {
							$reqdata['status'] = \Booker::STATSELFREC;
							$this->SaveOnce($mod,$utils,$reqdata,FALSE);	//update OnceTable record
						}
						//item-name(s) for messages
						if ($reqdata['subgrpcount'] > 1) {
							$sql = 'SELECT item_id FROM '.$mod->DispTable.' WHERE bkg_id='.$reqdata['bkg_id'];
							$items = $mod->dbHandle->GetCol($sql);
							$reqdata['what'] = $utils->GetNamedItems($mod,$items);
						} else {
							$reqdata['what'] = $utils->GetItemNameForID($mod,$reqdata['item_id']);
						}
					} else {
						$actual += $bucket; //revert
						if ($minpay < $actual || ($minpay > 0.0 && $minpay == $actual)) {
							$pfuncs->AddCredit($mod,$bookerid,$actual);
						}
						$reqdata['feepaid'] = 0.0;
						$reqdata['statpay'] = \Booker::STATNOTPAID;
						$reqdata['status'] = \Booker::STATNA;
						$this->SaveOnce($mod,$utils,$reqdata,FALSE);
						$cart->clear();
						$key = 'err_na';
						break;
					}
				} else {
					$recorded = FALSE;
				}
				if ($mfuncs) {
/*					if (!isset($propstore[$item_id])) {
						$propstore[$item_id] = $utils->GetItemProperties($mod,$item_id,
						array('item_id','name','approver','approvercontact','approvertell','membersname','smspattern','smsprefix'));
					}
					$idata = $propstore[$item_id];
*/
					$status = ($recorded) ? Messager::MSGRECORD : Messager::MSGSUBMIT;
					list($res,$msg1) = $mfuncs->StatusMessage($mod,$utils,(array)$data->itemdata,$reqdata,$status,'',$sndr);
					if ($res) {
						$payable = $itempay - $actual;
						if ($payable > $minpay || ($minpay > 0.0 && $minpay == $payable)) {
							$f = $pfuncs->AmountFormat($mod,$utils,$item_id,$payable);
							$msg1 .= ' '.$mod->Lang('booking_feedback3',$f);
						}
						$msg[] = $msg1;
					} else {
						$err[] = $msg1;
					}
				}
				$cart->removeItem($item->id);
			} //end cartitems loop

			$utils->SaveCart($cart,$cache,$params);
			$minpay = $mod->GetPreference('minpay');
			if ($bucket > $minpay || ($minpay > 0 && $minpay == $bucket)) {
				//TODO $bookerid if not already set
				$pfuncs->AddCredit($mod,$bookerid,$bucket);
			}
		} elseif (isset($params['cancel'])) {
			if ($cart) {
				$cart->clear();
				$utils->SaveCart($cart,$cache,$params);
			}
			$key = 'email_cancelled_title'; //lazy
		} else {
			//just continue the 'failed' status
			$key = 'error';
		}
		if (!$key) {
			if ($err) {//comm error
				return [FALSE,implode('<br />',array_unique($err))];
			}
			if ($msg) {
				return [TRUE,implode('<br />',array_unique($msg,SORT_STRING))];
			}
			return [TRUE,''];
		}
		return [FALSE,$mod->Lang($key)];
	}

	/**
	SaveOnce:
	Upsert OnceTable (NOT DispTable) to reflect relevant contents of @params
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@params: reference to parameters array, including new data for the request
	@is_new: boolean whether to insert or update, either could be by user or admin
	Returns: boolean indicating successful completion
	*/
	public function SaveOnce(&$mod, &$utils, &$params, $is_new)
	{
/* $params
'booker_id' => int
'comment' => string
'contact' => string
'fee' => int/float
'feepaid' => int/float
'item_id' => int
'lodged' => int
'name' => str (maybe publicid)
'statpay' => int
'slotlen' => int
'slotstart' => int
'status' => int
'subgrpcount' => int
'statpay' => int
'gatetransaction' => string maybe
OR
'bkg_id' => string
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
		//table fields unused here 'feepaid' 'gatetransaction 'gatedata'
 		//date/time $params[] have been verified before calling here
		if (!empty($params['conformuser'])) {
			$funcs = new Userops($mod);
			$funcs->UpdateUser($mod,$params); //record any booker-data change(s)
		}

		$db = $mod->dbHandle;
		if ($is_new) {
			$bid = $db->GenID($mod->OnceTable.'_seq');
			$params['bkg_id'] = $bid;
			$bookerid = $params['booker_id'];
			$idata = $utils->GetItemProperties($mod,$params['item_id'],'timezone');
			$args = [
				'bkg_id'=>$bid,
				'booker_id'=>$bookerid,
				'item_id'=>$params['item_id'],
			];
			//$params[] key to table-field translates
			foreach ([
			 'subgrpcount'=>TRUE,
			 'lodged'=>TRUE,
			 'approved'=>TRUE,
			 'removed'=>TRUE,
			 'slotstart'=>TRUE,
			 'slotlen'=>TRUE,
			 'comment'=>TRUE,
			 'fee'=>TRUE,
			 'feepaid'=>TRUE,
			 'requesttype'=>'status', //before 'status'! CHECKME relevance
			 'status'=>TRUE,
			 'statpay'=>TRUE,
			 'gatetransaction'=>TRUE,
//			 'gatedata'=>TRUE,
			] as $k=>$field) {
				if (!empty($params[$k])) {
					switch ($k) {
					 case 'subgrpcount':
					 case 'lodged':
					 case 'approved':
					 case 'removed':
					 case 'slotstart':
					 case 'slotlen':
					 case 'status':
					 case 'statpay': //CHECKME $pfuncs->GetPayStatus($mod,$bookerid,$poster,$itempay,$actual);
						$args[$k] = (int)$params[$k];
						break;
					 case 'requesttype':
						$args[$field] = (int)$params[$k]; //TODO func hence Booker::STATCHG etc
					 	break;
					 case 'fee':
					 case 'feepaid':
						$args[$k] = (float)$params[$k];
					 	break;
					 default:
					 	if ($field === TRUE) $field = $k;
						$args[$field] = $params[$k];
					}
				} else {
					switch ($k) {
					 case 'subgrpcount':
						$args[$k] = 1;
						break;
					 case 'lodged':
						$args[$k] = $utils->GetZoneTime($idata['timezone']);
						break;
					 case 'requesttype':
						$args[$field] = \Booker::STATNEW;
						break;
					}
				}
			}

			$fillers = str_repeat('?,',count($args)-1);
			$sql = 'INSERT INTO '.$mod->OnceTable.' ('.
				implode(',',array_keys($args)).') VALUES ('.$fillers.'?)';
//			return $utils->SafeExec($sql,$args);
//			$db->Execute($sql,$args);
		} else { //update
			$args = [];
			foreach ([
			 'booker_id'=>TRUE,
			 'item_id'=>TRUE,
			 'subgrpcount'=>TRUE,
			 'approved'=>TRUE,
			 'removed'=>TRUE,
			 'slotstart'=>TRUE,
			 'slotlen'=>TRUE,
			 'comment'=>TRUE,
			 'fee'=>TRUE, //TODO upstream - func(resource(s),times,user)
			 'feepaid'=>TRUE,
			 'requesttype'=>'status',
			 'status'=>TRUE,
			 'statpay'=>TRUE,
			 'gatetransaction'=>TRUE,
//			 'gatedata'=>TRUE,
			] as $k=>$field) {
				if (!empty($params[$k])) {
					switch ($k) {
					 case 'requesttype':
						$args[$field] = (int)$params[$k]; //Booker::STATCHG etc
					 	break;
					 default:
					 	if ($field === TRUE) $field = $k;
						$args[$field] = $params[$k];
					}
				}
			}
			$fillers = implode('=?,',array_keys($args));
			$sql = 'UPDATE '.$mod->OnceTable.' SET '.$fillers.'=? WHERE bkg_id=?';
			$args[] = (int)$params['bkg_id'];
//			return $utils->SafeExec($sql,$args);
//			$db->Execute($sql,$args);
//			return ($db->Affected_Rows() > 0);
		}
		$db->Execute($sql,$args);
//		return ($db->Affected_Rows() > 0); //racy? some sort of async problem?
		$res = ($db->Affected_Rows() > 0);
		return $res;
	}

	/**
	CartReq:
	Add request-item to booking cart
	@mod: reference to current Booker module-object
	@utils: reference to Utils-class object
	@params: reference to parameters array, including data for the request
	@idata: array of data about the resource being booked
	@cart: cart-object to which the request will be added
	Returns: 2-member array,
	 [0] boolean indicating success
	 [1] error-message or ''
	*/
	public function CartReq(&$mod, &$utils, &$params, $idata, $cart)
	{
		$item_id = (int)$params['item_id'];
		$item = new Cart\BookingCartItem($idata['name'],$item_id,$params['fee'],$idata['taxrate']); //$item_id will be the item 'type'
		$data = $item->getPackage();

		$ob = \cms_utils::get_module('FrontEndUsers');
		if ($ob) {
			$data->uid = $ob->LoggedInID();
			unset($ob);
		} else {
			$data->uid = FALSE;
		}

		//TODO get real maxlen from table-field size
		$data->maxlen = 0; //max comment length or 0 for unlimited

		if ($params['bookertype'] == 1) {
			//registered user
			$name = trim($params['account']);
			if ($params['contactnew']) {
				$t = trim($params['contactnew']);
			} else {
				$funcs = new Userops($mod);
				$row = $funcs->GetContact($mod,$params['booker_id']); //get current contact for account
				if ($row) {
					$t = ($row['address']) ? $row['address'] : $row['phone'];
				} else {
					$t = $mod->Lang('err_data');
				}
			}
		} else {
			$name = trim($params['name']);
			if (isset($params['contact'])) {
				$t = trim($params['contact']);
			} else {
				$t = $mod->Lang('err_data');
			}
		}
		$contact = $t;

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
		$minpay = $mod->GetPreference('minpay');
		if ($fee == 0.0) {
			$spay = \Booker::STATFREE;
		} elseif ($minpay > 0.0001 && $fee < $minpay) {
			$fee = 0.0;
			$spay = \Booker::STATFREE;
		} else {
			$spay = \Booker::STATPAYABLE;
		}
		$stat = (!empty($params['requesttype'])) ? (int)$params['requesttype'] : \Booker::STATNEW; //TODO status method

		//populate request data for later processing
		$data->request = [
		 'booker_id'=>(int)$params['booker_id'],
		 'name'=>$name,
		 'contact'=>$contact,
		 'item_id'=>$item_id,
		 'subgrpcount'=>$quantity,
		 'slotstart'=>$bs,
		 'slotlen'=>$be - $bs,
		 'lodged'=>$now,
		 'comment'=>trim($params['comment']),
		 'fee'=>$fee * $quantity,
		 'feepaid'=>0.0,
		 'status'=>$stat,
		 'statpay'=>$spay
		];
		$data->itemdata = $idata;

		$cart->addItem($item,$quantity);
		return [TRUE,''];
	}
	
	/**
	ItemRequestCount:
	Get the no. of pending requests to book the item represented by @item_id
	during part or all of @bs..@be inclusive
	@mod reference to current Booker module-object
	@utils: reference to Utils-class object
	@item_id: resource or group identifier
	@bs: UTC timestamp for start of interval
	@be: ditto for end (NOT 1-past-end)
	Returns: count
	*/
	public function ItemRequestCount(&$mod, &$utils, $item_id, $bs, $be)
	{
		$t = \Booker::STATMAXREQ;
		$s = \Booker::STATMAXOK;
		$sql = <<<EOS
SELECT 1 FROM $mod->OnceTable
WHERE item_id=? AND (status<=$t OR status>$s) AND slotstart<=? AND (slotstart+slotlen)>=? 
EOS;
		$data = $utils->SafeGet($sql,[$item_id,$be,$bs],'col');
		return count($data);
	}
}
