<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Repeats
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Repeats extends RepeatLexer
{
	public function __construct(&$mod)
	{
		parent::__construct($mod);
	}

	/*
	AllBlocks:
	Append to @starts[] and @ends[] pair(s) of timestamps consistent with @cond
		and @ss and @se

	@cond: member of parent::conds[] with parsed components of an interval-descriptor
		=>['P'] will be populated, =>['T'] may be populated
	@dts: resource-local DateTime object representing start of period-segment,
		providing base for relative calcs
	@ss: stamp for start of period being processed
	@se: stamp for one-past-end of period being processed
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	@starts: reference to array of block-start timestamps to be updated
	@ends: reference to array of block-end timestamps to be updated
	*/
	private function AllBlocks($cond, $dts, $ss, $se, &$sunparms, &$starts, &$ends)
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
*/
		switch ($cond['F']) {
/*		case 1:
				break;
			case 2:
				break;
			case 3:
				break;
			case 4:
				break;
			case 5:
				break;
			case 6:
				break;
			case 7:
				break;
			case 8:
				break;
			case 9:
				break;
			case 10:
				break;
			case 11:
				break;
*/
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
	Get stamp for adjusted value of @dtbase consistent with @timestr. @dtbase changed
	@timestr: relative time descriptor like [+1][H]H:[M]M
	@dtbase: resource-local DateTime object
	Returns: stamp of (probably modified) @dtbase
	*/
	private function RelTime($timestr, $dtbase)
	{
		$str = '';
		$nums = explode(':',$timestr,3);
		if (!empty($nums[0]) && $nums[0] != '00')
			$str .= ' '.$nums[0].' hours';
		if (!empty($nums[1]) && $nums[1] != '00')
			$str .= ' '.$nums[1].' minutes';
		if (!empty($str))
			$dtbase->modify('+'.$str);
		return $dtbase->getTimestamp();
	}

	/*
	TimeBlocks:
	Append to @starts[] and @ends[] pair(s) of timestamps consistent with @timedata
		and @ss and @se

	@timedata: a time-descriptor string representing a single time (in which case
		end-time is assumed to be start + 1 hour), or a time-range including '..'
	TODO support multiple values e.g. T1,T2... OR (T1,T2....)
	TODO support roll-over to contiguous segment(s) & time(s)
	@dtbase:  resource-local DateTime object representing start of period-segment,
		providing base for relative calcs
	@ss: stamp for start of period being processed
	@se: stamp for one-past-end of period being processed
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	@starts: reference to array of block-start timestamps to be updated
	@ends: reference to array of block-end timestamps to be updated
	*/
	private function TimeBlocks($timedata, $dts, $ss, $se, &$sunparms, &$starts, &$ends)
	{
		$dtw = clone $dts;
		if (is_array($timedata))
			$parts = array($timedata[0][0],$timedata[0][2]); //TODO CHECKME
		elseif (strpos($timedata,'..') !== FALSE)
			$parts = explode('..',$timedata,2);
		else {
			$dtw->setTime(0,0,0);
			$st = self::RelTime($timedata,$dtw);
			$dtw->setTimestamp($st);
			$dtw->modify('+1 hour');
			$parts = array($timedata,$dtw->format('G:i'));
		}
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
		$st = self::RelTime($parts[0],$dtw);
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
		$st = self::RelTime($parts[1],$dtw);
		if ($st >= $ss && $st < $se)
			$ends[] = $st;
		else
			$ends[] = $se;
	}

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
	BUT both arrays will be empty upon error
	*/
	public function GetBlocks($dts, $dte, &$sunparms, $defaultall=FALSE)
	{
		$starts = array();
		$ends = array();
		if ($dts >= $dte)
			return array($starts,$ends);
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
		//stamps for period limit checks
		$ss = $dts->getTimestamp();
		$se = $dte->getTimestamp();
		if ($this->conds) {
			//get parameters for time interpretation
//	$maxhours = self::GetSlotHours($item_id); TODO $item_id
			while ($dws < $dwe) {
				//update scratchpad for offsets from $dws
				$dtw->setTimestamp($dws->getTimestamp());
				foreach ($this->conds as &$one) {
					if ($one['T'] && !$one['P']) {
						//time only, any period (BUT maybe day-specific due to sun-related times)
						self::TimeBlocks($one['T'],$dtw,$ss,$se,$sunparms,$starts,$ends);
					} else {
						self::AllBlocks($one,$dtw,$ss,$se,$sunparms,$starts,$ends);
					}
				}
				unset($one);
				$dws->modify('+1 day'); //CHECKME longer interval in some cases?
			}
		} elseif ($defaultall) {
			$starts[] = $ss;
			$ends[] = $se-1;
		}
		return array($starts,$ends);
	}

//~~~~~~~~~~~~~~~~ PUBLIC INTERFACE ~~~~~~~~~~~~~~~~~~

	/*
	MergeBlocks:
	Coalesce and sort-ascending the timestamp-blocks represented in @starts and @ends.
	The arrays must be equal-sized, have numeric keys. Returned array keys may be
	non-contiguous.
	@starts: reference to array of block-start stamps, any order
	@ends: reference to array of corresponding block-end stamps, no FALSE value(s)
	*/
	public function MergeBlocks(&$starts, &$ends)
	{
		$c = count($starts);
		if ($c > 1) {
			$p = 0;
			$q = 1;
			while (1) {
				if ($q >= $c)
					return;
				if ($starts[$q] >= $starts[$p]) {
					if ($ends[$q] <= $ends[$p]) {
						unset($starts[$q]);
						unset($ends[$q]);
					} elseif ($starts[$q] <= $ends[$p]) {
						$ends[$p] = $ends[$q];
						unset($starts[$q]);
						unset($ends[$q]);
					} else {
						//next base
						while ($p < $c) {
							$p++;
							if (array_key_exists($p,$starts))
								break;
						}
					}
				} else { //swap & resume (if possible from previous index)
					list($starts[$p],$starts[$q],$ends[$p],$ends[$q]) = array($starts[$q],$starts[$p],$ends[$q],$ends[$p]);
					$t = $p;
					while ($t > -1) { //base back if possible
						$t--;
						if (array_key_exists($t,$starts))
							break;
					}
					if ($t > -1)
						$p = $t;
					//for new comparator
					$q = $p;
				}
				//next comparator
				while ($q < $c) {
					$q++;
					if (array_key_exists($q,$starts))
						break;
				}
			}
		}
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
	public function SunParms(&$idata, $at='now')
	{
		$zone = $idata['timezone'];
		if (!$zone)
			$zone = $this->mod->GetPreference('pref_timezone','UTC');
		switch ($zone) {
		 case FALSE:
		 case 'UTC':
		 case 'GMT':
			$offs = 0;
			break;
		 default:
			try {
				$tz = new \DateTimeZone($zone);
				$dt = new \DateTime($at,$tz);
				$offs = $dt->format('Z')/3600;
			} catch (Exception $e) {
				$offs = 0;
			}
			break;
		}
		return array (
		 'lat'=>(float)$idata['latitude'], //maybe 0.0
		 'long'=>(float)$idata['longitude'], //ditto
		 'gmtoff'=>$offs
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
				//get block-ends timestamps for $descriptor and over time-interval
				list($starts,$ends) = self::GetBlocks($dts,$dte,$sunparms,$defaultall); //TODO sunparms offset may change during interval
				//sort block-pairs, merge when needed
				self::MergeBlocks($starts,$ends);
				return array($starts,$ends);
			}
		}
		//nothing to report
		if ($defaultall)
			return array(array($st),array($nd));
		return FALSE;
	}

	/**
	NextInterval:
	Get pair of timestamps representing the earliest conforming time-block in the
	 interval starting at @dts and ending 1-second before @dte
	@descriptor: interval-language string to be interpreted, or some variety of FALSE
	@slotlen: length (seconds) of wanted block
	@dts: datetime object for UTC start (midnight) of 1st day of period being processed
	@dte: datetime object representing 1-second after the end of the period of interest
	@sunparms: reference to array of parameters from self::SunParms, used in sun-related time calcs
	Returns: array with 2 timestamps, or FALSE
	*/
	public function NextInterval($descriptor, $slotlen, $dts, $dte, &$sunparms)
	{
		//limiting timestamps
		$st = $dts->getTimestamp();
		$nd = $dte->getTimestamp();
		if ($descriptor) {
			if (parent::ParseDescriptor($descriptor/*,$locale*/)) {
			//TODO
			}
			return FALSE;
		}
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
	Returns: boolean
	*/
	public function IntervalComplies($descriptor, $dts, $dte, &$sunparms)
	{
		if ($descriptor) {
			if (parent::ParseDescriptor($descriptor/*,$locale*/)) {
			//TODO
			}
			return FALSE;
		}
		return TRUE;
	}
}
