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
		$sql = <<<EOS
SELECT D.*,B.name,B.publicid,B.address,B.phone,B.displayclass FROM {$mod->DataTable} D
JOIN {$mod->BookerTable} B ON D.booker_id=B.booker_id
WHERE D.bkg_id
EOS;
		if (is_array($bkg_id)) {
			return $mod->dbHandle->GetAssoc($sql.' IN ('.str_repeat('?,',count($bkg_id)-1).'?)',$bkg_id);
		} else
			return $mod->dbHandle->GetAssoc($sql.'=?',array($bkg_id));
	}

	/*
	MsgParms:
	Construct arguments for 'outward' message using MessageSender::Send()
	see also - Booker\Requestops::MsgParms
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@bdata: reference to array of booking data
	@idata: reference to array of booked-item data
	@mtype: enum MSGCHANGED or MSGCANCELLED
	@custommsg: text entered by user, to replace square-bracketed content of the message 'template'
	@extra: optional stuff for some types of message, default ''
	Returns: array or FALSE
	*/
	private function MsgParms(&$mod, &$utils, &$bdata, &$idata, $mtype, $custommsg, $extra='')
	{
		switch ($mtype) {
		 case self::MSGCHANGED:
			$ktitle = 'email_changed_title';
			$kbody1 = 'email_changed';
			$kbody2 = 'text_change';
		 	break;
		 case self::MSGCANCELLED:
			$ktitle = 'email_cancelled_title';
			$kbody1 = 'email_cancel';
			$kbody2 = 'text_cancel';
			break;
		 default:
		 	return FALSE; //error
		}

		$from = FALSE; //always use default sender
		$to = ($bdata['address']) ? array($bdata['name']=>$bdata['address']):$bdata['phone'];

		$what = ($bdata['subgrpcount'] > 1) ?
			sprintf('%d %s',$bdata['subgrpcount'],$idata['membersname']):
			$utils->GetItemName($mod,$idata);
		$dts = new \DateTime('@'.$bdata['slotstart'],new \DateTimeZone('UTC'));
		$on = $utils->IntervalFormat($mod,$dts,'D j M');
		if ($utils->GetInterval($mod,$idata['item_id'],'slot') >= 84600) {
			$detail = $mod->Lang('whatovrday',$what,$on);
		} else {
			$at = $dts->format('g:i A');
			$detail = $mod->Lang('whatonday',$what,$on,$at);
		}

		$msg = $mod->Lang($kbody1,$detail);
		$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
		$mailparms = array('subject'=>$mod->Lang($ktitle),'body'=>$msg);
		$msg = $mod->Lang($kbody2,$detail);
		$msg = preg_replace('/\[.*\]/',$custommsg,$msg);
		$textparms = array('prefix'=>$idata['smsprefix'],
			'pattern'=>$idata['smspattern'],'body'=>$msg);
		$tweetparms = array('body'=>$msg);
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
			$ob = \cms_utils::get_module('Notifier');
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
	@bkg_id: booking identifier, or array of them, or '*'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
	public function ExportBkg(&$mod, $bkg_id)
	{
		$funcs = new CSV();
		list($res,$key) = $funcs->ExportBookings($mod,FALSE,$bkg_id);
		if ($res)
			return array(TRUE,'');
		return array(FALSE,$mod->Lang($key));
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
			$ob = \cms_utils::get_module('Notifier');
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
		$sql = <<<EOS
SELECT R.*,B.name,B.address,B.phone FROM {$mod->RepeatTable} R
JOIN {$mod->BookerTable} B ON R.booker_id=B.booker_id
JOIN {$mod->DataTable} D ON R.booker_id=D.booker_id
WHERE D.bkg_id
EOS;
		if (is_array($bkg_id)) {
			$sql .= ' IN ('.str_repeat('?,',count($bkg_id)-1).'?)';
			$rows = $utils->SafeGet($sql,$bkg_id,'assoc');
		} else {
			$sql .= '=?';
			$rows = $utils->SafeGet($sql,array($bkg_id),'assoc');
		}

		if ($rows) {
			$ob = \cms_utils::get_module('Notifier');
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

	/**
	ConformBookingData:
	Conform tabled values: contact,displayclass and/or user according to @params values
	@mod: reference to current Booker module
	@params: reference to parameters array
	Returns: T/F indicating successful completion
	*/
	public function ConformBookingData(&$mod, &$params)
	{
		$utils = new Utils();
		$funcs = new Userops();
		$old = FALSE;
		$ret = TRUE;
		if (!empty($params['conformuser'])) {
			$old = $funcs->GetName($mod,$params['bkg_id']);
			if (!$old) return FALSE;
			$sql2 = '';
			$args = array();
			foreach (array('publicid','name') as $k) {
				if (!empty($params[$k])) {
					$sql2 .= $k.'=?';
					$args[] = trim($params[$k]);
				}
			}
			$args[] = $old;
			$sql = 'UPDATE '.$mod->BookerTable.' SET '.$sql2.' WHERE booker_id=?';
			if (!$utils->SafeExec($sql,$args)) {
				$ret = FALSE;
			} elseif(!(empty($params['publicid']) || empty($params['passwd']))) {
				$ret = $funcs->SetPassword($mod,$old,'FORCE',$params['passwd']);
			}
		}

		if (!empty($params['conformcontact'])) {
			if (!$old) {
				$old = $funcs->GetName($mod,$params['bkg_id']);
				if (!$old) return FALSE;
			}
			$ret = $ret && $funcs->SetContact($mod,$old,array(trim($params['address']),trim($params['phone'])));
		}

		if (!empty($params['conformstyle'])) {
			if (!$old) {
				$old = $funcs->GetName($mod,$params['bkg_id']);
				if (!$old) return FALSE;
			}
			$ret = $ret && $funcs->SetDisplayClass($mod,$old,(int)$params['displayclass']);
		}
		return $ret;
	}

	/**
	SaveBkg:
	Add to or update DataTable per data in @params
	@mod: reference to current Booker module
	@params: reference to parameters array
	@is_new: whether this is a new booking (hence add to table)
	Returns: boolean indicating successful completion
	*/
	public function SaveBkg(&$mod, &$params, $is_new)
	{
		if (empty($params['booker_id'])) {
	 		$funcs = new Userops();
			list($booker_id,$newbooker) = $funcs->GetParamsID($mod,$params);
			if ($booker_id === FALSE) {
				//TODO nicely handle bad password e.g. message
				sleep(2);
				return FALSE;
			}
		} else {
			$booker_id = (int)$params['booker_id']; //TODO upstream admin must supply this
		}

		if (isset($params['when'])) {
			$dt = new \DateTime($params['when'],new \DateTimeZone('UTC'));
			$t1 = $dt->getTimestamp();
			if (isset($params['until'])) {
				$dt->modify($params['until']);
				$t2 = $dt->getTimestamp() - $t1;
				if ($t2 < 120) $t2 = 120; //2-minutes min.
				$t2--; //TODO iff ends at any slot-start
			}
		}

		if ($is_new) {
			$sql2 = 'bkg_id,item_id,booker_id';
			$fillers = '?,?,?';
			$bid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
			$args = array(
				$bid,
				(int)$params['item_id'],
				$booker_id
			);
			foreach (array('when','until','paid') as $k) {
				if (isset($params[$k])) {
					switch ($k) {
					 case 'when':
						$args[] = $t1;
						$k = 'slotstart';
						break;
					 case 'until':
						$args[] = $t2;
						$k = 'slotlen';
						break;
					 case 'paid':
						$args[] = (int)$params[$k];
						break;
					}
					$sql2 .= ",$k";
					$fillers .= ',?';
				}
			}
			$sql = 'INSERT INTO '.$mod->DataTable.' ('.$sql2.') VALUES ('.$fillers.')';
		} else { //update
			self::ConformBookingData($mod,$params); //general update where needed
			$sql2 = array();
			$args = array();
			foreach (array('when','until','paid') as $k) {
				if (isset($params[$k])) {
					switch ($k) {
					 case 'when':
						$args[] = $params[$k];
						$k = 'slotstart';
						break;
					 case 'until':
						$args[] = $params[$k];
						$k = 'slotlen';
						break;
					 case 'paid':
						$args[] = (int)$params[$k];
						break;
					}
					$sql2[] = "$k=?";
				}
			}
			$args[] = (int)$params['bkg_id'];
			$sql = 'UPDATE '.$mod->DataTable.' SET '.implode(',',$sql2).' WHERE bkg_id=?';
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
SELECT D.*,B.name AS user,I.name FROM {$mod->DataTable} D
LEFT JOIN {$mod->BookerTable} B ON D.booker_id = B.booker_id
LEFT JOIN {$mod->ItemTable} I ON D.item_id = I.item_id
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
SELECT D.*,I.name FROM {$mod->DataTable} D
JOIN {$mod->ItemTable} I ON D.item_id = I.item_id
WHERE D.item_id IN ({$fillers}?) AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ?
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
SELECT D.slotstart,D.slotlen,B.name AS user,I.name FROM {$mod->DataTable} D
JOIN {$mod->BookerTable} B ON D.booker_id = B.booker_id
JOIN {$mod->ItemTable} I ON D.item_id = I.item_id
WHERE D.item_id IN ({$fillers}?) AND D.slotstart <= ? AND (D.slotstart+D.slotlen) >= ?
ORDER BY
EOS;
		switch ($lfmt) {
		 case \Booker::LISTUS:
			$t = ($is_group) ? 'I.name,':'';
			$sql .= ' B.name,'.$t.'D.slotstart';
			break;
		 case \Booker::LISTRS:
			$sql .= ' B.name,D.slotstart';
			if ($is_group) $sql .= ',I.name';
			break;
		 case \Booker::LISTSR: //only for groups
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
