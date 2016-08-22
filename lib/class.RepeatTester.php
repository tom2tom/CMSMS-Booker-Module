<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: RepeatTester
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class RepeatTester
{
	protected $mod; //reference to current module-object
	public $testscripts = array (
array('2000-10..2009-9,not(2005,2006-6)',	6),
        
array('March@15:00',	2),
array('March@(15:00,16:00,20:30)',	2),
array('January(2000)',	6),
array('(March,September),January(2000)',	2),
array('(March,September)@9:00,1(January(2000))',	2),
array('(March,September)@(9:00),January(2000)',		2),
array('2000',			5),
array('2000..2005',		5),
array('2020..2010',		5),
array('(2020,2024)',	5),
array('2016, 2014, 2020', 5), //EMPTY P/F/T due to Xdebug?
array('(2020..2030,except 2025)',	5),
array('2010,2014,2020,2008..2015',	5), //EMPTY P/F/T due to Xdebug?
array('January',		2),
array('(March,September)',	2),
array('July..December',		2),
array('March, January, July..December',	2), //EMPTY P/F/T due to Xdebug?
array('January(2000)',	6),
array('2000-6',			6),
array('(March,September)(2000)',	6),
array('July..December(2000)',		6),
array('2000-10..2001-3',			6),
array('2000-10..2009-9,not(2005,2006-6)',	6),
array('2009-10,2010-8,2010-2',		6),
array('2(week)',		3),
array('-1(week)',		3),
array('2..3(week)',		3),
array('(1,-1)(week)',	3),
array('2(week(March))',	8), //NO TYPE
array('1..3(week(July,August))',		8), //NO TYPE
array('(-2,-1)(week(April..July))',		8), //NO TYPE
array('(-2,1..3)(week(April..July))',	8), //NO TYPE
array('1(June)',		10),
array('-2(June..August)',	10),
array('(6..9,15,18,-1)(January,July)',	10),
array('2(Wednesday(June))',	10),
array('(1,-1)(Saturday(June..August))',	10),
array('1',		1),
array('1@',		4),
array('-2',		4),
array('1,2,3',	1),
array('(1,2,3)@',	4),
array('(15,18,-1,-2)',	4),
array('1..10',			1),
array('1..10@',		4),
array('2..-1',	4),
array('-3..-1',	4),
array('15,3..10,-3..-1',4),
array('1(Sunday)',		4),
array('-1(Wednesday..Friday)',	4),
array('1..3(Friday,Saturday)',	4),
array('Sunday(2(week))',		10),
array('(Saturday,Wednesday)(-3..-1(week(July)))',	10),
array('Monday..Friday((-2,-1)(week(April..July)))',	10),
array('(Monday..Friday)((-2,-1)(week)(April..July))',	10),
array('14,10,1..3,-1,-2(April..July)',	10),
array('Monday',		4),
array('(Monday,Wednesday,Friday)',	4),
array('Wednesday..Friday',			4),
array('Wednesday..Friday,Monday',	4),
array('2000-9-1',					11),
array('2000-10-1..2000-12-31',		11),
array('1(Aug(2015..2020))',			11),
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
 OR  4 day(s) of any month 1,10,-2 OR 1(Sunday)
		 5 specific year(s) 2020,2015
		 6 month(s) of specific year(s) Jan(2010..2020) OR 2015-1 OR each 3 month(2000-1..2002-12)
		 7 week(s) of specific year(s) 1(week(Aug..Dec(2020))) OR each 4 week(2000..2001)
		 8 week(s) of specific month(s) 1(week(August,September)) OR each 2 week(2000-1..2000-12)
		 9 day(s) of specific year(s) Wed((1,-1)(week(June(2015..2018))))
		10 day(s) of specific week(s)  Wed(2(week)) OR (Wed..Fri)(each 2(week))
 OR 10 day(s) of specific month(s) 1(Aug) OR Wed((1,2)(week(June))) OR each 2 day(2000-1..2000-2)
 			OR 2(Wed(June)) OR (1,-1)(Sat(June..August))
		11 specfic day/date(s) 2010-6-6 OR 1(Aug(2015..2020))
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
		$funcs = new RepeatLexer($this->mod);
//		$funcs2 = new PeriodInterpreter();
		$ares = array();
		foreach ($this->testscripts as &$test) {
			$res = $funcs->CheckCondition($test[0]);
			$clean = ($res == $test[0]) ? 'Samestring' : $res;
			$parsed = $funcs->conds;
			if (isset($parsed[0]['F'])) {
				$type = ($parsed[0]['F'] == $test[1]) ? 'Sametype' : $test[1].' >> '.$parsed[0]['F'];
			} else
				$type = 'No type, expected '.$test[1];
			$ares[$test[0]] = array($clean, $type, $parsed);
		}
		unset($test);
//		$ares = $funcs2->tester2(30,2015,4,-5,2016,3);
		$this->Crash();
	} 
}
