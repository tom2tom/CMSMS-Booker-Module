<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Histops - functions for processing history data
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Histops
{
	//TODO this file is mostly placeholder code

	private function GetBookings($params)
	{
		return $mod->dbHandle->GetArray('SELECT FROM '.$mod->HistoryTable.' WHERE ',$args);
	}

	private function GetHistory($params)
	{
		return $mod->dbHandle->GetArray('SELECT FROM '.$mod->HistoryTable.' WHERE ',$args);
	}

	/**
	CountBookingsBy:
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	@after: UTC timestamp, or FALSE
	@before: UTC timestamp, or FALSE
	Returns: integer
	*/
	public function CountBookingsBy(&$mod, $booker, $after=FALSE, $before=FALSE)
	{
		if ($after) {
		}
		if ($before) {
		}
		if (is_callable($booker)) {
			$valid = array(); //OR just a counter
			$all = self::GetBookings($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetBookings($params);
		} elseif ($booker == '*') {
			$valid = self::GetBookings($params);
		} else {
			$valid = self::GetBookings($params);
		}
		return count($valid);
	}

	/**
	CountBookingsOf:
	@item: a numeric item_id, or array of them, or '*' for all, or a callback for filtering
	@after: UTC timestamp, or FALSE
	@before: UTC timestamp, or FALSE
	Returns: integer
	*/
	public function CountBookingsOf(&$mod, $item, $after=FALSE, $before=FALSE)
	{
		if ($after) {
		}
		if ($before) {
		}
		if (is_callable($item)) {
			$valid = array(); //OR just a counter
			$all = self::GetBookings($params);
			if ($all) {
				foreach ($all as $one) {
					if ($item($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($item)) {
			$valid = self::GetBookings($params);
		} elseif ($item == '*') {
			$valid = self::GetBookings($params);
		} else {
			$valid = self::GetBookings($params);
		}
		return count($valid);
	}

	/**
	PaymentsFor: (use of)
	@item: a numeric item_id, or array of them, or '*' for all, or a callback for filtering
	@after: UTC timestamp, or FALSE
	@before: UTC timestamp, or FALSE
	Returns: 2-member array: [0] = no. of payments [1] = total amount paid
	*/
	public function PaymentsFor(&$mod, $item, $after=FALSE, $before=FALSE)
	{
		if ($after) {
		}
		if ($before) {
		}
		if (is_callable($item)) {
			$valid = array();
			$all = self::GetHistory($params);
			if ($all) {
				foreach ($all as $one) {
					if ($item($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($item)) {
			$valid = self::GetHistory($params);
		} elseif ($item == '*') {
			$valid = self::GetHistory($params);
		} else {
			$valid = self::GetHistory($params);
		}

		$count = 0;
		$amount = 0.0;
		foreach ($valid as $one) {
			$count++;
			$amount += $X;
		}
		return array($count,$amount);
	}

	/**
	PaymentsBy:
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	@after: UTC timestamp, or FALSE
	@before: UTC timestamp, or FALSE
	Returns: 2-member array: [0] = no. of payments [1] = total amount paid
	*/
	public function PaymentsBy(&$mod, $booker, $after=FALSE, $before=FALSE)
	{
		if ($after) {
		}
		if ($before) {
		}
		if (is_callable($booker)) {
			$valid = array();
			$all = self::GetHistory($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetHistory($params);
		} elseif ($booker == '*') {
			$valid = self::GetHistory($params);
		} else {
			$valid = self::GetHistory($params);
		}

		$count = 0;
		$amount  = 0.0;
		foreach ($valid as $one) {
			$count++;
			$amount += $X;
		}
		return array($count,$amount);
	}

	/**
	TotalCredit:
	Determine how much credit has been accumulated by @booker_id
	See also: Payments::TotalCredit();
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	Returns: float amount
	*/
	public function TotalCredit(&$mod, $booker)
	{
		if (is_callable($booker)) {
			$valid = array();
			$all = self::GetHistory($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetHistory($params);
		} elseif ($booker == '*') {
			$valid = self::GetHistory($params);
		} else {
			$valid = self::GetHistory($params);
		}
		$ret = 0.0;
		foreach ($valid as $one) {
			$ret += $X;
		}
		return $ret;
	}

	/**
	UseCredit:
	Reduce the the credit accumulated by @booker_id by @amount
	See also: Payments::UseCredit();
	@booker_id: numeric booker-identifier
	@amount:
	Returns: remaining credit for @booker_id (maybe < 0), or FALSE upon error
	*/
	public function UseCredit(&$mod, $booker_id, $amount)
	{
		$valid = self::GetHistory($params);
		foreach ($valid as $one) {
			$amount -= $X;
			if ($amount <= 0.0) {
				break;
			}
		}
		if ($amount > 0.0)
			return $amount;
		if ($amount < 0.0)
			$ret = -$amount;
		else
			$ret = 0.0;
		while (0) {//next($valid)
			$ret += $X;
		}
		return $ret;
	}

	/**
	ExpireCredit:
	Flag as 'expired' all credit records older than @before and matching @booker
	See also: Payments::ExpireCredit();
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	@before: UTC timestamp for limit on remaining credit
	Returns: nothing
	*/
	public function ExpireCredit(&$mod, $booker, $before)
	{
		if (is_callable($booker)) {
			$valid = array();
			$all = self::GetHistory($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetHistory($params);
		} elseif ($booker == '*') {
			$valid = self::GetHistory($params);
		} else {
			$valid = self::GetHistory($params);
		}
		foreach ($valid as $one) {
			//OR merged sql
		}
	}

	/**
	ExpireCreditFor:
	Flag as 'expired' all credit records matching @booker
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	Returns: nothing
	*/
	public function ExpireCreditFor(&$mod, $booker)
	{
		if (is_callable($booker)) {
			$valid = array();
			$all = self::GetHistory($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetHistory($params);
		} elseif ($booker == '*') {
			$valid = self::GetHistory($params);
		} else {
			$valid = self::GetHistory($params);
		}
		foreach ($valid as $one) {
			//OR merged sql
		}
	}

	/**
	ClearHistory:
	Delete all history records older than @before and matching @booker
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	@before: UTC timestamp for limit on retained history
	Returns: nothing
	*/
	public function ClearHistory(&$mod, $booker, $before)
	{
		if (is_callable($booker)) {
			$valid = array();
			$all = self::GetHistory($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetHistory($params);
		} elseif ($booker == '*') {
			$valid = self::GetHistory($params);
		} else {
			$valid = self::GetHistory($params);
		}
		foreach ($valid as $one) {
			//OR merged sql
		}
	}

	/**
	ClearHistoryFor:
	Delete all history records matching @booker
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	Returns: nothing
	*/
	public function ClearHistoryFor(&$mod, $booker)
	{
		if (is_callable($booker)) {
			$valid = array();
			$all = self::GetHistory($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetHistory($params);
		} elseif ($booker == '*') {
			$valid = self::GetHistory($params);
		} else {
			$valid = self::GetHistory($params);
		}
		foreach ($valid as $one) {
			//OR merged sql
		}
	}
}
