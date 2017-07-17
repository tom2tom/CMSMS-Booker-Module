<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: BookingChange - functions for modifying requests and bookings
# See also: Bookingops class
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class BookingChange
{
	private function PayStatus($f, $p, $minpay)
	{
		if ($f < $minpay) {
			return \Booker::STATFREE;
		}
		if ($f <= $p) {
			return \Booker::STATPAID;
		}
		if ($p > 0.0) {
			return \Booker::STATPARTPAID;
		}
		return \Booker::STATNOTPAID;
	}

	/**
	CancelBkg:
	To the extent possible, cancel (onetime) booking of @item_id, as detailed in @reqdata
	Any resultant excess payment is assigned to a credit
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@item_id: resource or group identifier
	@reqdata: some/all data from a OnceTable row or constructed-equivalent
	@record: boolean whether to record the change immediately, or else save as a request
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = '' or error message
	*/
	public function CancelBkg(&$mod, &$utils, $item_id, $reqdata, $record)
	{
		$bkg_id = (int)$reqdata['bkg_id'];
		$data1 = $mod->dbHandle->GetRow(
'SELECT * FROM '.$mod->OnceTable.' WHERE bkg_id=?',
			[$bkg_id]);
		if ($data1) {
			$pfuncs = new Payment();
			$bs = $data1['slotstart'];
			$be = $bs + $data1['slotlen'];
			$st = $reqdata['slotstart'];
			$nd = $st + $reqdata['slotlen'];
			if ($st <= $bs && $nd > $bs) {
				if ($nd < $be) {
					//some/all finish earlier
					$ns = $bs;
					$nl = $nd - $bs - 1;
					//determine how much to be deducted (per-item, ignoring tax)
					$A = $pfuncs->UsageFee($mod,$utils,$item_id,$data1['booker_id'],$ns,$ns+$nlk);
					$B = 0.0;
					$kill = FALSE;
				} else { //$nd >= $be
					//remove some/all
//					$cut = $pfuncs->UsageFee($mod,$utils,$item_id,$data1['booker_id'],$bs,$be);
					$kill = TRUE;
				}
				$split = FALSE;
			} elseif ($nd >= $be && $st < $be) {
				//some/all start later
				$ns = $st;
				$nl = $be - $st;
				$A = $pfuncs->UsageFee($mod,$utils,$item_id,$data1['booker_id'],$ns,$ns+$nl);
				$B = 0.0;
				$kill = FALSE;
				$split = FALSE;
			} elseif ($st > $bs && $nd < $be) {
				//split, finish earlier plus new start later
				$ns = $bs;
				$nl = $st - $bs - 1;
				$A = $pfuncs->UsageFee($mod,$utils,$item_id,$data1['booker_id'],$ns,$ns+$nl);
				$ns2 = $nd+1;
				$nl2 = $be - $ns2;
				$B = $pfuncs->UsageFee($mod,$utils,$item_id,$data1['booker_id'],$ns2,$ns2+$nl2);
				$split = TRUE;
			}

			$funcs = new Requestops();
			$minpay = $mod->GetPreference('minpay');
			$nold = (int)$data1['subgrpcount'];
			$ndel = $reqdata['subgrpcount'];
			$nstet = $nold - $ndel;

			if ($split) {
				if ($record) {
					if ($nstet <= 0) { //change applies to entire group-booking
						//1st sub-block = current-booking truncated
						$f = $A * $nold;
						$p = max($f,$data1['feepaid']);
						$local = [
							'bkg_id'=>$bkg_id,
							'slotstart'=>$ns,
							'slotlen'=>$nl,
							'fee'=>$f,
							'feepaid'=>$p];
						$funcs->SaveOnce($mod,$utils,$local,FALSE);
						//2nd sub-block = new
						$t = ($reqdata['comment']) ? $reqdata['comment'] : $data1['comment'];
						$f = $B * $nold;
						$p2 = max($f,$data1['feepaid']-$p);
						if ($p2 < 0.0001) {
							$p2 = 0.0;
						}
						$q = $this->PayStatus($f,$p2,$minpay);
						$local = [
							'booker_id'=>$data1['booker_id'],
							'item_id'=>$item_id,
							'subgrpcount'=>$nold,
							'lodged'=>time(),
							'slotstart'=>$ns2,
							'slotlen'=>$nl2,
							'comment'=>$t,
							'fee'=>$f,
							'feepaid'=>$p2,
							'status'=>$data1['status'],
							'statpay'=>$q,
							'gatetransaction'=>$data1['gatetransaction']];
						$funcs->SaveOnce($mod,$utils,$local,TRUE);
						//any leftover payment to extra credit
						$X = $data1['feepaid'] - $p - $p2;
					} else { //not the whole group
						$f = $data1['fee'] * $nstet / $nold;
						$p = max($f,$data1['feepaid']);
						$local = [
							'bkg_id'=>$bkg_id,
							'subgrpcount'=>$nstet,
							'fee'=>$f,
							'feepaid'=>$p];
						$funcs->SaveOnce($mod,$utils,$local,FALSE);
						$f = $A * $ndel;
						$p2 = max($f,$data1['feepaid']-$p);
						if ($p2 < 0.0001) {
							$p2 = 0.0;
						}
						$q = $this->PayStatus($f,$p2,$minpay);
						$at = time();
						$local = [
							'booker_id'=>$data1['booker_id'],
							'item_id'=>$item_id,
							'subgrpcount'=>$ndel,
							'lodged'=>$at,
							'slotstart'=>$ns2,
							'slotlen'=>$nl2,
							'comment'=>$t,
							'fee'=>$f,
							'feepaid'=>$p2,
							'status'=>$data1['status'],
							'statpay'=>$q,
							'gatetransaction'=>$data1['gatetransaction']];
						$funcs->SaveOnce($mod,$utils,$local,TRUE);
						$f = $B * $ndel;
						$p3 = max($f,$data1['feepaid']-$p-$p2);
						if ($p3 < 0.0001) {
							$p3 = 0.0;
						}
						$q = $this->PayStatus($f,$p3,$minpay);
						$local = [
							'booker_id'=>$data1['booker_id'],
							'item_id'=>$item_id,
							'subgrpcount'=>$ndel,
							'lodged'=>$at,
							'slotstart'=>$ns2,
							'slotlen'=>$nl2,
							'comment'=>$t,
							'fee'=>$f,
							'feepaid'=>$p3,
							'status'=>$data1['status'],
							'statpay'=>$q,
							'gatetransaction'=>$data1['gatetransaction']];
						$funcs->SaveOnce($mod,$utils,$local,TRUE);
						$X = $data1['feepaid'] - $p - $p2 - $p3;
					}
				} else { //notice-only
					$reqdata['lodged'] = time();
					$reqdata['slotstart'] = $st;
					$reqdata['slotlen'] = $be - $st;
					$reqdata['status'] = \Booker::STATDEL;
					$funcs->SaveOnce($mod,$utils,$reqdata,TRUE);
					$reqdata['slotstart'] = $ns2;
					$reqdata['slotlen'] = $nl2;
					$reqdata['status'] = \Booker::STATNEW;
					$funcs->SaveOnce($mod,$utils,$reqdata,TRUE);
				}
			} elseif ($kill) {
				if ($record) {
					if ($nstet <= 0) { //whole group
						$mod->dbHandle->Execute(
'DELETE FROM '.$mod->OnceTable.' WHERE bkg_id=?',
						[$bkg_id]);
						$X = $data1['feepaid'];
					} else {
						$f = $data1['fee'] * $ndel / $nold;
						$p = max($f,$data1['feepaid']);
						$q = $this->PayStatus ($f,$p,$minpay);
						$local = [
						'subgrpcount'=>$nstet,
						'fee'=>$f,
						'feepaid'=>$p,
						'statpay'=>$q,
						'bkg_id'=>$bkg_id];
						$funcs->SaveOnce($mod,$utils,$local,FALSE);
						$X = $data1['feepaid'] - $p;
					}
				} else {
					$reqdata['lodged'] = time();
					$reqdata['status'] = \Booker::STATDEL;
					$funcs->SaveOnce($mod,$utils,$reqdata,TRUE);
				}
			} else { //truncate
				if ($record) {
					if ($nstet <= 0) { //whole group
						$f = $A * $nold;
						$p = max($f,$data1['feepaid']);
						$q = $this->PayStatus ($f,$p,$minpay);
						$local = [
							'slotstart'=>$ns,
							'slotlen'=>$nl,
							'fee'=>$f,
							'feepaid'=>$p,
							'statpay'=>$q,
							'bkg_id'=>$bkg_id];
						$funcs->SaveOnce($mod,$utils,$local,FALSE);
						$X = $data1['feepaid'] - $p;
					} else {
						$f = $data1['fee'] * $nstet / $nold;
						$p = max($f,$data1['feepaid']);
						$q = $this->PayStatus ($f,$p,$minpay);
						$local = [
							'subgrpcount'=>$nstet,
							'fee'=>$f,
							'feepaid'=>$p,
							'statpay'=>$q,
							'bkg_id'=>$bkg_id];
						$funcs->SaveOnce($mod,$utils,$local,FALSE);
						$t = ($reqdata['comment']) ? $reqdata['comment'] : $data1['comment'];
						$f = $A * $ndel;
						$p2 = max($f,$data1['feepaid']-$p);
						if ($p2 < 0.0) {
							$p2 = 0.0001;
						}
						$q = $this->PayStatus ($f,$p,$minpay);
						$local = [
							'booker_id'=>$data1['booker_id'],
							'item_id'=>$item_id,
							'subgrpcount'=>$ndel,
							'lodged'=>time(),
							'slotstart'=>$ns,
							'slotlen'=>$nl,
							'comment'=>$t,
							'fee'=>$f,
							'feepaid'=>$p2,
							'status'=>$data1['status'],
							'statpay'=>$q,
							'gatetransaction'=>$data1['gatetransaction']];
						$funcs->SaveOnce($mod,$utils,$local,TRUE);
						$X = $data1['feepaid'] - $p - $p2;
					}
				} else {
					$reqdata['lodged'] = time();
					$reqdata['status'] = \Booker::STATDEL;
					$funcs->SaveOnce($mod,$utils,$reqdata,TRUE);
				}
			}

			if ($record && ($X > $minpay)) {
				$pfuncs->AddCredit($mod,$data1['booker_id'],$X);
			}

			if ($mod->havenotifier) {
				//setup messages destination
				$funcs = new Userops($mod);
				$res = $funcs->GetContact($mod,$reqdata['booker_id']);
				if ($res) {
					$reqdata['name'] = $utils->GetUserNameForID($mod,$reqdata['booker_id']);
					$reqdata += $res;
					$idata = $utils->GetItemProperties($mod,$item_id,
						['item_id','name','membersname','smspattern','smsprefix','approver','approvertell','approvercontact']);
					$funcs = new Messager();
				} else {
					$funcs = FALSE;
				}
			} else {
				$funcs = FALSE;
			}

			if ($record) {
				//TODO process/remove in likeness-order
				$data2 = $mod->dbHandle->GetArray(
'SELECT data_id,item_id,bulk,displayed FROM '.$mod->DispTable.' WHERE bkg_id=? ORDER BY item_id',
				[$bkg_id]);
				$more = $ndel;
				$items = [];
				foreach ($data2 as $one) {
					if ($more > 0) {
						$more--;
					} else {
						break;
					}
					$items[] = $one['item_id'];
					if ($split) {
						$mod->dbHandle->Execute(
'UPDATE '.$mod->DispTable.' SET slotstart=?,slotlen=?,bulk=1 WHERE data_id=?',
							[$ns,$nl,$one['data_id']]);
						$did = $mod->dbHandle->GenID($mod->DispTable.'_seq');
						$mod->dbHandle->Execute('INSERT INTO '.$mod->DispTable.
'data_id,bkg_id,booker_id,item_id,slotstart,slotlen,bulk,displayed VALUES (?,?,?,?,?,?,?,?)',
							[$did,$bkg_id,$reqdata['booker_id'],$one['item_id'],$ns,$nl,1,$one['bulk']]);
					} elseif ($kill) {
						$mod->dbHandle->Execute(
'DELETE FROM '.$mod->DispTable.' WHERE data_id=?',
							[$one['data_id']]);
					} else {
						$mod->dbHandle->Execute(
'UPDATE '.$mod->DispTable.' SET slotstart=?,slotlen=? WHERE data_id=?',
							[$ns,$nl,$one['data_id']]);
					}
				}
				$items = $utils->GetNamedItems($mod,$items);
				if ($funcs) {
					if ($items) {
						$reqdata['what'] = $items;
					}
					list($res,$msg) = $funcs->StatusMessage($mod,$utils,$idata,$reqdata,\Booker::STATDEL,FALSE);
				} else {
					$msg = '';
				}
				if (!$msg) {
//					usort ($items, array(Display,'cmp_nat'));
					natsort($items); //TODO lazy
					$msg = $mod->Lang('email_delete',implode(', ',$items));
				}
				return [TRUE,$msg];
			} else {
				$t = $utils->GetItemNameForID($item_id);
				if ($funcs) {
					$reqdata['what'] = $t;
					list($res,$msg) = $funcs->StatusMessage($mod,$utils,$idata,$reqdata,\Booker::STATDEL,FALSE);
				} else {
					$msg = '';
				}
				if (!$msg) {
					if ($item_id >= \Booker::MINGRPID) {
						$t = $mod->Lang('countof2',$ndel,$name);
					}
					$msg = $mod->Lang('email_reqdelete',$t);
				}
				return [TRUE,$msg];
			}
		} else {
			return [FALSE,$mod->Lang('err_data')];
		}
	}

	/**
	ModifyBkg:
	Modify as many as possible of (onetime) bookings in @reqdata, for @item_id.
	The status field in each @reqdata member will be updated to indicate what
	precisely has been done
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@item_id: resource or group identifier
	@reqdata: some/all data from a OnceTable row or constructed-equivalent
	@record: boolean whether to record the change immediately, or save as a request
	Returns: boolean indicating complete success
	*/
	public function ModifyBkg(&$mod, &$utils, $item_id, $reqdata, $record)
	{
		return FALSE;
		//TODO support reversion if update fails
	}
}
