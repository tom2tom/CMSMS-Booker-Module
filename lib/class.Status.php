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
		 \Booker::STATCHG => 'stat_chg',//change request, approver consideration pending
		 \Booker::STATDEL => 'stat_del',//delete request, approver consideration pending
		 \Booker::STATTELL => 'stat_tell',//further information submitted
		 \Booker::STATASK => 'stat_ask',//booker queried, waiting for response
		 \Booker::STATCANCEL => 'stat_cancel',//(request to be) abandoned by user, or admin on user's behalf
		 \Booker::STATOK => 'stat_approved',//aka APPROVED done/processed
		 \Booker::STATADMINREC => 'stat_rec',//booking recorded by admin
		 \Booker::STATSELFREC => 'stat_selfrec',//recorded by approved user (i.e. no request)
		 \Booker::STATTEMP => 'stat_temp',//user-recorded, pending admin confirmation, or at least 'nfa' flag
		 \Booker::STATDEFERRED => 'stat_defer',//booking to be re-scheduled, per user request or admin imposition
		 \Booker::STATGONE => 'stat_gone',//deletion pending, while its historical data needed
		 \Booker::STATBIG => 'stat_big',//too many slots requested
		 \Booker::STATDEFER => 'stat_early',//request not yet processed cuz' too far ahead
		 \Booker::STATLATE => 'stat_late',//request past or not far-enough ahead
		 \Booker::STATNA => 'stat_na',//resouce N/A at requested time, cannot accept
		 \Booker::STATDUP => 'stat_dup',//duplicate request, cannot accept
		 \Booker::STATPERM => 'stat_perm',//user not permitted
		 \Booker::STATNFEE => 'stat_nfee',//request not (entirely) pre/post-paid
		 \Booker::STATERR => 'stat_err',//system error while processing
		 \Booker::STATRETRY => 'stat_retry',//some temporary problem, try again later
		 \Booker::STATFAILED => 'stat_fail',//generic request-failure

		 \Booker::STATFREE => 'stat_free',//no fee for use
		 \Booker::STATPAID => 'stat_paid',//fee pre- or post-paid
		 \Booker::STATCREDITED => 'stat_cred',//fee to be paid upon request
		 \Booker::STATPAYABLE => 'stat_topay',//fee applies, none yet paid
		 \Booker::STATPARTPAID => 'stat_part',//fee not yet fully paid
		 \Booker::STATNOTPAID => 'stat_unpaid',//payable but unpaid for some non-credit-related reason
		 \Booker::STATOVRDUE => 'stat_ovrdue',//payment overdue
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
	 3 >> statpay status
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
	@mod: reference to current module-object
	@statpay: enum Booker::STATFREE .. Booker::STATMAXPAY
	@mode: enum 0,1,2 determines type of returned string
	Returns: string
	*/
	public function GetStatus(&$mod, $statpay, $mode=0)
	{
		if ($mode == 0)
			$key = $this->StatusKeys($statpay);
		} else {
			switch ($statpay) {
				case \Booker::STATPAID:
					$key = ($mode == 1) ? 'yes' : 'stat_paid';
					break;
				default:
					$key = ($mode == 1) ? 'no' : 'stat_nfee';
					break;
			}
		}
		return $mod->Lang($key);
	}

	/**
	PaidStatus:
	@statpay: enum Booker::STATFREE .. Booker::STATMAXPAY
	@yes: mixed, value to return if @statpay = Booker::STATPAID
	@no: mixed, value to return if @statpay neither Booker::STATFREE or Booker::STATPAID
	Returns: NULL or @yes or @no
	*/
	function PaidStatus($statpay, $yes, $no)
	{
		switch ($statpay) {
			case \Booker::STATFREE:
				return NULL;
			case \Booker::STATPAID:
				return $yes;
		}
		return $no;
	}
}
