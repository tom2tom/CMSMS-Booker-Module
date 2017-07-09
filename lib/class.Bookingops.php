<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Bookingops - functions for processing bookings
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Bookingops
{
	/**
	ExportBkg:
	Export data for one or more bookings
	@mod: reference to current Booker module
	@bkgid: booking identifier, or array of them, or '*'
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = '' or error message
	*/
	public function ExportBkg(&$mod, $bkgid)
	{
		$funcs = new Export();
		list($res, $key) = $funcs->ExportBookings($mod, FALSE, $bkgid);
		if ($res) {
			return [TRUE,''];
		}
		return [FALSE,$mod->Lang($key)];
	}

	/**
	DeleteBkg:
	Delete one or more 'onetime' bookings
	@mod: reference to current Booker module
	@bkgid: resource- or group-booking identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = '' or error message
	*/
	public function DeleteBkg(&$mod, $bkgid, $custommsg)
	{
		$rows = self::GetBkgMessageData($mod, $bkgid);
		if ($rows) {
			if ($mod->havenotifier) {
				$funcs = new Messager();
				$sndr = new \Notifier\MessageSender();
				$propstore = [];
				$msg = [];
			} else {
				$funcs = FALSE;
				$msg = FALSE;
			}

//TODO CHECK set active=0 instead of delete, iff keeptime > 0?
//TODO consequent DispTable change(s)
			$utils = new Utils();
			$sql = 'DELETE FROM '.$mod->OnceTable.' WHERE bkg_id=?';
			foreach ($rows as $bid => $one) {
				if ($utils->SafeExec($sql, [$bid])) {
					if ($funcs && $one['status'] !== \Booker::STATOK) {
						//notify user
						$item_id = $one['item_id'];
						if (!isset($propstore[$item_id])) {
							$propstore[$item_id] = ['item_id' => $item_id,'approvertell' => FALSE] //no message to sender
								+ $utils->GetItemProperties($mod, $item_id,
									['name', 'membersname', 'smspattern', 'smsprefix']);
						}
						$idata = $propstore[$item_id];
						list($res, $msg1) = $funcs->StatusMessage($mod, $utils, $idata, $one, \Booker::STATCANCEL, $custommsg, $sndr);
						if (!$res) {
							$msg[] = $msg1;
						}
					}
				} else {
					return [FALSE,$mod->Lang('err_data')];
				}
			}
			if ($msg) {
				return [FALSE,implode('<br />', array_unique($msg, SORT_STRING))];
			}
			return [TRUE,''];
		}
		return [FALSE,$mod->Lang('err_data')];
	}

	/**
	DeleteRepeat:
	Delete one or more 'repeat' bookings
	@mod: reference to current Booker module
	@bkgid: repeat-booking identifier, or array of them
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = '' or error message
	TODO periodic cleanup of inactive repeats with no remaining history
	*/
	public function DeleteRepeat(&$mod, $bkgid)
	{
		$utils = new Utils();
		$sql = 'SELECT bkg_id,item_id FROM '.$mod->RepeatTable.' WHERE bkg_id';
		if (is_array($bkgid)) {
			$sql .= ' IN ('.str_repeat('?,', count($bkgid) - 1).'?)';
			$rows = $utils->SafeGet($sql, $bkgid, 'assoc');
		} else {
			$sql .= '=?';
			$rows = $utils->SafeGet($sql, [$bkgid], 'assoc');
		}
		if ($rows) {
//TODO CHECK set active=0 iff keeptime > 0?
//TODO consequent DispTable change(s)
//TODO CHECK slot end < cutoff?
			$sql = 'DELETE FROM '.$mod->DispTable.' WHERE bkg_id=? AND slotstart>=?';
			$sql2 = 'SELECT COUNT(1) AS num FROM '.$mod->DispTable.' WHERE bkg_id=? AND slotstart<?';
//TODO removed=time();
			$sql3 = 'UPDATE '.$mod->RepeatTable.' SET active=0 WHERE bkg_id=?';
			$sql4 = 'DELETE FROM '.$mod->RepeatTable.' WHERE bkg_id=?';
			$propstore = [];
			$dtw = new \DateTime('@0', NULL); //DEBUG
			foreach ($rows as $bid => $one) {
				$item_id = (int)$one;
				if (!isset($propstore[$item_id])) {
					$idata = $utils->GetItemProperties($mod, $item_id, 'timezone');
					$propstore[$item_id] = $utils->GetZoneTime($idata['timezone']);
				}
				$dtw->setTimestamp($propstore[$item_id]); //DEBUG
				//if historic bookings exist, just flag stuff, don't delete yet
				$args = [$bid,$propstore[$item_id]];
				$count = $utils->SafeGet($sql2, $args, 'one');
				$sql5 = ($count > 0) ? $sql3 : $sql4;
				$allsql = [$sql,$sql5];
				$allargs = [$args,[$bid]];
				if (!$utils->SafeExec($allsql, $allargs)) {
					return [FALSE,$mod->Lang('err_data')];
				}
			}
			return [TRUE,''];
		}
		return [FALSE,$mod->Lang('err_data')];
	}

	/**
	ClearRepeat:
	Clear From DispTable all 'repeat' bookings intesecting with the interval
	  @st to @nd inclusive, and update RepeatTable scan-ranges accordingly
	@mod: reference to current Booker module
	@bkgid: repeat-booking identifier, or array of them
	@st: range-start timestamp or -1 for 'now', default -1
	@nd: range-end timestamp or -1 for 'now' or -2 for PHP_INT_MAX, default -2
	*/
	public function ClearRepeat(&$mod, $bkgid, $st=-1, $nd=-2)
	{
		if ($st == -1) {
			$st = time();
		}
		if ($nd == -1) {
			$nd = time();
		} elseif ($st == -2) {
			$st = PHP_INT_MAX;
		}
		if ($nd <= $st) {
			return;
		}

		$utils = new Utils();
		$sql1 = 'SELECT bkg_id,checkedfrom,checkedto FROM '.$mod->RepeatTable.' WHERE bkg_id';
		if (is_array($bkgid)) {
			$sql1 .= ' IN ('.str_repeat('?,', count($bkgid) - 1).'?)';
			$rows = $utils->SafeGet($sql1,$bkgid,'assoc');
		} else {
			$sql1 .= '=?';
			$rows = $utils->SafeGet($sql1,[$bkgid],'assoc');
		}
		if ($rows) {
			$sql = [];
			$args = [];
			$sql1 = 'DELETE FROM '.$mod->DispTable.' WHERE bkg_id=?';
			$sql2 = 'UPDATE '.$mod->RepeatTable.' SET checkedfrom=?,checkedto=? WHERE bkg_id=?';

			foreach ($rows as $bid => $one) {
				$from = $one['checkedfrom'];
				$to = $one['checkedto'];
				if ($to > $st && $from < $nd) {
					if ($st > $from && $nd >= $to) {
						$sql[] = $sql1.' AND slotstart>';
						$args[] = [$bid,$st];
						$sql[] = $sql2;
						$args[] = [$from,$st,$bid];
					} elseif ($st < $from && $nd <= $to) {
						$sql[] = $sql1.' AND slotstart+slotlen<?';
						$args[] = [$bid,$nd];
						$sql[] = $sql2;
						$args[] = [$nd,to,$bid];
					} else {
						$sql[] = $sql1;
						$args[] = [$bid];
						$sql[] = $sql2;
						$args[] = [0,0,$bid];
					}
				}
			}
			if ($sql) {
				$utils->SafeExec($sql,$args);
			}
		}
	}

	/* *
	SaveBkg: NOW DONE IN SCHEDULE FUNCS
	Upsert OnceTable per data in @params, without any schedule-check
	@mod: reference to current Booker module
	@params: reference to parameters array
	@is_new: whether this is a new booking (hence add to table)
	@bulk: optional repeat- or group-booking Booker:: BULK* enumerator {0,1,20,21}, default 0
	Returns: boolean indicating successful completion
	*/
/*	public function SaveBkg(&$mod, &$params, $is_new, $bulk=0)
	{
		if (empty($params['booker_id'])) {
			 $funcs = new Userops($mod);
			list($bookerid,$newbooker) = $funcs->GetParamsID($mod,$params);
			if ($bookerid === FALSE) {
				//TODO nicely handle bad password e.g. message
				sleep(2);
				return FALSE;
			}
		} else {
			$bookerid = (int)$params['booker_id']; //TODO upstream admin must supply this
		}
		if ($is_new) {
			if (isset($params['status'])) {
				$stat = $params['status'];
			} else {
				$stat = func($params['statpay']) ? \Booker::STATPAID : \Booker::STATNEW;
			}
			$ps = func($params);
			$sql = 'INSERT INTO '.$mod->OnceTable.
' (bkg_id,booker_id,item_id,slotstart,slotlen,status,statpay) VALUES (?,?,?,?,?,?,?)';
			$bkgid = $mod->dbHandle->GenID($mod->OnceTable.'_seq');
			$args = array(
				$bkgid,
				$bookerid,
				$params['item_id'],
				$params['slotstart'],
				$params['slotlen'],
				$stat,
				$ps
			);
		} else { //update
			if (isset($params['status'])) {
				$stat = $params['status'];
			} else {
				$stat = (TODOfunc($params['statpay'])) ? \Booker::STATPAID : \Booker::STATNEW;
			}
			$ps = func($params);
			$sql = 'UPDATE '.$mod->OnceTable.' SET slotstart=?,slotlen=?,status=?,statpay=? WHERE bkg_id=?';
			$args = array(
				$params['slotstart'],
				$params['slotlen'],
				$stat,
				$ps,
				$params['bkg_id']
			);
		}
		$utils = new Utils();
		return $utils->SafeExec($sql,$args);
	}
*/
	/**
	GetBkgMessageData:
	Get row(s) of data each with fields from DispTable & related, for @bkgid
	@mod: reference to current Booker module
	@bkgid: booking identifier, or array of them
	Returns: booking-id-keyed associative array, or FALSE
	*/
	public function GetBkgMessageData(&$mod, $bkgid)
	{
		$s = \Booker::STATNONE;		//default status
		$sql = <<<EOS
SELECT D.bkg_id,D.item_id,D.slotstart,
COALESCE(O.slotcount,R.slotcount,1) AS slotcount,
COALESCE(O.status,R.status,{$s}) AS status,
I.name AS what,
COALESCE(A.name,B.name,'') AS name,
COALESCE(A.address,B.address,'') AS address,B.phone,A.publicid
FROM $mod->DispTable D
LEFT JOIN $mod->OnceTable O ON D.bkg_id=O.bkg_id
LEFT JOIN $mod->RepeatTable R ON D.bkg_id=R.bkg_id
JOIN $mod->ItemTable I ON D.item_id=I.item_id
JOIN $mod->BookerTable B ON D.booker_id=B.booker_id
LEFT JOIN $mod->AuthTable A ON B.auth_id=A.id
WHERE D.bkg_id
EOS;
		if (is_array($bkgid)) {
			$sql .= ' IN ('.str_repeat('?,', count($bkgid) - 1).'?)';
			$args = $bkgid;
		} else {
			$sql .= '=?';
			$args = [$bkgid];
		}
		$utils = new Utils();
		return $utils->PlainGet($mod, $sql, $args, 'assoc');
	}

	/* *
	GetBooked:
	Get some DispTable & related data for bookings which cover any part of the
	 interval @startstamp to @endstamp inclusive
	@mod: reference to current Booker module
	@item: item identifier (a.k.a. item_id), or array or them
	@startstamp: timestamp representing start of period to check
	@endstamp: ditto for end of period
	Returns: array of rows, each with members 'item_id','name','publicid','what',
	 OR maybe empty
	*/
/*	public function GetBooked(&$mod, $item, $startstamp, $endstamp)
	{
		$sql = <<<EOS
SELECT D.item_id,COALESCE(A.name,B.name,'') AS name,A.publicid,I.name AS what
FROM $mod->DispTable D
LEFT JOIN $mod->BookerTable B ON D.booker_id=B.booker_id
LEFT JOIN $mod->AuthTable A ON B.auth_id=A.id
LEFT JOIN $mod->ItemTable I ON D.item_id=I.item_id
WHERE D.item_id
EOS;
		if (is_array($item)) {
			$args = $item;
			$fillers = str_repeat('?,', count($args) - 1);
			$sql .= ' IN('.$fillers.'?)';
		} elseif ($item >= \Booker::MINGRPID) {
			$utils = new Utils();
			$args = $utils->GetGroupItems($mod, $item);
			if (!$args) {
				return array();
			}
			unset($utils);
			$fillers = str_repeat('?,', count($args) - 1);
			$sql .= ' IN('.$fillers.'?)';
		} else {
			$args = array($item);
			$sql .= '=?';
		}
		$args[] = $endstamp;
		$args[] = $startstamp;
		$sql .= ' AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ? ORDER BY I.name,D.slotstart';
		$utils = new Utils();
		return $utils->PlainGet($mod, $sql, $args);
	}
*/
	/**
	GetTableBooked:
	Get some DispTable & related data for displaying bookings which cover any part of the
	 interval @startstamp to @endstamp inclusive, to suit tabular display (no booker info)
	@mod: reference to current Booker module
	@item: item_identifier (a.k.a item_id), or array of them
	@startstamp: timestamp representing start of period to check
	@endstamp: ditto for end of period
	Returns: array or FALSE
	*/
	public function GetTableBooked(&$mod, $item, $startstamp, $endstamp)
	{
		if (is_array($item)) {
			$args = $item;
		} else {
			$args = [$item];
		}
		$fillers = str_repeat('?,', count($args) - 1);
		$sql = <<<EOS
SELECT D.bkg_id,D.booker_id,D.item_id,D.slotstart,D.slotlen,I.name FROM $mod->DispTable D
JOIN $mod->ItemTable I ON D.item_id=I.item_id
WHERE D.item_id IN ({$fillers}?) AND D.displayed>0 AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ?
ORDER BY D.slotstart,I.name
EOS;
		$args[] = $endstamp;
		$args[] = $startstamp;
		$utils = new Utils();
		return $utils->SafeGet($sql, $args);
	}

	/**
	GetListBooked:
	Get some DispTable & related data for displaying bookings which cover any part of the
	 interval @startstamp to @endstamp inclusive, arranged to suit textform display
	@mod: reference to current Booker module
	@is_group: boolean, whether processing a resource-group
	@item: item_identifier (a.k.a. item_id), or array of them
	@lfmt: a LIST* constant
	@startstamp: timestamp representing start of period to check
	@endstamp: ditto for end of period
	Returns: array or FALSE
	*/
	public function GetListBooked(&$mod, $is_group, $item, $lfmt, $startstamp, $endstamp)
	{
		if (is_array($item)) {
			$args = $item;
		} else {
			$args = [$item];
		}
		$fillers = str_repeat('?,', count($args) - 1);
		$sql = <<<EOS
SELECT D.booker_id,D.item_id,D.slotstart,D.slotlen,COALESCE(A.name,B.name,'') AS name,A.publicid,I.name AS what
FROM $mod->DispTable D
JOIN $mod->BookerTable B ON D.booker_id=B.booker_id
LEFT JOIN $mod->AuthTable A ON B.auth_id=A.id
JOIN $mod->ItemTable I ON D.item_id=I.item_id
WHERE D.item_id IN ({$fillers}?) AND D.displayed>0 AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ?
ORDER BY
EOS;
		switch ($lfmt) {
		 case \Booker::LISTUS:
			$t = ($is_group) ? 'I.name,' : '';
			$sql .= ' B.name,'.$t.'D.slotstart';
			break;
		 case \Booker::LISTUR:
			$sql .= ' B.name,D.slotstart';
			if ($is_group) {
				$sql .= ',I.name';
			}
			break;
		 case \Booker::LISTRS: //only for groups
			$sql .= ' I.name,D.slotstart,B.name';
			break;
//	 case \Booker::LISTSU:
		 default:
			$sql .= ' D.slotstart,B.name';
			if ($is_group) {
				$sql .= ',I.name';
			}
			break;
		}
		$args[] = $endstamp;
		$args[] = $startstamp;
		$utils = new Utils();
		return $utils->PlainGet($mod, $sql, $args);
	}
}
