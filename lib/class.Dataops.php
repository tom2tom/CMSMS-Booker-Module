<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Dataops - functions for processing bookings data
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Dataops
{
	const BKGALL = 0;
	const BKGFUTURE = 1;
	const BKGPAST = 2;
	const BKGFREE = 4;
	const BKGUNPAID = 8;
	const BKGPAID = 16;
	const BKGONCE = 32;
	const BKGREPT = 64;
	const BKGSINGL = 128;
	const BKGGROUP = 256;
	const BKGITEM = 512;
	const BKGUSER = 1024;
	const BKGUTYPE = 2048;

	/**
	FilterTitle:
	Get data-descriptor representing @flags and other 'filter' parameter(s)
	@mod: reference to current Booker module
	@flags: booking-type(s) identifier, a combination of const's above to be AND'd
	@itemid: optional item identifier, or array of them, default FALSE
	@bookerid: optional booker identifier, or array of them, default FALSE
	@typeid: optional user-type identifier, or array of them, default FALSE
	Returns: string
	*/
	public function FilterTitle(&$mod, $flags, $itemid = FALSE, $bookerid = FALSE, $typeid = FALSE)
	{
		if ($flags == self::BKGALL) {
			return $mod->Lang('bkgtype_all');
		}

		$and = [];
		$fg = self::BKGFUTURE + self::BKGPAST;
		if (($flags & $fg) == $fg) {
			$flags &= ~$fg; //ignore both flags
		} elseif ($flags & self::BKGFUTURE) {
			$and[] = $mod->Lang('bkgtype_future');
		} elseif ($flags & self::BKGPAST) {
			$and[] = $mod->Lang('bkgtype_past');
		}

		$fg  = self::BKGFREE + self::BKGUNPAID + self::BKGPAID;
		if (($flags & $fg) == $fg) {
			$flags &= ~$fg;
		} else {
			if ($flags & self::BKGFREE) {
				$and[] = $mod->Lang('bkgtype_free');
			}

			$fg = self::BKGUNPAID + self::BKGPAID;
			if (($flags & $fg) != $fg) {
				if ($flags & self::BKGUNPAID) {
					$and[] = $mod->Lang('bkgtype_unpaid');
				} elseif ($flags & self::BKGPAID) {
					$and[] = $mod->Lang('bkgtype_paid');
				}
			}
		}

		$fg = self::BKGONCE + self::BKGREPT;
		if (($flags & $fg) != $fg) {
			if ($flags & self::BKGONCE) {
				$and[] = $mod->Lang('bkgtype_onetime');
			}
			if ($flags & self::BKGREPT) {
				$and[] = $mod->Lang('bkgtype_repeated');
			}
		}

		$fg = self::BKGSINGL + self::BKGGROUP;
		if (($flags & $fg) != $fg) {
			if ($flags & self::BKGSINGL) {
				$and[] = $mod->Lang('bkgtype_ungrouped');
			}
			if ($flags & self::BKGGROUP) {
				$and[] = $mod->Lang('bkgtype_grouped');
			}
		}

		if ($flags & self::BKGITEM) {
			if (is_array($itemid)) {
				//TODO $items = $utils->GetNamedItems($mod,$items);
				//$s = implode(',', $items);
				$s = 'name..name';
			} elseif ($itemid) {
				$s = 'name'; //TODO $s = $utils->GetItemNameForID($mod,$item_id)
			} else {
				$s = '';
			}
			if ($s) {
				$and[] = $mod->Lang('bkgtype_byitem', $s);
			}
		}

		if ($flags & self::BKGUSER) {
			if (is_array($bookerid)) {
				$s = 'name..name'; //TODO implode(',',get names)
			} elseif ($bookerid) {
				$s = 'name'; //TODO $utils->GetUserNameForID(&$mod,$bookerid);
			} else {
				$s = '';
			}
			if ($s) {
				$and[] = $mod->Lang('bkgtype_byuser', $s);
			}
		}
		if ($flags & self::BKGUTYPE) {
			if (is_array($typeid)) {
				$s = implode(',', $typeid);
				$and[] = $mod->Lang('bkgtype_bytype', $s);
			} elseif ($typeid) {
				$and[] = $mod->Lang('bkgtype_bytype', $typeid);
			}
		}
		return ''.implode(',', $and);
	}

	/**
	FilterData:
	Get row(s) of data each with some fields from OnceTable and/or RepeatTable
	 & related, per @flags and other 'filter' parameter(s)
	@mod: reference to current Booker module
	@flags: booking-type(s) identifier, a combination of const's above to be AND'd
	@itemid: optional resource identifier, or array of them, default FALSE
	@bookerid: optional booker identifier, or array of them, default FALSE
	@typeid: optional user-type identifier, or array of them, default FALSE
	Returns: array or FALSE
	*/
	public function FilterData(&$mod, $flags, $itemid = FALSE, $bookerid = FALSE, $typeid = FALSE)
	{
		$and = [];
//		$or = array();
		$args = [];

		$fg = self::BKGFUTURE + self::BKGPAST;
		if (($flags & $fg) == $fg) {
			$flags &= ~$fg; //ignore both flags
		} elseif ($flags & self::BKGFUTURE) {
			$and[] = 'D.slotstart>='.time(); //UTC-relative
		} elseif ($flags & self::BKGPAST) {
			$and[] = 'D.slotstart<'.time();
		}

		$fg  = self::BKGFREE + self::BKGUNPAID + self::BKGPAID;
		if (($flags & $fg) == $fg) {
			$flags &= ~$fg;
		} else {
			if ($flags & self::BKGFREE) {
				$and[] = 'O.statpay='.\Booker::STATFREE;
			}
			$fg = self::BKGUNPAID + self::BKGPAID;
			if (($flags & $fg) == $fg) {
				$and[] = 'O.statpay IN ('.
				\Booker::STATPAYABLE.','.
				\Booker::STATPAID.','.
				\Booker::CREDITED.','.
				\Booker::STATNOTPAID.','.
				\Booker::STATOVRDUE.')';
			} elseif ($flags & self::BKGUNPAID) {
				$and[] = 'O.statpay IN ('.
				\Booker::STATPAYABLE.','.
				\Booker::CREDITED.','.
				\Booker::STATNOTPAID.','.
				\Booker::STATOVRDUE.')';
			} elseif ($flags & self::BKGPAID) {
				$and[] = 'O.statpay='.\Booker::STATPAID;
			}
		}

		$bulks = [];
		$fg = self::BKGONCE + self::BKGREPT;
		if (($flags & $fg) == $fg) {
			$tfg = $fg; //preserve this
			$flags &= ~$fg;
		} elseif ($flags & self::BKGONCE) {
			$tfg = self::BKGONCE;
			$bulks = [0,1];
		} elseif ($flags & self::BKGREPT) {
			$tfg = self::BKGREPT;
			$bulks = [20,21];
		} else {
			$tfg = $fg;
		}

		$fg = self::BKGSINGL + self::BKGGROUP;
		if (($flags & $fg) == $fg) {
			$flags &= ~$fg;
		} elseif ($flags & self::BKGSINGL) {
			$bulks[] = 0;
			$bulks[] = 20;
		} elseif ($flags & self::BKGGROUP) {
			$bulks[] = 1;
			$bulks[] = 21;
		}
		if ($bulks) {
			sort($bulks);
			$bulks = array_unique($bulks,SORT_NUMERIC);
			$and[] = 'D.bulk IN('.implode(',',$bulks).')';
		}

		if ($flags & self::BKGITEM) {
			if (is_array($itemid)) {
				$and[] = 'D.item_id IN ('.str_repeat('?,', count($itemid) - 1).'?)';
				$args = array_merge($args, $itemid);
			} elseif ($itemid) {
				$and[] = 'D.item_id=?';
				$args[] = $itemid;
			}
		}

		if ($flags & self::BKGUSER) {
			if (is_array($bookerid)) {
				$and[] = 'D.booker_id IN ('.str_repeat('?,', count($bookerid) - 1).'?)';
				$args = array_merge($args, $bookerid);
			} elseif ($bookerid) {
				$and[] = 'D.booker_id=?';
				$args[] = $bookerid;
			}
		}

		if ($flags & self::BKGUTYPE) {
			if (is_array($typeid)) {
				$and[] = 'B.type IN ('.str_repeat('?,', count($typeid) - 1).'?)';
				$args = array_merge($args, $typeid);
			} elseif ($typeid) {
				$and[] = 'B.type=?';
				$args[] = $typeid;
			}
		}

		$noname = '&lt;'.$mod->Lang('noname').'&gt;';
		$sql = <<<EOS
SELECT D.slotstart,D.slotlen,O.fee,O.feepaid,O.status,O.statpay,I.name AS what,
B.auth_id,COALESCE(B.name,A.name,A.account,'$noname') AS name,COALESCE(B.address,A.address,'') AS address,B.phone
FROM $mod->DispTable D
JOIN $mod->OnceTable O ON D.bkg_id=O.bkg_id
JOIN $mod->ItemTable I ON D.item_id=I.item_id
JOIN $mod->BookerTable B ON D.booker_id=B.booker_id
LEFT JOIN $mod->AuthTable A ON B.auth_id=A.id
EOS;
/*		if ($and || $or) {
			$sql .=  ' WHERE ';
			if ($and) {
				$sql .=  implode(' AND ',$and);
			}
			if ($or) {
				if ($and) {
					$sql .= ' AND (';
				}
				$sql .= implode(' OR ',$or);
				if ($and) {
					$sql .= ')';
				}
			}
		}
*/
		if ($and) {
			$sql .= ' WHERE '.implode(' AND ', $and);
		}

		if ($tfg == self::BKGREPT) {
			$sql = str_replace([$mod->OnceTable.' O','O.'],[$mod->RepeatTable.' R','R.'],$sql);
		} elseif ($tfg == self::BKGONCE + self::BKGREPT) {
			$sql .= ' UNION '.str_replace([$mod->OnceTable.' O','O.'],[$mod->RepeatTable.' R','R.'],$sql);
		}
		$sql .= ' ORDER BY what,slotstart';

		$utils = new Utils();
		return $utils->PlainGet($mod, $sql, $args);
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
			$valid = []; //OR just a counter
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
			$valid = []; //OR just a counter
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
			$valid = [];
			$all = self::GetExtraData($params);
			if ($all) {
				foreach ($all as $one) {
					if ($item($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($item)) {
			$valid = self::GetExtraData($params);
		} elseif ($item == '*') {
			$valid = self::GetExtraData($params);
		} else {
			$valid = self::GetExtraData($params);
		}

		$count = 0;
		$amount = 0.0;
		foreach ($valid as $one) {
			++$count;
			$amount += $X;
		}
		return [$count,$amount];
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
			$valid = [];
			$all = self::GetExtraData($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetExtraData($params);
		} elseif ($booker == '*') {
			$valid = self::GetExtraData($params);
		} else {
			$valid = self::GetExtraData($params);
		}

		$count = 0;
		$amount  = 0.0;
		foreach ($valid as $one) {
			++$count;
			$amount += $X;
		}
		return [$count,$amount];
	}

	/**
	TotalCredit:
	Determine how much credit has been accumulated by @booker
	See also: Payments::TotalCredit();
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	Returns: float amount
	*/
	public function TotalCredit(&$mod, $booker)
	{
		if (is_callable($booker)) {
			$valid = [];
			$all = self::GetExtraData($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetExtraData($params);
		} elseif ($booker == '*') {
			$valid = self::GetExtraData($params);
		} else {
			$valid = self::GetExtraData($params);
		}
		$ret = 0.0;
		foreach ($valid as $one) {
			$ret += $X;
		}
		return $ret;
	}

	/**
	UseCredit:
	Reduce the the credit accumulated by @bookerid by @amount
	See also: Payments::UseCredit();
	@bookerid: numeric booker-identifier
	@amount:
	Returns: remaining credit for @bookerid (maybe < 0), or FALSE upon error
	*/
	public function UseCredit(&$mod, $bookerid, $amount)
	{
		$valid = self::GetExtraData($params);
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
			$valid = [];
			$all = self::GetExtraData($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetExtraData($params);
		} elseif ($booker == '*') {
			$valid = self::GetExtraData($params);
		} else {
			$valid = self::GetExtraData($params);
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
			$valid = [];
			$all = self::GetExtraData($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetExtraData($params);
		} elseif ($booker == '*') {
			$valid = self::GetExtraData($params);
		} else {
			$valid = self::GetExtraData($params);
		}
		foreach ($valid as $one) {
			//OR merged sql
		}
	}

	/**
	ClearExtraData:
	Delete all extradata records older than @before and matching @booker
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	@before: UTC timestamp for limit on retained history
	Returns: nothing
	*/
	public function ClearExtraData(&$mod, $booker, $before)
	{
		if (is_callable($booker)) {
			$valid = [];
			$all = self::GetExtraData($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetExtraData($params);
		} elseif ($booker == '*') {
			$valid = self::GetExtraData($params);
		} else {
			$valid = self::GetExtraData($params);
		}
		foreach ($valid as $one) {
			//OR merged sql
		}
	}

	/**
	ClearExtraDataFor:
	Delete all extradata records matching @booker
	@booker: a numeric booker_id, or array of them, or '*' for all, or a callback for filtering
	Returns: nothing
	*/
	public function ClearExtraDataFor(&$mod, $booker)
	{
		if (is_callable($booker)) {
			$valid = [];
			$all = self::GetExtraData($params);
			if ($all) {
				foreach ($all as $one) {
					if ($booker($one)) {
						$valid[] = $one;
					}
				}
			}
		} elseif (is_array($booker)) {
			$valid = self::GetExtraData($params);
		} elseif ($booker == '*') {
			$valid = self::GetExtraData($params);
		} else {
			$valid = self::GetExtraData($params);
		}
		foreach ($valid as $one) {
			//OR merged sql
		}
	}
}
