<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Blocks - functions for dealing with timestamp-blocks
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
//See also: Display::Coalesce($slots), Repeats::MergeBlocks(&$starts,&$ends)
namespace Booker;

class Blocks
{
	/**
	BlockIntersects:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	Returns: array:
		[0] = array of start-stamps for subblocks in both first-block and other-block
		[1] = array of corresponding end-stamps
	OR returns FALSE if nothing applies
	The returned arrays have corresponding, but not necessarily contiguous, numeric keys
	*/
	public function BlockIntersects($starts1, $ends1, $starts2, $ends2)
	{
		$starts = array();
		$ends = array();
		$i = 0;
		$ic = count($starts1);
		$j = 0;
		$jc = count($starts2);
		while ($i < $ic || $j < $jc) {
			$st1 = ($i < $ic) ? $starts1[$i] : ~PHP_INT_MAX; //aka PHP_INT_MIN
			$nd1 = ($i < $ic) ? $ends1[$i] : ~PHP_INT_MAX;
			$st2 = ($j < $jc) ? $starts2[$j] : ~PHP_INT_MAX;
			$nd2 = ($j < $jc) ? $ends2[$j] : ~PHP_INT_MAX;
			if (($st1 >= $st2 && $st1 < $nd2) || ($st2 >= $st1 && $st2 < $nd1)) { //$st1..$nd1 overlaps with $st2..$nd2
				$stb = max($st1,$st2);
				$ndb = min($nd1,$nd2);
				$starts[] = $stb;
				$ends[] = $ndb;
				if ($ndb == $ends1[$i]) { //1-block is ended
					if (++$i == $ic) {
						$j++;
						break;
					}
				}
				if ($ndb == $ends2[$j]) { //2-block is ended
					if (++$j == $jc) {
						$i++;
						break;
					}
				}
			} elseif ($ends1[$i] < $starts2[$j]) { //2-block starts after 1-block end
				if (++$i == $ic) {
					break;
				}
			} elseif ($starts1[$i] >= $ends2[$j]) { //1-block starts at or after 2-block end
				if (++$j == $jc) {
					break;
				}
			}
		}

		$ic = count($starts);
		if ($ic > 0) {
			if ($ic > 1) {
				$ic--;
				//merge adjacent blocks
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						$starts[$j] = $starts[$i];
						unset($starts[$i]);
						unset($ends[$i]);
					}
				}
			}
			return array($starts,$ends);
		}
		return FALSE;
	}

	/**
	BlockIntersectsRuled:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	@rules2: array of corresponding rules, FALSE represents no rule
	Returns: array:
		[0] = array of start-stamps for subblocks in both first-block and other-block
		[1] = array of corresponding end-stamps
		[2] = array of corresponding @rules2[] members
	OR returns FALSE if nothing applies
	The returned arrays have corresponding, but not necessarily contiguous, numeric keys
	*/
	public function BlockIntersectsRuled($starts1, $ends1, $starts2, $ends2, $rules2)
	{
		$starts = array();
		$ends = array();
		$userules = array();
		$i = 0;
		$ic = count($starts1);
		$j = 0;
		$jc = count($starts2);
		while ($i < $ic || $j < $jc) {
			$st1 = ($i < $ic) ? $starts1[$i] : ~PHP_INT_MAX; //aka PHP_INT_MIN
			$nd1 = ($i < $ic) ? $ends1[$i] : ~PHP_INT_MAX;
			$st2 = ($j < $jc) ? $starts2[$j] : ~PHP_INT_MAX;
			$nd2 = ($j < $jc) ? $ends2[$j] : ~PHP_INT_MAX;
			if (($st1 >= $st2 && $st1 < $nd2) || ($st2 >= $st1 && $st2 < $nd1)) { //$st1..$nd1 overlaps with $st2..$nd2
				$stb = max($st1,$st2);
				$ndb = min($nd1,$nd2);
				if ($rules2[$j]) {
					$starts[] = $stb;
					$ends[] = $ndb;
					$userules[] = $rules2[$j];
				}
				if ($ndb == $ends1[$i]) { //1-block block is ended
					if (++$i == $ic) {
						if ($ndb < $ends2[$j] && $rules2[$j]) {
							//rest of current 2-block
							$starts[] = $ndb;
							$ends[] = $ends2[$j];
							$userules[] = $rules2[$j];
						}
						$j++;
						break;
					}
				}
				if ($ndb == $ends2[$j]) { //2-block block is ended
					if (++$j == $jc) {
						$i++;
						break;
					}
				}
			} elseif ($ends1[$i] < $starts2[$j]) { //2-block starts after 1-block end
				if (++$i == $ic) {
					break;
				}
			} elseif ($starts1[$i] >= $ends2[$j]) { //1-block starts at or after 2-block end
				if ($rules2[$j]) { //rule exists
					$starts[] = $starts2[$j];
					$ends[] = $ends2[$j];
					$userules[] = $rules2[$j];
				}
				if (++$j == $jc) {
					break;
				}
			}
		}
		//left-overs
		for (; $j<$jc; $j++) {
			if ($rules2[$j]) { //rule exists
				$starts[] = $starts2[$j];
				$ends[] = $ends2[$j];
				$userules[] = $rules2[$j];
			}
		}

		$ic = count($starts);
		if ($ic > 0) {
			if ($ic > 1) {
				$ic--;
				//merge adjacent blocks with same rule
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						if ($rules2[$i] == $rules2[$j]) {
							$starts[$j] = $starts[$i];
							unset($starts[$i]);
							unset($ends[$i]);
							unset($userules[$i]);
						}
					}
				}
			}
			return array($starts,$ends,$userules);
		}
		return FALSE;
	}

	/**
	BlockIntersects2Ruled:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@rules1: array of corresponding rules, FALSE represents no rule
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	@rules2: array of corresponding rules, FALSE represents no rule
	Returns: array:
		[0] = array of start-stamps for subblocks in both first-block and other-block
		[1] = array of corresponding end-stamps
		[2] = array of corresponding @rules1[] members, with NULL's where @rules1[] doesn't apply
		[3] = array of corresponding @rules2[] members, with NULL's where @rules2[] doesn't apply
	OR returns FALSE if nothing applies
	The returned arrays have corresponding, but not necessarily contiguous, numeric keys
	*/
	public function BlockIntersects2Ruled($starts1, $ends1, $rules1, $starts2, $ends2, $rules2)
	{
		$starts = array();
		$ends = array();
		$userules1 = array();
		$userules2 = array();
		$i = 0;
		$ic = count($starts1);
		$j = 0;
		$jc = count($starts2);
		while ($i < $ic || $j < $jc) {
			$st1 = ($i < $ic) ? $starts1[$i] : ~PHP_INT_MAX; //aka PHP_INT_MIN
			$nd1 = ($i < $ic) ? $ends1[$i] : ~PHP_INT_MAX;
			$st2 = ($j < $jc) ? $starts2[$j] : ~PHP_INT_MAX;
			$nd2 = ($j < $jc) ? $ends2[$j] : ~PHP_INT_MAX;
			if (($st1 >= $st2 && $st1 < $nd2) || ($st2 >= $st1 && $st2 < $nd1)) { //$st1..$nd1 overlaps with $st2..$nd2
				$stb = max($st1,$st2);
				$ndb = min($nd1,$nd2);
				if ($rules1[$i] || $rules2[$j]) {
					$starts[] = $stb;
					$ends[] = $ndb;
					$userules1[] = $rules1[$i]; //maybe FALSE
					$userules2[] = $rules2[$j]; //maybe FALSE
				}
				if ($ndb == $ends1[$i]) { //1-block block is ended
					if (++$i == $ic) {
						if ($ndb < $ends2[$j] && $rules2[$j]) {
							//rest of current 2-block
							$starts[] = $ndb;
							$ends[] = $ends2[$j];
							$userules1[] = NULL; //1-block N/A here
							$userules2[] = $rules2[$j];
						}
						$j++;
						break;
					}
				}
				if ($ndb == $ends2[$j]) { //2-block block is ended
					if (++$j == $jc) {
						if ($ndb < $ends1[$i] && $rules1[$i]) {
							//rest of current 1-block
							$starts[] = $ndb;
							$ends[] = $ends1[$i];
							$userules1[] = $rules1[$i];
							$userules2[] = NULL; //2-block N/A here
						}
						$i++;
						break;
					}
				}
			} elseif ($ends1[$i] < $starts2[$j]) { //2-block starts after 1-block end
				if ($rules1[$i]) { //rule exists
					$starts[] = $starts1[$i];
					$ends[] = $ends1[$i];
					$userules1[] = $rules1[$i];
					$userules2[] = NULL; //2-block N/A here
				}
				if (++$i == $ic) {
					break;
				}
			} elseif ($starts1[$i] >= $ends2[$j]) { //1-block starts at or after 2-block end
				if ($rules2[$j]) { //rule exists
					$starts[] = $starts2[$j];
					$ends[] = $ends2[$j];
					$userules1[] = NULL; //1-block N/A here
					$userules2[] = $rules2[$j];
				}
				if (++$j == $jc) {
					break;
				}
			}
		}
		//left-overs (never from both 1-block and 2-block)
		for (; $i<$ic; $i++) {
			if ($rules1[$i]) { //rule exists
				$starts[] = $starts1[$i];
				$ends[] = $ends1[$i];
				$userules1[] = $rules1[$i];
				$userules2[] = NULL; //2-block N/A here
			}
		}
		for (; $j<$jc; $j++) {
			if ($rules2[$j]) { //rule exists
				$starts[] = $starts2[$j];
				$ends[] = $ends2[$j];
				$userules1[] = NULL; //1-block N/A here
				$userules2[] = $rules2[$j];
			}
		}

		$ic = count($starts);
		if ($ic > 0) {
			if ($ic > 1) {
				$ic--;
				//merge adjacent blocks with same rules
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						if ($userules1[$i] == $userules1[$j] && $userules2[$i] == $userules2[$j]) {
							$starts[$j] = $starts[$i];
							unset($starts[$i]);
							unset($ends[$i]);
							unset($userules1[$i]);
							unset($userules2[$i]);
						}
					}
				}
			}
			return array($starts,$ends,$userules1,$userules2);
		}
		return FALSE;
	}

	//Interpret $dtrule into stamp-block(s) covering $st..$nd
	private function BlocksforCalendarRule(&$mod, $st, $nd, $dtrule, $idata)
	{
		$funcs = new Repeats($mod);
		if ($funcs->ParseCondition($dtrule)) {
			$dts = new \DateTime('1900-1-1',new \DateTimeZone('UTC'));//CHECKME local?
			$dte = clone $dts;
			$dts->setTimestamp($st);
			$dte->setTimestamp($nd);
			$sunparms = $funcs->SunParms($idata);//TODO sunparms offset may change during interval
			list($starts,$ends) = $funcs->GetBlocks($dts,$dte,$sunparms);
			if ($starts) {
				return array($starts,$ends);
			}
		}
		return FALSE;
	}

	/**
	RepeatBlocks:
	This replicates RepeatRuledBlocks() except @rules is string(s), and
	rules-members are not returned.
	@mod: reference to Booker module object
	@idata: array of parameters for the resource being processed
	@slotstart: UTC timestamp for start of range
	@slotlen: length of range (seconds)
	@rules: single rule, or array of rules sorted in order of decreasing priority,
		[each] rule being a rule recognised by RepeatLexer (or FALSE)
	Returns: 2-member array,
		[0] has sorted block-start timestamps in @slotstart..@slotstart+@slotlen+1
		[1] has respective block-end timestamps in @slotstart..@slotstart+@slotlen+1
	OR returns FALSE if nothing is relevant
	*/
	public function RepeatBlocks(&$mod, $idata, $slotstart, $slotlen, $rules)
	{
		if (!is_array($rules)) {
			$rules = array($rules);
		}
		$ic = count($rules);
		$i = 0;

		$chkstarts = array($slotstart);
		$chkends = array($slotstart+$slotlen);
		$starts = array();
		$ends = array();
		//TODO make this support 'except' rules too - subtract from blocks previously accepted
		while ($i < $ic) {
			if ($rules[$i]) { //something to interpret
				$st = reset($chkstarts);
				$nd = end($chkends);
				$res = $this->BlocksforCalendarRule($mod,$st,$nd,$rules[$i],$idata); //NOT default to entire current blocks
				if ($res) {
					list($rulestarts,$ruleends) = $res;
					$res = $this->BlockIntersects($chkstarts,$chkends,$rulestarts,$ruleends);
					if ($res) {
						list($rulestarts,$ruleends) = $res;
						foreach ($rulestarts as $j=>$st) {
							$starts[] = $st;
							$chkends[] = $st;
							$nd = $ruleends[$j];
							$ends[] = $nd;
							$chkstarts[] = $nd;
						}
						//eliminate blocks already dealt with from further checks
						sort($chkstarts,SORT_NUMERIC);
						sort($chkends,SORT_NUMERIC);
						$cc = count($chkstarts) - 1;
						for ($c=0; $c<$cc; $c++) {
							$j = $c+1;
							if ($chkstarts[$j] <= $chkstarts[$c]) {
								unset($chkstarts[$c]);
								unset($chkends[$c]);
								unset($chkstarts[$j]);
								unset($chkends[$j]);
								$c = $j; //next loop will deal with follower
								$cc -= 2;
							}
						}
					}
//.			} else {
//				$c = 43; //DEBUG placeholder TODO
				}
			}
			$i++;
		}

		$ic = count($starts);
		if ($ic > 0) {
			if ($ic > 1) {
				array_multisort($starts,SORT_ASC,SORT_NUMERIC,$ends);
				$ic--;
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						$starts[$j] = $starts[$i];
						unset($starts[$i]);
						unset($ends[$i]);
					}
				}
			}
			return array($starts,$ends);
		}
		return FALSE;
	}

	/**
	RepeatRuledBlocks:
	This replicates RepeatBlocks() except @rules is array(s), and rules-members
	are returned.
	@mod: reference to Booker module object
	@idata: array of parameters for the resource being processed
	@slotstart: UTC timestamp for start of range
	@slotlen: length of range (seconds)
	@rules: single rule, or array of rules sorted in order of decreasing priority,
		[each] rule being an array including a member 'feecondition' which is a
		rule recognised by RepeatLexer (or FALSE)
	Returns: 3-member array,
		[0] has sorted block-start timestamps in @slotstart..@slotstart+@slotlen+1
		[1] has respective block-end timestamps in @slotstart..@slotstart+@slotlen+1
		[2] has respective members of @rules
	OR returns FALSE if nothing is relevant
	*/
	public function RepeatRuledBlocks(&$mod, $idata, $slotstart, $slotlen, $rules)
	{
		if (!is_array($rules)) {
			$rules = array($rules);
		}
		$ic = count($rules);
		$i = 0;

		$chkstarts = array($slotstart);
		$chkends = array($slotstart+$slotlen+1);
		$starts = array();
		$ends = array();
		$blkrules = array();

		//TODO make this support 'except' rules too - subtract from blocks previously accepted
		while ($i < $ic) {
			if ($rules[$i]) { //something to interpret
				$st = reset($chkstarts);
				$nd = end($chkends);
				$res = $this->BlocksforCalendarRule($mod, $bst,$bnd,$rules[$i]['feecondition'],$idata); //NOT default to entire current blocks
				if ($res) {
					list($rulestarts,$ruleends) = $res;
					$res = $this->BlockIntersects($chkstarts,$chkends,$rulestarts,$ruleends);
					if ($res) {
						list($rulestarts,$ruleends) = $res;
						foreach ($rulestarts as $j=>$st) {
							$starts[] = $st;
							$chkends[] = $st;
							$nd = $ruleends[$j];
							$ends[] = $nd;
							$chkstarts[] = $nd;
							$blkrules[] = $rules[$i];
						}
						//eliminate blocks already dealt with from further checks
						sort($chkstarts,SORT_NUMERIC);
						sort($chkends,SORT_NUMERIC);
						$cc = count($chkstarts) - 1;
						for ($c=0; $c<$cc; $c++) {
							$j = $c+1;
							if ($chkstarts[$j] <= $chkstarts[$c]) {
								unset($chkstarts[$c]);
								unset($chkends[$c]);
								unset($chkstarts[$j]);
								unset($chkends[$j]);
								$c = $j; //next loop will deal with follower
								$cc -= 2;
							}
						}
					}
				}
			}
			$i++;
		}

		$ic = count($starts);
		if ($ic > 0) {
			if ($ic > 1) {
				array_multisort($starts,SORT_ASC,SORT_NUMERIC,$ends,$blkrules);
				$ic--;
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						if ($blkrules[$i] == $blkrules[$j]) { //non-strict array comparison
							$starts[$j] = $starts[$i];
							unset($starts[$i]);
							unset($ends[$i]);
							unset($blkrules[$i]);
						}
					}
				}
			}
			return array($starts,$ends,$blkrules);
		}
		return FALSE;
	}

	/**
	UserRuledBlocks:
	@slotstart: UTC timestamp for start of range
	@slotlen: length of range (seconds)
	@rules: single rule, or array of rules sorted in order of decreasing priority,
		[each] rule being an array with members 'slotlen','fee','feecondition',
		the latter being a rule for discimination among users
	Returns: 3-member array,
		[0] has block-start timestamps @slotstart
		[1] has block-end timestamps @slotstart+@slotlen+1
		[2] has a member of @rules, to apply for the whole block
	*/
	public function UserRuledBlocks($slotstart, $slotlen, $rules)
	{
		$nd = $slotstart + $slotlen + 1;
		if (is_array($rules)) {
			$starts = array();
			$ends = array();
			$blkrules = array();
			foreach ($rules as $one) {
				$starts[] = $slotstart;
				$ends[] = $nd;
				$blkrules[] = $one;
			}
			return array($starts,$ends,$blkrules);
		} elseif ($rules) {
			return array(array($slotstart),array($nd),array($rules));
		} else { //should never happen
			return FALSE;
		}
	}
}
