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
		$propstore = array();
		$chk0starts = array($bs);
		$chk1ends = array($be);
		$ret0starts = array();
		$ret1ends = array();
		$userules = array();
		//TODO also support 'except' rules - subtract from blocks previously accepted and relative-rules
		foreach ($dorules as $i) {
			$one = $rules[$i];
			if ($one['feecondition']) { //something to interpret
				if ($funcs2->ParseDescriptor($one['feecondition'])) {
					$item_id = $one['item_id'];
					if (!isset($propstore[$item_id])) {
						$idata = $utils->GetItemProperty($mod,$item_id,array('slottype','slotcount'),TRUE);
						$idata = $idata + $utils->GetItemProperty($mod,$item_id,array('timezone','latitude','longitude'));
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
							list($chk0starts,$chk1ends) = $funcs->DiffBlocks(array($bs),array($be),$ret0starts,$ret1ends);
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
				$ic--;
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
			return array($ret0starts,$ret1ends,$userules);
		}
		return array(FALSE,FALSE,FALSE);
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
	Amounts:
	Get gross fee for use of resource, and current credit against which the fee
	may be offset
	@mod: reference to Booker module object
	@utils: reference to Utils-class object
	@item_id: resource identifier
	@bookerid: booker identifier
	@bs: UTC timestamp representing start of interval to be processed
	@be: correponding 1-past-end
	@search: optional boolean, whether to interrogate ancestor-rules, default TRUE
	Returns: 2-member array:
	 [0] gross fee payable for use of @item_id by @bookerid over @bs..@be
	 [1] current total credit held by @bookerid (so the net fee is the difference between the 2)
	*/
	public function Amounts(&$mod, &$utils, $item_id, $bookerid, $bs, $be, $search=TRUE)
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
			$rules = $mod->dbHandle->GetArray($sql,array($item_id));
		}

		$grossfee = 0.0;

		if ($rules) {
			//identify the relevant ones
			$funcs = new Userops($mod);
			$btype = $funcs->GetBaseType($mod,$bookerid);
			$dorules = array();
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
							$bl = $ends[$i] - $starts[$i];
							$amt = $bl*$one['fee']/$one['feelen'];
							//round in accord with the rule TODO also in accord with whole slot(s)
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

		$creditnow = $this->TotalCredit($mod,$bookerid);
		return array($grossfee,$creditnow);
	}

	/**
	MaybePayable:
	Check whether any fee-rule applies to @item_id. This is for summary-views,
	does not determine a specific amount
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@item_id: identifier of item (resource or group) for which rule is sought
	Returns: boolean
	*/
	public function MaybePayable(&$mod, &$utils, $item_id)
	{
		$groups = $utils->GetItemGroups($mod,$item_id);
		if ($groups) {
			$sql2 = ' IN (?,'.implode(',',$groups).')';
		} else {
			$sql2 = '=?';
		}
		$sql = 'SELECT 1 FROM '.$mod->FeeTable.' WHERE item_id'.$sql2.
			' AND feecondition IS NOT NULL AND feecondition<>\'\' AND active=1';
		$ruled = $mod->dbHandle->GetOne($sql,array($item_id));
		return ($ruled != FALSE);
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
		foreach (array('slottype','slotcount','fee','feecondition') as $k) {
			$sig .= (isset($row[$k]) && $row[$k] !== NULL) ? $row[$k] : 'NULL';
		}
		return crc32($sig);
	}

	/**
	TotalCredit:
	see also Histops::TotalCredit()
	@mod: reference to Booker module object
	@bookerid: booker identifier
	*/
	public function TotalCredit(&$mod, $bookerid)
	{
		$amount = 0.0;
		$sql = 'SELECT netfee FROM '.$mod->HistoryTable.
		' WHERE booker_id=? AND status='.\Booker::STATCREDITADDED;
		$rows = $mod->dbHandle->GetCol($sql,array($bookerid));
		foreach ($rows as $one) {
			$amount .= (float)$one;
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
		if ($amount > 0.0) {
			//the 'fee' field retains original credit, 'netfee' field will be used for adjustments
			$sql = 'INSERT INTO '.$mod->HistoryTable.
' (history_id,booker_id,lodged,fee,netfee,status) VALUES (?,?,?,?,?,?)';
			$hid = $mod->dbHandle->GenID($mod->HistoryTable.'_seq');
			$dt = new \DateTime('now',new \DateTimeZone('UTC'));
			$st = $dt->getTimestamp();
			$args = array(
				$hid,
				$bookerid,
				$st,
				$amount,
				$amount,
				\Booker::STATCREDITADDED
			);
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute($sql,$args);
		}
	}

	/**
	UseCredit:
	see also Histops::UseCredit()
	@mod: reference to Booker module object
	@bookerid: booker identifier
	@amount: amount of credit to be adjusted
	*/
	public function UseCredit(&$mod, $bookerid, $amount)
	{
		$sql = 'SELECT history_id,netfee FROM '.$mod->HistoryTable.
		' WHERE booker_id=? AND status='.\Booker::STATCREDITADDED.' ORDER BY lodged';
		$data = $mod->dbHandle->GetArray($sql,array($bookerid));
		if ($data) {
			$sql = 'UPDATE '.$mod->HistoryTable.' SET netfee=? WHERE history_id=?';
			foreach ($data as $row) {
				$now = (float)$row['netfee'];
				$amount -= $now;
				if ($amount >= 0.01) {
					$now = 0;
					$stop = ($amount <= 0.01);
				} else { //$amount < 0.0 approx.
					$now += $amount;
					$stop = TRUE;
				}
				//TODO build arrays, then $utils->SafeExec($sql[],$args[]);
				$mod->dbHandle->Execute($sql,array($now,$row['history_id']));
				if ($stop) {
					break;
				}
			}
			return TRUE;
		}
		return FALSE;
	}

	/**
	ExpireCredit:
	see also Histops::ExpireCredit()
	@mod: reference to Booker module object
	@bookerid: booker identifier
	@before: UTC timestamp for limit on remaining credit
	*/
	public function ExpireCredit(&$mod, $bookerid, $before)
	{
		$sql = 'UPDATE '.$mod->HistoryTable.' SET status='.\Booker::STATCREDITEXPIRED.
		' WHERE booker_id=? AND status='.\Booker::STATCREDITADDED.' AND lodged<?';
		//TODO $utils->SafeExec()
		$mod->dbHandle->Execute($sql,array($bookerid,$limit));
	}
}
