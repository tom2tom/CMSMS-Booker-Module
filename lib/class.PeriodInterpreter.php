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

	//$prefix 1-byte or FALSE
	//returns array
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

	//returns array
	private function rangifydate($element)
	{
		$parts = explode('..',$element);
		$s = $parts[0];
		$e = $parts[1];
		if ($s < $e) {
			$dtw = new \DateTime('@0',NULL);
			$dtw->modify($parts[0]);
			$dte = clone $dtw;
			$dte->modify($parts[1]);
			$ret = array();
			while ($dtw <= $dte) {
				$ret[] = $dtw->format('Y-m-d');
				$dtw->modify('+1 day');
			}
			return $ret;
		} else { //should never happen
			return array($parts[0]);
		}
	}

	//returns array
	private function toarray($element, $prefix=FALSE)
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
	@descriptor: string
	Returns:
	 if @descriptor contains '(', an array with keys some or all of
		'years','months','weeks','days'or'dates','each' and corresponding value(s)
		either an interpreted cleaned array or a string like EE\d+([DWMY]E)?
		representing a succession NOTE non-array for 'each n'th'
	 if @descriptor contains ',', an array with one of the above keys and
	 	values an interpreted cleaned array
	 otherwise if @descriptor contains '..', an array with one of the above keys
	 	and values an interpreted cleaned array
	 otherwise an array with one of the above keys and value a single-member array
	*/
	private function InterpretDescriptor($descriptor)
	{
		if (strpos($descriptor,'(') !== FALSE) {
			//string like A(B(C(D(E)))) where any/all of [ABCD] and associated '()' may be absent
			$descriptor = trim($descriptor,'!)'); //omit element-closers
			$parts = array_reverse(explode('(',$descriptor)); //prefer deeper-nested elements
			$lastkey = count($parts) - 1; //last key for comparison
		} elseif (strpos($descriptor,',') !== FALSE) {
			$parts = explode(',',$descriptor);
			$lastkey = -1; //no last-index match
		} elseif (strpos($descriptor,'..') !== FALSE) {
			if (preg_match('/([DWMY])/',$descriptor,$matches)) {
				$prefix = $matches[1];
			} else {
				$prefix = FALSE;
			}
			$parts = self::rangify(ltrim($descriptor,'!'),$prefix);
			$lastkey = -1;
		} else {
			$parts = array(ltrim($descriptor,'!'));
			$lastkey = -1;
		}
/* 'each'-descriptor examples
'D3(EE2WE(2020))'
'D3(EE2WE(2020-10))'
'D3(EE2WE(EE3ME(2020)))'
'D3(EE2WE(M5))'
'D3(EE2WE(M5..M9))'
'D3(EE3ME(2020))'
'EE10DE(EE2ME(2016))'
'EE10DE(EE2ME(M7..M9))'
'EE10DE(EE3ME(2016..2020))'
'EE10DE(EE3ME(EE2(2016..2020)))'
'EE10DE(M5)'
'EE10DE(M5,M7)'
'EE10DE(M5..M9)'
'EE10DE(M6,M7)'
'EE10DE(M9..M12)'
'EE2(2015..2020)'
'EE2D3(2015)'
'EE2D3(2015..2016)'
'EE2D3(EE2ME(2016))'
'EE2D3(EE2ME(M7..M9))'
'EE2D3(EE3ME(2016..2020))'
'EE2D3(EE3ME(EE2(2016..2020)))'
'EE2D3(M5)'
'EE2D3(M5,M7)'
'EE2D3(M5..M9)'
'EE2D3(M6,M7)'
'EE2D3(M9..M12)'
'EE2DE(-1(WE(2020-10)))'
'EE2DE(2(WE(2020)))'
'EE2DE(2(WE(2020-10)))'
'EE2DE(2(WE(M5)))'
'EE2DE(EE2WE(2020))'
'EE2DE(EE2WE(2020-10))'
'EE2DE(EE2WE(M5))'
'EE2ME(2015)'
'EE2ME(2015..2020)'
'EE2ME(M1..M12)'
'EE2WE(2015)'
'EE2WE(2015..2020)'
'EE2WE(2015-5)'
'EE2WE(2020-5..2020-12)'
'EE2WE(2020-5..2020-12)'
'EE2WE(EE2ME(M7..M9))'
'EE2WE(EE2YE(2015..2020))'
'EE2WE(M5)'
'EE2WE(M5(2015))'
'EE2WE((M5,M6)(2015..2020))'
'EE2WE(M5,M7)'
'EE2WE(M5..M9)'
'EE2WE(M6,M7)'
'EE2WE(M9..M12)'
'EE2YE(2015..2020)'
'EE30DE(2015)'
'EE30DE(2015..2016)'
'EE3(M7..M12)'
'EE3ME(EE2YE(2015..2020))'
*/
		$found = array();
		foreach ($parts as $element) {
			if (!isset($found['years'])) {
				if (preg_match('^[12]\d{3}([,.].+)?$',$element)) {
					$found['years'] = self::toarray($element);
				} elseif (preg_match('^EE([2-9]\d*)YE$',$element,$matches)) {
					$found['years'] = (int)$matches[1]; //non-array signals 'eacher'
				}
			} elseif (!isset($found['months'])) {
				if (strpos($element,'M') !== FALSE) {
					if (preg_match('^EE([2-9]|1[012])ME$',$element,$matches)) {
						$found['months'] = (int)$matches[1]; //for upstream iterpretation
					} else {
						$found['months'] = self::toarray($element,'M');
					}
				} elseif (preg_match('^[12]\d{3}\-(0?[1-9]|1[0-2])([,.].+)?$',$element)) {
					$found['months'] = self::toarray($element);
				}
			} elseif (!isset($found['weeks'])) {
				if (strpos($element,'W') !== FALSE) {
					if (preg_match('^EE([2-9]|[1-5]\d)WE$',$element,$matches)) {
						$found['weeks'] = (int)$matches[1]; //for upstream iterpretation
					} else {
						$found['weeks'] = self::toarray($element,'W');
					}
				}
			} elseif (!(isset($found['days']) || isset($found['dates']))) {
				if (strpos($element,'D') !== FALSE) {
					if (preg_match('^EE([2-9]|[1-3]\d{1,2}|[4-9]\d)DE$',$element,$matches)) {
						$found['days'] = (int)$matches[1]; //for upstream iterpretation
					} else {
						$found['days'] = self::toarray($element,'D');
					}
				} elseif (preg_match('^(\-)?([1-9]|[12]\d|3[01])([,.].+)?$',$element)) { //day(s) of month
					$found['days'] = self::toarray($element);
				} elseif (preg_match('^[12]\d{3}\-(0?[1-9]|1[0-2])\-(0?[1-9]|[12]\d|3[01])([,.].+)?$',$element)) { //date(s)
					if (strpos($element,',') !== FALSE) {
						$bits = explode(',',$element);
						foreach ($bits as &$b) {
							if (strpos($b,'..') !== FALSE) {
								$b = self::rangifydate($b);
							}
						}
						unset($b);
						$found['dates'] = array_unique($bits,SORT_STRING);
					} elseif (strpos($element,'..') !== FALSE) {
						$found['dates'] = self::rangifydate(ltrim($element,'!'));
					} else {
						$found['dates'] = array(ltrim($element,'!'));
					}
				}
			} elseif (!isset($found['each'])) {
				if (preg_match('^EE[2-9]\d*$',$element)) {
					if (key($scan) == $lastkey) {
						$found['each'] = (int)substr($element,2); //for upstream iterpretation
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
	Get days-of-year conforming to the arguments
	@year: year(s) identifier, array or string or ','-separated series.
	 Each is 4-digit e.g. 2000 or 2-digit e.g. 00 or anything else that can be
	 validly processed via date('Y')
	@month: optional month(s) identifier, array or string or ','-separated series.
	 Each is numeric 1..12 or 'M'-prefixed M1..M12
	 Default FALSE means all months in @year
	@week: optional weeks(s) identifier, array or string or ','-separated series.
	 Each is numeric -5..-1,1..5 or 'W'-prefixed W-5..W-1,W1..W5
	 Default FALSE means all days in @month (if any) AND @year
	@day: optional day(s) identifier, array or string or ','-separated series.
	 Each is numeric -31..-1,1..31 or 'D'-prefixed D1..D7
	 Default FALSE means all days in @week (if any) AND @month (if any) AND @year
	Returns: array, empty upon error, otherwise each member has
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
				return array();
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
				return array();
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
					return array();
			} else {
				$t = trim($month,' M,');
				if (is_numeric($t) && $t > 0 && $t < 13)
					$month = array((int)$t);
				else
					return array();
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
					return array();
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
					return array();
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
					return array();
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
						return array();
				} else
					return array();
			}
		} elseif ($week)
			$day = array('D1','D2','D3','D4','D5','D6','D7'); //all days of the week(s)
		else
			$day = range(1,31); //all days

		$ret = array();
		foreach ($year as $yn) {
			$doy = array();
			foreach ($month as $m) {
				$dmax = (int)gmdate('t',gmmktime(0,0,1,$m,1,$yn)); //days in month
				foreach ($day as $d) {
					if (($p = strpos($d,'D')) !== FALSE) {
						$t = (int)substr($d,$p+1) - 1; //D1 >> 0 etc
						$c = ($p > 0) ? (int)substr($d,0,$p) : 0;
						if ($c != 0) {
							$t2 = self::WeekDayInstanceinMonth($yn,$m,$dmax,$t,$c);
							if ($t2 >= 0)
								$doy[] = $t2;
						} else {
							$doy = array_merge_recursive($doy,self::WeekDaysinMonth($yn,$m,$week,$dmax,$t));
//							$dbg = self::WeekDaysinMonth($yn,$m,$week,$dmax,$t);
//							$doy = array_merge_recursive($doy,$dbg);
						}
						continue;
					}

					if (is_numeric($d) && $d < 0)
						$d += $dmax + 1;
					if ($d <= $dmax) {
						$st = gmmktime(0,0,1,$m,$d,$yn);
						$doy[] = (int)gmdate('z',$st);
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

	/*
 	WeekDaysinYear:
	Get each instance of a specific weekday @dow in specific [week(s) and] @month
	and @year
	@year: numeric year e.g. 2000
	@month: array of numeric month(s) 1..12, or EN for N-separated months,
		or FALSE for all months
	@week: array of numeric week(s) -5..-1,1..5, or EN for N-separated weeks,
		or FALSE for all weeks
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array, empty upon error, otherwise single-member has
	 key: @year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
/*	private function WeekDaysinYear($year, $month, $week, $dow)
	{
		if ($month) {
			if ($month[0] == 'E') { //each n'th month, ok if $month is array
				$n = (int)substr($month,1);
				$month = array();
				for ($i=1; $i<13; $i+=$n) {
					$month[] = $i;
				}
				$sort = FALSE;
			} else {
				$sort = TRUE;
			}
		} else {
			$month = range(1,12);
			$sort = FALSE;
		}
		$dtw = new \DateTime('@0',NULL);
		foreach ($month as $i) {
			$dtw->setDate($year,$i,1);
			$dmax = $dtw->format('t');
			$doy = self::WeekDaysinMonth($year,$i,$week,$dmax,$dow);
			if (isset($ret)) {
				$ret[$year] = array_merge_recursive($ret[$year],$doy);
			} else {
				$ret = $doy;
			}
		}
		if ($sort)
			$ret[$year] = array_unique($ret[$year],SORT_NUMERIC);

		return $ret;
	}
*/
	/*
	WeekDaysinMonth:
	Get each instance of a specific weekday @dow in specific [week(s) and] @month
	and @year
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@week: array of numeric week(s) -5..-1,1..5 in @year AND @month,
	  or EN for N-separated such weeks, or FALSE for all such weeks
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array, empty upon error, otherwise single-member has
	 key: @year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	private function WeekDaysinMonth($year, $month, $week, $dmax, $dow)
	{
		//first day in $year/$month as day-of-year
		$st = gmmktime(0,0,1,$month,1,$year);
		$base = (int)gmdate('z',$st);
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$firstdow = (int)gmdate('w',$st);
 		$doy = array();

		if ($week) {
			//count of part/whole Sun..Sat weeks in $month, populated on demand
			$wmax = 0;
			if ($week[0] == 'E') { //ok if $week is array
				$d = 6 - $firstdow;	//offset to Saturday/end of 1st week
				$wmax = 1 + ceil(($dmax-$d)/7);
				$d = 1;
				$n = (int)substr($week,1);
				$week = array();
				while ($d <= $wmax) {
					$week[] = $d;
					$d += $n;
				}
			}
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
			} else {
	 			return array();
			}
		} else { //all weeks
			//0-based offset to first wanted day
			$d = $dow - $firstdow;
			if ($d < 0)
				$d += 7;
			for ($i=$d; $i<$dmax; $i+=7) //0-based, so NOT <= $dmax
				$doy[] = $base + $i;
		}
		return array($year=>$doy);
	}

	/*
	WeekDayInstanceinYear:
	Get 'counted' instance of a specific weekday @dow in @month and @year
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	@instance: wanted instance of the day -5..-1,1..5
	Returns: array, empty upon error, or single member has
	 key: @year (4-digit integer)
	 val: array with one 0-based day-of-year in the year
	*/
/*	private function WeekDayInstanceinYear($year, $month, $dow, $instance)
	{
		if ($month) {
			if ($month[0] == 'E') { //each n'th month, ok if $month is array
				$n = (int)substr($month,1);
				$month = array();
				for ($i=1; $i<13; $i+=$n) {
					$month[] = $i;
				}
			}
		} else {
			$month = range(1,12);
		}

		$dtw = new \DateTime('@0',NULL);
		foreach ($month as $i) {
			$dtw->setDate($year,$i,1);
			$dmax = $dtw->format('t');
			$doy = self::WeekDayInstanceinMonth($year,$i,$dmax,$dow,$instance);
			if (isset($ret)) {
				$ret[$year] = array_merge_recursive($ret[$year],$doy);
			} else {
				$ret = $doy;
			}
		}
		$ret[$year] = array_unique($ret[$year],SORT_NUMERIC);

		return $ret;
	}
*/
	/*
	WeekDayInstanceinMonth:
	Get 'counted' instance of a specific weekday @dow in a specific @month and @year
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	@instance: wanted instance of the day -5..-1,1..5
	Returns: array, empty upon error, or single member has
	 key: @year (4-digit integer)
	 val: array with one 0-based day-of-year in the year
	*/
	private function WeekDayInstanceinMonth($year, $month, $dmax, $dow, $instance)
	{
		//first day in $year/$month as: 0 = Sunday .. 6 = Saturday
		$st = gmmktime(0,0,1,$month,1,$year);
		$firstdow = (int)gmdate('w',$st);
		//offset to 1st instance of the wanted day
		$d = $dow - $firstdow;
		if ($d < 0)
			$d += 7;
		if ($instance < 0) {
			$imax = (int)(($dmax - $d)/7); //no. of wanted days in the month
			$instance += 1 + $imax;
			if ($instance < 0 || $instance > $imax)
				return array();
		}
		if ($instance > 0) {
			$d += ($instance-1) * 7;
			if ($d >= $dmax)
				return array();
		} else
			return array();
		//first day in $year/$month as day-of-year
		$base = (int)gmdate('z',$st);
		$base += $d;

		return array($year=>array($base));
	}

	/*
	SeparatedMonths:
	Get each day in each n'th-month in a specified range
	@interval: integer no. of months between successive reports, >= 2
	@styear: numeric year e.g. 2000
	@stmonth: numeric month 1..12 in @styear
	@ndyear: numeric year e.g. 2000 or FALSE to use @styear
	@ndmonth: numeric month 1..12 in @ndyear or FALSE to use @stmonth
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year in [0]
	*/
/*	private function SeparatedMonths($interval, $styear, $stmonth,
		$ndyear=FALSE, $ndmonth=FALSE)
	{
		$t = $styear.'-'.$stmonth.'-1 0:0:0';
 		$dtw = new \DateTime($t,new \DateTimeZone('UTC'));
		if ($ndyear == FALSE)
			$ndyear = $styear;
		if ($ndmonth == FALSE)
			$ndmonth = $stmonth;
 		$dte = clone $dtw;
		$dte->setDate($ndyear,$ndmonth,1);
		$offs = '+'.$interval.' months';
		$ret = array();

		while ($dtw <= $dte) {
			$data = $dtw->format('Y|t|z');
			list($yn,$dn,$doy) = explode('|',$data);
			if (isset($ret[$yn])) {
				$ret[$yn] = array_merge($ret[$yn],range($doy,$doy+$dn-1));
			} else {
				$ret[$yn] = range($doy,$doy+$dn-1);
			}
			$dtw->modify($offs);
		}

		return $ret;
	}
*/
	/*
	SeparatedWeeks:
	Get each day in each n'th-week in a specified range
	@interval: integer no. of weeks between successive reports, >= 2
	@styear: numeric year e.g. 2000
	@stmonth: numeric month 1..12 in @styear
	@stweek: identfier in @styear/@stmonth
	@ndyear: numeric year e.g. 2000 or FALSE to use @styear
	@ndmonth: numeric month 1..12 in @ndyear or FALSE to use @stmonth
	@ndweek: identfier in @ndyear/@ndmonth or FALSE to use @stweek
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year in [0]
	*/
/*	private function SeparatedWeeks($interval, $styear, $stmonth, $stweek,
		$ndyear=FALSE, $ndmonth=FALSE, $ndweek=FALSE)
	{
		$t = $styear.'-'.$stmonth.'-1 0:0:0';
 		$dtw = new \DateTime($t,new \DateTimeZone('UTC'));
		$ns = $dtw->format('w'); //7-$ns = no. of days in 1st week
//		$ds = $dtw->format('t'); //no. of days in 1st month
		//start of wanted week, may be before start of month!
		if ($stweek > 1) {
			$d = ($stweek-1)*7 - $ns;
			$dtw->modify('+'.$d.' days');
		} elseif ($ns > 0) {
			$dtw->modify('-'.$ns.' days');
		}
		if ($ndyear == FALSE)
			$ndyear = $styear;
		if ($ndmonth == FALSE)
			$ndmonth = $stmonth;
		if ($ndweek == FALSE)
			$ndweek = $stweek;
 		$dte = clone $dtw;
		$dte->setDate($ndyear,$ndmonth,1);
		$ne = $dte->format('w');
		//start of wanted week, whose end may be after end of month!
		if ($ndweek > 1) {
			$d = ($ndweek-1)*7 - $ne;
			$dte->modify('+'.$d.' days');
		} elseif ($ne > 0) {
			$dte->modify('-'.$ne.' days');
		}
		$offs = '+'.$interval.' weeks';
		$ret = array();

 		while ($dtw <= $dte) {
			$data = $dtw->format('Y|z');
			list($yn,$doy) = explode('|',$data);
			if (isset($ret[$yn])) {
				$ret[$yn] = array_merge($ret[$yn],range($doy,$doy+6));
			} else {
				$ret[$yn] = range($doy,$doy+6);
			}
			$dtw->modify($offs);
		}
		//TODO trim 1st and/or last weeks to corresponding months
//		$de = $dte->format('t'); //no. of days in last month

 		return $ret;
	}
*/
	/*
	SeparatedDays:
	Get each n'th-day in a specified range
	@interval: integer no. of days between successive reports, >= 2
	@styear: numeric year e.g. 2000
	@stmonth: numeric month 1..12 in @styear
	@stday: identfier in @styear/@stmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W))
	@ndyear: numeric year e.g. 2000 or FALSE to use @styear
	@ndmonth: numeric month 1..12 in @ndyear or FALSE to use @stmonth
	@ndday: identfier in @ndyear/@ndmonth e.g. 1,-1,1D1,-2D6 TODO support e.g. D4(2(W)) or FALSE to use last day-of-month
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
/*	private function SeparatedDays($interval, $styear, $stmonth, $stday,
		$ndyear=FALSE, $ndmonth=FALSE, $ndday=FALSE)
	{
		if ($ndyear == FALSE)
			$ndyear = $styear;
		if ($ndmonth == FALSE)
			$ndmonth = $stmonth;
		if ($stday < 0) {
			$st = gmmktime(0,0,0,$stmonth,1,$styear);
			$stday += 1 + gmdate('t',$st);
		} elseif (($p = strpos($stday,'D')) !== FALSE) {
			//TODO support e.g. D4(2(W))
			$st = gmmktime(0,0,0,$stmonth,1,$styear);
			$dmax = gmdate('t',$st);
			$dow = (int)substr($stday,$p+1) - 1; //D1 >> 0 etc
			$c = ($p > 0) ? (int)substr($stday,0,$p) : 1;
			if ($c == 0) $c = 1;
			$t2 = self::WeekDayInstanceinMonth($styear,$stmonth,$dmax,$dow,$c);
			$stday = ($t2 >= 0) ? $t2 : 1; //default to start
		}
		if ($ndday == FALSE) {
			$st = gmmktime(0,0,0,$ndmonth,1,$ndyear); //days in month
			$ndday = (int)gmdate('t',$st);
		} elseif ($ndday < 0) {
			$st = gmmktime(0,0,0,$ndmonth,1,$ndyear);
			$ndday  += 1 + gmdate('t',$st);
		} elseif (($p = strpos($ndday,'D')) !== FALSE) {
			//TODO support e.g. D4(2(W))
			$st = gmmktime(0,0,0,$ndmonth,1,$ndyear);
			$dmax = (int)gmdate('t',$st);
			$dow = (int)substr($ndday,$p+1) - 1; //D1 >> 0 etc
			$c = ($p > 0) ? (int)substr($ndday,0,$p) : 1;
			if ($c == 0) $c = 1;
			$t2 = self::WeekDayInstanceinMonth($ndyear,$ndmonth,$dmax,$dow,$c);
			$ndday = ($t2 >= 0) ? $t2 : $dmax; //default to end
		}
		$t = $styear.'-'.$stmonth.'-'.$stday.' 0:0:0';
 		$dtw = new \DateTime($t,new \DateTimeZone('UTC'));
 		$dte = clone $dtw;
		$dte->setDate($ndyear,$ndmonth,$ndday);
		$offs = '+'.$interval.' days';
		$ret = array();
		while ($dtw <= $dte) {
			$data = $dtw->format('Y|z');
			list($yn,$doy) = explode('|',$data);
			if (!isset($ret[$yn])) {
				$ret[$yn] = array($doy);
			} else {
				$ret[$yn][] = $doy;
			}
			$dtw->modify($offs);
		}
		return $ret;
	}
*/
	/**
	SpecificYears:
	@descriptor: string including 4-digit year, or sequence of those, or
		','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report year-starts instead of day-starts
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or year in the year
	*/
	public function SpecificYears($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || empty($parsed['years'])) {
			return array();
		} elseif (!is_array($parsed['years'])) { //each n'th year
			$d = $parsed['years'];
			$wanted = array();
//			$wanted[] = each d'th in ?..?
		} else {
			$wanted = $parsed['years'];
		}

		$years = self::BlockYears($bs,$be,$dtw); //get year(s) in block, clean $bs,$be
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
	@descriptor: string including token M1..M12, or sequence(s) of those,
		or ','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report month-starts instead of day-starts
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or month in the year
	*/
	public function SpecificMonths($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || empty($parsed['months'])) {
			return array();
		}
		$years = self::BlockYears($bs, $be, $dtw); //get year(s) in block, clean $bs,$be
		if (isset($parsed['years'])) {
//			if (is_array($parsed['years'])) {
				$wanted = $parsed['years'];
/*			} else { //each n'th year
				$d = $parsed['years'];
				$wanted = array();
				for ($i=$TODO; $i<$TODO; $i+=$d) {
					$wanted[] = $i;
				}			
			}
*/
			$years = array_intersect($years,$wanted);
			if (!$years)
				return array();
		}
		//get relevant months
		$months = self::BlockMonths($bs,$be,$dtw);
		if (is_array($parsed['months'])) {
			$wanted = $parsed['months'];
		} else { //each n'th month
			$d = $parsed['months'];
			$wanted = array();
			for ($i=1; $i<13; $i+=$d) {
				$wanted[] = $i;
			}			
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
	@descriptor: string including token W1..W5 or W-5..W-1, or sequence of those,
		or ','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	@merge: optional boolean, whether to report week-starts instead of day-starts
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day or week in the year
	*/
	public function SpecificWeeks($descriptor, $bs, $be, $dtw, $merge=FALSE)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || empty($parsed['weeks'])) {
			return array();
		}
		$years = self::BlockYears($bs,$be,$dtw); //get year(s) in block
		if (isset($parsed['years'])) {
			$years = array_intersect($years,$parsed['years']); //each n'th year N/A
			if (!$years)
				return array();
		}
		if (isset($parsed['months'])) {
			$months = self::BlockMonths($bs,$be,$dtw);
			if (is_array($parsed['months'])) {
				$wanted = $parsed['months'];
			} else { //each n'th month
				$d = $parsed['months'];
				$wanted = array();
				for ($i=1; $i<13; $i+=$d) {
					$wanted[] = $i;
				}			
			}
			$months = array_intersect($months,$wanted);
			if (!$months)
				return array();
		} else {
			$months = FALSE;
		}
		if (is_array($parsed['weeks'])) {
			$weeks = $parsed['weeks'];
		} else { //each n'th week
			$d = $parsed['weeks'];
			$weeks = array();
			for ($i=1; $i<6; $i+=$d) { //CHECKME need year/month specific max weeks?
				$weeks[] = $i;
			}
		}

		$wanted = self::AllDays($years,$months,$weeks);
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
	@descriptor: string including token D1..D7, or number 1..31 or -31..-1, or
		a sequence of those, or ','-separated series of any of the former
	@bs: timestamp for start of block
	@be: timestamp for 1-past-end of block
	@dtw: modifiable DateTime object
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
	*/
	public function SpecificDays($descriptor, $bs, $be, $dtw)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || empty($parsed['days'])) {
			return array();
		}
		$years = self::BlockYears($bs,$be,$dtw); //get year(s) in block
		if (isset($parsed['years'])) {
			$years = array_intersect($years,$parsed['years']); //each n'th year N/A
			if (!$years)
				return array();
		}
		if (isset($parsed['months'])) {
			$months = self::BlockMonths($bs,$be,$dtw);
			if (is_array($parsed['months'])) {
				$wanted = $parsed['months'];
			} else { //each n'th month
				$d = $parsed['months'];
				$wanted = array();
				for ($i=1; $i<13; $i+=$d) {
					$wanted[] = $i;
				}			
			}
			$months = array_intersect($months,$wanted);
			if (!$months)
				return array();
		} else {
			$months = FALSE;
		}
		if (isset($parsed['weeks'])) {
			if (is_array($parsed['weeks'])) {
				$weeks = $parsed['weeks'];
			} else { //each n'th week
				$d = $parsed['weeks'];
				$weeks = array();
				for ($i=1; $i<6; $i+=$d) { //CHECKME need year/month specific max weeks?
					$weeks[] = $i;
				}
			}
		} else {
			$weeks = FALSE;
		}
		if (is_array($parsed['days'])) {
			$days = $parsed['days'];
		} else { //each n'th day
			$d = $parsed['days'];
			$days = array();
			for ($i=1; $i<32; $i+=$d) { //CHECKME need year/month-specific maxdays?
				$days[] = $i;
			}
		}

		$wanted = self::AllDays($years,$months,$weeks,$days); //must get something
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
	Returns: array, empty upon error, otherwise each member has
	 key: a year (4-digit integer)
	 val: array of integers, each a timestamp for the beginning of a day in the year
*/
	public function SpecificDates($descriptor, $bs, $be, $dtw)
	{
		$parsed = self::InterpretDescriptor($descriptor);
		if (!$parsed || empty($parsed['dates'])) {
			return array();
		}

/*TODO	if (0) { //each n'th date
//			$d = $parsed['dates'];
//			return self::SeparatedDays($matches[1],$styear,$stmonth,$stday,$ndyear,$ndmonth,$ndday);
		}
*/

		$ret = array();
		$dtw->setTime(0,0,0);
		foreach ($parsed['dates'] as $s) {
			$dtw->modify($s);
			$st = $dtw->getTimestamp();
			if ($st >= $bs && $st < $be) {
				$yn = (int)$dtw->format('Y');
				if (isset($ret[$yn])) {
					$ret[$yn][] = $st;
				} else
					$ret[$yn] = array($st);
			}
		}

		if ($ret) {
			asort($ret,SORT_NUMERIC);
			foreach ($ret as &$doy) {
				$doy = array_unique($doy,SORT_NUMERIC);
			}
			unset($doy);
		}
		return $ret;
	}
}
