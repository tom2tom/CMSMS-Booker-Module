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
	const MSGCHANGED = 3;
	const MSGCANCELLED = 4;

	/*
	GetBkgData:
	Get from DataTable the row(s) of data for @bkg_id
	@mod: reference to current Booker module
	@bgk_id: booking identifier, or array of them
	*/
	private function GetBkgData(&$mod, $bkg_id)
	{
		if (is_array($bkg_id)) {
			$fillers = str_repeat('?,',count($bkg_id)-1);
			return $mod->dbHandle->GetAssoc('SELECT * FROM '.$mod->DataTable.' WHERE bkg_id IN ('.$fillers.'?)',$bkg_id);
		} else
			return $mod->dbHandle->GetAssoc('SELECT * FROM '.$mod->DataTable.' WHERE bkg_id=?',array($bkg_id));
	}

	/*
	MsgParms:
	see also - bkrrequestops::MsgParms
	Construct arguments for MessageSender::Send()
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@bdata: reference to array of booking data
	@idata: reference to array of booked-item data
	@mtype: MSG* enum
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	@extra: optional stuff for some types of message, default ''
	*/
	private function MsgParms(&$mod, &$utils, &$bdata, &$idata, $mtype, $custommsg, $extra='')
	{
		$overday = ($utils->GetInterval($mod,$idata['item_id'],'slot') >= 84600);
		switch ($mtype) {
		 case self::MSGCHANGED:
			$ktitle = 'email_changed_title';
			if ($overday) {
				$kbody1 = 'email_changed';
				$kbody2 = 'text_change';
			} else {
				$kbody1 = 'email_changedat';
				$kbody2 = 'text_changeat';
			}
		 	break;
		 case self::MSGCANCELLED:
			$ktitle = 'email_cancelled_title';
			if ($overday) {
				$kbody1 = 'email_cancel';
				$kbody2 = 'text_cancel';
			} else {
				$kbody1 = 'email_cancelat';
				$kbody2 = 'text_cancelat';
			}
			break;
		 default:
		 	return FALSE; //error
		}

		$from = FALSE; //always use default sender
		$to = array($bdata['sender']=>$bdata['contact']);
		$what = ($bdata['subgrpcount'] > 1) ?
			sprintf('%d %s',$bdata['subgrpcount'],$idata['membersname']):
			$utils->GetItemName($mod,$idata);
		$dts = new \DateTime('1900-1-1',new \DateTimeZone('UTC'));
		$dts->setTimestamp($bdata['slotstart']);
		$on = $utils->IntervalFormat($mod,$dts,'D j M');

		$textparms = array('prefix'=>$idata['smsprefix'],'pattern'=>$idata['smspattern']);
		$mailparms = array('subject'=>$mod->Lang($ktitle));
		$tweetparms = array();
		if ($overday) {
			$msg = $mod->Lang($kbody1,$what,$on);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$mailparms['body'] = $msg;
			$msg = $mod->Lang($kbody2,$what,$on);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$textparms['body'] = $msg;
			$tweetparms['body'] = $msg;
		} else {
			$at = $dts->format('g:i A');
			$msg = $mod->Lang($kbody1,$what,$on,$at);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$mailparms['body'] = $msg;
			$msg = $mod->Lang($kbody2,$what,$on,$at);
			$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
			$textparms['body'] = $msg;
			$tweetparms['body'] = $msg;
		}
		return array($from,$to,$textparms,$mailparms,$tweetparms);
	}

	/**
	NotifyBooker:
	Send message to 'user' of one or more bookings
	@mod: reference to current Booker module
	@bkg_id: booking identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	Returns: 2-member TRUE or error-message string
	*/
	public function NotifyBooker(&$mod, $bkg_id, $custommsg)
	{
		$rows = self::GetBkgData($mod,$bkg_id);
		if ($rows) {
			$ob = cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				$funcs = new \MessageSender();
				$utils = new Utils();
				$fails = array();
				foreach ($rows as $bid=>$one) {
					$idata = $utils->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$utils,$one,$idata,self::MSGCHANGED,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if (!$res)
						$fails[] = $msg;
				}
				if ($fails)
					return array(FALSE,implode('<br />',$fails));
				return array(TRUE,'');
			}
		}
		return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	ExportBkg:
	Export data for one or more bookings
	@mod: reference to current Booker module
	@bkg_id: booking identifier, or array of them
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function ExportBkg(&$mod, $bkg_id)
	{
		$rows = self::GetBkgData($mod,$bkg_id);
		if ($rows) {
			$funcs = new CSV();
			list($res,$key) = $funcs->ExportBookings($mod,FALSE,array_keys($rows));
			if ($res)
				return array(TRUE,'');
			return array(FALSE,$mod->Lang($key));
		} else
			return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	DeleteBkg:
	Delete one or more 'onetime' bookings
	@mod: reference to current Booker module
	@bkg_id: booking identifier, or array of them
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function DeleteBkg(&$mod, $bkg_id, $custommsg)
	{
		$rows = self::GetBkgData($mod,$bkg_id);
		if ($rows) {
			$ob = cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				$funcs = new \MessageSender();
				$fails = array();
			} else
				$funcs = FALSE;

			$utils = new Utils();
			$sql = 'DELETE FROM '.$mod->DataTable.' WHERE bkg_id=?';

			foreach ($rows as $bid=>$one) {
				if ($funcs && $one['status'] !== \Booker::STATOK) {
					//notify user
					$idata = $utils->GetItemProperty($mod,$one['item_id'],'*');
					list($from,$to,$textparms,$mailparms,$tweetparms) = self::MsgParms($mod,$utils,$one,$idata,self::MSGCANCELLED,$custommsg);
					list($res,$msg) = $funcs->Send($from,$to,$textparms,$mailparms,$tweetparms);
					if (!$res)
						$fails[] = $msg;
				}
				if (!$utils->SafeExec($sql,array($bid))) //remove it
					return array(FALSE,$mod->Lang('err_data'));
			}
			if ($fails)
				return array(FALSE,implode('<br />',$fails));
			return array(TRUE,'');
		}
		return array(FALSE,$mod->Lang('err_data'));
	}

	/**
	DeleteRepeat:
	Delete one or more 'repeat' bookings
	@mod: reference to current Booker module
	@bkg_id: booking identifier, or array of them
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	TODO periodic cleanup of inactive repeats with no remaining history
	*/
	public function DeleteRepeat(&$mod, $bkg_id)
	{
		$utils = new Utils();
		if (is_array($bkg_id)) {
			$fillers = str_repeat('?,',count($bkg_id)-1);
			$sql = 'SELECT * FROM '.$mod->RepeatTable.' WHERE bkg_id IN ('.$fillers.'?)';
			$rows = $utils->SafeGet($sql,$bkg_id,'assoc');
		} else {
			$sql = 'SELECT * FROM '.$mod->RepeatTable.' WHERE bkg_id=?';
			$rows = $utils->SafeGet($sql,array($bkg_id),'assoc');
		}

		if ($rows) {
			$ob = cms_utils::get_module('Notifier');
			if ($ob) {
				unset($ob);
				$funcs = new \MessageSender();
				$fails = array();
			} else
				$funcs = FALSE;

			$sql = 'DELETE FROM '.$mod->DataTable.' WHERE bkg_id=? AND slotstart>=?';
			$sql2 = 'SELECT COUNT(1) AS num FROM '.$mod->DataTable.' WHERE bkg_id=? AND slotstart<?';
			$sql3 = 'UPDATE '.$mod->RepeatTable.' SET active=0 WHERE bkg_id=?';
			$sql4 = 'DELETE FROM '.$mod->RepeatTable.' WHERE bkg_id=?';
			foreach ($rows as $bid=>$one) {
				$idata = $utils->GetItemProperty($mod,$one['item_id'],'timezone');
				$st = $utils->GetZoneTime($idata['timezone']); //TODO cache this
				$args = array($bid,$st);
				//delete interpreted bookings from now foward
				$count = $utils->SafeGet($sql2,$args,'one');
 				//if historic bookings exist, just flag stuff, don't delete yet
				$qry = ($count > 0) ? $sql3 : $sql4;
				$allsql = array($sql,$qry);
				$allargs = array($args,array($bid));
				if (!$utils->SafeExec($allsql,$allargs))
					return array(FALSE,$mod->Lang('err_data'));
			}
			return array(TRUE,'');
		}
		return array(FALSE,$mod->Lang('err_data'));
	}

	private function CurrentBookingUser(&$mod, &$params)
	{
		if ($params['repeat'])
			$sql = 'SELECT user FROM '.$mod->RepeatTable.' WHERE bkg_id=?';
		else
			$sql = 'SELECT user FROM '.$mod->DataTable.' WHERE bkg_id=?';
		return $mod->dbHandle->GetOne($sql,array($params['bkg_id']));
	}

	/**
	ConformBookingData:
	Conform tabled values: contact,userclass and/or user according to @params values
	@mod: reference to current Booker module
	@params: reference to parameters array
	Returns: T/F indicating successful completion
	*/
	public function ConformBookingData(&$mod, &$params)
	{
		$utils = new Utils();
		$old = FALSE;
		$ret = TRUE;
		if (!empty($params['conformcontact'])) {
			$old = self::CurrentBookingUser($mod,$params);
			if (!$old) return;
			$allsql = array(
			'UPDATE '.$mod->DataTable.' SET contact=? WHERE user=?',
			'UPDATE '.$mod->RepeatTable.' SET contact=? WHERE user=?');
			$args = array($params['contact'],$old);
			if (!$utils->SafeExec($allsql,array($args,$args)))
				$ret = FALSE;
		}

		if (!empty($params['conformstyle'])) {
			if (!$old)
				$old = self::CurrentBookingUser($mod,$params);
			if (!$old) return;
			$allsql = array(
			'UPDATE '.$mod->DataTable.' SET userclass=? WHERE user=?',
			'UPDATE '.$mod->RepeatTable.' SET userclass=? WHERE user=?');
			$args = array((int)$params['userclass'],$old);
			if (!$utils->SafeExec($allsql,array($args,$args)))
				$ret = FALSE;
		}

		if (!empty($params['conformuser'])) {
			if (!$old)
				$old = self::CurrentBookingUser($mod,$params);
			if (!$old) return;
			$allsql = array(
			'UPDATE '.$mod->DataTable.' SET user=? WHERE user=?',
			'UPDATE '.$mod->RepeatTable.' SET user=? WHERE user=?');
			$args = array($params['user'],$old);
			if (!$utils->SafeExec($allsql,array($args,$args)))
				$ret = FALSE;
		}
		return $ret;
	}

	/**
	SaveBkg:
	@mod: reference to current Booker module
	@params: reference to parameters array
	@is_new:
	Returns: T/F indicating successful completion
	*/
	public function SaveBkg(&$mod, &$params, $is_new)
	{
		if (isset($params['when'])) {
			$dts = new \DateTime($params['when'],new \DateTimeZone('UTC'));
			$params['when'] = $dts->getTimestamp();
			if (isset($params['until'])) {
				$dts->modify($params['until']);
				$t = $dts->getTimestamp() - $params['when'];
				if ($t < 60) $t = 60;
				$params['until'] = $t;
			}
		}
		if ($is_new) {
			$sql2 = 'bkg_id,item_id,slotstart,user,contact,userclass';
			$fillers = '?,?,?,?,?,?';
			$bid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
			$args = array(
				$bid,
				(int)$params['item_id'],
				$params['user'],
				$params['contact'],
				(int)$params['userclass']
			);
			foreach (array('when','until','paid') as $k) {
				if (isset($params[$k])) {
					$sql2 .= ",$k";
					$fillers .= ',?';
					$args[] = (int)$params[$k];
				}
			}
			$sql = 'INSERT INTO '.$mod->DataTable.' ('.$sql2.') VALUES ('.$fillers.')';
		} else { //update
			self::ConformBookingData($mod,$params); //general update where needed
			$sql2 = 'slotstart=?,user=?,contact=?,userclass=?';
			$args = array(
				$params['user'],
				$params['contact'],
				(int)$params['userclass']
			);
			foreach (array('when','until','paid') as $k) {
				if (isset($params[$k])) {
					$sql2 .= ",$k=?";
					$args[] = (int)$params[$k];
				}
			}
			$args[] = (int)$params['bkg_id'];
			$sql = 'UPDATE '.$mod->DataTable.' SET '.$sql2.' WHERE bkg_id=?';
		}
		$utils = new Utils();
		return $utils->SafeExec($sql,$args);
	}

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
SELECT B.*,I.name FROM {$mod->DataTable} B
LEFT JOIN {$mod->ItemTable} I ON B.item_id = I.item_id
WHERE B.item_id
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
		$sql .= ' AND B.slotstart <= ? AND (B.slotstart+B.slotlen) >= ? ORDER BY I.name,B.slotstart';
		$utils = new Utils();
		return $utils->SafeGet($sql,$args);
	}

	/**
	GetTableBooked:
	Get data for bookings which cover any part of the interval
	 @startstamp to @endstamp inclusive
	@mod: reference to current Booker module
	@item_id: item_identifier, or array of them
	@startstamp: timestamp representing start of period to check
	@endstamp: ditto for end of period
	Returns: array or FALSE
	*/
	public function GetTableBooked(&$mod, $item_id, $startstamp, $endstamp)
	{
		if (!is_array($item_id))
			$args = array($item_id);
		else
			$args = $item_id;
		$fillers = str_repeat('?,',count($args)-1);
		$sql = <<<EOS
SELECT B.*,I.name FROM {$mod->DataTable} B
JOIN {$mod->ItemTable} I ON B.item_id = I.item_id
WHERE B.item_id IN ({$fillers}?) AND B.slotstart <= ? AND (B.slotstart+B.slotlen) >= ?
ORDER BY B.slotstart,I.name
EOS;
		$args[] = $endstamp;
		$args[] = $startstamp;
		$utils = new Utils();
		return $utils->SafeGet($sql,$args);
	}

	/**
	GetListBooked:
	Get data for bookings which cover any part of the interval
	 @startstamp to @endstamp inclusive
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
SELECT B.slotstart,B.slotlen,B.user,I.name FROM {$mod->DataTable} B
LEFT JOIN {$mod->ItemTable} I ON B.item_id = I.item_id
WHERE B.item_id IN ({$fillers}?) AND B.slotstart <= ? AND (B.slotstart+B.slotlen) >= ?
ORDER BY
EOS;
		switch ($lfmt) {
		 case \Booker::LISTUS:
			$t = ($is_group) ? 'I.name,':'';
			$sql .= ' B.user,'.$t.'B.slotstart';
			break;
		 case \Booker::LISTRS:
			$sql .= ' B.user,B.slotstart';
			if ($is_group) $sql .= ',I.name';
			break;
		 case \Booker::LISTSR: //only for groups
			$sql .= ' I.name,B.slotstart,B.user';
			break;
//	 case \Booker::LISTSU:
		 default:
			$sql .= ' B.slotstart,B.user';
			if ($is_group) $sql .= ',I.name';
			break;
		}
		$args[] = $endstamp;
		$args[] = $startstamp;
		$utils = new Utils();
		return $utils->SafeGet($sql,$args);
	}
}
