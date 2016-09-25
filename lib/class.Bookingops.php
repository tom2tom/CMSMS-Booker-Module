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
	GetBkgData:
	Get row(s) of data for @bkgid from DataTable
	@mod: reference to current Booker module
	@bkgid: booking identifier, or array of them
	*/
	public function GetBkgData(&$mod, $bkgid)
	{
		$sql = <<<EOS
SELECT D.*,B.name,B.publicid,B.address,B.phone,B.displayclass FROM $mod->DataTable D
JOIN $mod->BookerTable B ON D.booker_id=B.booker_id
WHERE D.bkg_id
EOS;
		if (is_array($bkgid)) {
			return $mod->dbHandle->GetAssoc($sql.' IN ('.str_repeat('?,',count($bkgid)-1).'?)',$bkgid);
		} else
			return $mod->dbHandle->GetAssoc($sql.'=?',array($bkgid));
	}

	/**
	ExportBkg:
	Export data for one or more bookings
	@mod: reference to current Booker module
	@bkgid: booking identifier, or array of them, or '*'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function ExportBkg(&$mod, $bkgid)
	{
		$funcs = new Export();
		list($res,$key) = $funcs->ExportBookings($mod,FALSE,$bkgid);
		if ($res)
			return array(TRUE,'');
		return array(FALSE,$mod->Lang($key));
	}

	/**
	DeleteBkg:
	Delete one or more 'onetime' bookings
	@mod: reference to current Booker module
	@bkgid: resource- or group-booking identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function DeleteBkg(&$mod, $bkgid, $custommsg)
	{
		$rows = self::GetBkgData($mod,$bkgid);
		if ($rows) {
			$ob = \cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				$funcs = new Messager();
				$sndr = new \MessageSender();
				$propstore = array();
				$msg = array();
			} else {
				$funcs = FALSE;
				$msg = FALSE;
			}

			$utils = new Utils();
			$sql = 'DELETE FROM '.$mod->DataTable.' WHERE bkg_id=? OR bulk_id=?';

			foreach ($rows as $bid=>$one) {
				if ($utils->SafeExec($sql,array($bid,$bid))) {
					if ($funcs && $one['status'] !== \Booker::STATOK) {
						//notify user
						$item_id = $one['item_id'];
						if (!isset($propstore[$item_id])) {
							$propstore[$item_id] = $utils->GetItemProperty($mod,$item_id,
								array('item_id','name','membersname','smspattern','smsprefix'));
							$propstore[$item_id]['approvertell'] = FALSE; //no message to sender
						}
						$idata = $propstore[$item_id];
						list($res,$msg1) = $funcs->StatusMessage($mod,$utils,$idata,$one,\Booker::STATCANCEL,$custommsg,$sndr);
						if (!$res)
							$msg[] = $msg1;
					}
				} else {
					return array(FALSE,$mod->Lang('err_data'));
				}
			}
			if ($msg) {
				return array(FALSE,implode('<br />',array_unique($msg,SORT_STRING)));
			}
			return array(TRUE,'');
		}
		return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	DeleteRepeat:
	Delete one or more 'repeat' bookings
	@mod: reference to current Booker module
	@bkgid: repeat-booking identifier, or array of them
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	TODO periodic cleanup of inactive repeats with no remaining history
	*/
	public function DeleteRepeat(&$mod, $bkgid)
	{
		$utils = new Utils();
		$sql = 'SELECT bkg_id,item_id FROM '.$mod->RepeatTable.' WHERE bkg_id';
		if (is_array($bkgid)) {
			$sql .= ' IN ('.str_repeat('?,',count($bkgid)-1).'?)';
			$rows = $utils->SafeGet($sql,$bkgid,'assoc');
		} else {
			$sql .= '=?';
			$rows = $utils->SafeGet($sql,array($bkgid),'assoc');
		}
		if ($rows) {
			$sql = 'DELETE FROM '.$mod->DataTable.' WHERE bulk_id=? AND slotstart>=?';
			$sql2 = 'SELECT COUNT(1) AS num FROM '.$mod->DataTable.' WHERE bulk_id=? AND slotstart<?';
			$sql3 = 'UPDATE '.$mod->RepeatTable.' SET active=0 WHERE bkg_id=?';
			$sql4 = 'DELETE FROM '.$mod->RepeatTable.' WHERE bkg_id=?';
			$propstore = array();
$dtw = new \DateTime('@0',NULL); //DEBUG
			foreach ($rows as $bid=>$one) {
				$item_id = (int)$one;
				if (!isset($propstore[$item_id])) {
					$idata = $utils->GetItemProperty($mod,$item_id,'timezone');
					$propstore[$item_id] = $utils->GetZoneTime($idata['timezone']);
				}
$dtw->setTimestamp($propstore[$item_id]);
				//if historic bookings exist, just flag stuff, don't delete yet
				$args = array($bid,$propstore[$item_id]);
				$count = $utils->SafeGet($sql2,$args,'one');
 				$sql5 = ($count > 0) ? $sql3 : $sql4;
				$allsql = array($sql,$sql5);
				$allargs = array($args,array($bid));
				if (!$utils->SafeExec($allsql,$allargs))
					return array(FALSE,$mod->Lang('err_data'));
			}
			return array(TRUE,'');
		}
		return array(FALSE,$mod->Lang('err_data'));
	}

	/* *
	SaveBkg:
	Add to or update DataTable per data in @params, without any schedule-check
	@mod: reference to current Booker module
	@params: reference to parameters array
	@is_new: whether this is a new booking (hence add to table)
	@bulk_id: optional repeat- or group-booking identifier, default 0
	Returns: boolean indicating successful completion
	*/
/*	public function SaveBkg(&$mod, &$params, $is_new, $bulk_id=0)
	{
		if (empty($params['booker_id'])) {
	 		$funcs = new Userops();
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
				$stat = ($params['paid']) ? \Booker::STATPAID : \Booker::STATNEW;
			}
$this->Crash();
			$sql = 'INSERT INTO '.$mod->DataTable.
' (bkg_id,bulk_id,item_id,slotstart,slotlen,booker_id,status,paid) VALUES (?,?,?,?,?,?,?,?)';
			$bkgid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
			$args = array(
				$bkgid,
				$bulk_id,
				$params['item_id'],
				$params['slotstart'],
				$params['slotlen'],
				$bookerid,
				$stat,
				$params['paid']
			);
		} else { //update
			if (isset($params['status'])) {
				$stat = $params['status'];
			} else {
				$stat = ($params['paid']) ? \Booker::STATPAID : \Booker::STATNEW;
			}
			$sql = 'UPDATE '.$mod->DataTable.' SET slotstart=?,slotlen=?,status=?,paid=? WHERE bkg_id=?';
			$args = array(
				$params['slotstart'],
				$params['slotlen'],
				$stat,
				$params['paid'],
				$params['bkg_id']
			);
		}
		$utils = new Utils();
		return $utils->SafeExec($sql,$args);
	}
*/
	/**
	GetBooked:
	Get data for bookings which cover any part of the interval
	 @startstamp to @endstamp inclusive
	@mod: reference to current Booker module
	@item_id: item identifier, or array or them
	@startstamp: timestamp representing start of period to check
	@endstamp: ditto for end of period
	Returns: array, maybe empty
	*/
	public function GetBooked(&$mod, $item_id, $startstamp, $endstamp)
	{
		$sql = <<<EOS
SELECT D.*,B.name,I.name AS what FROM {$mod->DataTable} D
LEFT JOIN {$mod->BookerTable} B ON D.booker_id=B.booker_id
LEFT JOIN {$mod->ItemTable} I ON D.item_id=I.item_id
WHERE D.item_id
EOS;
		if (is_array($item_id)) {
			$args = $item_id;
			$fillers = str_repeat('?,',count($args)-1);
			$sql .= ' IN('.$fillers.'?)';
		} elseif ($item_id >= \Booker::MINGRPID) {
			$utils = new Utils();
			$args = $utils->GetGroupItems($mod,$item_id);
			if (!$args)
				return array();
			unset($utils);
			$fillers = str_repeat('?,',count($args)-1);
			$sql .= ' IN('.$fillers.'?)';
		} else {
			$args = array($item_id);
			$sql .= '=?';
		}
		$args[] = $endstamp;
		$args[] = $startstamp;
		$sql .= ' AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ? ORDER BY I.name,D.slotstart';
		$utils = new Utils();
		return $utils->SafeGet($sql,$args);
	}

	/**
	GetTableBooked:
	Get data for bookings which cover any part of the interval
	 @startstamp to @endstamp inclusive, to suit tabular display (no booker info)
	@mod: reference to current Booker module
	@item_id: item_identifier, or array of them
	@startstamp: timestamp representing start of period to check
	@endstamp: ditto for end of period
	Returns: array or FALSE
	*/
	public function GetTableBooked(&$mod, $item_id, $startstamp, $endstamp)
	{
$dts = new \DateTime('@0',NULL); //DEBUG
$dts->setTimestamp($startstamp);
$dte = new \DateTime('@0',NULL); //DEBUG
$dte->setTimestamp($endstamp);

		if (!is_array($item_id))
			$args = array($item_id);
		else
			$args = $item_id;
		$fillers = str_repeat('?,',count($args)-1);
		$sql = <<<EOS
SELECT D.bkg_id,D.item_id,D.slotstart,D.slotlen,D.booker_id,I.name FROM $mod->DataTable D
JOIN $mod->ItemTable I ON D.item_id=I.item_id
WHERE D.item_id IN ({$fillers}?) AND D.active>0 AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ?
ORDER BY D.slotstart,I.name
EOS;
		$args[] = $endstamp;
		$args[] = $startstamp;
		$utils = new Utils();
		return $utils->SafeGet($sql,$args);
	}

	/**
	GetListBooked:
	Get data for bookings which cover any part of the interval
	 @startstamp to @endstamp inclusive, arranged to suit textform display
	@mod: reference to current Booker module
	@is_group: boolean, whether processing a resource-group
	@item_id: item_identifier, or array of them
	@lfmt: a LIST* constant
	@startstamp: timestamp representing start of period to check
	@endstamp: ditto for end of period
	Returns: array or FALSE
	*/
	public function GetListBooked(&$mod, $is_group, $item_id, $lfmt, $startstamp, $endstamp)
	{
		if (!is_array($item_id))
			$args = array($item_id);
		else
			$args = $item_id;
		$fillers = str_repeat('?,',count($args)-1);
		$sql = <<<EOS
SELECT D.item_id,D.slotstart,D.slotlen,B.name,I.name AS what FROM $mod->DataTable D
JOIN $mod->BookerTable B ON D.booker_id=B.booker_id
JOIN $mod->ItemTable I ON D.item_id=I.item_id
WHERE D.item_id IN ({$fillers}?) AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ?
ORDER BY
EOS;
		switch ($lfmt) {
		 case \Booker::LISTUS:
			$t = ($is_group) ? 'I.name,':'';
			$sql .= ' B.name,'.$t.'D.slotstart';
			break;
		 case \Booker::LISTUR:
			$sql .= ' B.name,D.slotstart';
			if ($is_group) $sql .= ',I.name';
			break;
		 case \Booker::LISTRS: //only for groups
			$sql .= ' I.name,D.slotstart,B.name';
			break;
//	 case \Booker::LISTSU:
		 default:
			$sql .= ' D.slotstart,B.name';
			if ($is_group) $sql .= ',I.name';
			break;
		}
		$args[] = $endstamp;
		$args[] = $startstamp;
		$utils = new Utils();
		return $utils->SafeGet($sql,$args);
	}
}
