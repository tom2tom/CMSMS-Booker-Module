<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Display
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Display
{
	private $mod; //Booker module-object reference
	private $utils; //Utils-class object
	protected $Langcache = array(); //translated-strings cache
	protected $Usercache = array(); //user-names cache

	public function __construct(&$mod)
	{
		$this->mod = $mod;
		$this->utils = new Utils();
	}

	/*
	DisplayTimes:
	@dtw: DateTime worker-object for local manipulations
	@bs: timestamp for start of 1st day of report period
	@be: stamp for end of last day of report period
	@$seglen: enum 0..2 representing table-column spanning day,week,month
	@$slen: booking slot length (seconds)
	@starts: ascending-sorted array of start-available-block timestamps, maybe empty
	@ends: array of corresponding end-block timestamps, likewise maybe empty
	Returns: array with 4 members, 3 of them being a seconds-offset
	 [0] = min offset i.e. from any seg start to 1st available block start in that seg
	 [1] = max seg-offset i.e. from any seg start to end of last available block in that segment
	 [2] = max period-offset i.e. from @bs to end of last available block in the period
	AND
	 [3] = array of 0-based indices of segments/columns not available, to exclude from the display
	*/
	private function DisplayTimes($dtw, $bs, $be, $seglen, $slen, $starts, $ends)
	{
		$dtw->setTimestamp($bs);
		$rels = array('+1 day','+7 days','+1 month');
		$offs = $rels[$seglen];

		if ($starts) {
			$ob = PHP_INT_MAX;
			$oe = 0;
			$oa = $bs+1;
			$si = 0;
			$skips = array();
			$blocks = new Blocks();
			$dte = clone $dtw;
			$dte->setTimestamp($be);
			while ($dtw < $dte) {
				$ss = $dtw->getTimestamp();
				$segs = array($ss);
				$dtw->modify($offs);
				$se = $dtw->getTimestamp()-1;
				$sege = array($se);
				list($chks,$chke) = $blocks->DiffBlocks($segs,$sege,$starts,$ends);
				if ($segs != $chks || $sege != $chke || !($chks || $chke)) {
					$d = reset($chke) - $ss;
					$d = (int)($d/$slen) * $slen;
					if ($d < $ob) {
						$ob = $d;
					}
					$oa = end($chks);
					$d = (int)(ceil(($oa - $ss) / $slen) * $slen);
					if ($d > $oe) {
						$oe = $d;
					}
				} else {
					$skips[] = $si;
				}
				$si++;
			}
			if ($ob < PHP_INT_MAX)
				$ob++;
			if ($oe > 0)
				$oe--;
			$oa -= $bs - 1;
		} else { //whole period is available
			$ob = 0;
			$dtw->modify($offs);
			$oe = $dtw->getTimestamp() - $bs - 1;
			$oa = $be;
			$skips = array();
		}
		return array($ob,$oe,$oa,$skips);
	}

	/*
	GetSlotNames:
	@idata: reference to data array for item being processed
	@dtw: DateTime worker-object for local manipulations
	@bs: timestamp for start of 1st day of total report period
	@$seglen: enum 0..2 representing report-segment length (e.g. per table column)
	@offst: seconds-offset from segment start to start of 1st row (from DisplayTimes())
	@offnd: seconds-offset from segment start to end of last row
	@slen: booking slot length (seconds)
	@celloff: string representing cell coverage: '' for @slen, otherwise DateTime modifier '+1 X'
	Returns: array with a member for each relevant booking slot for the report segment
	*/
	private function GetSlotNames(&$idata, $dtw, $bs, $offst, $offnd, $seglen, $slen, $celloff)
	{
		$dtw->setTimestamp($bs);
		$dt2 = clone $dtw;

		switch ($seglen) {
		 case \Booker::SEGDAY: //day-per-column
			$dt2->modify('+1 day'); //segment limit
			$fmt = $idata['timeformat'] ? $idata['timeformat'] : 'G:i';
			break;
		 case \Booker::SEGWEEK: //week-per-column
			$t = $dtw->format('w');
			if ($t > 0) //Sunday start
				$dtw->modify('-'.$t.' days'); //segment start
			$base = $dtw->format('Y-m-d');
			$dt2->modify($base.' +7 days'); //segment limit
			$fmt = $idata['dateformat'] ? $idata['dateformat'] : 'j M';
			$shortday = (strpos($fmt,'D') !== FALSE);
			$daynames = $this->utils->DayNames($this->mod,range(0,6),$shortday);
			break;
		 case \Booker::SEGMTH: //month-per-column
			$t = $dtw->format('Y-m');
			$dtw->modify($t.'-1 0:0:0');
			$dt2->modify($t.'-1 0:0:0 +31 days'); //in this context, assume each reported month has max. # days
			break;
		}

		$esl = $dt2->getTimestamp() - $bs; //effective slot = whole-period
		if ($slen > $esl)
			$slen = $esl; //limit 'effective-slot' to report-segment-length
		$ss = $dtw->getTimestamp();
		$se = $ss + $offnd;
		$ss += $offst;
		$cells = array();

		while ($ss < $se) {
			$dt2->setTimestamp($ss);
			$one = new \stdClass();
			switch ($seglen) {
			 case \Booker::SEGDAY:
				$one->data = $dt2->format($fmt);
				$one->iso = $dt2->format(' G:i'); //space essential
				break;
			 case \Booker::SEGWEEK:
				$t = $dt2->format('w'); //day of week 0..6
				$one->data = $daynames[$t];
				$one->iso = $dt2->format('-d');
				break;
			 case \Booker::SEGMTH:
				$t = count($cells) + 1; //next integer = day of (longest) month 1..31
				$one->data = sprintf('%2d',$t);
				$one->iso = $dt2->format('-d');
				break;
			}
			$one->style = 'class="slotname"';
			$cells[] = $one;

			if ($celloff) {
				$dt2->modify($celloff);
				$ss = $dt2->getTimestamp();
			} else
				$ss += $slen;
		}
		return $cells;
	}

	/*
	GetTitles:
	Populate array of column-titles
	@idata: reference to array of data for item as per table-record, with inherited data where available
	@dtw: DateTime worker-object for local manipulations
	@bs: UTC timestamp for start of 1st day of period for which titles are wanted
	@be: corresponding timestamp for end of last day (NOT 1-past)
	@range: enum 0..3 representing the total span of the report period
	@seglen: enum 0..2 representing the duration of each report-segment (e.g. column)
	@skips: array of 0-based indices of columns to be omitted, or FALSE
	Returns: 2-member array,
	 [0] = array of column-title strings for public display, possibly empty
	 [1] = array of corresponding date-strings as YYYY-MM-DD, for lookup upon column-click, maybe empty
	*/
	private function GetTitles(&$idata, $dtw, $bs, $be, $range, $seglen, $skips)
	{
		$dtw->setTimestamp($bs);
		$dte = clone $dtw;
		$dte->setTimestamp($be);
		$titles = array();
		$fmt = $idata['dateformat'] ? $idata['dateformat'] : 'j M';
		$shortday = (strpos($fmt,'D') !== FALSE);
		$longday = (strpos($fmt,'l') !== FALSE);
		$isos = array();
		$si = 0;
		switch ($range) {
		 case \Booker::RANGEDAY: //single-day-view
		 	if (!$skips || !in_array($si,$skips)) {
				$t = $dtw->format('w'); //0 (for Sunday) .. 6 (for Saturday)
				$d = $this->utils->DayNames($this->mod,$t,$shortday);
				if ($shortday)
					$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
				if ($longday)
					$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
				$titles[] = $d.'<br />'.$dtw->format($fmt);
				$isos[] = $dtw->format('Y-m-d'); //rows must append ' G:i'
			}
			break;
		 case \Booker::RANGEWEEK: //week-view
			$names = $this->utils->DayNames($this->mod,range(0,6),$shortday);
			if ($shortday)
				$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
			if ($longday)
				$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
			$t = $dtw->format('w'); //0 (for Sunday) .. 6 (for Saturday)
			$t1 = $t;
			$inc = new \DateInterval('P1D');
			do {
			 	if (!$skips || !in_array($si,$skips)) {
					$d = $names[$t];
					$titles[] = $d.'<br />'.$dtw->format($fmt);
					$isos[] = $dtw->format('Y-m-d');
				}
				$t++;
				if ($t > 6)
					$t = 0;
				$dtw->add($inc);
				$si++;
			} while ($t != $t1);
			break;
		 case \Booker::RANGEMTH: //month-view
			if ($seglen == \Booker::SEGDAY) { //day-per-column
				//show individual days
				$names = $this->utils->DayNames($this->mod,range(0,6),TRUE); //for 30-ish cols, force short name
				if ($shortday)
					$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
				if ($longday)
					$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
				$t = $dtw->format('j'); //1 to 31
				$l = $dtw->format('t'); //28 to 31
				$t1 = $t;
				$inc = new \DateInterval('P1D');
				do {
				 	if (!$skips || !in_array($si,$skips)) {
						$d = $dtw->format('w');
						$titles[] = $names[$d].'<br />'.$dtw->format($fmt);
						$isos[] = $dtw->format('Y-m-d');
					}
					$t++;
					if ($t > $l)
						$t = 1;
					$dtw->add($inc);
					$si++;
				} while ($t != $t1);
			} else { //$seglen == \Booker::SEGWEEK, week-per-column
				if ($shortday)
					$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
				if ($longday)
					$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
				//may need custom title for start of week including $start
				$w = $dtw->format('w');
				switch ($w) {
				 case 0:
					break;
				 case 1:
					$dtw->modify('-1 day');
				 default:
					$dtw->modify('-'.$w.' days');
					break;
				}
				$inc = new \DateInterval('P7D');
				do {
				 	if (!$skips || !in_array($si,$skips)) {
						$titles[] = $dtw->format($fmt);
						$isos[] = $dtw->format('Y-m');  //rows must append '-d'
					}
					$dtw->add($inc);
					$si++;
				} while ($dtw < $dte);
			}
			break;
		 case \Booker::RANGEYR: //year-view
			if ($seglen == \Booker::SEGWEEK) { //week-per-column
				$w = $dtw->format('w');
				switch ($w) {
					case 0:
						break;
					case 1:
						$dtw->modify('-1 day');
					default:
						$dtw->modify('-'.$w.' days');
						break;
				}
				$inc = new \DateInterval('P7D');
				do {
					$titles[] = $dtw->format($fmt);
					$isos[] = $dtw>format('Y-m');
					$dtw->add($inc);
				} while ($dtw < $dte);
			} else { //$seglen == \Booker::SEGMTH, month-per-column
				$shortmonth = (strpos($fmt,'M') !== FALSE);
				//$longmonth = (strpos($fmt,'F') !== FALSE);
				$names = $this->utils->MonthNames($this->mod,range(1,12),$shortmonth);
				$t = $dtw->format('n'); //1 to 12
				$inc = new \DateInterval('P1M');
				do {
					$titles[] = $names[$t].'<br />'.$dtw->format('Y');
					$isos[] = $dtw->format('Y-m');
					$t++;
					if ($t > 12)
						$t = 1;
					$dtw->add($inc);
				} while ($dtw < $dte);
			}
			break;
		}
		return array($titles,$isos);
	}

	/*
	DocumentCell:
	Get object populated with data for a table-cell.

	@idata: reference to array of resource parameters
	@dt: UTC DateTime object
	@ss: UTC timestamp for start of cell (usually also slot start)
	@se: corresponding end of cell
	@celloff: string representing cell coverage: '' for slotlength,
		otherwise DateTime modifier '+1 X'
	@countall: no. of usable resources for this cell
	@position: @iter's offset in the data array
	@iter: reference to valid ArrayIterator for whole data array whose
		contents are sorted (first) by booking-start ASC
	@ufuncs: reference to Userops-class object
	@blocks: reference to Blocks-class object
	Returns: 2-member array:
		[0] = object with properties for the cell
		[1] = value of @position to use in next call
	*/
	private function DocumentCell(&$idata, $dt, $ss, $se, $celloff, $countall, $position, &$iter, &$ufuncs, &$blocks)
	{
		if ($celloff) {
			$dtw = clone $dt; //preserve $dt
			$dtw->modify($celloff);
			$se = $dtw->getTimestamp();
		}

		$users = array(); //at most 2 members
		$displayclass = FALSE; //first-found class to be used
		$resources = array(); //id(s) actually booked
		$starts = array(); //booking-starts (automatically) sorted by increasing $bs
		$ends = array();
		$ipos = -1;
		//interrogate all bookings-data for the cell
		while ($iter->valid()) {
			$row = $iter->current();
			$bs = (int)$row['slotstart'];
			$be = $bs + $row['slotlen'];
			if ($bs <= $se - 20 && $be >= $ss + 20) {
				//log relevant booking-slots
				$starts[] = $bs;
				$ends[] = $be;
				//log distinct users until count users > 1, so we can report as 'multiple'
				if (!isset($users[1])) {
					$t = $row['booker_id'];
					if (!array_key_exists($t,$this->Usercache)) {
						$this->Usercache[$t] = $ufuncs->GetName($this->mod,$t); //TODO $row['user']
					}
					$n = $this->Usercache[$t];
					if (!in_array($n,$users)) {
						$users[] = $n;
						//log first-found displayclass
						if(!$displayclass)
							$displayclass = $ufuncs->GetDisplayClass($this->mod,$t); //TODO row['userclass']
					}
				}
				//log resource-name(s)
				if ($row['name'])
					$resources[$row['name']] = 1;
				if ($be > $se + 20) { //20-second slop
					//log back-seek position
					if ($ipos == -1) {
						$ipos = $position;
					}
				}
				if ($bs > $ss + 20) {
					break;
				}
				$iter->next();
				$position++;
			} else {
				break;
			}
		}
		if ($ipos != -1) {
			$iter->seek($ipos);
			$position = $ipos;
		}
		$one = new \stdClass();
		if ($starts) { //found booking(s)
			if (count($users) == 1) {
				$one->data = reset($users);
				$multi = FALSE;
			} else {
				$one->data = $this->Langcache['various'];
				$multi = TRUE;
			}
			if (count($resources) < $countall) {
				$one->data .= ' + '.$this->Langcache['vacancies'];
				$whole = FALSE;
			} else {
				$blocks->MergeBlocks($starts,$ends);
				if ((
					count($starts) < 2
				 && reset($starts) < $ss + 20 //20-second slop
				 && end($ends) > $se - 20
				)) {
					$whole = TRUE;
				} else {
					$one->data .= ' + '.$this->Langcache['vacancies'];
					$whole = FALSE;
				}
			}

			$one->tip = implode(',',array_keys($resources));

			if ($multi) {
//TODO	$one->bkgid = which one ?
				if ($whole)
					$one->style = 'class="fullm"';
				else
					$one->style = 'class="partm"';
			} else { //single-user
				$one->bkgid = (int)$row['bkg_id'];
				if (!$celloff) {
					$dtw = clone $dt; //preserve $dt
				}
				$dtw->setTimestamp($starts[0]);
				$d = $this->utils->IntervalFormat($this->mod,$dtw,$idata['dateformat']);
				$fmt = $idata['timeformat'];
				if (!$fmt)
					$fmt = 'G:i';
				$t1 = $dtw->format($fmt);
				$dtw->setTimestamp(end($ends));
				$t2 = $dtw->format($fmt);
				$one->tip .= '&#013;'.$d.'&#013;'.sprintf($this->Langcache['rangefmt'],$t1,$t2);

				$type = ($whole) ? 'full':'part';
				if ($displayclass)
					$one->style = 'class="'.$type.$displayclass.'"';
				else
					$one->style = 'class="'.$type.'"';
			}
		} else { //all vacant
			$one->data = NULL;
			$one->style = 'class="vacant"';
		}
		return array($one,$position);
	}

	/*
	FillTable:
	Populates array of data (for passing to smarty then template) representing cells
	in a table, to show bookings for a single resource over time interval represented
	by @range.
	Displayed times are localised from stored UTC values. No runtime availability checking.
	For intervals up to month, columns are for each day. For longer intevals, columns
	are for each month.
	@idata: array of data for item as per table-record, with inherited data where available
	@start: UTC timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year (per Utils::DisplayIntervals())
	Returns: array of columns' data, each member being an array of cells' data,
	 first column has slot-titles
	*/
	private function FillTable(&$idata, $start, $range)
	{
		$slotlen = $this->utils->GetInterval($this->mod,$idata['item_id'],'slot');
		list($dts,$dte) = $this->utils->GetRangeLimits($start,$range); //$dte represents 1-past end of wanted range
		$cc = 0;
		switch ($range) {
		 case \Booker::RANGEDAY:
			$cc = 1; //1 column
		 case \Booker::RANGEWEEK:
			if($cc == 0) $cc = 7; //7 columns
		 case \Booker::RANGEMTH:
			if($cc == 0) {
				$interval = $dts->diff($dte,TRUE);
				$cc = $interval->days;
			}
			$seglen = \Booker::SEGDAY; //table-column period one day
			//$celloff: cell-coverage '' = slot, otherwise DateTime modifier like '+1 X'
			$celloff = ($slotlen < 84600) ? '':'+1 day'; //each cell spans min(slotlen,report period)
			break;
		 case \Booker::RANGEYR:
			$cc = 12; //12 columns
			$seglen = \Booker::SEGMTH; //report divided into months
			$celloff = ($slotlen < 84600) ? '+1 day':''; //each cell :: min(day,slotlen)
			break;
		}
		switch ($seglen) {
		 case \Booker::SEGWEEK: //week-per-column
			$t = $dts->format('w');
			if ($t > 0) //Sunday start
				$dts->modify('-'.$t.' days');
			$t = $dte->format('w');
			if ($t > 0)
				$dte->modify('+'.(7-$t).' days');
			break;
		 case \Booker::SEGMTH: //month-per-column
			$c = $dts->format('j');
			if ($c > 1) {
				$t = $dts->format('Y-n');
				$dts->modify($t.'-1 0:0:0');
				$t = $dte->format('Y-n');
				$dte->modify($t.'-1 0:0:0 +1 month');
			}
			break;
		}

		$item_id = (int)$idata['item_id'];
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group) {
			$all = $this->utils->GetGroupItems($this->mod,$item_id);
		} else {
			$all = $item_id;
		}

		//update respective last-processed-repeats dates, if relevant
		$funcs = new Schedule();
		$bs = $dts->getTimestamp();
		$be = $dte->getTimestamp()-1;
		$funcs->UpdateRepeats($this->mod,$this->utils,$all,$bs,$be);

		//get availability-blocks
		$rules = $this->utils->GetOneHeritableProperty($this->mod,$item_id,'available');
		$rules = array_filter($rules); //omit empties
		if ($rules) {
			$funcs = new WhenRules($this->mod);
			$timeparms = $funcs->TimeParms($idata);
			list($starts,$ends) = $funcs->AllIntervals(reset($rules),$bs,$be,$timeparms); //proximal-rule-only, no ancestor-merging
		} else { //all available
			$starts = array();
			$ends = array();
		}
		$dtw = clone $dts;

		if ($range < \Booker::RANGEYR) {
			//get offsets of each column's top- and bottom-row
			list($segoffst,$segoffnd,$rangeoffnd,$skips) = self::DisplayTimes($dtw,$bs,$be,$seglen,$slotlen,$starts,$ends);
		} else { //nominal values
			$segoffst = 0;
			$segoffnd = 31*84600;
			$rangeoffnd = $segoffnd*366;
			$skips = FALSE;
		}
		$keeps = range(0,$cc-1);
		if ($skips) {
			$keeps = array_diff($keeps,$skips);
			if ($keeps) {
				$keeps = array_values($keeps);
			} else {
				return FALSE;
			}
		}

		//populate column of row-titles
		$cells = self::GetSlotNames($idata,$dtw,$bs,$segoffst,$segoffnd,$seglen,$slotlen,$celloff);

		//prepend top-left cell
		$one = new \stdClass();
		$one->data = NULL;
		$one->iso = NULL;
		$one->style = 'class="topleft"';
		array_unshift($cells,$one);
		$rc = count($cells); //includes header/titles row

		$columns = array();
		$columns[] = $cells;
		//populate titles array
		list($titles,$isos) = self::GetTitles($idata,$dtw,$bs,$be,$range,$seglen,$skips);
		$cc = count($titles);

		$funcs = new Bookingops();
		$booked = $funcs->GetTableBooked($this->mod,$all,$bs,$be);
		if ($booked) {
			$iter = new \ArrayIterator($booked);
			$position = 0; //init array-iterator-position
		} else {
			$iter = FALSE;
		}
		$countall = ($is_group) ? count($all):1;
		$funcs = new Userops($this->mod);
		$blocks = new Blocks();

		if (!array_key_exists('rangefmt',$this->Langcache)) {
			//cached lookups for FillCell()
			$this->Langcache['rangefmt'] = $this->mod->Lang('showrange');
			$this->Langcache['vacancies'] = $this->mod->Lang('title_vacancies');
			$this->Langcache['various'] = $this->mod->Lang('title_various');
		}

		$rels = array('+1 day','+7 days','+1 month','+1 year');
		$offs = $rels[$seglen]; //column-adjuster

		//other column(s)
		for ($c = 0; $c < $cc; $c++) {
			$cells = array();
			//title
			$one = new \stdClass();
			$one->data = $titles[$c];
			$one->iso = $isos[$c];
			$one->style = 'class="periodname"';
			$cells[] = $one;
			if ($c > 0) {
				//forward to next displayed-segment start
				for ($i=$keeps[$c] - $keeps[$c-1]; $i>0; $i--) {
					$dts->modify($offs);
				}
			}
			$bs = $dts->getTimestamp(); //start-stamp for current segment
			$bs += $segoffst; //start-stamp for 1st displayed slot in segment
			//iterate slots for this segment
			for ($r = 1; $r < $rc; $r++) {
				$be = $bs + $slotlen - 1; //end-stamp of current slot, maybe < end-of-cell
				$dtw->setTimestamp($bs);
				if ($iter && $iter->valid()) {
					list($one,$position) = self::DocumentCell(
						$idata,$dtw,$bs,$be,$celloff,$countall,$position,$iter,$funcs,$blocks);
				} else {
					$one = new \stdClass();
					$one->data = NULL;
					$one->style = 'class="vacant"';
				}
				$cells[] = $one;
				//skip to next cell start
				if ($celloff) {
					$dtw->modify($celloff);
					$bs = $dtw->getTimestamp();
				} else {
					$bs += $slotlen;
				}
			}
			$columns[] = $cells;
		}
		return $columns;
	}

	/*
	TextInterval:
	Get formatted datetime-interval string, primarily for list-display
	@dts: localised DateTime representing start of booking
	@dte: ditto for end of booking
	@range: segment-length enum 0 .. 3
	@majr_fmt: PHP date() format for possibly-shared (and so, not to be duplicated) component of reported string
	@minr_fmt: ditto for always-used component of returned string
	@rangefmt: PHP sprintf() format for returning like 'start to end'
	@$timegroup: boolean, TRUE for time-grouped segments >> major_part in string
	Returns: the string
	*/
	private function TextInterval($dts, $dte, $range, $majr_fmt, $minr_fmt, $rangefmt, $timegroup)
	{
		$st = $dts->format($majr_fmt);
		$nd = $dte->format($majr_fmt);
		$st2 = $dts->format($minr_fmt);
		$nd2 = $dte->format($minr_fmt);
		if ($st == $nd) {
			switch ($range) {
			 case \Booker::RANGEYR:
				return sprintf($rangefmt,$st.' '.$st2,$nd2);
			 case \Booker::RANGEMTH:
			 case \Booker::RANGEWEEK:
				if (!$timegroup)
					return sprintf($rangefmt,$st.' '.$st2,$nd2);
				else
					return sprintf($rangefmt,$st2,$nd2);
			 case \Booker::RANGEDAY:
				return sprintf($rangefmt,$st2,$nd2);
			}
		}
		return sprintf($rangefmt,$st.' '.$st2,$nd.' '.$nd2);
	}

	/*
	FillList:
	@idata: array of data for resource or group as per table-record, with inherited data where available
	@start: timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year (per Utils::DisplayIntervals())
	Returns: array of sections' data, each member being an object with array of text-rows
	*/
	private function FillList(&$idata, $start, $range)
	{
		list($dts,$dte) = $this->utils->GetRangeLimits($start,$range);
		$item_id = (int)$idata['item_id'];
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group) {
			$all = $this->utils->GetGroupItems($this->mod,$item_id);
		} else {
			$all = $item_id;
		}
		//update respective last-processed-repeats dates, if relevant
		$funcs = new Schedule();
		$bs = $dts->getTimestamp();
		$be = $dte->getTimestamp()-1;
		$funcs->UpdateRepeats($this->mod,$this->utils,$all,$bs,$be);
		$funcs = new Bookingops();
		$lfmt = (int)$idata['listformat'];
		$booked = $funcs->GetListBooked($this->mod,$is_group,$allresource,$lfmt,$bs,$be-1);
		if ($booked) {
			$majr_fmt = $idata['dateformat']; //part of report  //c.f. Utils::IntervalFormat($mod,$format,$dts)
			$minr_fmt = $idata['timeformat']; //other part
			$rangefmt = $this->mod->Lang('showrange');
			switch ($lfmt) {
			 case \Booker::LISTUS:
			 case \Booker::LISTUR:
				$tkey = 'name';
				break;
			 case \Booker::LISTRS:
				$tkey = 'what';
				break;
//			 case \Booker::LISTSU:
			 default:
				$tkey = 'slotstart';
				switch ($range) {
				 case \Booker::RANGEDAY:
				 case \Booker::RANGEWEEK:
				 case \Booker::RANGEMTH:
 					$hfmt = $idata['dateformat']; //title-format, group by day  //c.f. Utils::IntervalFormat($mod,$format,$dts)
					break;
				 case \Booker::RANGEYR:
					$hfmt = 'n'; //group by month
					break;
				}
			}
			//merge adjacent slots
			$utils = new Utils();
			$propstore = array();
			$ic = count($booked);
			for($i=0; $i<$ic; $i++) {
				$one = $booked[$i];
				$idi = $one['item_id'];
				$ssi = $one['slotstart'];
				$sei = $ssi + $one['slotlen'];
				$bki = $one['booker_id'];
				for ($j=$i+1; $j<$ic; $j++) {
					$other = $booked[$j];
					if ($idi != $other['item_id'] || $bki != $other['booker_id']) {
						break;
					}
					if (!isset($propstore[$idi])) {
						$propstore[$idi] = ($utils->GetInterval($this->mod,$item_id,'slot') >= 84600);
					}
					if ($propstore[$idi]) {
						$sei = $other['slotstart'] + $other['slotlen'];
						unset($booked[$j]);
					} else {
						$ssj = $other['slotstart'];
						if ($ssj < $sei + 200) { //slots sufficiently contiguous
							$sei = $ssj + $other['slotlen'];
							unset($booked[$j]);
						} else {
							break;
						}
					}
				}
				$i = $j-1;
				$one['slotlen'] = $sei - $ssi;
			}

			$sections = array();
			$title = chr(2).chr(3); //anything unused, not empty
			$oneset = FALSE;
			foreach ($booked as &$one) {
				$dts->setTimestamp($one['slotstart']);
				if ($tkey == 'slotstart')
					$t = $dts->format($hfmt);
				else
					$t = $one[$tkey];
				if ($t != $title) {
					if ($oneset) {
						$oneset->rows = $rows;
						$sections[] = $oneset;
					}
					$title = $t;
					$oneset = new \stdClass();
					if ($tkey != 'slotstart' || $range > \Booker::RANGEDAY) {
						if ($tkey == 'slotstart' && $range == \Booker::RANGEYR) //year special case
							$oneset->ttl = $dts->format('F Y'); //TODO translated month-name
						else
							$oneset->ttl = $t;
					} else
						$oneset->ttl = ''; //no need for repeated date for a single day
					$rows = array();
				}
				//populate
				$is_group = ($one['item_id'] >= \Booker::MINGRPID);
				$dte->setTimestamp($one['slotstart'] + $one['slotlen']);
				$t = self::TextInterval($dts,$dte,$range,$majr_fmt,$minr_fmt,$rangefmt,($tkey == 'slotstart'));
				switch ($lfmt) {
				 case \Booker::LISTUS:
					$txt = $t;
					if ($is_group) $txt .= ' :: '.$one['what'];
					break;
				 case \Booker::LISTUR:
					$txt = $one['what'].' :: '.$t;
					break;
				 case \Booker::LISTRS:
					$txt = $t;
					if ($is_group) $txt .= ' :: '.$one['what'];
					$txt .= ' :: '.	$one['name'];
					break;
//				 case \Booker::LISTSU:
				 default:
					$txt = $t;
					if ($is_group) $txt .= ' :: '.$one['what'];
					$txt .= ' :: '.$one['name'];
					break;
				}
				$rows[] = $txt;
			}
			unset($one);
			if ($oneset) {
				$oneset->rows = $rows;
				$sections[] = $oneset;
			}
			return $sections;
		}
		return FALSE;
	}

	/**
	Tabulate:
	Populate @smarty vars for display of tabulated bookings-data for relevant range
	@tplvars: reference to array of template variables
	@idata: array of data for item as per table-record, with inherited data where available
	@start: UTC timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year
	*/
	public function Tabulate(&$tplvars, &$idata, $start, $range)
	{
		$columns = self::FillTable($idata,$start,$range);
		if ($columns) {
			$tplvars['columns'] = $columns;
			$tplvars['colcount'] = count($columns);
			$tplvars['rowcount'] = count(reset($columns));
			switch ($range) {
			 case \Booker::RANGEDAY:
				$tc = 'daily';
				break;
			 case \Booker::RANGEWEEK:
				$tc = 'weekly';
				break;
			 case \Booker::RANGEMTH:
				$tc = 'monthly';
				break;
			 case \Booker::RANGEYR:
				$tc = 'yearly';
				break;
			}
			$tplvars['tableclass'] = $tc;
		} else {
			$tplvars['nobookings'] = $this->mod->Lang('nodata'); //should never happen
		}
	}

	/**
	Listify:
	Populate @smarty vars for display of list-style bookings-data for relevant range
	@tplvars: reference to array of template variables
	@idata: array of data for item as per table-record, with inherited data where available
	@start: UTC timestamp for start of first day to be reported
	@range: enum 0..3 indicating span of report day..year
	*/
	public function Listify(&$tplvars, &$idata, $start, $range)
	{
		$sections = self::FillList($idata,$start,$range);
		$tplvars['sections'] = $sections; //maybe empty
		if (!$sections)
			$tplvars['nobookings'] = $this->mod->Lang('nodata');
	}

}
