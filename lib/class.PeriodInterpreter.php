<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: PeriodInterpreter
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class PeriodInterpreter
{
	/*
	BlockYears:
	Get year(s) in block, also sets swaps @bs, @be if necessary, sets $dtw to @bs
	@bs: reference to block-start stamp
	@be: reference to 1-past-block-end stamp
	Returns: array, one member or a range
	*/
	private function BlockYears(&$bs, &$be, $dtw)
	{
		if ($bs > $be) {
			list($bs,$be) = array($be,$bs);
		}
		$dtw->setTimestamp($be-1);
		$e = (int)$dtw->format('Y');
		$dtw->setTimestamp($bs);
		$s = (int)$dtw->format('Y');
		if ($e > $s)
			return range($s,$e);
		else
			return array($s);
	}

	/*
	BlockMonths:
	Get months(s) in block
	@bs: block-start stamp
	@be: 1-past-block-end stamp
	Returns: array, one member or a range, not necessarily contiguous
	*/
	private function BlockMonths($bs, $be, $dtw)
	{
		$dtw->setTimestamp($be-1);
		$e = (int)$dtw->format('m');
		$dtw->setTimestamp($bs);
		$s = (int)$dtw->format('m');
		if ($e != $s) {
			if ($e > $s) {
				return range($s,$e);
			} else {
				return array_merge(range(1,$e),range($s,12));
			}
		} else
			return array($s);
	}

	//$prefix 1-byte or FALSE
	private function rangify($element, $prefix)
	{
		$parts = explode('..',$element);
		$s = $parts[0];
		$e = $parts[1];
		if ($prefix) {
			$s = substr($s,1);
			$e = substr($e,1);
		}
		if ($s < $e) {
			if ($prefix)
				return array_map(function($s) use($prefix) {
					return $prefix.$s;
				},range($s,$e));
			else
				return range($s,$e);
		} else { //should never happen
			return array($parts[0]);
		}
	}

	private function parse($element, $prefix=FALSE)
	{
		if (strpos($element,',') !== FALSE) {
			$parts = explode(',',$element);
			$ret = array();
			foreach ($parts as $element) {
				if (strpos($element,'..') !== FALSE) {
					$ret = array_merge($ret,self::rangify($element,$prefix));
				} else {
					$ret[] = $element;
				}
			}
			return array_unique($ret);
		} elseif (strpos($element,'..') !== FALSE) {
			return self::rangify($element,$prefix);
		}
		return array($element);
	}

	/*
	InterpretDescriptor:
	@descriptor: string containing one or more '('
	Returns: array with keys some or all of 'years','months','weeks','days','each',
		or empty array
	*/
	private function InterpretDescriptor($descriptor/*, $hint*/)
	{
		$descriptor = trim($descriptor,'!)'); //omit element-closers
		$parts = array_reverse(explode('(',$descriptor)); //prefer deeper-nested elements
		end($parts);
		$key = key($parts); //last key for comparison

		$found = array();
		foreach ($parts as $element) {
			if (!isset($found['years'])) {
				if (preg_match('^\d{4}([,.].*)?$',$element)) {
					$found['years'] = self::parse($element);
				} elseif (preg_match('^E[2-9]\d*Y$',$element)) {
					$found['years'] = $element;
				}
			} elseif (!isset($found['months'])) {
				if (strpos($element,'M') !== FALSE) {
					if (preg_match('^E([2-9]|1[012])M$',$element)) {
						$found['months'] = $element;
					} else {
						$found['months'] = self::parse($element,'M');
					}
				} elseif (preg_match('^\d{4}\-[1-9]([012])?([,.].*)?$',$element)) {
					$found['months'] = self::parse($element);
				}
			} elseif (!isset($found['weeks'])) {
				if (strpos($element,'W') !== FALSE) {
					if (preg_match('^E([2-9]|[1-5]\d)W$',$element)) {
						$found['weeks'] = $element;
					} else {
						$found['weeks'] = self::parse($element,'W');
					}
				}
			} elseif (!isset($found['days'])) {
				if (strpos($element,'D') !== FALSE) {
					if (preg_match('^E([2-9]|[1-3]\d{1,2}|[4-9]\d)D$',$element)) {
						$found['days'] = $element;
					} else {
						$found['days'] = self::parse($element,'D');
					}
				} elseif (preg_match('^$',$element)) {
					$found['days'] = self::parse($element);
				}
			} elseif (!isset($found['each'])) {
				if (preg_match('^E[2-9]\d*$',$element)) {
					if (key($scan) == $key) {
						$found['each'] = $element;
					}
				}
			} else {
				break;
			}
		}
		return $found;
	}

	/*
	AllDays:
	Get days-of-year in a specified range
	@year: year or array of them or ','-separated series of them
	  Each year is 4-digit e.g. 2000 or 2-digit e.g. 00 or anything else that
	  can be validly processed via date('Y')
	@month: optional tokenised month(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric 1..12 or with 'M' prefix i.e. M1..M12
		FALSE means all months in @year
	@week: optional tokenised weeks(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -5..-1,1..5 or with 'W' prefix i.e. W-5..W-1,W1..W5
		FALSE means all days in @month AND @year
	@day: optional tokenised day(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -31..-1,1..31 or with 'D' prefix i.e. D1..D7
		FALSE means all days in @week (if any) AND @month AND @year
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	private function AllDays($year, $month=FALSE, $week=FALSE, $day=FALSE)
	{
		//verify and interpret arguments
		$now = FALSE;
		if (!is_array($year) && strpos($year,',') !== FALSE)
			$year = explode(',',$year);

		if (is_array($year)) {
			foreach ($year as &$one) {
				$t = trim($one,' ,');
				if (is_numeric($t)) {
					$one = (int)$t;
					if ($one < 100) {
						if ($now == FALSE)
							$now = getdate();
						$one += 100 * (int)($now['year']/100);
					}
				} else {
					$t2 = date_parse($t); //PHP 5.2+
					if ($t2)
						$one = $t2['year'];
					else
						$one = FALSE;
				}
			}
			unset($one);
			$year = array_unique(array_filter($year),SORT_NUMERIC);
			$t = count($year);
			if (t == 0 || ($t > 1 && !sort($year,SORT_NUMERIC)))
				return FALSE;
		} elseif (is_numeric($year)) {
			$t = (int)$year;
			if ($t < 100) {
				if ($now == FALSE)
					$now = getdate();
				$t += 100 * (int)($now['year']/100);
			}
			$year = array($t);
		} else {
			$t = date_parse($year); //PHP 5.2+
			if ($t)
				$year = array($t['year']);
			else
				return FALSE;
		}

		if ($month) {
			if (!is_array($month) && strpos($month,',') !== FALSE)
					$month = explode(',',$month);

			if (is_array($month)) {
				foreach ($month as &$one) {
					$t = trim($one,' M,');
					if (is_numeric($t) && $t > 0 && $t < 13)
						$one = (int)$t;
					else
						$one = FALSE;
				}
				unset($one);
				$month = array_unique(array_filter($month),SORT_NUMERIC);
				$t = count($month);
				if ($t == 0 || ($t > 1 && !sort($month,SORT_NUMERIC)))
					return FALSE;
			} else {
				$t = trim($month,' M,');
				if (is_numeric($t) && $t > 0 && $t < 13)
					$month = array((int)$t);
				else
					return FALSE;
			}
		} else
			$month = range(1,12);

		if ($week) {
			if (!is_array($week) && strpos($week,',') !== FALSE)
					$week = explode(',',$week);

			if (is_array($week)) {
				foreach ($week as &$one) {
					$t = trim($one,' W,');
					if (is_numeric($t) && $t > -6 && $t != 0 && $t < 6)
						$one = (int)$t;
					else
						$one = FALSE;
				}
				unset($one);
				$week = array_unique(array_filter($week),SORT_NUMERIC);
				$t = count($week);
				if ($t == 0 || ($t > 1 && !sort($week,SORT_NUMERIC)))
					return FALSE;
				if ($t > 1) {
					//rotate all -ve's to end
					while (($t = reset($week)) < 0) {
						array_shift($week);
						$week[] = $t;
					}
				}
			} else {
				$t = trim($week,' W,');
				if (is_numeric($t) && $t > -6 && $t != 0 && $t < 6)
					$week = array((int)$t);
				else
					return FALSE;
			}
		} else
			$week = array(); //default no-weeks i.e. use all specified days

		if ($day) {
			if (!is_array($day) && strpos($day,',') !== FALSE)
				$day = explode(',',$day);

			if (is_array($day)) {
				foreach ($day as &$one) {
					$t = trim($one,' ,');
					if (is_numeric($t) && $t > -32 && $t != 0 && $t < 32)
						$one = (int)$t;
					elseif (($p = strpos($t,'D')) !== FALSE) {
						$t2 = substr($t,$p+1);
						if (is_numeric($t2) && $t2 > 0 && $t < 8) //SYNTAX FOR e.g. LAST Sunday: -1D1
							$one = $t;
						else
							$one = FALSE;
					} else
						$one = FALSE;
				}
				unset($one);
				$day = array_unique(array_filter($day));
				if (!$day) //actual days-array sorted before reutrn || !sort($day,SORT_NUMERIC))
					return FALSE;
				//rotate all -ve's to end
/*				reset($day);
				while (($t = $day[0]) < 0) {
					array_shift($day);
					$day[] = $t;
				}
*/
			} else {
				$t = trim($day,' ,');
				if (is_numeric($t) && $t > -32 && $t != 0 && $t < 32)
					$day = array((int)$t);
				elseif (($p = strpos($t,'D')) !== FALSE) {
					$t2 = substr($t,$p+1);
					if (is_numeric($t2) && $t2 > 0 && $t < 8)
						$day = array($t);
					else
						return FALSE;
				} else
					return FALSE;
			}
		} elseif ($week)
			$day = array('D1','D2','D3','D4','D5','D6','D7'); //all days of the week(s)
		else
			$day = range(1,31); //all days

		$ret = array();
		foreach ($year as $yn) {
			$doy = array();
			foreach ($month as $m) {
				$dmax = (int)date('t',gmmktime(0,0,1,$m,1,$yn)); //days in month
				foreach ($day as $d) {
					if (($p = strpos($d,'D')) !== FALSE) {
						$t = (int)substr($d,$p+1) - 1; //D1 >> 0 etc
						$c = ($p > 0) ? (int)substr($d,0,$p) : 0;
						if ($c != 0) {
							$t2 = self::MonthDay($yn,$m,$dmax,$c,$t);
							if ($t2 >= 0)
								$doy[] = $t2;
						} elseif ($week) {
							$doy = array_merge($doy,self::WeeksDays($yn,$m,$week,$dmax,$t));
//							$dbg = self::WeeksDays($yn,$m,$week,$dmax,$t);
//							$doy = array_merge($doy,$dbg);
						} else {
							$doy = array_merge($doy,self::MonthWeekDays($yn,$m,$dmax,$t));
//							$dbg = self::MonthWeekDays($yn,$m,$dmax,$t);
//							$doy = array_merge($doy,$dbg);
						}
						continue;
					}

					if (is_numeric($d) && $d < 0)
						$d += $dmax + 1;
					if ($d <= $dmax) {
						$st = gmmktime(0,0,1,$m,$d,$yn);
						$doy[] = (int)date('z',$st);
					}
				}
			}
			if ($doy) {
				if (isset($ret[$yn])) {
					$ret[$yn] = array_unique(array_merge($ret[$yn],$doy),SORT_NUMERIC);
				} else {
					sort($doy,SORT_NUMERIC);
					$ret[$yn] = $doy;
				}
			}
		}
		return $ret;
	}

	/**
	BlockDays:
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
	*/
	public function BlockDays($bs, $be, $dtw)
	{
		$years = self::BlockYears($bs, $be, $dtw, FALSE);
		$dtw->setTime(0,0,0);
		$dte = clone $dtw;
		$ret = array();
		$yn = reset($years);

		if (count($years) > 1) {
			$ye = end($years);
			while ($yn < $ye) {
				$doy = array();
				$t = ($yn+1).'-1-1';
				$dte->modify($t);
				while ($dtw < $dte) {
					$doy[] = $dtw->getTimestamp();
					$dtw->modify('+1 day');
				}
				if ($doy) {
					$ret[$yn] = $doy;
				}
				$dtw = $dte;
				$yn++;
			}
		}
		$doy = array();
		$dte->setTimestamp($be-1);
		while ($dtw <= $dte) {
			$doy[] = $dtw->getTimestamp();
			$dtw->modify('+1 day');
		}
		if ($doy)
			$ret[$yn] = $doy;

		return $ret;
	}

	/**
	SpecificYears:
	@descriptor: 4-digit year, or array like [startyear,'.',endyear]
		representing a sequence
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report year-starts instead of day-starts
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or year in the year
	*/
	public function SpecificYears($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$years = self::BlockYears($bs, $be, $dtw); //get year(s) in block, clean $bs,$be
		if (is_array($descriptor)) {
			//get years in sequence
			$yn = $descriptor[0];
			$ye = $descriptor[2];
			if ($mn < $me) {
				$wanted = range($yn,$ye);
			} else { //should never happen
				$wanted = array($yn);
			}
		} elseif (strpos($descriptor,'(') !== FALSE) {
			$parsed = self::InterpretDescriptor($descriptor);
			if (!$parsed || !$parsed['years']) {
				return array();
			} else {
				//TODO interpret - array or each N
			}
		} elseif (strpos($descriptor,',') !== FALSE) {
			$descriptor = trim($descriptor,'()'); //just in case
			$wanted = explode(',',$descriptor);
		} else {
			$wanted = array($descriptor);
		}
		$years = array_intersect($years,$wanted);
		if (!$years)
			return array();

		$dtw->setTime(0,0,0);
		$dte = clone $dtw;
		$ret = array();
		$yn = reset($years);

		if (count($years) > 1) {
			$ye = end($years);
			while ($yn < $ye) {
				$doy = array();
				$t = ($yn+1).'-1-1';
				$dte->modify($t);
				while ($dtw < $dte) {
					$st = $dtw->getTimestamp();
					if ($st >= $bs && $st < $be) {
						if (!$merge || $dtw->format('z') == 0) {
							$doy[] = $st;
						}
					}
					$dtw->modify('+1 day');
				}
				if ($doy) {
					$ret[$yn] = $doy;
				}
				$dtw = $dte;
				$yn++;
			}
		}
		$doy = array();
		$dte->setTimestamp($be-1);
		while ($dtw <= $dte) {
			$st = $dtw->getTimestamp();
			if ($st >= $bs && $st < $be) {
				if (!$merge || $dtw->format('z') == 0) {
					$doy[] = $st;
				}
			}
			$dtw->modify('+1 day');
		}
		if ($doy)
			$ret[$yn] = $doy;

		return $ret;
	}

	/**
	SpecificMonths:
	@descriptor: token M1 to M12, or array like [Mstart,'.',Mend]
		representing a sequence
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report month-starts instead of day-starts
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or month in the year
	*/
	public function SpecificMonths($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$years = self::BlockYears($bs, $be, $dtw); //get year(s) in block, clean $bs,$be
		//get relevant months
		$months = self::BlockMonths($bs, $be, $dtw);

		if (is_array($descriptor)) {
			//get months in sequence
			$mn = ltrim($descriptor[0],'M');
			$me = ltrim($descriptor[2],'M');
			if ($mn < $me) {
				$wanted = range($mn,$me);
			} else { //should never happen
				$wanted = array($mn);
			}
		} elseif (strpos($descriptor,'(') !== FALSE) {
			$parsed = self::InterpretDescriptor($descriptor);
			if (!$parsed || !$parsed['months']) {
				return array();
			} else {
				//TODO interpret - array or each N
			}
		} elseif (strpos($descriptor,',') !== FALSE) {
			$descriptor = str_replace('()M','',$descriptor);
			$wanted = explode(',',$descriptor);
		} else {
			$wanted = array(trim($descriptor,'()M'));
		}
		$months = array_intersect($months,$wanted);
		if (!$months)
			return array();

		$wanted = self::AllDays($years,$months);
		if (!$wanted)
			return array();

		$ret = array();
		$dtw->setTime(0,0,0); //just in case
		//convert offsets to stamps - daily or monthly
		foreach ($wanted as $yn=>$offs) {
			$doy = array();
			foreach ($offs as $d) {
				$dtw->modify($yn.'-1-1 +'.$d.' days');
				if (!$merge || $dtw->format('j') == 1)
					$doy[] = $dt->getTimestamp();
			}
			if ($doy)
				$ret[$yn] = $doy;
		}
		return $ret;
	}

	/**
	SpecificWeeks:
	@descriptor: token W1 to W5, or -W5 to -W1, or array like [Wstart,'.',Wend]
		representing a sequence
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report week-starts instead of day-starts
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or week in the year
	*/
	public function SpecificWeeks($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$years = self::BlockYears($bs, $be, $dtw); //get year(s) in block

		if (is_array($descriptor)) {
			//get weeks in sequence e.g. -5..-1 1..5
			$wn = ltrim($descriptor[0],'W');
			$we = ltrim($descriptor[2],'W');
			$st = ($wn < $we) ? 1:-1; //should always be +1
			$weeks = range($wn,$we,$st);
			if (count($weeks) == 1) {
				$weeks = $weeks[0];
			}
		} elseif (strpos($descriptor,'(') !== FALSE) {
			$parsed = self::InterpretDescriptor($descriptor);
			if (!$parsed || !$parsed['weeks']) {
				return array();
			} else {
				//TODO interpret - array or each N
			}
		} elseif (strpos($descriptor,',') !== FALSE) {
			$descriptor = str_replace('()W','',$descriptor);
			$weeks = explode(',',$descriptor);
		} else {
			$weeks = trim($descriptor,'()W');
		}

		$wanted = self::AllDays($years,FALSE,$weeks);
		if (!$wanted)
			return array();

		$ret = array();
		//convert offsets to stamps - daily or monthly
		$dtw->setTime(0,0,0); //just in case
		foreach ($wanted as $yn=>$offs) {
			$doy = array();
			foreach ($offs as $d) {
				$dtw->modify($yn.'-1-1 +'.$d.' days');
				if (!$merge || $dtw->format('w') == 0)
					$doy[] = $dt->getTimestamp();
			}
			if ($doy)
				$ret[$yn] = $doy;
		}
		return $ret;
	}

	/**
	SpecificDays:
	@descriptor: token D1 to D7, or 1..31 or -31..-1 or array like [start,'.',end]
		representing a sequence
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
	*/
	public function SpecificDays($descriptor, $bs, $be, $dtw)
	{
		$years = self::BlockYears($bs, $be, $dtw); //get year(s) in block

		if (is_array($descriptor)) {
			if ($descriptor[0][0] == 'D') {
				$dn = substr($descriptor[0],1);
				$de = substr($descriptor[2],1);
				$st = ($dn < $de) ? 1:-1; //should always be +1
				$days = range($dn,$de,$st);
				foreach ($days as &$d) {
					$d = 'D'.$d;
				}
				unset($d);
			} else {
				$days = range($descriptor[0],$descriptor[2]);
			}
		} elseif (strpos($descriptor,'(') !== FALSE) {
			$parsed = self::InterpretDescriptor($descriptor);
			if (!$parsed || !$parsed['days']) {
				return array();
			} else {
				//TODO interpret - array or each N
			}
		} elseif (strpos($descriptor,',') !== FALSE) {
//			$descriptor = str_replace('()D','',$descriptor);
			$days = explode(',',$descriptor);
		} else {
			$days = array($descriptor);
		}

		$wanted = self::AllDays($years,FALSE,FALSE,$days); //must get something
		$ret = array();
		//convert offsets to stamps - daily or monthly
		$dtw->setTime(0,0,0); //just in case
		foreach ($wanted as $yn=>$offs) {
			$doy = array();
			foreach ($offs as $d) {
				$dtw->modify($yn.'-1-1 +'.$d.' days');
				$doy[] = $dt->getTimestamp();
			}
			if ($doy)
				$ret[$yn] = $doy;
		}
		return $ret;
	}

	/**
	SpecificDates:
	@descriptor: ISO-format date, or array like ['ISOstart','.','ISOend']
		representing a sequence
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
*/
	public function SpecificDates($descriptor, $bs, $be, $dtw)
	{
		$ret = array();
		if (is_array($descriptor)) {
			$dtw->modify($descriptor[0].' 0:0:0');
			$dte = clone $dtw;
			$dte->modify($descriptor[2].' 0:0:0');
			while ($dtw <= $dte) {
				$st = $dtw->getTimestamp();
				if ($st >= $bs && $st < $be) {
					$yn = (int)$dtw->format('Y');
					if (isset($ret[$yn])) {
						$ret[$yn][] = $st;
					} else
						$ret[$yn] = array($st);
				}
				$dtw->modify('+1 day');
			}
			if ($ret) {
				asort($ret,SORT_NUMERIC);
				foreach ($ret as $doy) {
					$doy = array_unique($doy,SORT_NUMERIC);
				}
				unset($doy);
			}
		} elseif (strpos($descriptor,',') !== FALSE) {
			//TODO
		} else {
			$dtw->modify($descriptor.' 0:0:0');
			$st = $dtw->getTimestamp();
			if ($st >= $bs && $st < $be) {
				$yn = (int)$dtw->format('Y');
				$ret[$yn] = array($st);
			}
		}
		return $ret;
	}

	/*
	MonthWeekDays:
	Get array of each instance of a specific weekday @dow in a specific month
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array, or FALSE upon error. The single-member array has
	 key: @year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	private function MonthWeekDays($year, $month, $dmax, $dow)
	{
		//first day in $year/$month as day-of-year
		$st = gmmktime(0,0,1,$month,1,$year);
		$base = (int)date('z',$st);
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$firstdow = (int)date('w',$st);
		//0-based offset to first wanted day
		$d = $dow - $firstdow;
		if ($d < 0)
			$d += 7;

		$doy = array();
		for ($i = $d; $i < $dmax; $i += 7) //0-based, so NOT <= $dmax
			$doy[] = $base + $i;

		return array($year=>$doy);
	}

	/*
	WeeksDays:
	Get array of each instance of a specific weekday @dow in specified week(s) in
	a specific month
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@week: array if numeric week(s) -5..-1,1..5 in @year AND @month
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array, or FALSE upon error. The single-member array has
	 key: @year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	private function WeeksDays($year, $month, $week, $dmax, $dow)
	{
		//first day in $year/$month as day-of-year
		$st = gmmktime(0,0,1,$month,1,$year);
		$base = (int)date('z',$st);
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$firstdow = (int)date('w',$st);
		//count of part/whole Sun..Sat weeks in $month, populated on demand
		$wmax = 0;
		$doy = array();

		foreach ($week as $w) {
			if ($w < 0) {
				if ($wmax == 0) {
					$d = 6 - $firstdow;	//offset to Saturday/end of 1st week
					$wmax = 1 + ceil(($dmax-$d)/7);
				}
				$w += $wmax + 1;
			}

			$d = $dow - $firstdow + ($w-1) * 7;
			if ($d >= 0 && $d < $dmax) //0-based, so NOT <= $dmax
				$doy[] = $base + $d;
		}
		if ($doy) {
			$doy = array_unique($doy,SORT_NUMERIC);
			return array($year=>$doy);
		}
		return array();
	}

	/*
	MonthDay:
	Get 'counted' instance of a specific weekday @dow in a specific month
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@dmax: 1-based index of last day in @month
	@count: index of wanted day-of-month -5..-1,1..5
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array, or FALSE upon error. The single-member array has
	 key: @year (4-digit integer)
	 val: array with one 0-based day-of-year in the year
	*/
	private function MonthDay($year, $month, $dmax, $count, $dow)
	{
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$st = gmmktime(0,0,1,$month,1,$year);
		$firstdow = (int)date('w',$st);
		//offset to 1st instance of the wanted day
		$d = $dow - $firstdow;
		if ($d < 0)
			$d += 7;
		if ($count < 0) {
			$cmax = (int)(($dmax - $d)/7); //no. of wanted days in the month
			$count += 1 + $cmax;
			if ($count < 0 || $count > $cmax)
				return -1;
		}
		if ($count > 0) {
			$d += ($count -1) * 7;
			if ($d >= $dmax)
				return -1;
		} else
			return -1;
		//first day in $year/$month as day-of-year
		$base = (int)date('z',$st);
		$base += $d;

		return array($year=>array($base));
	}

	/**
	SuccessiveDays:
	Get each n'th-day in a specified range
	@interval: integer no. of weeks between successive reports, >= 1
	@styear: numeric year e.g. 2000
	@stmonth: numeric month 1..12 in @styear
	@stday: identfier in @styear/@stmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W))
	@ndyear: numeric year e.g. 2000 or FALSE to use @styear
	@ndmonth: numeric month 1..12 in @ndyear or FALSE to use @stmonth
	@ndday: identfier in @ndyear/@ndmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W)) or FALSE to use last day-of-month
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	public function SuccessiveDays($interval, $styear, $stmonth, $stday,
		$ndyear=FALSE, $ndmonth=FALSE, $ndday=FALSE)
	{
		if ($ndyear == FALSE)
			$ndyear = $styear;
		if ($ndmonth == FALSE)
			$ndmonth = $stmonth;
		if ($stday < 0) {
			$st = gmmktime(0,0,0,$stmonth,1,$styear);
			$stday += 1 + date('t',$st);
		} elseif (($p = strpos($stday,'D')) !== FALSE) {
			//TODO support e.g. D4(2(W))
			$st = gmmktime(0,0,0,$stmonth,1,$styear);
			$dmax = date('t',$st);
			$c = ($p > 0) ? (int)substr($stday,0,$p) : 1;
			if ($c == 0) $c = 1;
			$dow = (int)substr($stday,$p+1) - 1; //D1 >> 0 etc
			$t2 = self::MonthDay($styear,$stmonth,$dmax,$c,$dow);
			$stday = ($t2 >= 0) ? $t2 : 1; //default to start
		}
		if ($ndday == FALSE) {
			$st = gmmktime(0,0,0,$ndmonth,1,$ndyear); //days in month
			$ndday = (int)date('t',$st);
		} elseif ($ndday < 0) {
			$st = gmmktime(0,0,0,$ndmonth,1,$ndyear);
			$ndday  += 1 + date('t',$st);
		} elseif (($p = strpos($ndday,'D')) !== FALSE) {
			//TODO support e.g. D4(2(W))
			$st = gmmktime(0,0,0,$ndmonth,1,$ndyear);
			$dmax = (int)date('t',$st);
			$c = ($p > 0) ? (int)substr($ndday,0,$p) : 1;
			if ($c == 0) $c = 1;
			$dow = (int)substr($ndday,$p+1) - 1; //D1 >> 0 etc
			$t2 = self::MonthDay($ndyear,$ndmonth,$dmax,$c,$dow);
			$ndday = ($t2 >= 0) ? $t2 : $dmax; //default to end
		}
		$tz = new \DateTimeZone('UTC');
		$st = gmmktime(0,0,0,$stmonth,$stday,$styear);
		$dts = new \DateTime('@'.$st,$tz);
		$st = gmmktime(0,0,0,$ndmonth,$ndday,$ndyear);
		$dte = new \DateTime('@'.$st,$tz);
		$diff = ($interval == 1) ? '+1 day':'+'.$interval.' days';
		$yn = FALSE;
		$doy = FALSE;
		$ret = array();
		while ($dts <= $dte) {
			$yt = (int)$dts->format('Y');
			if ($yt != $yn) {
				if ($doy) {
					if (isset($ret[$yn])) {
						$ret[$yn] = array_unique(array_merge($ret[$yn],$doy),SORT_NUMERIC);
					} else
						$ret[$yn] = $doy;
				}
				$yn = $yt;
				$doy = array();
			}
			$doy[] = (int)$dts->format('z');
			$dts->modify($diff);
		}
		if ($doy) {
			if (isset($ret[$yn])) {
				$ret[$yn] = array_unique(array_merge($ret[$yn],$doy),SORT_NUMERIC);
			} else
				$ret[$yn] = $doy;
		}
		return $ret;
	}

	/**
	SuccessiveWeeks:
	Get each day in each n'th-week in a specified range
	@interval: integer no. of days between successive reports, >= 1
	@styear: numeric year e.g. 2000
	@stmonth: numeric month 1..12 in @styear
	@stday: identfier in @styear/@stmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W))
	@ndyear: numeric year e.g. 2000 or FALSE to use @styear
	@ndmonth: numeric month 1..12 in @ndyear or FALSE to use @stmonth
	@ndday: identfier in @ndyear/@ndmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W)) or FALSE to use last day-of-month
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year in [0]
	*/
	public function SuccessiveWeeks($interval, $styear, $stmonth, $stday,
		$ndyear=FALSE, $ndmonth=FALSE, $ndday=FALSE)
	{
	}

	/**
	SuccessiveMonths:
	Get each day in each n'th-month in a specified range
	@interval: integer no. of months between successive reports, >= 1
	@styear: numeric year e.g. 2000
	@stmonth: numeric month 1..12 in @styear
	@stday: identfier in @styear/@stmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W))
	@ndyear: numeric year e.g. 2000 or FALSE to use @styear
	@ndmonth: numeric month 1..12 in @ndyear or FALSE to use @stmonth
	@ndday: identfier in @ndyear/@ndmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W)) or FALSE to use last day-of-month
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year in [0]
	*/
	public function SuccessiveMonths($interval, $styear, $stmonth, $stday,
		$ndyear=FALSE, $ndmonth=FALSE, $ndday=FALSE)
	{
	}

	/*
	isodate_from_format:
	Convert @dvalue to ISO format i.e. like Y-M-d H:i:s
	For testing, at least
	@dformat: string which includes one or more of many (not all) format-characters
	 understood by PHP date(). If it includes 'z', the corresponding element of
	 @dvalue must be 1-based
	@dvalue: date-time string consistent with @dformat
	*/
	public function isodate_from_format($dformat, $dvalue)
	{
		$sformat = str_replace(
			array('Y' ,'M' ,'m' ,'d' ,'H' ,'h' ,'i' ,'s' ,'a' ,'A' ,'z'),
			array('%Y','%b','%m','%d','%H','%I','%M','%S','%P','%p','%j'),$dformat);
		$parts = strptime($dvalue,$sformat); //PHP 5.1+
		return sprintf('%04d-%02d-%02d %02d:%02d:%02d',
			$parts['tm_year'] + 1900,  //tm_year = relative to 1900
			$parts['tm_mon'] + 1,      //tm_mon = 0-based
			$parts['tm_mday'],
			$parts['tm_hour'],
			$parts['tm_min'],
			$parts['tm_sec']);
	}

	/*
	args as as for AllDays($year,$month=FALSE,$week=FALSE,$day=FALSE)
	*/
	public function tester($year, $month, $week, $day)
	{
		$ret = array();
		$dt = new \DateTime('@0',new \DateTimeZone('UTC'));
		$data = self::AllDays($year,$month,$week,$day);
		foreach ($data as $row) {
			$yr = $row[0];
			$days = $row[1];
			foreach ($days as $doy) {
				$d = sprintf('%03d',$doy+1);	//downstream strptime() expects padded, 1-based, day-of-year
				$newdate = self::isodate_from_format('Y z',$yr.' '.$d);
				$dt->modify($newdate);
				$ret[] = $dt->format('D j M Y');
			}
		}
		return $ret;
	}
	/*
	args as as for SuccessiveDays($interval,$styear,$stmonth,$stday,$ndyear=FALSE,$ndmonth=FALSE,$ndday=FALSE)
	*/
	public function tester2($interval, $styear, $stmonth, $stday,
		$ndyear=FALSE, $ndmonth=FALSE, $ndday=FALSE)
	{
		$ret = array();
		$dt = new \DateTime('@0',new \DateTimeZone('UTC'));
		$data = self::SuccessiveDays($interval,$styear,$stmonth,$stday,$ndyear,$ndmonth,$ndday);
		foreach ($data as $row) {
			$yr = $row[0];
			$days = $row[1];
			foreach ($days as $doy) {
				$d = sprintf('%03d',$doy+1);	//downstream strptime() expects padded, 1-based, day-of-year
				$newdate = self::isodate_from_format('Y z',$yr.' '.$d);
				$dt->modify($newdate);
				$ret[] = $dt->format('D j M Y');
			}
		}
		return $ret;
	}
}
