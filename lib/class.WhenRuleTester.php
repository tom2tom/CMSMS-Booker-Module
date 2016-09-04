<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: WhenRuleTester
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class WhenRuleTester
{
	protected $mod; //reference to current module-object
	/*
	$conds will be array of parsed descriptors, or FALSE

	Each member of $conds is an array, with members:
	'F' => 'focus'-enum (as below) to assist interpeting any or all applicable
	PERIOD value(s)
		 0 can't decide
		 1 no period i.e. any (time-only)
		 2 month(s) of any year June,July
		 3 week(s) of any month (1,-1)week
		 4 day(s) of any week Sun..Wed
		 5 day(s) of any month 1,10,-2 OR 1(Sunday)
		 6 specific year(s) 2020,2015
		 7 month(s) of specific year(s) Jan(2010..2020) OR 2015-1 OR each 3 month(2000-1..2002-12)
		 8 week(s) of specific month(s) 1(week(August,September)) OR each 2 week(2000-1..2000-12)
		 9 week(s) of specific [month(s) and] year(s) 1(week(Aug..Dec(2020))) OR each 4 week(2000..2001)
		10 day(s) of specific week(s)  Wed(2(week)) OR (Wed..Fri)(each 2(week))
		11 day(s) of specific [week(s) and] month(s) 1(Aug) OR Wed((1,2)(week(June)))
			OR each 2 day(2000-1..2000-2) OR 2(Wed(June)) OR (1,-1)(Sat(June..August))
		12 day(s) of specific [week(s) and/or month(s) and] year(s) Wed((1,-1)(week(June(2015..2018))))
		13 specfic day/date(s) 2010-6-6 OR 1(Aug(2015..2020))
	'P' => FALSE or PERIOD = structure of arrays and strings representing
		period-values and/or period-value-ranges (i.e. not series), all ordered by
		increasing value/range-start (TODO EXCEPT ROLLOVERS?) Negative values
		in ['P'] are sorted after positives, but not interpreted to corresponding
		actual values.
	'T' => FALSE or TIME = array of strings representing time-values and/or
		time-value-ranges and/or time-value-series, with sun-related ones first,
		all ordered by increasing value/range-start (TODO EXCEPT ROLLOVERS?)
		Times are not interpreted. Other than sun-related values, they can be
		converted to midnight-relative seconds, and any overlaps 'coalesced'.
		Sun-related values must of course be interpreted for each specific day
		evaluated.
	perhaps - cached interpretation data:
	'S' => resource-local date which is the earliest for (currently) interpreted data in ['A']
	'E' => resource-local date which is the latest for (currently) interpreted data in ['A']
	'A' => array of arrays, each with a pair of members:
		[0] = 4-digit year, maybe with -ve sign indicating this is data for a
			'except/not' interval
		[1] = array of 0-based day-of-year indices for the year in [0] and within
			the bounds of ['S'] to ['E'] inclusive

	Descriptor-string parsing works LTR. Maybe sometime RTL languages will also
	be supported ?!
	$conds will be sorted, first on members' ['F']'s, then on their ['P']'s,
	then on their ['T']'s.
	*/
	public function __construct(&$mod)
	{
		$this->mod = $mod; //cache current module object
	}

	public function Run()
	{
		$tests = array (
array('2000-10..2009-9,not(2005,2006-6)',	7),

array('March@15:00',	2),
array('March@(15:00,16:00,20:30)',	2),
array('January(2000)',	7),
array('(March,September),January(2000)',	2),
array('(March,September)@9:00,1(January(2000))',	2),
array('(March,September)@(9:00),January(2000)',		2),
array('2000',			6),
array('2000..2005',		6),
array('2020..2010',		6),
array('(2020,2024)',	6),
array('2016, 2014, 2020', 6), //EMPTY P/F/T due to Xdebug?
array('(2020..2030,except 2025)',	6),
array('2010,2014,2020,2008..2015',	6), //EMPTY P/F/T due to Xdebug?
array('January',		2),
array('(March,September)',	2),
array('July..December',		2),
array('March, January, July..December',	2), //EMPTY P/F/T due to Xdebug?
array('2000-6',			7),
array('(March,September)(2000)',	7),
array('July..December(2000)',		7),
array('2000-10..2001-3',			7),
array('2000-10..2009-9,not(2005,2006-6)',	7),
array('2009-10,2010-8,2010-2',		7),
array('2(week)',		3),
array('-1(week)',		3),
array('2..3(week)',		3),
array('(1,-1)(week)',	3),
array('2(week(March))',	8), //NO TYPE
array('1..3(week(July,August))',		8), //NO TYPE
array('(-2,-1)(week(April..July))',		8), //NO TYPE
array('(-2,1..3)(week(April..July))',	8), //NO TYPE
array('1(June)',		11),
array('-2(June..August)',	11),
array('(6..9,15,18,-1)(January,July)',	11),
array('2(Wednesday(June))',	11),
array('(1,-1)(Saturday(June..August))',	11),
array('1',		1),
array('1@',		5),
array('-2',		5),
array('1,2,3',	1),
array('(1,2,3)@',	5),
array('(15,18,-1,-2)',	5),
array('1..10',			1),
array('1..10@',		5),
array('2..-1',	5),
array('-3..-1',	5),
array('15,3..10,-3..-1',5),
array('1(Sunday)',		5),
array('-1(Wednesday..Friday)',	5),
array('1..3(Friday,Saturday)',	5),
array('Sunday(2(week(2016)))',		10),
array('Saturday,Sunday(July(2016..2018)))',		10),
array('(Saturday,Wednesday)(-3..-1(week(July)))',	11),
array('Monday..Friday((-2,-1)(week(April..July)))',	11),
array('(Monday..Friday)((-2,-1)(week)(April..July))',	11),
array('14,10,1..3,-1,-2(April..July)',	11),
array('Sunday(2(week))',		12),
array('Monday',		4),
array('(Monday,Wednesday,Friday)',	4),
array('Wednesday..Friday',			4),
array('Wednesday..Friday,Monday',	4),
array('2000-9-1',					13),
array('2000-10-1..2000-12-31',		13),
array('1(Aug(2015..2020))',			13),
//~~~~~~~~~~~~ SEPARATIONS ~~~~~~~~~~~
array('each 2(2015..2020)',			6),
array('each 2 year(2015..2020)',	6),
array('each 1(2015..2020)',			6),

array('each 2 month(2015)',			7),
array('each 2 month(2015..2020)',	7),
array('each 3 month(each 2 year(2015..2020))',7),
array('each 2 month(January..December)',	2),
array('each 3(July..December)',		2),

array('each 2 week(May)',			8),
array('each 2 week(May,July)',		8),
array('each 2 week(July,June)',		8),
array('each 2 week(May..September)',8),
array('each 2 week(December..September)',8),
array('each 2 week(each 2 month(July..September))',8),

array('each 2 week(2015)',			9),
array('each 2 week(2015..2020)',	9),
array('each 2 week((May,June)(2015..2020))',	9),
array('each 2 week(May(2015))',		9),
array('each 2 week(2015-5)',		9),
array('each 2 week(2020-5..2020-12)',	9),
array('each 2 week(2020-12..2020-5)',	9),
array('each 2 week(each 2 year(2015..2020))',9),
//----------------
array('each 30 day(2015)',			12),
array('each 30 day(2015..2016)',	12),

array('each 10 day(May)',			11),
array('each 10 day(May,July)',		11),
array('each 10 day(July,June)',		11),
array('each 10 day(May..September)',11),
array('each 10 day(December..September)',11),
array('each 10 day(each 2 month(July..September))',11),

array('each 10 day(each 2 month(2016))',12),
array('each 10 day(each 3 month(2016..2020))',12),
array('each 10 day(each 3 month(each 2(2016..2020)))',12),

array('each 2 day(2(week(May)))',		11),
array('each 2 day(each 2 week(May))',	11),
array('each 2 day(2(week(2020-10)))',	12),
array('each 2 day(-1(week(2020-10)))',	12),
array('each 2 day(each 2 week(2020-10))',	12),
array('each 2 day(2(week(2020)))',		12),
array('each 2 day(each 2 week(2020))',	12),
//----------------
array('each 2 Tuesday(2015)',			12),
array('each 2 Tuesday(2015..2016)',		12),

array('each 2 Tuesday(May)',			11),
array('each 2 Tuesday(May,July)',		11),
array('each 2 Tuesday(July,June)',		11),
array('each 2 Tuesday(May..September)',11),
array('each 2 Tuesday(December..September)',11),
array('each 2 Tuesday(each 2 month(July..September))',11),

array('each 2 Tuesday(each 2 month(2016))',12),
array('each 2 Tuesday(each 3 month(2016..2020))',12),
array('each 2 Tuesday(each 3 month(each 2(2016..2020)))',12),
//----------------
array('Tuesday(each 2 week(May))',	11),
array('Tuesday(each 2 week(May..September))',	11),
array('Tuesday(each 2 week(2020-10))',	12),
array('Tuesday(each 2 week(2020))',	12),
array('Tuesday(each 2 week(each 3 month(2020)))',	12),
array('Tuesday(each 3 month(2020))',	12),
//~~~~~~~~~~~~ TIMES ~~~~~~~~~~~
array('9',		1),
array('2:30',		1),
array('(9,12,15:15)',	1),
array('(12:00,14,10)',	1),
array('15:30,15:15',	1),
array('12..23',		1),
array('6..15:30',	1),
array('10,15:30..18,8..9',	1),
array('sunrise..16',	1),
array('9..sunset-3:30',	1),
array('sunrise 1:15..sunset',	1),
array('sunrise+1:15..sunrise+3:45',	1),
array('sunrise+5:15..sunrise+3:45',	1),
array('sunset-5:15..sunset-6:15',	1),
array('sunset+2..sunset-1',	1),
array('sunrise+5:15..sunrise+5:15',	1),
array('0..sunrise,sunset..11:59',	1),
		);

		$funcs = new WhenRuleLexer($this->mod);
//		$funcs2 = new PeriodInterpreter();
		$ares = array();
		foreach ($tests as &$test) {
			$res = $funcs->CheckDescriptor($test[0]);
			$clean = ($res == $test[0]) ? 'Samestring' : $res;
			$parsed = $funcs->conds;
			if (isset($parsed[0]['F'])) {
				$type = ($parsed[0]['F'] == $test[1]) ? 'Sametype' : $test[1].' >> '.$parsed[0]['F'];
			} else
				$type = 'No type, expected '.$test[1];
			$ares[$test[0]] = array($clean, $type, $parsed);
		}
		unset($test);
		$this->Crash();
	}

	public function Separations()
	{
		$tests = array(
'EE2YE(2016..2024)',
'EE2(2016..2024)',
'EE10DE(EE2ME(2016))',
'EE10DE(EE2ME(M7..M9))',
'EE10DE(EE3ME(2016..2020))',
'EE7DE(EE3ME(2016,2018,2020))',
'EE14DE(EE3ME(EE2(2016..2020)))',
'EE10DE(M5)',
'EE20DE(M5,M7)',
'EE10DE(M5..M9)',
'EE10DE(M6,M7)',
'EE10DE(M9..M12)',
'EE2(2015..2020)',
'EE2D3(2015)',
'EE2D3(2015..2016)',
'EE2D3(EE2ME(2016))',
'EE2D3(EE2ME(M7..M9))',
'EE2D3(EE3ME(2016..2020))',
'EE2D3(EE3ME(EE2(2016..2020)))',
'EE2D3(M5)',
'EE2D3(M5,M7)',
'EE2D3(M5..M9)',
'EE2D3(M6,M7)',
'EE2D3(M9..M12)',
'EE2DE(-1(W(2020-10)))',
'EE2DE(1..3(W(2020-10)))',
'EE2DE((1,-1)(W(2020-10)))',
'EE2DE(2(W(2020)))',
'EE2DE(2(W(2020-10)))',
'EE2DE(2(W(M5)))',
'EE2DE(EE2WE(2020))',
'EE2DE(EE2WE(2020-10))',
'EE2DE(EE2WE(M5))',
'EE2ME(2015)',
'EE2ME(2015..2020)',
'EE2ME(M1..M12)',
'EE2WE(2015)',
'EE2WE(2015..2020)',
'EE2WE(2015-5)',
'EE2WE(2020-5..2020-12)',
'EE2ME(2020-5..2020-12)',
'EE2WE(EE2ME(M7..M9))',
'EE2WE(EE2YE(2015..2020))',
'EE2WE(M5)',
'EE2WE(M5(2015))',
'EE2WE((M5,M6)(2015..2020))',
'EE2WE(M5,M7)',
'EE2WE(M5..M9)',
'EE2WE(M6,M7)',
'EE2WE(M9..M12)',
'EE2YE(2015..2020)',
'EE30DE(2015)',
'EE30DE(2015..2016)',
'EE3(M7..M12)',
'EE4ME(EE2YE(2015..2020))',
'D3(EE2WE(2020))',
'D3(EE2WE(2020-10))',
'D3(EE2WE(EE3ME(2020)))',
'D3(EE2WE(M5))',
'D3(EE2WE(M5..M9))',
'D3(EE3ME(2020))',
'(D3,D4)(EE3ME(2020))'
		);
		$ares = array();
		foreach ($tests as &$test) {
			$ares[$test] = self::InterpretDescriptor($test);
		}
		unset($test);
		$this->Crash();
	}

	private function separate($hint, $interval, &$found)
	{
		if ($hint) {
			switch ($hint) {
			 case 'D':
				$key = 'days';
				if ($found[$key][0] == '*') {
					if ($found['months'][0] != '*' || $found['years'][0] == '*') {
						$found[$key] = range(1,31);
					} elseif ($found['weeks'][0] != '*') {
						$found[$key] = range(1,7);
					} else {
						$found[$key] = range(1,366);
					}
				}
				break;
			 case 'W':
				$key = 'weeks';
				if ($found[$key][0] == '*') {
					if ($found['months'][0] != '*' || $found['years'][0] == '*') {
						$found[$key] = range(1,5);
					} else {
						$found[$key] = range(1,52);
					}
				}
				break;
			 case 'M':
				$key = 'months';
				if ($found[$key][0] == '*')
					$found[$key] = range(1,12);
				break;
			 case 'Y':
				$key = 'years';
				break;
			 default:
				return;
			}
			if ($found[$key][0] == '*')
				return;
		} else {
			foreach (array('days','weeks','months','years','-1') as $i=>$key) {
				if ($found[$key][0] != '*') {
					break;
				}
			}
			if ($i > 3)
				return;
		}

		$i = 0;
		foreach ($found[$key] as $k=>$value) {
			if ($i++ % $interval != 0) {
				unset($found[$key][$k]);
			}
		}
	}

	//$prefix 1-byte or FALSE
	//returns array
	private function rangify($element, $prefix)
	{
		$parts = explode('..',$element);
		$s = $parts[0];
		$e = $parts[1];
		if ($prefix) {
			if ($s[0] == $prefix)
				$s = substr($s,1);
			if ($e[0] == $prefix)
				$e = substr($e,1);
		}
		if ($s < $e) {
/*			if ($prefix)
				return array_map(function($s) use($prefix) {
					return $prefix.$s;
				},range($s,$e));
			else
*/
				return range($s,$e);
		} else { //should never happen
//			return array($parts[0]);
			return array($s);
		}
	}

	//returns array
	private function rangifymonth($element)
	{
		$parts = explode('..',$element);
		$dtw = new \DateTime('@0',NULL);
		$dtw->modify($parts[0].'-1 0:0:0');
		$dte = clone $dtw;
		$dte->modify($parts[1].'-1 0:0:0');
		if ($dtw < $dte) {
			$ret = array();
			while ($dtw <= $dte) {
				$ret[] = $dtw->format('Y-n');
				$dtw->modify('+1 month');
			}
			return $ret;
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
				} elseif (is_numeric($element)) {
					$ret[] = (int)$element;
				} elseif ($prefix && $prefix == $element[0] && $prefix != 'D') {
					$ret[] = (int)substr($element,1);
				} else {
					$ret[] = $element;
				}
			}
			return array_unique($ret);
		} elseif (strpos($element,'..') !== FALSE) {
			return self::rangify($element,$prefix);
		}
		if ($prefix && $prefix != 'D') {
			$element = substr($element,1);
		}
		if (is_numeric($element)) {
			$element = (int)$element;
		}
		return array($element);
	}

	/*
	InterpretDescriptor:
	@descriptor: string like
	 A(B(C(D(E)))) where any/all of [ABCD] and associated '()' may be absent,
	   or may be like (P,Q,R) i.e. bracketed
	 or W,X[,Y...] where any/all may be S..E
	 or single S..E
	Returns: array with members 'years','months','weeks','days' and maybe 'dates'
	Each member an array of numbers or strings, or a single string:
	'days' may have members like 'D3' or 'EE2D3' or may be '*'
	'weeks','months' and/or 'years' may be '*' or '-'
	*/
	private function InterpretDescriptor($descriptor)
	{
		if (strpos($descriptor,'(') !== FALSE) {
			$descriptor = str_replace(array('!',')','(('),array('','','('),$descriptor); //omit element-closers
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
		$ic = count($parts);
		$dc = 0;
		$found = array('years'=>'*','months'=>'*','weeks'=>'*','days'=>'*'); //no 'dates'
		for ($i=0; $i<$ic; $i++) { //NOT foreach cuz members can change on-the-fly
			$element = $parts[$i];
			if ($found['years'][0] == '*') {
				if (preg_match('/^[12]\d{3}([,.].+)?$/',$element)) {
					$found['years'] = self::toarray($element);
					$dc++;
					continue;
				} elseif (preg_match('/^EE([2-9]|1\d+)YE$/',$element,$matches)) {
					$found['years'] = array(); //'eacher' but we can't know where to start/end
					$dc++;
					continue;
				}
			}
			if ($found['months'][0] == '*') {
				if (strpos($element,'M') !== FALSE) {
					if (preg_match('/^EE([2-9]|1[012])ME$/',$element,$matches)) { //each n'th month
						if ($found['years'][0] != '*')
							$found['months'] = range(1,12,$matches[1]);
						$dc++;
						continue;
					} elseif ($element != 'M') {
						$found['months'] = self::toarray(str_replace('M','',$element));
						$dc++;
						continue;
					} else {
						$parts[$i+1] = 'M'.$parts[$i+1];
						$dc++;
						continue;
					}
				} elseif (preg_match('/^[12]\d{3}\-(0?[1-9]|1[0-2])([,.].+)?$/',$element)) {
					$found['months'] = self::rangifymonth($element);
					$found['years'] = '-';
					$dc++;
					continue;
				}
			}
			if ($found['weeks'][0] == '*') {
				if (strpos($element,'W') !== FALSE) {
					if (preg_match('/^EE([2-9]|[1-5]\d)WE$/',$element,$matches)) { //each n'th week
						if ($found['months'][0] != '*')
							$d = 5; //upstream must check year/month specific max weeks
						elseif ($found['years'][0] != '*') {
							$d = 52;
							$found['months'] = '-';
						} else
							$d = 0;
						if ($d > 0)
							$found['weeks'] = range(1,$d,$matches[1]);
						$dc++;
						continue;
					} elseif ($element != 'W') {
						$found['weeks'] = self::toarray($element,'W');
						$dc++;
						continue;
					} else {
						$parts[$i+1] = 'W'.$parts[$i+1];
						$dc++;
						continue;
					}
				}
			}
			if ($found['days'][0] == '*' || isset($found['dates'])) {
				if (strpos($element,'D') !== FALSE) {
					if (preg_match('/^EE([2-9]|[1-3]\d{1,2}|[4-9]\d)DE$/',$element,$matches)) {
						if ($found['weeks'][0] != '*')
							$d = 7;
						elseif ($found['months'][0] != '*') {
							$d = 31; //upstream must check year/month specific max days
							$found['weeks'] = '-';
						} elseif ($found['years'][0] != '*') {
							$d = 366; //upstream must check year specific max days
							$found['weeks'] = '-';
							$found['months'] = '-';
						} else
							$d = 0;
						if ($d > 0)
							$found['days'] = range(1,$d,$matches[1]);
						$dc++;
						continue;
					} elseif ($element != 'D') {
						$found['days'] = self::toarray($element,'D');
						$dc++;
						continue;
					} else {
						$parts[$i+1] = 'D'.$parts[$i+1];
						$dc++;
						continue;
					}
				} elseif (preg_match('/^(\-)?([1-9]|[12]\d|3[01])([,.].+)?$/',$element)) { //day(s) of month
					$found['days'] = self::toarray($element);
					$dc++;
					continue;
				} elseif (preg_match('/^[12]\d{3}\-(0?[1-9]|1[0-2])\-(0?[1-9]|[12]\d|3[01])([,.].+)?$/',$element)) { //date(s)
					if (strpos($element,',') !== FALSE) {
						$bits = explode(',',$element);
						$xtras = array();
						foreach ($bits as &$b) {
							if (strpos($b,'..') !== FALSE) {
								$xtras = array_merge($xtras,self::rangifydate($b));
								unset($b);
							}
						}
						unset($b);
						if ($xtras) {
							$bits = array_merge($bits,$xtras);
						}
						$found['dates'] = array_unique($bits,SORT_STRING);
						$dc++;
						continue;
					} elseif (strpos($element,'..') !== FALSE) {
						$found['dates'] = self::rangifydate(ltrim($element,'!'));
						$dc++;
						continue;
					} else {
						$found['dates'] = array(ltrim($element,'!'));
						$dc++;
						continue;
					}
				}
			}
			if (preg_match('/^EE([2-9]|1\d+)((.)E)?$/',$element,$matches)) {
				$hint = isset($matches[3]) ? $matches[3]:FALSE;
				self::separate($hint,(int)$matches[1],$found);
				$dc++;
			}
		}

		if ($dc == $lastkey) { //1 more unparsed element
			if (preg_match('/^EE([2-9]|1\d+)((.)E)?$/',$element,$matches)) {
				$hint = isset($matches[3]) ? $matches[3]:FALSE;
				self::separate($hint,(int)$matches[1],$found);
			}
		}

		if ($found['days'][0] != '*') {
			if ($found['months'][0] == '*') {
				if ($found['years'][0] != '*') {
					$found['months'] = '-';
				}
			}
			if ($found['weeks'][0] == '*') {
				if ($found['months'][0] != '*') {
					$found['weeks'] = '-';
				}
			}
		} else { //all days
			if ($found['weeks'][0] == '*') {
				$found['weeks'] = '-'; //ignore weeks
			}
//			if (isset($found['dates'])) {
//				unset($found['days']);
		}

		return $found;
	}

	/**
	isodate_from_format:
	Convert @dvalue to ISO format i.e. like Y-M-d H:i:s
	For testing, at least
	@dformat: string which includes one or more of many (not all) format-characters
	 understood by PHP date(). If it includes 'z', the corresponding element of
	 @dvalue must be 1-based
	@dvalue: date-time string consistent with @dformat
	*/
	private function isodate_from_format($dformat, $dvalue)
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

	/**
	args as as for PeriodInterpreter::AllDays($year,$month=FALSE,$week=FALSE,$day=FALSE)
	*/
	public function AllDaysTester($year, $month, $week, $day)
	{
		$funcs = new PeriodInterpreter();
		$ret = array();
		$dt = new \DateTime('@0',new \DateTimeZone('UTC'));
		$data = $funcs->AllDays($year,$month,$week,$day);
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

	/**
	args as as for PeriodInterpreter::SuccessiveDays($interval,$styear,$stmonth,$stday,$ndyear=FALSE,$ndmonth=FALSE,$ndday=FALSE)
	*/
	public function SuccessiveDaysTester($interval, $styear, $stmonth, $stday,
		$ndyear=FALSE, $ndmonth=FALSE, $ndday=FALSE)
	{
		$funcs = new PeriodInterpreter();
		$ret = array();
		$dt = new \DateTime('@0',new \DateTimeZone('UTC'));
		$data = $funcs->SuccessiveDays($interval,$styear,$stmonth,$stday,$ndyear,$ndmonth,$ndday);
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
