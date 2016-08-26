<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Blocks - functions for dealing with timestamp-blocks
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
//See also: Display::Coalesce($slots)
namespace Booker;

class Blocks
{
	/**
	BlockDiffs:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	Returns: 2-member array,
	 [0] = array of start-stamps for subblocks in first-block and not in other-block
	 [1] = array of corresponding end-stamps
	 The arrays have corresponding but not necessarily contiguous numeric keys, or may be empty.
	 1-second blocks are omitted, so care needed for off-by-1 in supplied arrrays.
	*/
	public function BlockDiffs($starts1, $ends1, $starts2, $ends2)
	{
		$i = 0;
		$ic = count($starts1);
		$j = 0;
		$jc = count($starts2);
		while ($i < $ic && $j < $jc) {
			$s1 = $starts1[$i];
			$e1 = $ends1[$i];
			$s2 = $starts2[$j];
			$e2 = $ends2[$j];
			if (!(($s2 < $s1 && $e2 <= $s1)
			   || ($s1 < $s2 && $e1 <= $s2))) { //there's overlap
				if ($s2 <= $s1 && $e2 <= $e1) {
					$starts1[$i] = $e2+1;
				} elseif ($s1 <= $s2 && $e1 <= $e2) {
					$ends1[$i] = $s2-1;
				} elseif ($s1 > $s2 && $e1 < $e2) {
					unset($starts1[$i]);
					unset($ends1[$i]);
					$i++;
					continue;
				} elseif ($s2 > $s1 && $e2 < $e1) {
					$t = array_search($i,array_keys($starts1)); //current array-offset
					array_splice($ends1,$t,0,$s2-1); //insert before $ends1[$i]
					$t++;
					array_splice($starts1,$t,0,$e2+1); //insert after $starts1[$i]
					$i = $t; //arrays have been re-keyed
					$ic++;
					continue;
				}
			}
			$t = $j;
			if ($ends2[$j] <= $ends1[$i]) {
				$j++;
			}
			if ($ends1[$i] <= $ends2[$t]) {
				$i++;
			}
		}
		foreach ($starts1 as $i=>$t) {
			if ($ends1[$i] == $t) {
				unset($starts1[$i]);
				unset($ends1[$i]);
			}
		}
		return array($starts1,$ends1);
	}

	/**
	BlockIntersects:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	Returns: 2-member array,
	 [0] = array of start-stamps for subblocks in both first-block and other-block
	 [1] = array of corresponding end-stamps
	 The arrays have corresponding numeric keys
	OR if nothing applies
	 [0] = FALSE
	 [1] = FALSE
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
			$nd1 = ($i < $ic) ? $ends1[$i] : PHP_INT_MAX;
			$st2 = ($j < $jc) ? $starts2[$j] : ~PHP_INT_MAX;
			$nd2 = ($j < $jc) ? $ends2[$j] : PHP_INT_MAX;
			if (($st1 >= $st2 && $st1 < $nd2) || ($st2 >= $st1 && $st2 < $nd1)) { //$st1..$nd1 overlaps with $st2..$nd2
				$bst = max($st1,$st2);
				$bnd = min($nd1,$nd2);
				$starts[] = $bst;
				$ends[] = $bnd;
				if ($bnd == $ends1[$i]) { //1-block is ended
					if (++$i == $ic) {
						$j++;
						break;
					}
				}
				if ($bnd == $ends2[$j]) { //2-block is ended
					if (++$j == $jc) {
						$i++;
						break;
					}
				}
			} elseif ($ends1[$i] <= $starts2[$j]) { //2-block starts at or after 1-block end
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
				//merge adjacent blocks
				$ic--;
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						$starts[$j] = $starts[$i];
						unset($starts[$i]);
						unset($ends[$i]);
					}
				}
				$starts = array_values($starts);
				$ends = array_values($ends);
			}
			return array($starts,$ends);
		}
		return array(FALSE,FALSE);
	}

	/**
	BlockIntersectsRuled:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	@rules2: array of corresponding rules, FALSE represents no rule
	Returns: 3-member array,
	 [0] = array of start-stamps for subblocks in both first-block and other-block
	 [1] = array of corresponding end-stamps
	 [2] = array of corresponding @rules2[] members
	 The arrays have corresponding numeric keys
	OR if nothing applies
	 [0] = FALSE
	 [1] = FALSE
	 [2] = FALSE
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
				$bst = max($st1,$st2);
				$bnd = min($nd1,$nd2);
				if ($rules2[$j]) {
					$starts[] = $bst;
					$ends[] = $bnd;
					$userules[] = $rules2[$j];
				}
				if ($bnd == $ends1[$i]) { //1-block block is ended
					if (++$i == $ic) {
						if ($bnd < $ends2[$j] && $rules2[$j]) {
							//rest of current 2-block
							$starts[] = $bnd;
							$ends[] = $ends2[$j];
							$userules[] = $rules2[$j];
						}
						$j++;
						break;
					}
				}
				if ($bnd == $ends2[$j]) { //2-block block is ended
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
				$starts = array_values($starts);
				$ends = array_values($ends);
				$userules = array_values($userules);
			}
			return array($starts,$ends,$userules);
		}
		return array(FALSE,FALSE,FALSE);
	}

	/**
	BlockIntersects2Ruled:
	@starts1: array of first-block start-stamps, sorted ascending
	@ends1: array of corresponding end-stamps
	@rules1: array of corresponding rules, FALSE represents no rule
	@starts2: array of other-block start-stamps, sorted ascending
	@ends2: array of corresponding end-stamps
	@rules2: array of corresponding rules, FALSE represents no rule
	Returns: 4-member array,
	 [0] = array of start-stamps for subblocks in both first-block and other-block
	 [1] = array of corresponding end-stamps
	 [2] = array of corresponding @rules1[] members, with NULL's where @rules1[] doesn't apply
	 [3] = array of corresponding @rules2[] members, with NULL's where @rules2[] doesn't apply
	 The arrays have corresponding numeric keys
	OR if nothing applies
	 [0] = FALSE
	 [1] = FALSE
	 [2] = FALSE
	 [3] = FALSE
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
				$bst = max($st1,$st2);
				$bnd = min($nd1,$nd2);
				if ($rules1[$i] || $rules2[$j]) {
					$starts[] = $bst;
					$ends[] = $bnd;
					$userules1[] = $rules1[$i]; //maybe FALSE
					$userules2[] = $rules2[$j]; //maybe FALSE
				}
				if ($bnd == $ends1[$i]) { //1-block block is ended
					if (++$i == $ic) {
						if ($bnd < $ends2[$j] && $rules2[$j]) {
							//rest of current 2-block
							$starts[] = $bnd;
							$ends[] = $ends2[$j];
							$userules1[] = NULL; //1-block N/A here
							$userules2[] = $rules2[$j];
						}
						$j++;
						break;
					}
				}
				if ($bnd == $ends2[$j]) { //2-block block is ended
					if (++$j == $jc) {
						if ($bnd < $ends1[$i] && $rules1[$i]) {
							//rest of current 1-block
							$starts[] = $bnd;
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
				$starts = array_values($starts);
				$ends = array_values($ends);
				$userules1 = array_values($userules1);
				$userules2 = array_values($userules2);
			}
			return array($starts,$ends,$userules1,$userules2);
		}
		return array(FALSE,FALSE,FALSE,FALSE);
	}

	//Interpret $dtrule into stamp-block(s) covering $st..$nd
	private function BlocksforCalendarRule(&$mod, $st, $nd, $dtrule, $idata)
	{
		$funcs = new WhenRules($mod);
		if ($funcs->ParseDescriptor($dtrule)) {
			$dts = new \DateTime('@'.$st,new \DateTimeZone('UTC'));
			$dte = clone $dts;
			$dte->setTimestamp($nd);
			$sunparms = $funcs->SunParms($idata);
			list($starts,$ends) = $funcs->GetBlocks($dts,$dte,$sunparms); //$defaultall FALSE
			if ($starts) {
				return array($starts,$ends);
			}
		}
		return array(FALSE,FALSE);
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
		[each] rule being a descriptor recognisable by WhenRuleLexer (or FALSE)
	Returns: 2-member array,
	 [0] = sorted array of block-start timestamps in @slotstart..@slotstart+@slotlen
	 [1] = array of respective block-end timestamps in @slotstart..@slotstart+@slotlen
	OR if nothing is relevant
	 [0] = FALSE
	 [1] = FALSE
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
		//TODO this must also support 'except' rules - subtract from blocks previously accepted
		while ($i < $ic) {
			if ($rules[$i]) { //something to interpret
				$st = reset($chkstarts);
				$nd = end($chkends);
				list($rulestarts,$ruleends) = $this->BlocksforCalendarRule($mod,$st,$nd,$rules[$i],$idata); //NOT default to entire current blocks
				if ($rulestarts) {
					list($rulestarts,$ruleends) = $this->BlockIntersects($chkstarts,$chkends,$rulestarts,$ruleends);
					if ($rulestarts) {
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
				$starts = array_values($starts);
				$ends = array_values($ends);
			}
			return array($starts,$ends);
		}
		return array(FALSE,FALSE);
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
		descriptor recognisable by WhenRuleLexer (or FALSE)
	Returns: 3-member array,
	 [0] = sorted array of block-start timestamps in @slotstart..@slotstart+@slotlen+1
	 [1] = array of respective block-end timestamps in @slotstart..@slotstart+@slotlen+1
	 [2] = array of respective members of @rules
	OR if nothing is relevant
	 [0] = FALSE
	 [1] = FALSE
	 [2] = FALSE
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
		$userules = array();

		//TODO this must also support 'except' rules - subtract from blocks previously accepted
		while ($i < $ic) {
			if ($rules[$i]) { //something to interpret
				$st = reset($chkstarts);
				$nd = end($chkends);
				list($rulestarts,$ruleends) = $this->BlocksforCalendarRule($mod,$bst,$bnd,$rules[$i]['feecondition'],$idata); //NOT default to entire current blocks
				if ($rulestarts) {
					list($rulestarts,$ruleends) = $this->BlockIntersects($chkstarts,$chkends,$rulestarts,$ruleends);
					if ($rulestarts) {
						foreach ($rulestarts as $j=>$st) {
							$starts[] = $st;
							$chkends[] = $st;
							$nd = $ruleends[$j];
							$ends[] = $nd;
							$chkstarts[] = $nd;
							$userules[] = $rules[$i];
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
				array_multisort($starts,SORT_ASC,SORT_NUMERIC,$ends,$userules);
				$ic--;
				for ($i=0; $i<$ic; $i++) {
					$j = $i+1;
					if ($ends[$i] >= $starts[$j]-1) {
						if ($userules[$i] == $userules[$j]) { //non-strict array comparison
							$starts[$j] = $starts[$i];
							unset($starts[$i]);
							unset($ends[$i]);
							unset($userules[$i]);
						}
					}
				}
				$starts = array_values($starts);
				$ends = array_values($ends);
				$userules = array_values($userules);
			}
			return array($starts,$ends,$userules);
		}
		return array(FALSE,FALSE,FALSE);
	}

	/**
	UserRuledBlocks:
	@slotstart: UTC timestamp for start of range
	@slotlen: length of range (seconds)
	@rules: single rule, or array of rules sorted in order of decreasing priority,
		[each] rule being an array with members 'slotlen','fee','feecondition',
		the latter being a rule for discimination among users
	Returns: 3-member array,
	 [0] = array of block-start timestamps all @slotstart
	 [1] = array of corresponding block-end timestamps all @slotstart+@slotlen+1
	 [2] = array of members of @rules, to apply to the corresponding (whole) block
	OR if @rules is FALSE
	 [0] = FALSE
	 [1] = FALSE
	 [2] = FALSE
	*/
	public function UserRuledBlocks($slotstart, $slotlen, $rules)
	{
		$nd = $slotstart + $slotlen + 1;
		if (is_array($rules)) {
			$starts = array();
			$ends = array();
			$userules = array();
			foreach ($rules as $one) {
				$starts[] = $slotstart;
				$ends[] = $nd;
				$userules[] = $one;
			}
			if ($starts)
				return array($starts,$ends,$userules);
		} elseif ($rules) {
			return array(array($slotstart),array($nd),array($rules));
		}
		return array(FALSE,FALSE,FALSE);
	}

	/**
	MergeBlocks:
	Coalesce and sort-ascending the timestamp-blocks represented in @starts and @ends.
	The arrays must be equal-sized, have numeric keys. Returned array keys may be
	non-contiguous.
	@starts: reference to array of block-start stamps, any order
	@ends: reference to array of corresponding block-end stamps, no FALSE value(s)
	*/
	public function MergeBlocks(&$starts, &$ends)
	{
		$c = count($starts);
		if ($c > 1) {
			$p = 0;
			$q = 1;
			while (1) {
				if ($q >= $c)
					return;
				if ($starts[$q] >= $starts[$p]) {
					if ($ends[$q] <= $ends[$p]) {
						unset($starts[$q]);
						unset($ends[$q]);
					} elseif ($starts[$q] <= $ends[$p]) {
						$ends[$p] = $ends[$q];
						unset($starts[$q]);
						unset($ends[$q]);
					} else {
						//next base
						while ($p < $c) {
							$p++;
							if (array_key_exists($p,$starts))
								break;
						}
					}
				} else { //swap & resume (if possible from previous index)
					list($starts[$p],$starts[$q],$ends[$p],$ends[$q]) = array($starts[$q],$starts[$p],$ends[$q],$ends[$p]);
					$t = $p;
					while ($t > -1) { //base back if possible
						$t--;
						if (array_key_exists($t,$starts))
							break;
					}
					if ($t > -1)
						$p = $t;
					//for new comparator
					$q = $p;
				}
				//next comparator
				while ($q < $c) {
					$q++;
					if (array_key_exists($q,$starts))
						break;
				}
			}
		}
	}
}
