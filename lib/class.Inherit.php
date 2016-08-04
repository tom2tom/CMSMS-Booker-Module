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
