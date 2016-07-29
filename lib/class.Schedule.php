<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Schedule - apply booking(s) to relevant resource(s)
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Schedule
{
	private $slotsdone = array(); //which slots we've filled

	//Compare arrays of request-data
	//for sorting on lodge-time ASC
	private function cmp_reqs($a, $b)
	{
		$d = $a['lodged'] - $b['lodged'];
		return ($d != 0) ? $d : ($a['slotstart'] - $b['slotstart']);
	}

	/*
	Get value with status-flags for a slot
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@session_id: identifier for cache-interrogation
	@item_id: resource (not group) identifier
	@dtstart: DateTime object populated for booking start
	@dtend: ditto for end (i.e. NOT 1-past-end)
	flags:
	 b0: set if slot is 'busy' i.e. being considered for booking by another thread
	 b1: set if slot is NOT available
	 b2: set if slot is booked
	 b3: set if slot is requested (unused here)
	Returns: integer with flags set as appropriate
	*/
	private function GetSlotStatus(&$mod, &$utils, $session_id, $item_id, $dtstart, $dtend)
	{
/*		$cache = Booker\Cache::GetCache($mod);
		if ($cache && )	//$cache->
*/
		if (0) { //TODO slot-status is cached
			//TODO get cached data
			$slotstatus = $X;
		} else {
			$slotstatus = 0;
			if (!self::ItemAvailable($mod,$utils,$item_id,$dtstart,$dtend))
				$slotstatus += 2;
			if (self::ItemBooked($mod,$item_id,$dtstart,$dtend))
				$slotstatus += 4;
//			if (0) //slot is requested
//				$slotstatus += 8;
//			cache $slotstatus | 1 //look busy to others ALL SLOTS COVERED BY INTERVAL
//			$cache->
		}
		return $slotstatus;
	}

	//Try to match displayclass with an existing booking
	private function MatchUserClass(&$mod, &$utils, $item_id, $user)
	{
		$sql = 'SELECT item_id,userlass FROM '.$mod->DataTable.' WHERE user=?';
		$rows = $utils->SafeGet($sql,array($user),'assoc');
		if ($rows) {
			if (isset($rows[$item_id]))
				return (int)$rows[$item_id];
			return (int)reset($rows);
		}
		return 0; //default to unknown
	}

	/*
	GetRepeats:
	Interpret any relevant repeat-booking for one specified resource into
	 specific bookings in bookings table
	This is low-level method, normally UpdateRepeats() would be more appropriate
	@mod: reference to current Booker module
	@utils: reference to Booker\Utils object
	@reps: reference to bkrrepeats object
	@idata: reference to array of item parameters, including at least
		'item_id','timezone','latitude','longitude'
	@dtstart: resource-local datetime object for beginning of 1st day of period to be processed
	@dtend: resource-local datetime object for beginning of DAY AFTER last day of period
	Returns: boolean indicating complete success
	*/
	private function GetRepeats(&$mod, &$utils, &$reps, &$idata, $dtstart, $dtend)
	{
		$ret = TRUE;
		$item_id = (int)$idata['item_id'];
		$sql = 'SELECT bkg_id,formula,user,contact,displayclass,subgrpcount,paid FROM '.
			$mod->RepeatTable.' WHERE item_id=? AND active=1';
		$rows = $utils->SafeGet($sql,array($item_id));
		if ($rows) {
			$times = array();
			$parms = array();
			$sunparms = $reps->SunParms($idata); //TODO extra arg current date/time
			foreach ($rows as &$one) {
				$utimes = $reps->AllIntervals($one['formula'],$dtstart,$dtend,$sunparms);
				if ($utimes) {
					$user = $one['user'];
					if (!array_key_exists($user,$times))
						$times[$user] = $utimes;
					else
						$times[$user] = $times[$user] + $utimes;
					if (!array_key_exists($user,$parms)) {
						$parms[$user] = array(
							(int)$one['displayclass'], //0
							trim($one['contact']), //1
							(int)$one['paid']); //2
					} else {
						if ($parms[$user][0] == 0); //keep first non-0 class
							$parms[$user][0] = (int)$one['displayclass'];
						if ($parms[$user][1] == FALSE); //keep first non-empty contact
							$parms[$user][1] = trim($one['contact']);
						if (!$one['paid'])
							$parms[$user][2] = 0; //part-paid reported as unpaid
					}
				}
			}
			unset($one);
			if ($times) {
				foreach ($times as $user=>$utimes) {
					$data = array(
					 'slotstart'=>0,
					 'slotlen'=>0,
					 'sender'=>$user,
					 'displayclass'=>$parms[$user][0],
					 'contact'=>$parms[$user][1],
					 'paid'=>$parms[$user][2]
					);
					//rationalise $times into valid pairs of times
					$c = count($utimes);
					$starts = array();
					$ends = array();
					for ($i=0; $i<$c; $i+=2) {
						$starts = $utimes[$i];
						$ends = $utimes[$i+1];
					}
					$reps->MergeBlocks($starts,$ends);
					$c = count($starts);

					$session_id = 0; //TODO $mod->dbHandle->GenID($some.'_seq'); //uid for cached slotstatus data
					for ($i=0; $i<$c; $i++) {
						$sb = $starts[$i];
						$data['slotstart'] = $sb;
						$data['slotlen'] = $ends[$i] - $sb + 1;
						if ($one['subgrpcount'] < 2) {
							if (!self::Schedule1($mod,$utils,$session_id,$item_id,$data))
								$ret = FALSE;
						} else {
							if (!self::ScheduleGroup($mod,$utils,$item_id,$data))
								$ret = FALSE;
						}
					}
				}
			}
		}
		return $ret;
	}

	/*
	Get latest-end in $reqdata, update repeat-bookings to that day
	*/
	private function UpdateRequestedRepeats(&$mod, $item_id, $slotlen, &$reqdata)
	{
		$max = -99;
		foreach ($reqdata as $one) {
			if (empty($reqdata['slotlen']))
				$reqdata['slotlen'] = $slotlen;
			$se = $reqdata['slotstart'] + $reqdata['slotlen'] - 1; //last second of the booking
			if ($se > $max)
				$max = $se;
		}
		$ndt = new \DateTime('1900-1-1',new \DateTimeZone('UTC'));
		$ndt->setTimestamp($max);
		$ndt->setTime(0,0,0);
		$ndt->modify('+1 day');
		self::UpdateRepeats($mod,$item_id,$ndt);
	}

	/*
	Process a request for a single resource
	Updates DataTable if possible, but not HistoryTable.
	The status field in @reqdata will be updated to indicate what precisely has been done
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@session_id: identifier for cache-interrogation
	@item_id: resource-identifier
	@reqdata: reference to one row of data from HistoryTable, or equivalent constructed array
	Returns: boolean indicating success
	*/
	private function Schedule1(&$mod, &$utils, $session_id, $item_id, &$reqdata)
	{
		$idata = $utils->GetItemProperty($mod,$item_id,array('bookcount','timezone'));
		$sb = $reqdata['slotstart'];
		$dts = new \DateTime('1900-1-1',new \DateTimeZone('UTC'));
		$dts->setTimestamp($sb);
		$sl = $utils->GetInterval($mod,$item_id,'slot');
		if (empty($reqdata['slotlen']))
			$reqdata['slotlen'] = $sl;
		$se = $sb + $reqdata['slotlen'] - 1; //last second of the booking
		$dte = clone $dts;
		$dte->setTimestamp($se);
		$utils->TrimRange($dts,$dte,$sl);
		$sb = $dts->getTimestamp();
		//limit for advance-bookings (relative to now, not lodged-time)
		$limit = $utils->GetZoneTime($idata['timezone']) + $utils->GetInterval($mod,$item_id,'lead');
		if ($sb > $limit) { //too far ahead
			$reqdata['status'] = \Booker::STATDEFER;
			return FALSE;
		}
		$se = $dte->getTimestamp();
		if ($se - $sb > $idata['bookcount'] * $sl) {
			$reqdata['status'] = \Booker::STATBIG;
			return FALSE;
		}

		//TODO use cache that's public
		$slotstatus = self::GetSlotStatus($mod,$utils,$session_id,$item_id,$dts,$dte);
		$cando = (($slotstatus & 7) == 0); //slot not busy, and available, and not booked
		if ($cando) {
			//TODO signature(s) for actual slot(s), not booking interval
			$sig = $item_id.$sb.$se; //signature = string form of long number
			if (!in_array($this->slotsdone,$sig)) { //TODO
				$this->slotsdone[] = $sig;
				//record booking
				$bid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
				$class = (!empty($reqdata['displayclass'])) ? $reqdata['displayclass'] :
					self::MatchUserClass($mod,$utils,$item_id,$reqdata['sender']);
				$status = \Booker::STATNONE; //TODO or STATNOTPAID etc
				$args = array(
					$bid,
					$item_id,
					$sb, //slotstart
					$se-$sb+1, //slotlen
					$reqdata['sender'], //assume is also user, change later if needed
					$reqdata['contact'],
					$class,
					$status,
					!empty($reqdata['paid'])
				);
				$sql = 'INSERT INTO '.$mod->DataTable.
' (bkg_id,item_id,slotstart,slotlen,user,contact,displayclass,status,paid) VALUES (?,?,?,?,?,?,?,?,?)';
				if ($utils->SafeExec($sql,$args)) {
					$reqdata['status'] = $status;
					$reqdata['approved'] = TRUE;
					return TRUE;
				} else {
					$reqdata['status'] = \Booker::STATERR; //system error? RETRY?
					return FALSE;
				}
			} else {
				$slotstatus = 4; //duplicated, cached locally
				$cando = FALSE;
			}
		}
		if (($slotstatus & 4) > 0)
			$status = \Booker::STATDUP;
		elseif (($slotstatus & 2) > 0)
			$status = \Booker::STATNA;
//		elseif (($slotstatus & 1) > 0)
//			$status = \Booker::STATRETRY;
		else
			$status = \Booker::STATRETRY;
		$reqdata['status'] = $status;
		return $cando;
	}

	//Compare arrays of request-data
	//for sorting on resources-count DESC, lodge-time ASC
	private function cmp_reqscount($a,$b)
	{
		$d = $a['subgrpcount'] - $b['subgrpcount'];
		if ($d != 0)
			return -$d;
		$d = $a['lodged'] - $b['lodged'];
		return ($d != 0) ? $d : ($a['slotstart'] - $b['slotstart']);
	}

	/*
	MembersLike:
	Get array of id's of resources in group @gid, sorted on 'likeness'
	@mod: reference to current module-object
	@gid: identifier of group to be interrogated (non-groups are ignored)
	Returns: array of item-ids from breadth-first scan, likeness-ordered
	*/
	private function MembersLike(&$mod, $gid, $down=0)
	{
		$ids = array();
		$db = $mod->dbHandle;
		$members = $db->GetCol('SELECT DISTINCT child FROM '.$mod->GroupTable.
			' WHERE parent=? ORDER BY likeorder',array($gid));
		if ($members) {
			foreach ($members as $mid) {
				if ($mid < \Booker::MINGRPID)
					$ids[] = (int)$mid;
			}
			foreach ($members as $mid) {
				if ($mid >= \Booker::MINGRPID) {
					$downers = self::MembersLike($mod,$mid,$down+1); //recurse
					if ($downers)
						$ids = array_merge($ids,$downers);
				}
			}
		}

		if ($down == 0) {
			if (count($ids) > 1)
				$ids = array_unique($ids);
		}
		return $ids;
	}

	/*
	If possible, select @num adjacent bookable items from @likes (which has @lcount
	members), scanning from index @first, with rollaround to start if optional @roll = TRUE
	*/
	private function FindCluster(&$mod, $likes, $lcount, $num, $first, $dts, $dte, $session_id, $roll=FALSE)
	{
//		$cache = Booker\Cache::GetCache($mod);
		$c = $num;
		$i = $first;
		$ret = array();
		while (1) {
			$id = $likes[$i];
			if ((self::GetSlotStatus($mod,$utils,$session_id,$id,$dts,$dte) & 7) == 0) { //item can be booked
/*				$cache->
				TODO set cache-flag busy and/or booked?
*/
				$ret[] = $id;
				if (--$c == 0)
					return $ret;
			} elseif (isset($ret[0])) { //>0 array-members
				 //fresh start
				$c = $num;
				$ret = array();
			}

			if (++$i == $lcount) {
				if ($roll)
					$i = 0;
				else
					return FALSE;
			}
			if ($i == $first)
				return FALSE;
		}
	}

	/*
	ClusterPick:
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@session_id: identifier for cache-interrogation
	@likes: likeness-sorted array of resource identifiers, from which specific
		resources are to be selected
	@dts: DateTime object populated for booking start
	@dte: ditto for booking end (NOT 1-past)
	@rescount: no. of individual resources to be booked
	@alloctype: one of the ALLOC* constants, determines preferred initial allocation of subgroup
	@allocdata: reference to supporting data for some values of @alloctype
	Returns: array of resource id's, or FALSE
	*/
	private function ClusterPick(&$mod, &$utils, $session_id, $likes,
		$dts, $dte, $rescount, $alloctype, &$allocdata)
	{
		$lcount = count($likes);
		if ($lcount == 1) {
			if ($rescount == 1 && (self::GetSlotStatus($mod,$utils,$session_id,$likes[0],$dts,$dte) & 7) == 0)
				return $likes;
			return FALSE;
		}
		//determine preferred starting-index
		$countback = FALSE;
		if ($rescount > 1) {
			$part = $lcount % $rescount;
			$blocks = (int)($lcount / $rescount);
			if ($part > 0) {
				$blocks++;
				$countback = TRUE;
			}
		} else {
			$blocks = $rescount;
		}

		switch ($alloctype) {
		 case \Booker::ALLOCCHOOSE:
			$F = $allocdata;
			break;
		 case \Booker::ALLOCRAND:
			$R = mt_rand(0,$blocks-1);
			if ($countback && $R == $blocks-1)
				$F = $lcount - $rescount + 1;
			else
				$F = $R * $rescount;
			break;
		 case \Booker::ALLOCROTE:
			$indx = $allocdata;
			$R = (int)($indx % $blocks);
			if ($countback && $R == $blocks-1)
				$F = $lcount - $rescount + 1;
			else
				$F = $R * $rescount;
			break;
//		 case \Booker::ALLOCFIRST:
//		 case \Booker::ALLOCNONE:
		 default:
			$F = 0;
			break;
		}
		$first = $F;

		while (1) {
			$ret = self::FindCluster($mod,$likes,$lcount,$rescount,$first,$dts,$dte);
			if ($ret) {
				if ($alloctype == \Booker::ALLOCROTE)
					$allocdata += $rescount; //CHECKME or ++?
				return $ret;
			}
			$first += $rescount;
			if ($first >= $lcount)
				$first -= $lcount;
			if ($first >= $F && $first < $F+$rescount) //slop
				return FALSE;
		}
	}

	/**
	ScheduleResource:
	Register as many as possible of bookings in @reqdata, for a single resource @item_id.
	The status field in each @reqdata member will be updated to indicate what
	precisely has been done
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@item_id: item identifier
	@reqdata: reference to row of data from HistoryTable, or array of them
	Returns: boolean indicating complete success
	*/
	public function ScheduleResource(&$mod, &$utils, $item_id, &$reqdata)
	{
		if (is_array($reqdata)) {
			if (count($reqdata) > 1)
				usort($reqdata,array($this,'cmp_reqs')); //sort by lodge-time
		} else
			$reqdata = array($reqdata);

		$sl = $utils->GetInterval($mod,$item_id,'slot');
		self::UpdateRequestedRepeats($mod,$item_id,$sl,$reqdata);

		$ret = TRUE;
		$session_id = 0; //TODO $mod->dbHandle->GenID($some.'_seq'); //uid for cached slotstatus data
		foreach ($reqdata as &$one) {
			if (!self::Schedule1($mod,$utils,$session_id,$item_id,$one))
				$ret = FALSE;
		}
		unset($one);
/*	$cache = Booker\Cache::GetCache($mod);
		TODO clear any cached PUBLIC slotstatus data for this session
*/
		return $ret;
	}

	/**
	ScheduleGroup:
	Register as many as possible of bookings in @reqdata, for resource-group @item_id.
	The status field in each @reqdata member will be updated to reflect what was done.
	A simple but quite effective algorithm called 'first-fit decreasing' is the
	primary	allocator. Requests are sorted in decreasing order of resource-count,
	then each request is placed into the first 'cluster' (sequence of sequential
	available resources) that the request fits into. Finally, any unplaced requests
	are allocated wherever a vacancy is found.
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@item_id: group identifier
	@reqdata: reference to row of data from HistoryTable, or array of them
	Returns: boolean indicating complete success
	*/
	public function ScheduleGroup(&$mod, &$utils, $item_id, &$reqdata)
	{
		$likes = self::MembersLike($mod,$item_id);
		if (!$likes)
			return FALSE;

		if (is_array($reqdata)) {
			//sort reqdata on resources-count DESC, lodge-time ASC (favours bigger bookings)
			if (count($reqdata) > 1)
				usort($reqdata,array($this,'cmp_reqscount'));
		} else
			$reqdata = array($reqdata);

		$sl = $utils->GetInterval($mod,$item_id,'slot'); //assumes no need for resource-specific slotlength
		self::UpdateRequestedRepeats($mod,$item_id,sl,$reqdata);

		$idata = $utils->GetItemProperty($mod,array('bookcount','timezone','subgrpalloc','subgrpdata')); //get only what's needed
		$allocdata = $idata['subgrpdata']; //for local use
		//limit for advance-bookings (relative to now, not lodged-time)
		//assume no need for resource-specific leadtimes
		$limit = $utils->GetZoneTime($idata['timezone']) + $utils->GetInterval($mod,$item_id,'lead');
		$session_id = 0; //TODO $mod->dbHandle->GenID(cms_db_prefix().'module_bkrcache_seq'); //uid for cached slotstatus data
//	$cache = Booker\Cache::GetCache($mod);
		$sql = 'INSERT INTO '.$mod->DataTable.
' (bkg_id,item_id,slotstart,slotlen,user,contact,displayclass,status,paid) VALUES (?,?,?,?,?,?,?,?,?)';
		$ret = TRUE;

		foreach ($reqdata as &$one) { //process decreasing subgrpcount
			if ($one['subgrpcount'] < 2) {
				//simpler processing for remaining single-resource requests
				$full = FALSE;
				do {
					$items = ($full) ? FALSE :
						self::ClusterPick($mod,$utils,$session_id,$likes,$dts,$dte,1,$idata['subgrpalloc'],$allocdata);
					if ($items) {
						if (self::Schedule1($mod,$utils,$session_id,$items[0],$one))
							$ret = FALSE;
					} else {
						$full = TRUE;
						switch ($one['status']) {
						 case \Booker::STATNEW:
							$one['status'] = \Booker::STATRETRY;
							break;
						}
						$ret = FALSE;
					}
					$one = &next($reqdata);
				} while ($one !== FALSE);
				break;
			}

			$sb = $one['slotstart'];
			$dts = new \DateTime('1900-1-1',new \DateTimeZone('UTC'));
			$dts->setTimestamp($sb);
			if (empty($reqdata['slotlen']))
				$reqdata['slotlen'] = $sl;
			$se = $sb + $reqdata['slotlen'] - 1; //last second of the booking
			$dte = clone $dts;
			$dte->setTimestamp($se);
			$utils->TrimRange($dts,$dte,$sl);
			$sb = $dts->getTimestamp();
			if ($sb > $limit) { //too far ahead
				$one['status'] = \Booker::STATDEFER;
				$ret = FALSE;
				continue;
			}
			$se = $dte->getTimestamp();
			if ($se - $sb > $idata['bookcount'] * $sl) {
				$one['status'] = \Booker::STATBIG;
				$ret = FALSE;
				continue;
			}

			$items = self::ClusterPick($mod,$utils,$session_id,$likes,$dts,$dte,$one['subgrpcount'],$idata['subgrpalloc'],$allocdata);
			if ($items) {
				//record booking
				$allsql = array();
				$allargs = array();
				$bid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
				$class = (!empty($one['displayclass'])) ? $one['displayclass'] :
					self::MatchUserClass($mod,$utils,reset($items),$one['sender']);
				$status = \Booker::STATNONE; //TODO or STATNOTPAID etc
				foreach ($items as $memberid) {
					//TODO signature(s) for actual slot(s), not booking interval
					//signature = string form of long number
					$this->slotsdone[] = $memberid.$sb.$se;

					$allsql[] = $sql;
					$allargs[] = array(
						$bid,
						$memberid,
						$sb, //slotstart
						$se-$sb+1, //slotlen
						$one['sender'], //assume is also user, change later if needed
						$one['contact'],
						$class,
						$status,
						!empty($one['paid'])
					);
				}

				if ($utils->SafeExec($allsql,$allargs)) {
					$one['status'] = $status;
					$one['approved'] = TRUE;
				} else {
					$one['status'] = \Booker::STATERR; //system error? RETRY?
					$ret = FALSE;
				}
/*				if ($cache && $cache->
				//TODO update cached slot(s) status
*/
			} else {
				$one['status'] = \Booker::STATRETRY;
				$ret = FALSE;
			}
		}
		unset($one);
		if ($allocdata != $idata['subgrpdata']) {
			$mod->dbHandle->Execute(
				'UPDATE '.$mod->ItemTable.' SET subgrpdata=? WHERE item_id=? AND subgrpdata=?',
				array($allocdata,$item_id,$idata['subgrpdata']));
		}
/*		if ($cache && $cache->
		TODO clear any cached slotstatus data for this session
*/
		return $ret;
	}

	/**
	UpdateRepeats:
	Interpret any relevant repeat-booking for the specified resource(s) into
	 specific bookings in bookings table, and do consequential stuff
	@mod: reference to current Booker module
	@item_id: resource or group identifier
	@dtend: datetime object populated for beginning of DAY AFTER last day of
		update-period
	Returns: nothing
	*/
	public function UpdateRepeats(&$mod, $item_id, $dtend)
	{
		$dt = clone $dtend;
		$ndt = clone $dtend; //preserve upstream
		$ndt->modify('-1 day'); //beginning of last day of update-period
		$se = $ndt->getTimestamp();

		$db = $mod->dbHandle;
		$sql1 = 'SELECT repeatsuntil FROM '.$mod->ItemTable.' WHERE item_id=? AND active>0';
		$sql2 = 'UPDATE '.$mod->ItemTable.' SET repeatsuntil=? WHERE item_id=?';

		$utils = new Utils();
		$reps = new Repeats($mod);

		if ($item_id >= \Booker::MINGRPID)
			$all = self::MembersLike($mod,$item_id);
		else
			$all = array($item_id);
		foreach ($all as $one) {
			$stamp = $db->GetOne($sql1,array($one));
			//one day after last-processed = possible start of further interpretation
			if (!is_null($stamp)) {
				$dt->setTimestamp($stamp);
				$dt->setTime(0,0,0);
				$dt->modify('+1 day');
			} else {
				$dt->modify('midnight');
			}
			if ($dt < $ndt) {
				$idata = $utils->GetItemProperty($mod,$one,array('timezone','latitude','longitude'));
				$idata['item_id'] = $one;
				self::GetRepeats($mod,$utils,$reps,$idata,$dt,$ndt); //TODO don't ignore failure
				//log last-interpreted date
				$db->Execute($sql2,array($se,$one));
			}
		}
	}

	/**
	ItemAvailable:
	Determine whether the item represented by @item_id is available for	use
	over the whole time-interval @dtstart to @dtend inclusive
	@mod reference to current module-object
	@utils: reference to Booker\Utils object
	@item_id: resource or group identifier
	@dtstart: UTC DateTime object for start of range
	@dtend: ditto for end
	Returns: boolean
	*/
	public function ItemAvailable(&$mod, &$utils, $item_id, $dtstart, $dtend)
	{
		if ($item_id >= \Booker::MINGRPID);
		{
			//TODO decide how to interrogate & report on group-members
		}
		$idata = $utils->GetItemProperty($mod,$item_id,'*');
		if (empty($idata['available']))
			return TRUE;
		$funcs = new Repeats($mod);
		$sunparms = $funcs->SunParms($idata); //TODO extra arg current date/time
		$dtw = clone $dtend;
		$dtw->modify('+1 second'); //past the end
		$avail = $funcs->AllIntervals($idata['available'],$dtstart,$dtw,$sunparms);
		if ($avail) {
			$sb = $dtstart->getTimestamp();
			$se = $dtend->getTimestamp();
			$c = count($avail);
			for ($i=0; $i<$c; $i+=2) {
				if ($avail[$i] < $se && $avail[$i+1] > $sb) //ignore possible 1-sec overlaps
					return FALSE;
			}
		}
		return TRUE;
	}

	/**
	ItemBooked:
	Determine whether the item represented by @item_id is already booked during
	part or all of the period @dtstart to @dtend inclusive
	@mod reference to current module-object
	@item_id: resource or group identifier
	@dtstart: UTC DateTime object for start of range
	@dtend: ditto for end
	@bkg_id: optional booking id to be ignored during the check, default FALSE
	(a valid id when updating an existing booking, FALSE when inserting a new one)
	Returns: boolean
	*/
	public function ItemBooked(&$mod, $item_id, $dtstart, $dtend, $bkg_id=FALSE)
	{
		$utils = new Utils();
		if ($item_id >= \Booker::MINGRPID) {
			//TODO decide how to interrogate & report on group-members
		}
		$args = array($item_id);
		$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE item_id=?';
		if ($bkg_id) {
			$args[] = (int)$bkg_id;
			$sql .= ' AND bgk_id!=?';
		}
		$args[] = $dtend->getTimestamp();
		$args[] = $dtstart->getTimestamp();
		 //ignore possible 1-sec overlaps
		$sql .= ' AND slotstart < ? AND (slotstart + slotlen) > ?';
		$used = $utils->SafeGet($sql,$args,'one');
		return ($used != FALSE);
	}
}
