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
	/**
	BlockDays:
	@ss: timestamp for start of block
	@se: timestamp for 1-past-end of block
	@dtw: DateTime worker
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	public function BlockDays($ss, $se, $dtw)
	{
		$dtw->setTimestamp($ss);
		$dtw->setTime(0,0,0);
		$yn = (int)$dtw->format('Y');
		$dte = clone $dtw;
		$dte->setTimestamp($se-1);
		$dte->setTime(0,0,0);
		$ye = (int)$dte->format('Y');

		$dt2 = FALSE;
		$ret = array();

		while ($yn < $ye) {
			$doy = array();
			if (!$dt2)
				$dt2 = clone $dtw;
			$t = ($yn+1).'-1-1 0:0:0';
			$dt2->modify($t);
			while ($dtw < $dt2) {
				$doy[] = $dtw->getTimestamp();
				$dtw->modify('+1 day');
			}
			if ($doy) {
				$ret[$yn] = $doy;
			}
			$dtw = $dt2;
			$yn++;
		}
		$doy = array();
		while ($dtw <= $dte) {
			$doy[] = $dtw->getTimestamp();
			$dtw->modify('+1 day');
		}
		if ($doy)
			$ret[$ye] = $doy;

		return $ret;
	}

	/**
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
	public function MonthWeekDays($year, $month, $dmax, $dow)
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

	/**
	WeeksDays:
	Get array of each instance of a specific weekday @dow in specified week(s)
	in a specific month
	@year: numeric year e.g. 2000
	@month: numeric month 1..12 in @year
	@week: array if numeric week(s) -5..-1,1..5 in @year AND @month
	@dmax: 1-based index of last day in @month
	@dow: index of wanted day-of-week, 0 (for Sunday) .. 6 (for Saturday) c.f. date('w'...)
	Returns: array, or FALSE upon error. The single-member array has
	 key: @year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	public function WeeksDays($year, $month, $week, $dmax, $dow)
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

	/**
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
	public function MonthDay($year, $month, $dmax, $count, $dow)
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
	YearDays:
	Get days-of-year in a specified range
	@year: year or array of them or ','-separated series of them
	  Each year is 4-digit e.g. 2000 or 2-digit e.g. 00 or anything else that
	  can be validly processed via date('Y')
	@month: optional tokenised month(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric 1..12 or with 'M' prefix i.e. M1..M12
		FALSE means all months in @year
	@week: optional tokenised weeks(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -5..-1,1..5 or with 'W' prefix i.e. W-5..W-1,W1..W5
		FALSE means all DAYS in @month AND @year
	@day: optional tokenised day(s) identifier, string or array, default FALSE
		String may be ','-separated series. Tokens numeric -31..-1,1..31 or with 'D' prefix i.e. D1..D7
		FALSE means all days in @week (if any) AND @month AND @year
	Returns: array, or FALSE upon error. Each array member has
	 key: a year (4-digit integer)
	 val: array of integers, each a 0-based day-of-year in the year
	*/
	public function YearDays($year, $month=FALSE, $week=FALSE, $day=FALSE)
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
	args as as for YearDays($year,$month=FALSE,$week=FALSE,$day=FALSE)
	*/
	public function tester($year, $month, $week, $day)
	{
		$ret = array();
		$dt = new \DateTime('@0',new \DateTimeZone('UTC'));
		$data = self::YearDays($year,$month,$week,$day);
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
