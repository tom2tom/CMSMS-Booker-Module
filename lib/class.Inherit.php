<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Inherit - functions for dealing with property-inheritance
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Inherit
{
	//TODO consider migrating some of the Utils getters - GetItemProperty() etc

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
				if ($ndb = $ends1[$i]) { //1-block block is ended
					if (++$i == $ic) {
						$j++;
						break;
					}
				}
				if ($ndb = $ends2[$j]) { //2-block block is ended
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
				if ($ndb = $ends1[$i]) { //1-block block is ended
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
				if ($ndb = $ends2[$j]) { //2-block block is ended
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
				if ($ndb = $ends1[$i]) { //1-block block is ended
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
				if ($ndb = $ends2[$j]) { //2-block block is ended
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

	/**
	RangeInherit:
	@slotstart: UTC timestamp for start of range
	@slotlen: length of range (seconds)
	@rules: single rule, or array of rules sorted in order of decreasing priority,
		[each] rule being a date/time 'condition'
	Returns: 2-member array,
		[0] has sorted block-start timestamps in @slotstart..@slotstart+@slotlen
		[1] has respective block-end timestamps in @slotstart..@slotstart+@slotlen
	OR returns FALSE if nothing is relevant
	*/
	public function RangeInherit($slotstart, $slotlen, $rules)
	{
		$starts = array();
		$ends = array();
		//DEBUG
		$starts[] = $slotstart;
		$ends[] = $slotstart+$slotlen+1;

		if (!is_array($rules)) {
			$rules = array($rules);
		}
		foreach ($rules as $one) {
		/* TODO
		get blocks in $slotstart..$slotstart+$slotlen not yet recorded and
			covered by condition $one
		record those in $starts[] and $ends[]
		? cleanup $starts[] and $ends[] to facilitate next inclusion-check
		*/
		}
		if ($starts) {
			if (count($starts) > 1) {
				array_multisort($starts,SORT_ASC,SORT_NUMERIC,$ends);
				//? extend almost-adjacent blocks before & after midnight
				//TODO coalesce adjacent blocks
				//c.f. Display::Coalesce($slots) & Repeats::MergeBlocks($starts,$ends)
			}
			return array($starts,$ends);
		}
		return FALSE;
	}

	/**
	RangeInheritRuled:
	@slotstart: UTC timestamp for start of range
	@slotlen: length of range (seconds)
	@id: identifier for rule-comparisons e.g. item_id, booker_id
	@rules: single rule, or array of rules sorted in order of decreasing priority,
		[each] rule being an array with members 'slotlen','fee','feecondition'
	 'slotlen' = -1 signals a fixed fee, otherwise it's the no. of seconds that
	 a payment of 'fee' covers
	Returns: 3-member array,
		[0] has sorted block-start timestamps in @slotstart..@slotstart+@slotlen
		[1] has respective block-end timestamps in @slotstart..@slotstart+@slotlen
		[2] has respective rules that apply from [0] to [1]-1, inclusive
	OR returns FALSE if nothing is relevant
	*/
	public function RangeInheritRuled($slotstart, $slotlen, $id, $rules)
	{
		$starts = array();
		$ends = array();
		$blkrules = array();
		//DEBUG
		$starts[] = $slotstart;
		$ends[] = $slotstart+$slotlen+1;
		$blkrules[] = 20.0;

		if (!is_array($rules)) {
			$rules = array($rules);
		}
		foreach ($rules as $one) {
		/* TODO
		get blocks in $slotstart..$slotstart+$slotlen not yet recorded and
			covered by $one['feecondition']
		record those in $starts[] and $ends[]
		AND if $one['feecondition'] is date/time related, $blkrules[] = $blocklen/$one['slotlen']*$one['fee'])
		OR if $one['feecondition'] is id-related, $blkrules[] = func($one['feecondition'],$id)
		? cleanup arrays to facilitate next inclusion-check
		*/
		}
		if ($starts) {
			if (count($starts) > 1) {
				array_multisort($starts,SORT_ASC,SORT_NUMERIC,$ends,$blkrules);
				//? extend almost-adjacent blocks before & after midnight
				//TODO coalesce adjacent blocks with same condition
				//c.f. Display::Coalesce($slots) & Repeats::MergeBlocks($starts,$ends)
			}
			return array($starts,$ends,$blkrules);
		}
		return FALSE;
	}
}
