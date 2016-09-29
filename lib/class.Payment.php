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
	private function IntersectsTotal($data1, $data2, &$funcs, $calcer) //PHP 5.4+ supports callable typehint
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

	//$data is array of 3 arrays, respective start-stamps, end-stamps, rules
	private function RulesTotal($data, $calcer) //PHP 5.4+ supports callable typehint
	{
		$total = 0.0;
		foreach ($data[0] as $i=>$st) {
			$slen = $data[1][$i] - $st;
			$total += $calcer($slen,$data[2][$i]);
		}
		return $total;
	}

	//Interpret each array-member's slottype,slotcount parameters
	//into (approximate) corresponding seconds, stored in 'slotlen' parameter
	private function ParseIntervals(&$rules)
	{
		//slotype = 0..5 per Utils::TimeIntervals() i.e. for minute,hour,day,week,month,year
		//or -1 for fixed amount
		foreach ($rules as $k=>&$one) {
			switch ($one['slottype']) {
			 case -1:
				$t = 0;	//store slotlen = -1
				break;
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
			 default:
				$t = -1;
				break;
			}
			if ($t >= 0) {
				unset($one['slottype']);
				$c = $one['slotcount'] ? $one['slotcount'] : 1;
				unset($one['slotcount']);
				$one['slotlen'] = $t * $c - 1;
			} else {
				unset($rules[$k]);
			}
		}
		unset($one);
	}

	/**
	Amounts:
	Get gross fee for use of resource, and current credit against which the fee
	may be offset
	@mod: reference to Booker module object
	@item_id: resource identifier
	@bookerid: booker identifier
	@slotstart: UTC timestamp representing start of interval to be processed
	@slotlen: duration (seconds) of the interval
	Returns: 2-member array, 1st is gross fee payable for use of @item_id by
		@bookerid over the nomiated interval, 2nd is current total credit held
	    by @bookerid (so the net fee is the difference between the 2)
	*/
	public function Amounts(&$mod,$item_id,$bookerid,$slotstart,$slotlen)
	{
		$funcs = new Blocks();
		$sql = <<<EOS
SELECT slottype,slotcount,fee,feecondition FROM {$mod->FeeTable}
WHERE condtype=0 AND active>0
ORDER BY condorder
EOS;
		$rules = $mod->dbHandle->GetArray($sql);
		if ($rules) {
			$this->ParseIntervals($rules);
			$utils = new Utils();
			$idata = $utils->GetItemProperty($mod,$item_id,'*');
			$dtdata = $funcs->RepeatRuledBlocks($mod,$idata,$slotstart,$slotlen,$rules);
			if ($dtdata[0] == FALSE)
				$dtdata = FALSE;
		} else {
			$dtdata = FALSE;
		}

		$sql = <<<EOS
SELECT slottype,slotcount,fee,feecondition FROM {$mod->FeeTable}
WHERE condtype=1 AND active>0
ORDER BY condorder
EOS;
		$rules = $mod->dbHandle->GetArray($sql);
		if ($rules) {
			$this->ParseIntervals($rules);
			$udata = $funcs->UserRuledBlocks($slotstart,$slotlen,$rules);
			if ($udata[0] == FALSE)
				$udata = FALSE;
		} else {
			$udata = FALSE;
		}

		if ($udata) {
			if ($dtdata) {
				$grossfee = $this->IntersectsTotal($udata,$dtdata,$funcs,
					function($slen,$urule,$drule) use ($bookerid)
					{
						return 0.0; //TODO interpret & calc
						if ($drule) {
						}
						if ($urule) {
						}
					});
			} else {
				$grossfee = $this->RulesTotal($udata,
					function($slen,$rule) use ($bookerid)
					{
						return 0.0; //TODO interpret & calc
						if ($rule) {
						}
					});
			}
		} elseif ($dtdata) {
			$grossfee = $this->RulesTotal($dtdata,
				function($slen,$rule)
				{
					return 0.0; //TODO interpret & calc
					if ($rule) {
					}
				});
		} else {
			$grossfee = 0.0;
		}
		$creditnow = $this->TotalCredit($mod,$bookerid);
		return array($grossfee,$creditnow);
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
	GetItemFee:
	NOTE this does not determine a payable amount (which may be time/duration-dependent,
	and/or affected by the booker's credit status)
	@mod: reference to current Booker module object
	@item_id: identifier of item (resource or group) for which fee(s) is/are sought
	@feefactors: optional array describing parameter(s) relevant to payability
	 Recognised array contents:
	 'booker'=>id
	 'booker'=>'*'
	 'bookertype'=>enum
	 'bookertype'=>'*'
	 'slotstart'=>stamp
	 'slotend'=>stamp
	 'slotlen'=>seconds
	 'when'=>string datetime
	 'until'=>string datetime
	 'absent'=>anything
	 'id'=>indx i.e condition-index for the fee item
	default FALSE means unconditional/no check needed
	@search: optional boolean, whether to check for missing fee in ancestor
		groups (if any), default TRUE
	Returns: the first-found non-NULL fee if @feefactors === FALSE, or
	 the first-found non-NULL fee whose condition matches the contents of 
	 non-FALSE @feefactors, or
	 boolean FALSE if there are no relevant fee-data (feefactors or otherwise)
	TODO handle relative-fees e.g. +10% - keep looking until can evaluate ?
	*/
	public function GetItemFee(&$mod, $item_id, $feefactors=FALSE, $search=TRUE)
	{
		$db = $mod->dbHandle;
		if ($search) {
			$args = self::GetItemGroups($mod,$item_id);
			array_unshift($args, $item_id); //priority-ordered for checking
			$fillers = str_repeat('?,',count($args)-1);
			$sql = 'SELECT item_id,slottype,slotcount,fee,feecondition,condtype FROM '.$mod->FeeTable.
			' WHERE item_id IN ('.$fillers.'?) AND active=1 ORDER BY item_id,condorder'; //a bit of downstream sorting might help ...
			$fees = $db->GetArray($sql,$args); //NB ordered by item_id prob not what we want: $args has that
			if ($fees) {
				usort($fees,function($a, $b) use ($args)
				{
					$ta = $a['item_id'];
					$tb = $b['item_id'];
					if ($ta != $tb) {
						$ka = array_search($ta,$args);
						$kb = array_search($tb,$args);
						if ($ka != $kb) {
							return ($ka-$kb); //should always happen!
						}
					}
					return ($a['condorder'] - $b['condorder']);
				});
			}
		} else {
			$sql = 'SELECT slottype,slotcount,fee,feecondition,condtype FROM '.$mod->FeeTable.
			' WHERE item_id=? AND active=1 ORDER BY condorder';
			$fees = $db->GetArray($sql,array($item_id));
		}

		if ($fees) {
/*for $item_id == 1, $fees = array
 0 =>array
  'item_id' => string '1'
  'slottype' => string '-1'
  'slotcount' => null
  'fee' => string '20.00'
  'feecondition' => string 'sunrise..sunset'
  'condtype' => string '0'
 1 =>array
  'item_id' => string '10003'
  'slottype' => string '1'
  'slotcount' => string '1'
  'fee' => string '10.00'
  'feecondition' => string '0..sunrise,sunset..23:59'
  'condtype' => string '0'
*/
/*
			if (strpos($feefactors,'ID<:>') === 0) {
				$cid = substr($feefactors,5);
				$feefactors = $db->GetOne('SELECT feecondition FROM '.
					$mod->FeeTable.' WHERE item_id=? AND condition_id=?',array($item_id,$cid));
				if ($feefactors === FALSE)
					return FALSE; //error
				elseif (!$feefactors)
					$feefactors = '';
			}

			foreach ($fees as $one) {
				if ($one['fee'] != NULL) {
					if ($feefactors === FALSE) {
						return $one['fee']; //CHECKME
					} elseif ($one['feecondition']) {
	//TODO $this->FeeTable stuff
						if (0) {//TODO check for conforming condition
							return $one['fee'];
						}
					} elseif ($feefactors === '') {
						return $one['fee'];
					}
				}
			}
*/
		}
		return FALSE;
	}

	/**
	GetItemPayable:
	@mod: reference to current Booker module object
	@item_id: identifier of item (resource or group) for which property is/are sought
	@feefactors: optional array, representing condition(s). See GetItemFee() for details.
	@search: optional boolean, whether to check for missing fee in ancestor groups (if any), default TRUE
	Returns: boolean, FALSE if there are no relevant fee-data, or
	 TRUE if @feefactors === FALSE there's any fee > 0, or
	 TRUE if @feefactors !== FALSE and there's a fee > 0 for a condition that matches it
	 or FALSE
	*/
	public function GetItemPayable(&$mod, $item_id, $feefactors=FALSE, $search=TRUE)
	{
		$fee = self::GetItemFee($mod,$item_id,$feefactors,$search);
		return ($fee > 0);
	}
}
