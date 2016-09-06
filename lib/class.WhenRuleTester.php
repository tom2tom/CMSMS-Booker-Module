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
//TODO more validation of this stuff
/*
'EE2YE(2016..2020)',
'EE2(2016..2019)',
'EE10DE(EE2ME(2016))',
'EE10DE(EE2ME(M7..M9))',
'EE10DE(EE3ME(2016..2016))',
'EE7DE(EE3ME(2016,2018,2020))',
'EE14DE(EE3ME(EE2(2016..2020)))',
'EE10DE(M5)',
'EE20DE(M5,M7)',
'EE10DE(M5..M9)',
'EE10DE(M6,M7)',
'EE10DE(M9..M12)',
'EE2(2015..2020)',
*/
//'EE2D3(2015)', 199
//'EE2D3(2015..2016)', 199
/* NOT ALL DEBUGGED
'EE2D3(2016-11)',
'EE2D3(EE2ME(2016))',
'EE2D3(EE2ME(M7..M9))',
'EE2D3(EE3ME(2016..2020))',
'EE2D3(EE3ME(EE2(2016..2020)))',
*/
/*
'EE2D3(M5)',
'EE2D3(M5,M7)',
'EE2D3(M5..M9)',
'EE2D3(M6,M7)',
'EE2D3(M9..M12)',
'EE2DE(-1(W(2020-10)))',
'EE2DE(1..3(W(2020-10)))',
'EE2DE((1,-1)(W(2020-10)))',
'EE2DE((1,2)(W(2019-10,2020-10)))',
'EE2DE(2(W(2020)))',
'EE2DE(2(W(2020-10)))',
'EE2DE(2(W(M5)))',
*/
//'EE2DE(EE2WE(2020))',
//'EE2DE(EE2WE(2020-10))',
/*
'EE2DE(EE2WE(M5))',
'EE2ME(2015)',
'EE2ME(2015..2020)',
'EE2ME(M1..M12)',
'EE2WE(2015)',
'EE2WE(2015..2020)',
'EE2WE(2015-5)',
'EE2WE(2020-5..2020-12)',
'EE2ME(2020-5..2020-12)',
*/
'EE2WE(EE2ME(M7..M9))',
'EE2WE(EE2YE(2015..2018))',
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
		$bres = array();
		$dtw = new \DateTime('@0',NULL);
		$funcs = new PeriodInterpreter();
		foreach ($tests as $test) {
			$ares[$test] = $funcs->InterpretDescriptor($test);
			$years = ($ares[$test]['years'][0] != '*') ?
				$ares[$test]['years'] : array(2016,2017);
			$ares[$test]['alldays'] = $funcs->AllDays($years,$ares[$test]['months'],$ares[$test]['weeks'],$ares[$test]['days'],$dtw);
		}
		$this->Crash();
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
