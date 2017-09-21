<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Payment - functions for dealing with fee payments
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Payment
{
	//$data1,$data2 are arrays of 3 arrays, respective start-stamps, end-stamps, rules
	//$funcs is reference to Blocks-class object
/*	private function IntersectsTotal($data1, $data2, &$funcs, $calcer) //PHP 5.4+ supports callable typehint
	{
		array_multisort($data1[0],SORT_ASC,SORT_NUMERIC,$data1[1],$data1[2]);
		array_multisort($data2[0],SORT_ASC,SORT_NUMERIC,$data2[1],$data2[2]);
		//determine $data1,$data2 intersects & corresponding rule(s)
		list($starts,$ends,$rules1,$rules2) = $funcs->IntersectBlocks2Ruled(
			$data1[0],$data1[1],$data1[2],$data2[0],$data2[1],$data2[2]);
		$total = 0.0;
		if ($starts) {
			foreach ($starts as $i=>$st) {
				$slen = $ends[$i] - $st;
				$total += $calcer($slen,$rules1[$i],$rules2[$i]);
			}
		}
		return $total;
	}
*/
	//$data is array of 3 arrays, respective start-stamps, end-stamps, rules
/*	private function RulesTotal($data, $calcer) //PHP 5.4+ supports callable typehint
	{
		$total = 0.0;
		foreach ($data[0] as $i=>$st) {
			$slen = $data[1][$i] - $st;
			$total += $calcer($slen,$data[2][$i]);
		}
		return $total;
	}
*/

	//Interpret $descriptor into stamp-block(s) covering $bs..$be, $idata for TimeParms()
/*	private function BlocksforRule(&$mod, $descriptor, $bs, $be, $idata)
	{
		$funcs = new WhenRules($mod);
		if ($funcs->ParseDescriptor($descriptor)) {
			$timeparms = $funcs->TimeParms($idata);
			list($starts,$ends) = $funcs->GetBlocks($bs,$be,$timeparms); //$defaultall FALSE
			if ($starts) {
				return array($starts,$ends);
			}
		}
		return array(FALSE,FALSE);
	}
*/
	/* *
	WhenRuledBlocks:
	This replicates WhenBlocks() except @rules is array(s), and rules-members
	are returned.
	@mod: reference to Booker module object
	@utils: reference to Utils-class object
	@bs: UTC timestamp for start of block
	@be: corresponding end-of-block, NOT 1-past
	@dorules: arrray of indices in @rules to be processed
	@rules: reference to array of data sorted in order of decreasing priority,
	 each member being an array including a member 'feecondition' which is a
	 descriptor recognisable by WhenRuleLexer (or FALSE)
	Returns: 3-member array,
	 [0] = sorted array of block-start timestamps in @bs..@be
	 [1] = array of respective block-end timestamps in @bs..@be
	 [2] = array of respective indices of @rules members TODO multiple if relative-rules found before absolute
	OR if nothing is relevant
	 [0] = FALSE
	 [1] = FALSE
	 [2] = FALSE
	*/
	private function WhenRuledBlocks(&$mod, &$utils, $bs, $be, $dorules, &$rules)
	{
		$funcs = new Blocks();
		$funcs2 = new WhenRules($mod);
		$propstore = [];
		$chk0starts = [$bs];
		$chk1ends = [$be];
		$ret0starts = [];
		$ret1ends = [];
		$userules = [];
		//TODO also support 'except' rules - subtract from blocks previously accepted and relative-rules
		foreach ($dorules as $i) {
			$one = $rules[$i];
			if ($one['feecondition']) { //something to interpret
				if ($funcs2->ParseDescriptor($one['feecondition'])) {
					$item_id = $one['item_id'];
					if (!isset($propstore[$item_id])) {
						$idata = $utils->GetItemProperties($mod,$item_id,
							['slottype','slotcount','timezone','latitude','longitude'],TRUE);
						$propstore[$item_id] = $funcs2->TimeParms($idata);
					}
					$timeparms = $propstore[$item_id];
					$bst = reset($chk0starts);
					$bnd = end($chk1ends);
					list($rule0starts,$rule1ends) = $funcs2->GetBlocks($bst,$bnd,$timeparms); //$defaultall FALSE
					if ($rule0starts) {
						list($rule0starts,$rule1ends) = $funcs->IntersectBlocks($chk0starts,$chk1ends,$rule0starts,$rule1ends);
						if ($rule0starts) {
							foreach ($rule0starts as $j=>$st) {
								$ret0starts[] = $st;
								$ret1ends[] = $rule1ends[$j];
								$userules[] = $i;
							}
							array_multisort($ret0starts,SORT_ASC,SORT_NUMERIC,$ret1ends,$userules);
							//if ($one['relative']) { TODO keep looking for absolute rule
							//eliminate blocks already dealt with from further checks
							list($chk0starts,$chk1ends) = $funcs->DiffBlocks([$bs],[$be],$ret0starts,$ret1ends);
						}
					}
				}
			} else { //no condition, always applies
				foreach ($chk0starts as $j=>$st) {
					$ret0starts[] = $st;
					$ret1ends[] = $chk1ends[$j];
					$userules[] = $i;
				}
				array_multisort($ret0starts,SORT_ASC,SORT_NUMERIC,$ret1ends,$userules);
			}
		}

		$ic = count($ret0starts);
		if ($ic > 0) {
			if ($ic > 1) {
				--$ic;
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ret1ends[$i] >= $ret0starts[$j]-1) {
						if ($userules[$i] == $userules[$j]) { //non-strict array comparison
							$ret0starts[$j] = $ret0starts[$i];
							unset($ret0starts[$i]);
							unset($ret1ends[$i]);
							unset($userules[$i]);
						}
					}
				}
				$ret0starts = array_values($ret0starts);
				$ret1ends = array_values($ret1ends);
				$userules = array_values($userules);
			}
			return [$ret0starts,$ret1ends,$userules];
		}
		return [FALSE,FALSE,FALSE];
	}

	//Interpret $rule's slottype,slotcount parameters
	//into (approximate) corresponding seconds
/*	private function ParseFeeInterval(&$rule)
	{
		//c.f. Utils::GetCurrentSlotlen($bs,$slottype,$slotcount)
		//slotype = 0..5 per Utils::TimeIntervals() i.e. for minute,hour,day,week,month,year
		//or -1 for fixed amount
		switch ($rule['slottype']) {
		 case 0:
			$t = 60;
			break;
		 case 1:
			$t = 3600;
			break;
		 case 2:
			$t = 84600; //ignore badness for daylight-saving transitions
			break;
		 case 3:
			$t = 84600*7; //ignore badness for daylight-saving transitions
			break;
		 case 4:
			$t = 84600*30; //ignore badness for some months
			break;
		 case 5:
			$t = 84600*365; //ignore badness for leap-years
			break;
		 case -1: //fixed payment
			return  -1;
		 default:
			return 0;
		}
		if ($t >= 0) {
			$c = $rule['slotcount'] ? $rule['slotcount'] : 1;
			return $t * $c;
		}
	}
*/

	/**
	AmountFormat:
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@item_id: identifier of item (resource or group) for which rule is sought
	@amount: number as recorded (8.2f)
	Returns: string
	*/
	public function AmountFormat(&$mod, &$utils, $item_id, $amount)
	{
		$idata = $utils->GetItemProperties($mod,$item_id,'paymentiface');
		$t = $idata['paymentiface'];
		if ($t && $t != -1) {
			$imod = \cms_utils::get_module($t);
			$handlerclass = $imod->GetPayer();
			$ifuncs = new $handlerclass($mod, $imod);
			return $ifuncs->PublicFormat($amount*100);
		}
		return $amount;
	}

	/**
	GetFeeSignature:
	Get identifier usable for cross-resource fee-comparisons
	@row: array of fee-data including members: slottype,slotcount,fee,feecondition
	Returns: 32-bit integer
	*/
	public function GetFeeSignature($row)
	{
		$sig = '';
		foreach (['slottype','slotcount','fee','feecondition'] as $k) {
			$sig .= (isset($row[$k]) && $row[$k] !== NULL) ? $row[$k] : 'NULL';
		}
		return crc32($sig);
	}

	/**
	UsageFee:
	Get gross fee for use of @item_id
	@mod: reference to Booker module object
	@utils: reference to Utils-class object
	@item_id: item identifier
	@bookerid: booker identifier
	@bs: UTC timestamp representing start of interval to be processed
	@be: corresponding interval-end, NOT 1-past
	@search: optional boolean, whether to interrogate ancestor-rules, default TRUE
	Returns: 2-member array:
	 [0] float, gross fee payable for use of @item_id by @bookerid over @bs..@be
	 [1] float, current total credit held by @bookerid (so the net fee is the difference between the 2)
	*/
	public function UsageFee(&$mod, &$utils, $item_id, $bookerid, $bs, $be, $search=TRUE)
	{
		if ($search) {
			$all = $utils->GetItemGroups($mod,$item_id);
			array_unshift($all,$item_id); //priority-ordered for checking
			$fillers = implode(',',$all);
			$sql = 'SELECT item_id,slottype,slotcount,fee,feecondition,usercondition,condorder FROM '.$mod->FeeTable.
			' WHERE item_id IN ('.$fillers.') AND active=1 ORDER BY item_id,condorder'; //a bit of downstream sorting might help ...
			$rules = $mod->dbHandle->GetArray($sql); //NB ordered by item_id prob not what we want: $all has that
			if ($rules) {
				usort($rules,function($a, $b) use ($all)
				{
					$ta = $a['item_id'];
					$tb = $b['item_id'];
					if ($ta != $tb) {
						$ka = array_search($ta,$all);
						$kb = array_search($tb,$all);
						if ($ka != $kb) {
							return ($ka-$kb); //should always happen!
						}
					}
					return ($a['condorder'] - $b['condorder']);
				});
			}
		} else {
			$sql = 'SELECT item_id,slottype,slotcount,fee,feecondition,usercondition FROM '.$mod->FeeTable.
			' WHERE item_id=? AND active=1 ORDER BY condorder';
			$rules = $mod->dbHandle->GetArray($sql,[$item_id]);
		}

		$grossfee = 0.0;

		if ($rules) {
			//identify the relevant ones
			$funcs = new Userops($mod);
			$btype = $funcs->GetBaseType($mod,$bookerid);
			$dorules = [];
			foreach($rules as $i=>&$one)
			{
				$t = $one['usercondition'];
				if ($t == FALSE || $t == '*') {
					$dorules[] = $i;
					$one['relative'] = preg_match('/^ *[+-]/',$one['fee']);
				} else {
					$types = explode(',',$t);
					if (in_array($btype,$types)) {
						$dorules[] = $i;
						$one['relative'] = preg_match('/^ *[+-]/',$one['fee']);
					}
				}
			}
			unset($one);

			if ($dorules) {
				list($starts,$ends,$indices) = $this->WhenRuledBlocks($mod,$utils,$bs,$be,$dorules,$rules);
				if ($starts) {
					//TODO support relative-fee-rules
					foreach ($indices as $i=>$p) {
						$one = $rules[$p];
						if ($one['slottype'] >= 0) {
							if (!isset($one['feelen'])) {
								$one['feelen'] = $utils->GetCurrentSlotlen($bs,$one['slottype'],$one['slotcount']);
							}
							$bl = ceil(($ends[$i] - $starts[$i])/$one['feelen']) * $one['feelen']; //whole slot(s)
							$amt = $bl*$one['fee']/$one['feelen'];
							//round in accord with the rule
							$t = strrpos($one['fee'],'.');
							if ($t !== FALSE) {
								$p = strlen($one['fee'])-$t-1;
							} else {
								$p = 0;
							}
							$grossfee += round($amt,$p);
						} else { //fixed payment
							$grossfee = $one['fee'] + 0;
							break;
						}
					}
				}
			}
		}
		return $grossfee;
	}

	/**
	GetPayable:
	Check whether any fee-rule applies to @item_id. This is for summary-views,
	does not determine a specific amount
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@item_id: identifier of item (resource or group) for which rule is sought
	Returns: boolean
	*/
	public function GetPayable(&$mod, &$utils, $item_id)
	{
		$groups = $utils->GetItemGroups($mod,$item_id);
		if ($groups) {
			$sql2 = ' IN (?,'.implode(',',$groups).')';
		} else {
			$sql2 = '=?';
		}
		$sql = 'SELECT 1 FROM '.$mod->FeeTable.' WHERE item_id'.$sql2.
			' AND feecondition IS NOT NULL AND feecondition<>\'\' AND active=1';
		$ruled = $mod->dbHandle->GetOne($sql,[$item_id]);
		return ($ruled != FALSE);
	}

	/**
	GetPayStatus:
	Get relevant statpay enumerator having regard to @bookerid's total credit and
	  @grossdue, @grosspaid, @postpayable. @bookerid's total credit is not changed
	@mod: reference to Booker module object
	@bookerid: booker identifier
	@postpayable: boolean
	@grossdue: amount that should have been paid
	@grosspaid: amount actually paid
	Returns: relevant Booker::STAT* enum
	*/
	public function GetPayStatus(&$mod, $bookerid, $postpayable, $grossdue, $grosspaid)
	{
		$minpay = $mod->GetPreference('minpay');
		if ($grosspaid > $minpay || ($minpay > 0.0 && $minpay == $grosspaid)) {
			$tc = $this->TotalCredit($mod,$bookerid) + $grosspaid - $grossdue;
			if ($tc + $grosspaid >= $grossdue) {
				return \Booker::STATPAID;
			} elseif ($tc + $grosspaid > 0.0) {
				return \Booker::STATPARTPAID;
			} elseif ($postpayable) {
				return \Booker::STATCREDITED; //aka \Booker::STATPAYABLE
			}
			return \Booker::STATNOTPAID;
		} else {
			return \Booker::STATFREE;
		}
	}

	/**
	ChangePayment:
	Update OnceTable or RepeatTable fields: 'fee' and/or 'feepaid', and 'statpay',
	 and the booker's total credit
	@mod: reference to Booker module object
	@bkg_id: booking identifier
	@amount: float to set or adjust, or '--' to represent -current-feepaid i.e. clear it
	@relamt: optional boolean, whether @amount is for a relative-change, default FALSE
	@setfee:  optional boolean, whether to update the relevant 'fee' field, default TRUE
	@$setpaid:  optional boolean, whether to update the relevant 'feepaid' field, default TRUE
	*/
	public function ChangePayment(&$mod, $bkg_id, $amount, $relamt = FALSE, $setfee = TRUE, $setpaid = TRUE)
	{
		if (!($setfee || $setpaid)) {
			return;
		}
		$sql = <<<EOS
SELECT 0 AS rept,O.booker_id,O.fee,O.feepaid,B.type FROM $mod->OnceTable O
JOIN $mod->BookerTable B ON O.booker_id=B.booker_id WHERE O.bkg_id=?
UNION
SELECT 1 AS rept,R.booker_id,R.fee,R.feepaid,B.type FROM $mod->RepeatTable R
JOIN $mod->BookerTable B ON R.booker_id=B.booker_id WHERE R.bkg_id=?
EOS;
		$data = $mod->dbHandle->GetRow($sql,[$bkg_id,$bkg_id]);
		if (!$data) {
			return;
		}
		if ($amount == '--') {
			$amount = -$data['feepaid'];
		}
		$tbl = ($data['rept'] == 0) ? $mod->OnceTable : $mod->RepeatTable;
		if ($setfee) {
			$newfee = ($relamt) ? max($amount + $data['fee'],0.0) : max($amount,0.0);
		} else {
			$newfee = $data['fee'] + 0.0;
		}
		if ($setpaid) {
			$newpaid = ($relamt) ? max($amount + $data['feepaid'],0.0) : max($amount,0.0);
		} else {
			$newpaid = $data['feepaid'] + 0.0;
		}

		$xs = $data['feepaid']-$newpaid;
		if ($newpaid > $newfee) {
			$xs += $newpaid - $newfee;
			$newpaid = $newfee;
		} elseif ($newpaid < $newfee) {
			if ($xs > 0.0) {
				$xo = min($xs,$newfee-$newpaid);
				$newpaid += $xo;
				$xs -= $xo;
			}
		}

		$bookerid = (int)$data['booker_id'];
		if ($xs != 0.0) {
			$this->AddCredit($mod,$bookerid,$xs); //new total credit
		}

		$funcs = new Userops($mod);
		$poster = $funcs->HasRight($mod,$bookerid,'postpay',$data['type']);
		$stat = $this->GetPayStatus($mod,$bookerid,$poster,$newfee,$newpaid); //uses current total credit

		$sql = 'UPDATE '.$tbl.' SET fee=?,feepaid=?,statpay=? WHERE bkg_id=?';
		$mod->dbHandle->Execute($sql,[$newfee,$newpaid,$stat,$bkg_id]);
	}

	/**
	TotalCredit:
	see also Dataops::TotalCredit()
	@mod: reference to Booker module object
	@bookerid: booker identifier
	*/
	public function TotalCredit(&$mod, $bookerid)
	{
		$amount = 0.0;
		$sql = 'SELECT latest FROM '.$mod->CreditTable.
		' WHERE booker_id=? AND status!='.\Booker::CREDITEXPIRED;
		$data = $mod->dbHandle->GetCol($sql,[$bookerid]);
		if ($data) {
			$funcs = new Crypter($mod);
			foreach ($data as $one) {
				$amount += (float)$funcs->uncloak_value($one);
			}
		}
		return $amount;
	}

	/**
	AddCredit:
	@mod: reference to Booker module object
	@bookerid: booker identifier
	@amount: amount of credit to be added
	*/
	public function AddCredit(&$mod, $bookerid, $amount)
	{
		$funcs = new Crypter($mod);
		$sql = 'SELECT pay_id,latest FROM '.$mod->CreditTable.
		' WHERE booker_id=? AND status!='.\Booker::CREDITEXPIRED.' ORDER BY updated DESC';
		$data = $mod->dbHandle->GetArray($sql,[$bookerid]);
		if ($data) {
			$sql = 'UPDATE '.$mod->CreditTable.' SET updated=?,latest=? WHERE pay_id=?';
			$now = (float)$funcs->uncloak_value($data[0]['latest']);
			$val = $funcs->cloak_value($now+$amount,16);
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute($sql,[time(),$val,$data[0]['pay_id']]);
		} elseif ($amount > 0.0) {
			$sql = 'INSERT INTO '.$mod->CreditTable.
			' (pay_id,booker_id,updated,original,latest) VALUES (?,?,?,?,?)';
			$pid = $mod->dbHandle->GenID($mod->CreditTable.'_seq');
			$val = $funcs->cloak_value($amount,16);
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute($sql,[$pid,$bookerid,time(),$val,$val]);
		}
	}

	/**
	UseCredit:
	see also Dataops::UseCredit()
	@mod: reference to Booker module object
	@bookerid: booker identifier
	@amount: amount of credit to be adjusted (pos or neg float)
	*/
	public function UseCredit(&$mod, $bookerid, $amount)
	{
		$sql = 'SELECT pay_id,latest FROM '.$mod->CreditTable.
		' WHERE booker_id=? AND status!='.\Booker::CREDITEXPIRED.' ORDER BY updated';
		$data = $mod->dbHandle->GetArray($sql,[$bookerid]);
		if ($data) {
			if ($amount < 0) {
				$amount = -$amount;
			}
			$funcs = new Crypter($mod);
			$sql1 = 'UPDATE '.$mod->CreditTable.' SET status='.\Booker::CREDITUSED.',latest=? WHERE pay_id=?';
			$sql2 = 'UPDATE '.$mod->CreditTable.' SET latest=? WHERE pay_id=?';
			foreach ($data as $row) {
				$now = (float)$funcs->uncloak_value($row['latest']);
				if ($now > 0.01) {
					$amount -= $now;
					if ($amount >= 0.01) {
						$now = 0.0; //all used
						$sql = $sql1;
					} else { //$amount < 0.0 approx.
						$now = -$amount;
						$sql = $sql2;
					}
					$latest = $funcs->cloak_value($now,16);
					//TODO build arrays, then $utils->SafeExec($sql[],$args[]);
					$mod->dbHandle->Execute($sql,[$latest,$row['pay_id']]);
					if ($amount < 0.01) {
						break;
					}
				}
			}
			return TRUE;
		}
		return FALSE;
	}

	/**
	ExpireCredit:
	see also Dataops::ExpireCredit()
	@mod: reference to Booker module object
	@bookerid: booker identifier, or array of them, or '*'
	@before: UTC timestamp for limit on remaining credit
	*/
	public function ExpireCredit(&$mod, $bookerid, $before)
	{
		if (is_array($bookerid)) {
			$fillers = str_repeat('?,', count($bookerid)-1);
			$sql = 'UPDATE '.$mod->CreditTable.' SET status='.\Booker::CREDITEXPIRED.
			' WHERE booker_id IN('.$fillers.'?) AND status!='.\Booker::CREDITEXPIRED.' AND updated<?';
			$args = $bookerid;
			array_push($args,$before);
		} elseif ($bookerid == '*') {
			$sql = 'UPDATE '.$mod->CreditTable.' SET status='.\Booker::CREDITEXPIRED.
			' WHERE status!='.\Booker::CREDITEXPIRED.' AND updated<?';
			$args = [$before];
		} else {
			$sql = 'UPDATE '.$mod->CreditTable.' SET status='.\Booker::CREDITEXPIRED.
			' WHERE booker_id=? AND status!='.\Booker::CREDITEXPIRED.' AND updated<?';
			$args = [$bookerid,$before];
		}
		//TODO $utils->SafeExec()
		$mod->dbHandle->Execute($sql,$args);
	}
}
