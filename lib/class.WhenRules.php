<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: WhenRules
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class WhenRules extends WhenRuleLexer
{
	public function __construct(&$mod)
	{
		parent::__construct($mod);
	}

	/*
	PeriodBlocks:
	Append to @starts[] and @ends[] pair(s) of timestamps in $ss..$se and consistent with
		the period-component of @cond
	@cond: member of parent::conds[] with parsed components of an interval-descriptor
		=>['P'] will be populated, =>['T'] may be populated
	@ss: stamp for start of period being processed
	@se: stamp for one-past-end of period being processed
	@dtw: modifiable DateTime object for use in relative calcs
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	@starts: reference to array of block-start timestamps to be updated
	@ends: reference to array of block-end timestamps to be updated
	*/
	private function PeriodBlocks($cond, $ss, $se, $dtw, &$sunparms, &$starts, &$ends)
	{
/*TODO convert $cond e.g.
 array
	'P' =>
		array  OR SCALAR
			0 => string 'D2..D6'
			! => ...
	'F' => int 4
	'T' => string '20:00..21:00'
OR e.g.
 array
	'P' => boolean FALSE
	'F' => int 1
	'T' => string '6:00-23:00'
to blocks in $ss..$se
'T' may be FALSE
no FALSE in $ends[]

		//assuming there may be specific time(s) involved, we use day-wise interrogation
			//day-walker
			$dws = clone $dts;
			$dws->SetTime(0,0,0); //ensure that-day-start
			//end-checker
			$dwe = clone $dte;
			$dwe->SetTime(0,0,0);
			if ($dwe != $dte) //ensure next-day-start
				$dwe->modify('+1 day');
			//worker
			$dtw = clone $dws;
			//get parameters for time interpretation
//	$maxhours = self::GetSlotHours($item_id); TODO $item_id
			while ($dws < $dwe) {
				//update scratchpad for offsets from $dws
				$dtw->setTimestamp($dws->getTimestamp());
				foreach ($this->conds as &$one) {
					if ($one['T'] && !$one['P']) {
						//time only, any period (BUT maybe day-specific due to sun-related times)
						self::TimeBlocks($one,$ss,$se,$dtw,$sunparms,$starts,$ends);
					} else {
						self::PeriodBlocks($one,$ss,$se,$dtw,$sunparms,$starts,$ends);
						//TODO handle times
					}
				}
				unset($one);
				$dws->modify('+1 day'); //CHECKME longer interval in some cases?
			}
$cond['F']:
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
*/
		switch ($cond['F']) {
		 case 1:
			//whole of $ss, $se
			break;
		 case 2:
			//stamps for months(s) in any year in $ss, $se
			break;
		 case 3:
			//stamps for week(s) in any month in any year in $ss, $se
			break;
		 case 4:
			//stamps for day(s) of week or month in any year in $ss, $se
			break;
		 case 5:
			//stamps for year(s) in $ss, $se TODO $cond['T']
			break;
		 case 6:
			//stamps for months(s) in specific year(s) in $ss, $se
			break;
		 case 7:
			//stamps for week(s) in specific [month(s) and] year(s) in $ss, $se
			break;
		 case 8:
			//stamps for week(s) in specific month(s) in $ss, $se
			break;
		 case 9:
			//stamps for day(s) in weeks(s) and specific month(s) and specific year(s) in $ss, $se
			break;
		 case 10:
			//stamps for day(s) in weeks(s) or month(s) in $ss, $se
			break;
		 case 11:
			//stamps for specific day(s) in $ss, $se
			break;
		 default:
			break;
		}
	}

	/*
	GetSlotHours:
	Get float in {0.0..24.0} representing the actual or notional slot-length for
	@item_id, to assist interpretation of ambiguous hour-of-day or day-of-month values
	@item_id: resource or group identifier
	*/
/*	private function GetSlotHours($item_id)
	{
		$utils = new Utils();
		$len = $utils->GetInterval($this->mod,$item_id,'slot');
		return min($len/3600,24.0);
	}
*/
	/*
	timecheck:
	@times: reference to array of ...
	@sameday: whether ...
	*/
/*	private function timecheck(&$times, $sameday)
	{
		foreach ($times as &$range) {
			//TODO interpet any sun-related times
			$s = ($sameday) ? max($range[0],$tstart) : $range[0];
			//TODO support roll-over to contiguous day(s) & time(s)
			if ($range[1] >= $s+$length) {
				unset($range);
				return $s;
			}
		}
		unset($range);
		return FALSE;
	}
*/

	/*
	RelTime:
	Adjust @dtbase consistent with @timestr.
	@dtbase: DateTime object representing 'base' datetime
	@timestr: relative time descriptor like [+1][H]H:[M]M
	Returns: nothing, but @dtbase is probably changed
	*/
	private function RelTime($dtbase, $timestr)
	{
		$str = '';
		$nums = explode(':',$timestr,3);
		if (!empty($nums[0]) && $nums[0] != '00')
			$str .= ' '.$nums[0].' hours';
		if (!empty($nums[1]) && $nums[1] != '00')
			$str .= ' '.$nums[1].' minutes';
		if ($str)
			$dtbase->modify('+'.$str);
	}

	/*
	TimeBlocks:
	Append to @starts[] and @ends[] pair(s) of timestamps in $ss..$se and consistent with
		the time-component of @cond
	@cond: member of parent::conds[] with parsed components of an interval-descriptor
		=>['P'] may be populated, =>['T'] will be populated
	@ss: stamp for start of period being processed
	@se: stamp for one-past-end of period being processed
	@dtw: modifiable DateTime object for use in relative calcs
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	@starts: reference to array of block-start timestamps to be updated
	@ends: reference to array of block-end timestamps to be updated
	*/
	private function TimeBlocks($cond, $ss, $se, $dtw, &$sunparms, &$starts, &$ends)
	{
/*		if (is_array($timedata))
			$parts = array($timedata[0][0],$timedata[0][2]);
		elseif (strpos($timedata,'..') !== FALSE)
			$parts = explode('..',$timedata,2);
		else { //default to 1-hour block
			$dtw->setTime(0,0,0);
			self::RelTime($dtw,$timedata);
			$dtw->modify('+1 hour');
			$parts = array($descriptor,$dtw->format('G:i'));
		}
		TODO support multiple values e.g. T1,T2... OR (T1,T2....)
		TODO support 'except' values e.g. !T1,!T2... OR !(T1,T2....)
		TODO support roll-over to contiguous segment(s) & time(s)

 old-SunParms extract
		switch ($sunparms['zone']) {
		 case FALSE:
		 case 'UTC':
		 case 'GMT':
			$offs = 0;
			break;
		 default:
			try {
				$at = ;
				$tz = new \DateTimeZone($zone);
				$dt = new \DateTime($at,$tz);
				$offs = $dt->format('Z')/3600; //TODO may change during interval, cuz DST changes
			} catch (Exception $e) {
				$offs = 0;
			}
			break;
		}

*/
		$parts = func($cond['T']); //TODO
		$tbase = $dts->getTimestamp();
		//block-start
		if (strpos($parts[0],'R') !== FALSE) {
			$revert = $tbase;
			//we use zenith for 'civilian twilight'
			$tbase = date_sunrise($tbase,SUNFUNCS_RET_TIMESTAMP,$sunparms['lat'],$sunparms['long'],96.0,$sunparms['gmtoff']);
			$parts[0] = str_replace('R','',$parts[0]);
			if ($parts[0] == '')
				$parts[0] = '0:0';
		} elseif (strpos($parts[0],'S') !== FALSE) {
			$revert = $tbase;
			$tbase = date_sunset($tbase,SUNFUNCS_RET_TIMESTAMP,$sunparms['lat'],$sunparms['long'],96.0,$sunparms['gmtoff']);
			$parts[0] = str_replace('S','',$parts[0]);
			if ($parts[0] == '')
				$parts[0] = '0:0';
		} else
			$revert = FALSE;

		$dtw->setTimestamp($tbase);
		self::RelTime($dtw,$parts[0]);
		$st = $dtw->getTimestamp();
		if ($st >= $ss && $st < $se)
			$starts[] = $st;
		else
			$starts[] = $ss;
		//block-end
		if ($revert !== FALSE)
			$tbase = $revert;
		if (strpos($parts[1],'R') !== FALSE) {
			$tbase = date_sunrise($tbase,SUNFUNCS_RET_TIMESTAMP,$sunparms['lat'],$sunparms['long'],96.0,$sunparms['gmtoff']);
			$parts[1] = str_replace('R','',$parts[1]);
			if ($parts[1] == '')
				$parts[1] = '0:0';
		} elseif (strpos($parts[1],'S') !== FALSE) {
			$tbase = date_sunset($tbase,SUNFUNCS_RET_TIMESTAMP,$sunparms['lat'],$sunparms['long'],96.0,$sunparms['gmtoff']);
			$parts[1] = str_replace('S','',$parts[1]);
			if ($parts[1] == '')
				$parts[1] = '+0:0';
		}
		$dtw->setTimestamp($tbase);
		self::RelTime($dtw,$parts[1]);
		$st = $dtw->getTimestamp();
		if ($st >= $ss && $st < $se)
			$ends[] = $st;
		else
			$ends[] = $se;
	}

//~~~~~~~~~~~~~~~~ PUBLIC INTERFACE ~~~~~~~~~~~~~~~~~~

	/**
	GetBlocks:
	Interpret parent::conds into seconds-blocks covering the interval from
	@dts to immediately (1-sec) before @dte.
	@dts: datetime object representing resource-local start of period being
		processed, not necessarily a midnight
	@dte: datetime object representing resource-local one-past-end of the period,
		not necessarily a midnight
	@sunparms: reference to array of parameters for sun-related time calcs
	@defaultall: optional boolean, whether to return, if parent::conds is not set,
	the whole interval as one block instead of empty arrays, default FALSE
	Returns: 2-member array:
	 [0] = timestamps representing block-starts
	 [1] = timestamps for corresponding block-ends (NOT 1-past)
	BUT both arrays will be empty upon error, or if nothing applies and
		$defaultall is FALSE
	*/
	public function GetBlocks($dts, $dte, &$sunparms, $defaultall=FALSE)
	{
		$starts = array();
		$ends = array();
		if ($dts < $dte && $this->conds) {
			//stamps for period limit checks
			$ss = $dts->getTimestamp();
			$se = $dte->getTimestamp();
			$dtw = clone $dts;
			$blocks = new Blocks();
			//for all inclusion-conditions, add to $starts,$ends
			foreach ($this->conds as &$cond) {
				if ($cond['P']) {
					if ($cond['P'][0] == '!') //exclusive
						continue;
				} elseif ($cond['T']) {
					if (is_array($cond['T'])) {
						if ($cond['T'][0][0] == '!')
							continue;
					} elseif ($cond['T'][0] == '!')
						continue;
				} else {
					continue; //should never happen
				}
				$gets = array();
				$gete = array();
				self::PeriodBlocks($cond,$ss,$se,$dtw,$sunparms,$gets,$gete);
				//merge $starts,$ends,$gets,$gete
				if ($starts) {
					list($gets,$gete) = $blocks->BlockIntersects($starts,$ends,$gets,$gete);
				} else {
					//want something to compare with
					list($gets,$gete) = $blocks->BlockIntersects(array($ss),array($se),$gets,$gete);
				}
				if ($gets !== FALSE) {
					$starts = $gets;
					$ends = $gete;
				}
				if (count($starts) == 1 && reset($starts) <= $ss && end($ends) >= $se-1) //all of $ss..$se now covered
					break;
			}
//			unset($cond);
			if ($starts) {
				//for all exclusion-conditions, subtract from $starts,$ends
				foreach ($this->conds as &$cond) {
					if ($cond['P']) {
						if ($cond['P'][0] != '!') //inclusive
							continue;
					} elseif ($cond['T']) {
						if (is_array($cond['T'])) {
							if ($cond['T'][0][0] != '!')
								continue;
						} elseif ($cond['T'][0] != '!')
							continue;
					} else {
						continue; //should never happen
					}
					$gets = array();
					$gete = array();
					self::PeriodBlocks($cond,$ss,$se,$dtw,$sunparms,$gets,$gete);
					//diff $starts,$ends,$gets,$gete
					if ($starts) {
						list($gets,$gete) = $blocks->BlockDiffs($starts,$ends,$gets,$gete);
					} else {
						//want something to compare with
						list($gets,$gete) = $blocks->BlockDiffs(array($ss),array($se),$gets,$gete);
					}
					if ($gets !== FALSE) {
						$starts = $gets;
						$ends = $gete;
					}
					if (!$starts) //none of $ss..$se now covered
						break;
				}
			}
			unset($cond);
			if ($starts) {
				//sort block-pairs, merge when needed
				$blocks->MergeBlocks($starts,$ends);
			}
		} elseif ($defaultall) {
			$ss = $dts->getTimestamp();
			$se = $dte->getTimestamp() - 1;
			$starts[] = min($ss,$se);
			$ends[] = max($ss,$se);
		}
		return array($starts,$ends);
	}

	/*
	SunParms:
	c.f. TimeInterpreter::GetSunData()
	Get @itemdata-derived parameters for location-specific sunrise/set calcs
	No checks here for valid parameters in @itemdata - assumed done before
	@idata: reference to array of data (possibly inherited) for a resource or group
	@at: optional datetime string for offset calc, default 'now'
	Returns: array of parameters: latitude, longitude, zoneoffset-hours
	 the latter is determined as of @at
	*/
	public function SunParms(&$idata)
	{
		$zone = $idata['timezone'];
		if (!$zone)
			$zone = $this->mod->GetPreference('pref_timezone','UTC');
		return array (
		 'lat'=>(float)$idata['latitude'], //maybe 0.0
		 'long'=>(float)$idata['longitude'], //ditto
		 'zone'=>$zone
		);
/*
Sunrise $zenith=90+50/60;
Twilights: see http://www.timeanddate.com/astronomy/about-sun-calculator.html
Civilian twilight $zenith=96.0; <<< USE THIS FOR OUTDOORS N/A >= +/-60.5° latitude
Nautical twilight $zenith=102.0;
Astronomical twilight $zenith=108.0;
*/
	}

	/* *
	IntervalComplies:

	Determine whether the interval @start to @start + @length satisfies constraints
	specified in relevant fields in @itemdata. Also returns FALSE if the
	interval-descriptor string is malformed.
	parent::CheckDescriptor() or ::ParseDescriptor() must be called before this func.

	@idata: reference to array of data (possibly inherited) for a resource or group
	@dts: datetime object resource-local preferred/first start time
	@dte: optional, datetime object resource-local preferred/first end time, default FALSE
	@length: optional length (seconds) of time period to be checked, default 0
	*/
/*	public function IntervalComplies(&$idata, $dts, $dte=FALSE, $length=0)
	{
		if ($this->conds == FALSE)
			return FALSE;
/ *
		$sunparms = self::SunParms($idata);
		$maxhours = self::GetSlotHours($idata['item_id']);
		$dstart = floor($start/86400);
		$dend = $dstart + $laterdays;
		$tstart = $start - $dstart;
		foreach ($this->conds as &$cond) {
			//TODO
		}
		unset($cond);
* /
		return FALSE;
	}
*/
	/* *
	NextInterval:

	Get start-time (timestamp) matching constraints specified in relevant fields in
	@itemdata, and	starting no sooner than @start, or ASAP within @later days after
	the one including @start, and where the available time is at least @length.
	Returns FALSE if no such time is available within the specified interval (or
	the availability-descriptor string is malformed).
	parent::CheckDescriptor() or ::ParseDescriptor() must be called before this func.

	@idata: reference to array of data (possibly inherited) for a resource or group
	@dts: datetime object resource-local preferred/first start time
	@dte: optional, datetime object resource-local preferred/first start time, default FALSE
	@length: optional length (seconds) of time period to be discovered, default 0
	*/
/*	public function NextInterval(&$idata, $dts, $dte=FALSE, $length=0)
	{
		if ($this->conds == FALSE)
			return FALSE;
/ *		$sunparms = self::SunParms($idata);
		$maxhours = self::GetSlotHours($idata['item_id']);
		$dstart = floor($start/86400);
		$dend = $dstart + $laterdays;
		$tstart = $start - $dstart;
		foreach ($this->conds as &$cond) {
			$times = $cond[2];
			if (!$times)
				$times = array(0=>array(0,86399)); //whole day's worth of seconds
			if ($cond[1] == FALSE) { //time(s) on any day
				$X = self::timecheck($times,TRUE);
				if ($X !== FALSE) {
					uset($cond);
					return $X; //TODO + $cond[1]>day-index * 86400 + zone offset seconds
				}
				if ($laterdays > 0) {
					$X = self::timecheck($times,FALSE);
					if ($X !== FALSE) {
						uset($cond);
						return $X; //TODO + $cond[1]>day-index * 86400 + zone offset seconds
					}
				}
			} else {
				/*interpret $cond[1]
				  foreach day of interpreted
				    get day-index
						if IN $dstart to $dend inclusive
							$sameday = (day-index == $dstart);
							$X = self::timecheck($times,$sameday);
							if ($X !== FALSE) {
								uset($cond);
								return $X; //TODO + $cond[1]>day-index * 86400 + zone offset seconds
							}
				* /
			}
		}
		unset($cond);
* /
		return FALSE;
	}
*/

	/**
	AllIntervals:
	Get array of pairs of timestamps representing conforming time-blocks in the
	 interval starting at @dts and ending 1-second before @dte
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@dts: datetime object for UTC start (midnight) of 1st day of period being processed
	@dte: datetime object representing 1-second after the end of the period of interest
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	@defaultall: optional boolean, whether to return, upon some sort of problem,
		arrays representing the whole period instead of FALSE, default FALSE
	Returns: array with 2 members
	 [0] = array of UTC timestamps for starts of complying intervals during the period
	 [1] = array of corresponding stamps for interval-last-seconds (NOT 1-past)
	 OR FALSE if no descriptor, or parsing fails, and @defaultall is FALSE
	*/
	public function AllIntervals($descriptor, $dts, $dte, &$sunparms, $defaultall=FALSE)
	{
		//limiting timestamps
		$st = $dts->getTimestamp();
		$nd = $dte->getTimestamp();
		if ($descriptor) {
			if (parent::ParseDescriptor($descriptor)) {
				return self::GetBlocks($dts,$dte,$sunparms,$defaultall);
			}
		}
		//nothing to report
		if ($defaultall)
			return array(array($st),array($nd-1));
		return FALSE;
	}

	/**
	NextInterval:
	Get pair of timestamps representing the earliest conforming time-block in the
	 interval starting at @dts and ending 1-second before @dte
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@dts: datetime object for UTC start (midnight) of 1st day of period being processed
	@dte: datetime object representing 1-second after the end of the period of interest
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	@slotlen: length (seconds) of wanted block
	Returns: array with 2 timestamps, or FALSE
	*/
	public function NextInterval($descriptor, $dts, $dte, &$sunparms, $slotlen)
	{
		$res = self::AllIntervals($descriptor, $dts, $dte, &$sunparms, TRUE);
		if ($res) {
			list($starts,$ends) = $res;
			foreach ($starts as $i->$st) {
				$nd = $st+$slotlen;
				if ($ends[$i] >= $nd) {
					return array($st,$nd);
				}
			}
			return FALSE;
		}
		//limiting timestamps
		$st = $dts->getTimestamp();
		$nd = $dte->getTimestamp();
		if ($st+$slotlen <= $nd)
			return array($st,$st+$slotlen);
		return FALSE;
	}

	/**
	IntervalComplies:
	Determine whether the time-block starting at @dts and ending 1-second
	 before @dte is consistent with @descriptor
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@dts: datetime object for UTC start (midnight) of 1st day of period being processed
	@dte: datetime object representing 1-second after the end of the period of interest
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	Returns: boolean representing compliance, or TRUE if @descriptor is FALSE,
		or FALSE if @descriptor is not parsable
	*/
	public function IntervalComplies($descriptor, $dts, $dte, &$sunparms)
	{
		$res = self::AllIntervals($descriptor, $dts, $dte, &$sunparms, TRUE);
		if ($res) {
			$blocks = new Blocks();
			list($starts,$ends) = $blocks->BlockDiffs(
				array($dts->getTimestamp()),array($dte->getTimestamp()), //TODO off-by-1 ?
				$res[0],$res[1]);
			return (count($starts) == 0); //none of the interval is not covered by $descriptor
		}
		return FALSE;
	}
}
