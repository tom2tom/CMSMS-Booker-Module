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
	//$data is array of 3 arrays, respectively start-stamps, end-stamps, rules
	private function RulesTotal($data, $calcer) //callable typehint for PHP 5.4+
	{
		$total = 0.0;
		foreach ($data[0] as $i=>$st) {
			$slen = $data[1][$i] - $st;
			$total += $calcer($slen,$data[2][$i]);
		}
		return $total;
	}

	//$data1,$data2 are arrays of 3 arrays, respectively start-stamps, end-stamps, rules
	private function IntersectsTotal($data1, $data2, $calcer) //callable typehint for PHP 5.4+
	{
		$starts = array();
		$ends = array();
		$rules1 = array();
		$rules2 = array();
		array_multisort($data1[0],SORT_ASC,SORT_NUMERIC,$data1[1],$data1[2]);
		array_multisort($data2[0],SORT_ASC,SORT_NUMERIC,$data2[1],$data2[2]);
		//determine $data1,$data2 intersects & corresponding rule(s)
		$i = 0;
		$ic = count($data1[0]);
		$j = 0;
		$jc = count($data2[0]);
		while ($i < $ic || $j < $jc) {
			$st1 = ($i < $ic) ? $data1[0][$i] : ~PHP_INT_MAX; //aka PHP_INT_MIN
			$nd1 = ($i < $ic) ? $data1[1][$i] : ~PHP_INT_MAX;
			$st2 = ($j < $jc) ? $data2[0][$j] : ~PHP_INT_MAX;
			$nd2 = ($j < $jc) ? $data2[1][$j] : ~PHP_INT_MAX;
			if (($st1 >= $st2 && $st1 < $nd2) || ($st2 >= $st1 && $st2 < $nd1)) { //$st1..$nd1 overlaps with $st2..$nd2
				$stb = max($st1,$st2);
				$ndb = min($nd1,$nd2);
				if ($data1[2][$i] || $data2[2][$j]) {
					$starts[] = $stb;
					$ends[] = $ndb;
					$rules1[] = $data1[2][$i]; //maybe empty
					$rules2[] = $data2[2][$j]; //maybe empty
				}
				if ($ndb = $data1[1][$i]) { //data1 block is ended
					if (++$i == $ic) {
						if ($ndb < $data2[1][$j] && $data2[2][$j]) {
							//rest of current data2
							$starts[] = $ndb;
							$ends[] = $data2[1][$j];
							$rules1[] = NULL; //data1 N/A here
							$rules2[] = $data2[2][$j];
						}
						$j++;
						break;
					}
				}
				if ($ndb = $data2[1][$j]) { //data2 block is ended
					if (++$j == $jc) {
						if ($ndb < $data1[1][$i] && $data1[2][$i]) {
							//rest of current data1
							$starts[] = $ndb;
							$ends[] = $data1[1][$i];
							$rules1[] = $data1[2][$i];
							$rules2[] = NULL; //data2 N/A here
						}
						$i++;
						break;
					}
				}
			} elseif ($data1[1][$i] < $data2[0][$j]) { //data2 starts after data1 end
				if ($data1[2][$i]) { //rule exists
					$starts[] = $data1[0][$i];
					$ends[] = $data1[1][$i];
					$rules1[] = $data1[2][$i];
					$rules2[] = NULL; //data2 N/A here
				}
				if (++$i == $ic) {
					break;
				}
			} elseif ($data1[0][$i] >= $data2[1][$j]) { //data1 starts at or after data2 end
				if ($data2[2][$j]) { //rule exists
					$starts[] = $data2[0][$j];
					$ends[] = $data2[1][$j];
					$rules1[] = NULL; //data1 N/A here
					$rules2[] = $data2[2][$j];
				}
				if (++$j == $jc) {
					break;
				}
			}
		}
		//left-overs (never from both data1 and data2)
		for (; $i<$ic; $i++) {
			if ($data1[2][$i]) { //rule exists
				$starts[] = $data1[0][$i];
				$ends[] = $data1[1][$i];
				$rules1[] = $data1[2][$i];
				$rules2[] = NULL; //data2 N/A here
			}
		}
		for (; $j<$jc; $j++) {
			if ($data2[2][$j]) { //rule exists
				$starts[] = $data2[0][$j];
				$ends[] = $data2[1][$j];
				$rules1[] = NULL; //data1 N/A here
				$rules2[] = $data2[2][$j];
			}
		}

		$total = 0.0;
		foreach ($starts as $i=>$st) {
			$slen = $ends[$i] - $st;
			$total += $calcer($slen,$rules1[$i],$rules2[$i]);
		}
		return $total;
	}

	//Interpret each array-member's slottype,slotcount parameters
	//into corresponding seconds, stored in 'slotlen' parameter
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
	@booker_id: booker identifier
	@slotstart: UTC timestamp representing start of interval to be processed
	@slotlen: duration (seconds) of the interval
	Returns: 2-member array, 1st is gross fee payable for use of @item_id by
		@booker_id over the nomiated interval, 2nd is current total credit held
	    by @booker_id (so the net fee is the difference between the 2)
	*/
	public function Amounts(&$mod,$item_id,$booker_id,$slotstart,$slotlen)
	{
		$funcs = new Booker\Inherit();
		$sql = <<<EOS
SELECT slottype,slotcount,fee,feecondition FROM {$mod->FeeTable}
WHERE condtype=0 AND active>0
ORDER BY condorder
EOS;
		$rules = $mod->dbHandle->GetArray($sql);
		if ($rules) {
			$this->ParseIntervals($rules);
			$dtdata = $funcs->RangeInheritRuled($slotstart,$slotlen,$item_id,$rules);
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
			$udata = $funcs->RangeInheritRuled($slotstart,$slotlen,$booker_id,$rules);
		} else {
			$udata = FALSE;
		}

		if ($udata) {
			if ($dtdata) {
				$grossfee = $this->IntersectsTotal($udata,$dtdata,
					function($slen,$urule,$drule) use ($booker_id)
					{
						return 0.0; //TODO interpret & calc
					});
			} else {
				$grossfee = $this->RulesTotal($udata,
					function($slen,$rule) use ($booker_id)
					{
						return 0.0; //TODO interpret & calc
					});
			}
		} elseif ($dtdata) {
			$grossfee = $this->RulesTotal($dtdata,
				function($slen,$rule)
				{
					return 0.0; //TODO interpret & calc
				});
		} else {
			$grossfee = 0.0;
		}
		$creditnow = $this->TotalCredit($mod,$booker_id);
		return array($grossfee,$creditnow);
	}

	/**
	TotalCredit:
	@mod: reference to Booker module object
	@booker_id: booker identifier
	*/
	public function TotalCredit(&$mod, $booker_id)
	{
		$amount = 0.0;
		$sql = 'SELECT netfee FROM '.$mod->HistoryTable.
		' WHERE booker_id=? AND status IN('.implode(',',array(
		\Booker::STATCREDITADDED,
		\Booker::STATCREDITPART
		)).')';
		$rows = $mod->dbHandle->GetCol($sql,array($booker_id));
		foreach ($rows as $one) {
			$amount .= (float)$one;
		}
		return $amount;
	}

	/**
	UseCredit:
	@mod: reference to Booker module object
	@booker_id: booker identifier
	@amount: amount of credit to be adjusted
	*/
	public function UseCredit(&$mod, $booker_id, $amount)
	{
		$sql = 'SELECT history_id,netfee FROM '.$mod->HistoryTable.
		' WHERE booker_id=? AND status IN('.implode(',',array(
		\Booker::STATCREDITADDED,
		\Booker::STATCREDITPART
		)).') ORDER BY lodged';
		$data = $mod->dbHandle->GetArray($sql,array($booker_id));
		if ($data) {
			//TODO this is CRAP for reporting
			$sql = 'UPDATE '.$mod->HistoryTable.' SET netfee=?,status=? WHERE history_id=?';
			foreach ($data as $row) {
				$pay = (float)$row['netfee'];
				$amount -= $pay;
				if ($amount >= 0.01) {
					$pay = 0;
					$stat = \Booker::STATCREDITFULL;
					$stop = !($amount > 0.01);
				} else { //$amount < 0.0 approx.
					$pay += $amount;
					$stat = \Booker::STATCREDITPART;
					$stop = TRUE;
				}
				//TODO build arrays, then $utils->SafeExec($sql[],$args[]);
				$mod->dbHandle->Execute($sql,array($pay,$stat,$row['history_id']));
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
	@mod: reference to Booker module object
	@booker_id: booker identifier
	@oldest: UTC timestamp for oldest remaining credit
	*/
	public function ExpireCredit(&$mod, $booker_id, $oldest)
	{
		$sql = 'UPDATE '.$mod->HistoryTable.' SET status='.\Booker::STATCREDITEXPIRED.
		' WHERE booker_id=? AND status IN('.implode(',',array(
		\Booker::STATCREDITADDED,
		\Booker::STATCREDITPART
		)).') AND lodged<?';
		$mod->dbHandle->Execute($sql,array($booker_id,$limit));
	}

	/**
	AddCredit:
	@mod: reference to Booker module object
	@booker_id: booker identifier
	@amount: amount of credit to be added
	*/
	public function AddCredit(&$mod, $booker_id, $amount)
	{
		if ($amount > 0.0) {
			$sql = 'INSERT INTO '.$mod->HistoryTable.
' (history_id,booker_id,lodged,approved,fee,netfee,status) VALUES (?,?,?,?,?,?,?)';
			$hid = $mod->dbHandle->GenID($mod->HistoryTable.'_seq');
			$dt = new \DateTime('now',new \DateTimeZone('UTC'));
			$st = $dt->getTimestamp();
			$args = array(
				$hid,
				$booker_id,
				$st,
				$st,
				$amount,
				$amount,
				\Booker::STATCREDITADDED
			);
			$mod->dbHandle->Execute($sql,$args);
		}
	}
}
