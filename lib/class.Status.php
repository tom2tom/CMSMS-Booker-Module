<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Status
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Status
{
	//$wanted = single STAT* enum or array of those or '*'
	private function StatusKeys($wanted)
	{
		$stats = array(
		 \Booker::STATNONE => 'stat_none',//unknown/normal/default
		 \Booker::STATNEW => 'stat_new',//new, approver consideration pending
		 \Booker::STATTELL => 'stat_tell',//further information submitted
		 \Booker::STATCHG => 'stat_chg',//change request, approver consideration pending
		 \Booker::STATDEFER => 'stat_defer',//request not yet processed cuz' too far ahead
		 \Booker::STATDEL => 'stat_del',//delete request, approver consideration pending
		 \Booker::STATTEMP => 'stat_temp',//user-recorded, pending admin confirmation
		 \Booker::STATASK => 'stat_ask',//booker queried, waiting for response
		 \Booker::STATCANCEL => 'stat_cancel',//abandoned by user or admin on user's behalf
		 \Booker::STATADMINREC => 'stat_rec',//booking recorded by admin
		 \Booker::STATBIG => 'stat_big',//too many slots requested
		 \Booker::STATDEFERRED => 'stat_defer',//booking to be re-scheduled, per user request or admin imposition
		 \Booker::STATDUP => 'stat_dup',//duplicate request, cannot accept
		 \Booker::STATERR => 'stat_err',//system error while processing
		 \Booker::STATFAILED => 'stat_fail',//generic request-failure
		 \Booker::STATGONE => 'stat_gone',//deletion pending, while its historical data needed
		 \Booker::STATLATE => 'stat_late',//request past or not far-enough ahead
		 \Booker::STATNA => 'stat_na',//resouce N/A at requested time, cannot accept
		 \Booker::STATPERM=> 'stat_perm',//user not permitted
		 \Booker::STATOK => 'stat_approved',//aka APPROVED done/processed
		 \Booker::STATRETRY => 'stat_retry',//some temporary problem, try again later
		 \Booker::STATSELFREC => 'stat_selfrec'//recorded by approved user (i.e. no request)
		);
	
		if (is_array($wanted)) {
			$ret = array();
			foreach($wanted as $t) {
				if (array_key_exists($t,$stats)) {
					$ret[$t] = $stats[$t];
				}
			}
			return array_unique($ret);
		} elseif ($wanted == '*') {
			return $stats;
		} elseif (array_key_exists($wanted,$stats)) {
			return $stats[$wanted];
		} else {
			return 'err_parm';
		}		
	}

	/**
	GetStatusName:
	@mod: reference to current module-object
	@status: single Booker::STAT* enum or array of those or '*'
	Returns: string or number
	*/
	public function GetStatusName(&$mod, $status)
	{
		$key = self::StatusKeys($status);
		if (is_string($key))
			return $mod->Lang($key);
		return $key;
	}

	/**
	GetStatusChoices:
	@mod: reference to current module-object
	@mode: bitflags:
	 0 >> request-status,
	 1 >> post-request ok etc,
	 2 >> post-request problem
	 3 >> payment status
	Returns: array suitable for dropdown picklist, keys = text or number, values = STAT* enum
	*/
	public function GetStatusChoices(&$mod, $mode)
	{
		if ($mode & 1) {
			$wanted = range(\Booker::STATNONE,\Booker::STATMAXREQ);
		} else {
			$wanted = array();
		}
		if ($mode & 2) {
			$wanted = array_merge($wanted,range(\Booker::STATMAXREQ+1,\Booker::STATMAXOK));
		}
		if ($mode & 4) {
			$wanted = array_merge($wanted,range(\Booker::STATMAXOK+1,\Booker::STATMAXBAD));
		}
		if ($mode & 8) {
			$wanted = array_merge($wanted,range(\Booker::STATFREE,\Booker::STATMAXPAY));
		}
		$choices = self::StatusKeys($wanted);
		foreach ($choices as &$key) {
			if (is_string($key))
				$key = $mod->Lang($key);
		}
		unset($key);
		return array_flip($choices);
	}

	/**
	GetStatus:
	@params: variables to be used to determine the status
	Returns: a suitable \Booker::STAT* constant
	*/
	public function GetStatus($params)
	{
		//stage,payable,paid,will-pay,overdue etc
		return \Booker::STATOK; //TODO
	}
}
