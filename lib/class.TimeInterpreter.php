<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: TimeInterpreter
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class TimeInterpreter
{

	/**
	GetIntervalHours:
	Get no. in {0.0..24.0} representing the actual or notional slot-length to
	assist interpretation of ambiguous hour-of-day or day-of-month values
	@idata: reference to array of item-parameters
	Returns: float
	*/
	public function GetIntervalHours(&$idata)
	{
		if ($idata['placegap']) {
			switch ($idata['placegaptype']) {
			 case 1: //minute
				return MIN($idata['placegap']/60,24.0);
			 case 2: //hour
				return MIN((float)$idata['placegap'],24.0);
			 case 3: //>= day
			 case 4:
			 case 5:
			 case 6:
				return 24.0;
			 default:
				break;
			}
		}
		//TODO if $bdata['startdate'] to $bdata['enddate'] short/< N days 
		// assume nominated values are hours, return appropriate value
		return 0.0;
	}

	/**
	timecheck:
	@times: reference to array of timestamp pairs, each member representing a
		period-start and period-end from the relevant interval-descriptor (TODO CHECK end+1??)
	@sameday: boolean, whether ...
	Returns: $X or FALSE
	*/
	public function timecheck(&$times, $sameday)
	{
		$tstart = $TODO;
		$length = $TODO;
		foreach ($times as &$range) {
			//TODO interpet any sun-related times
			$s = ($sameday) ? MAX($range[0],$tstart) : $range[0];
			//TODO support roll-over to contiguous day(s) & time(s)
			if ($range[1] >= $s+$length) {
				unset($range);
				return $s;
			}
		}
		unset($range);
		return FALSE;
	}

}
