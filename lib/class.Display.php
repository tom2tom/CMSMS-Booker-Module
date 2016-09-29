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
	private $rangefmt; //cache for translated string used in cell-tips

	public function __construct(&$mod)
	{
		$this->mod = $mod;
		$this->utils = new Utils();
	}

	/*
	GetLimits:
	@dts: datetime object representing start of 1st day of total report period
	@dte: datetime object representing start of 1st day AFTER total report period
	@$seglen: enum 0..3 representing report-segment length (i.e. per table column)
	@$slen: booking slot length (seconds)
	@starts: ascending-sorted array of start-available-block timestamps, maybe empty
	@ends: array of corresponding end-block timestamps, likewise maybe empty
	Returns: array with 3 members, each a seconds-offset
	 [0] = min offset i.e. from any seg start to 1st available block start in that seg
	 [1] = max seg-offset i.e. from any seg start to end of last available block in that segment
	 [2] = max period-offset i.e. from @dts to end of last available block in the period
	*/
	private function GetLimits($dts, $dte, $seglen, $slen, $starts, $ends)
	{
		$rels = array('+1 day','+7 days','+1 month','+1 year');
		$offs = $rels[$seglen];
		$dtw = clone $dts;

		if ($starts) {
			$segs = array();
			$sege = array();
			while ($dtw < $dte) {
				$segs[] = $dtw->getTimestamp();
				$dtw->modify($offs);
				$sege[] = $dtw->getTimestamp()-1;
			}

			$biggest = $sege[0] - $segs[0];
			$ob = $biggest;
			$oe = 0;
			$blocks = new Blocks();

			list($nots,$note) = $blocks->DiffBlocks($segs,$sege,$starts,$ends);
			$iter = new \ArrayIterator($note);
			foreach ($segs as $i=>$st) {
				//get smallest $note[] member > $st
				while ($iter->valid()) {
					$t = $iter->current();
					if ($t > $st) {
						if ($t < $sege[$i]) {
							$t -= $st;
							if ($t < $ob)
								$ob = $t;
						} else {
							$ob = 0;
						}
						break;
					}
					$iter->next();
				}
				//get biggest $nots[] member < $sege[$i]
				$st = $sege[$i];
				while ($iter->valid()) {
					$t = $iter->current();
					if ($t > $st) {
						$oe = $biggest;
						break;
					} elseif ($t == $st) {
						$j = $iter->key();
						$t = $nots[$j] - $segs[$i];
						if ($t > $oe)
							$oe = $t;
						break;
					}
					$iter->next();
				}
				if (!$iter->valid()) { //no more limits
					$j = end(array_keys($segs));
					if ($i != $j) { //more segment(s)
						$ob = 0;
						$oe = $biggest;
					}
					break;
				}
			}
			if ($ob < $biggest)
				$ob++;
			if ($oe > 0)
				$oe--;
			$oa = end($nots) - $segs[0];
		} else { //whole period is available
			$ob = 0;
			$st = $dts->getTimestamp();
			$dtw->modify($offs);
			$oe = $dtw->getTimestamp() - $st - 1;
			$oa = $dte->getTimestamp() - 1;
		}
		return array($ob,$oe,$oa);
	}

	/*
	GetSlotNames:
	@idata: reference to data array for item being processed
	@dts: datetime object representing start of 1st day of total report period
	@$seglen: enum 0..2 representing report-segment length (e.g. per table column)
	@offst: seconds-offset from segment start to start of 1st row (from GetLimits())
	@offnd: seconds-offset from segment start to one-past end of last row (OR maybe == end)
	@slen: booking slot length (seconds)
	@celloff: string representing cell coverage: '' for @slen, otherwise DateTime modifier '+1 X'
	Returns: array with a member for each relevant booking slot for the report segment
	*/
	private function GetSlotNames(&$idata, $dts, $offst, $offnd, $seglen, $slen, $celloff)
	{
		$dtw = clone $dts;
		$dt2 = clone $dts;

		switch ($seglen) {
		 case \Booker::SEGDAY: //day-per-column
			$dt2->modify('+1 day'); //segment limit
			$fmt = $idata['timeformat'] ? $idata['timeformat'] : 'G:i';
			break;
		 case \Booker::SEGWEEK: //week-per-column
			$t = $dts->format('w');
			if ($t > 0) //Sunday start
				$dtw->modify('-'.$t.' days'); //segment start
			$base = $dtw->format('Y-m-d');
			$dt2->modify($base.' +7 days'); //segment limit
			$fmt = $idata['dateformat'] ? $idata['dateformat'] : 'j M';
			$shortday = (strpos($fmt,'D') !== FALSE);
			$daynames = $this->utils->DayNames($this->mod,range(0,6),$shortday);
			break;
		 case \Booker::SEGMTH: //month-per-column
			$t = $dts->format('Y-m');
			$dtw->modify($t.'-1 0:0:0');
			$dt2->modify($t.'-1 0:0:0 +31 days'); //in this context, assume each reported month has max. # days
			break;
		}

		$esl = $dt2->getTimestamp() - $dts->getTimestamp(); //effective slot = whole-period
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
				$one->iso = $dt2->format(' G:i');
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
	@dts: datetime object representing start of 1st day of period for which titles are wanted
	@dte: datetime object representing 1-past last day
	@range: enum 0..3 representing the total span of the report period
	@seglen: enum 0..2 representing the duration of each report-segment (e.g. column)
	Returns: 2-member array,
	 [0] = array of column-title strings for public display
	 [1] = array of corresponding date-strings as YYYY-MM-DD, for lookup upon column-click
	*/
	private function GetTitles(&$idata, $dts, $dte, $range, $seglen)
	{
		$titles = array();
		$fmt = $idata['dateformat'] ? $idata['dateformat'] : 'j M';
		$shortday = (strpos($fmt,'D') !== FALSE);
		$longday = (strpos($fmt,'l') !== FALSE);
		$isos = array();
		switch ($range) {
		 case \Booker::RANGEDAY: //single-day-view
			$t = $dts->format('w'); //0 (for Sunday) .. 6 (for Saturday)
			$d = $this->utils->DayNames($this->mod,$t,$shortday);
			if ($shortday)
				$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
			if ($longday)
				$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
			$titles[] = $d.'<br />'.$dts->format($fmt);
			$isos[] = $dts->format('Y-m-d'); //rows must append ' G:i'
			break;
		 case \Booker::RANGEWEEK: //week-view
			$names = $this->utils->DayNames($this->mod,range(0,6),$shortday);
			if ($shortday)
				$fmt = preg_replace('/(?<!\\\)D,?\w*/','',$fmt);
			if ($longday)
				$fmt = preg_replace('/(?<!\\\)l,?\w*/','',$fmt);
			$dtw = clone $dts; //preserve $dts
			$t = $dtw->format('w'); //0 (for Sunday) .. 6 (for Saturday)
			$t1 = $t;
			$inc = new \DateInterval('P1D');
			do {
				$d = $names[$t];
				$titles[] = $d.'<br />'.$dtw->format($fmt);
				$isos[] = $dtw->format('Y-m-d');
				$t++;
				if ($t > 6)
					$t = 0;
				$dtw->add($inc);
			} while ($t != $t1);
			break;
		 case \Booker::RANGEMTH: //month-view
			$dtw = clone $dts; //preserve $dts
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
					$d = $dtw->format('w');
					$titles[] = $names[$d].'<br />'.$dtw->format($fmt);
					$isos[] = $dtw->format('Y-m-d');
					$t++;
					if ($t > $l)
						$t = 1;
					$dtw->add($inc);
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
					$titles[] = $dtw->format($fmt);
					$isos[] = $dtw->format('Y-m');  //rows must append '-d'
					$dtw->add($inc);
				} while ($dtw < $dte);
			}
			break;
		 case \Booker::RANGEYR: //year-view
			$dtw = clone $dts;
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
					$n = $ufuncs->GetName($this->mod,$t); //TODO $row['user']
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
				$one->data = $this->mod->Lang('title_various');
				$multi = TRUE;
			}
			if (count($resources) < $countall) {
				$one->data .= ' + '.$this->mod->Lang('title_vacancies');
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
					$one->data .= ' + '.$this->mod->Lang('title_vacancies');
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
				$one->tip .= '&#013;'.$d.'&#013;'.sprintf($this->rangefmt,$t1,$t2);

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
		list($dts,$dte) = $this->utils->RangeStamps($start,$range); //$dte represents 1-past end of wanted range
		switch ($range) {
		 case \Booker::RANGEDAY:
		 case \Booker::RANGEWEEK:
		 case \Booker::RANGEMTH:
			$seglen = \Booker::SEGDAY; //table-column period one day
			//$celloff: cell-coverage '' = slot, otherwise DateTime modifier like '+1 X'
			$celloff = ($slotlen < 84600) ? '':'+1 day'; //each cell spans min(slotlen,report period)
			break;
//		 case \Booker::RANGEMTH:
//			$seglen = \Booker::SEGDAY; //report divided into days
//			$celloff = ($slotlen < 84600) ? '+1 hour':'+1 day'; //each cell :: min(hour,report period)
//			break;
		 case \Booker::RANGEYR:
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
		$dtw = clone $dts;

		$item_id = (int)$idata['item_id'];
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group) {
			$allresource = $this->utils->GetGroupItems($this->mod,$item_id);
		} else {
			$allresource = array($item_id);
		}

		//update respective last-processed-repeats dates, if relevant
		$funcs = new Schedule();
		foreach ($allresource as $one) {
			$funcs->UpdateRepeats($this->mod,$one,$dts,$dte);
		}
		//get availability-blocks
		$rules = $this->utils->GetOneHeritableProperty($this->mod,$item_id,'available');
		$rules = array_filter($rules); //omit empties
		if ($rules) {
			$funcs = new WhenRules($this->mod);
			$timeparms = $funcs->TimeParms($idata);
			list($starts,$ends) = $funcs->AllIntervals(reset($rules),$dts,$dte,$timeparms); //proximal-rule-only, no ancestor-merging
		} else { //all available
			$starts = array();
			$ends = array();
		}
		//get offsets of each column's top- and bottom-row
		list($segoffst,$segoffnd,$rangeoffnd) = self::GetLimits($dts,$dte,$seglen,$slotlen,$starts,$ends);
		//populate column of row-titles
		$cells = self::GetSlotNames($idata,$dts,$segoffst,$segoffnd,$seglen,$slotlen,$celloff);

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
		list($titles,$isos) = self::GetTitles($idata,$dts,$dte,$range,$seglen);
		$cc = count($titles);

		$funcs = new Bookingops();
		$booked = $funcs->GetTableBooked($this->mod,$allresource,$dts->getTimestamp(),$dte->getTimestamp()-1);
		if ($booked) {
			$iter = new \ArrayIterator($booked);
			$position = 0; //init array-iterator-position
		} else {
			$iter = FALSE;
		}
		$countall = count($allresource);
		$funcs = new Userops();
		$blocks = new Blocks();

		$this->rangefmt = $this->mod->Lang('showrange'); //cache for FillCell()
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

			$ss = $dts->getTimestamp(); //start-stamp for current segment
			$ss += $segoffst; //start-stamp for 1st displayed slot in segment
			//iterate slots for this segment
			for ($r = 1; $r < $rc; $r++) {
				$se = $ss + $slotlen - 1; //end-stamp of current slot, maybe < end-of-cell
				$dtw->setTimestamp($ss);
				if ($iter && $iter->valid()) {
					list($one,$position) = self::DocumentCell(
						$idata,$dtw,$ss,$se,$celloff,$countall,$position,$iter,$funcs,$blocks);
				} else {
					$one = new \stdClass();
					$one->data = NULL;
					$one->style = 'class="vacant"';
				}
				$cells[] = $one;
				//skip to next cell start
				if ($celloff) {
					$dtw->modify($celloff);
					$ss = $dtw->getTimestamp();
				} else {
					$ss += $slotlen;
				}
			}
			$columns[] = $cells;
			//skip to next segment start
			$dts->modify($offs);
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
		list($dts,$dte) = $this->utils->RangeStamps($start,$range);
		$dtw = clone $dts;
		$item_id = (int)$idata['item_id'];
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group)
			$allresource = $this->utils->GetGroupItems($this->mod,$item_id);
		else
			$allresource = array($item_id);
		//update respective last-processed-repeats dates, if relevant
		$funcs = new Schedule();
		foreach ($allresource as $one) {
			$funcs->UpdateRepeats($this->mod,$one,$dts,$dte);
		}
		$funcs = new Bookingops();
		$lfmt = (int)$idata['listformat'];
		$booked = $funcs->GetListBooked($this->mod,$is_group,$allresource,
			$lfmt,$dts->getTimestamp(),$dte->getTimestamp()-1);
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
							$oneset->title = $dts->format('F Y'); //TODO translated month-name
						else
							$oneset->title = $t;
					} else
						$oneset->title = ''; //no need for repeated date for a single day
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
			$rc = count(reset($columns));
			$tplvars['rowcount'] = $rc;
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
