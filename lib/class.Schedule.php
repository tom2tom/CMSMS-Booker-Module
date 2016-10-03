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

	/*
	Get value with status-flags for a slot
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@session_id: identifier for cache-interrogation
	@item_id: resource (not group) identifier
	@bs: UTC timestamp for slot start
	@be: ditto for end (i.e. NOT 1-past-end)
	flags:
	 b0: set if slot is 'busy' i.e. being considered for booking by another thread
	 b1: set if slot is NOT available
	 b2: set if slot is [fully] booked
	 b3: set if slot is requested (unused here)
	Returns: integer with flags set as appropriate
	*/
	private function GetSlotStatus(&$mod, &$utils, $session_id, $item_id, $bs, $be)
	{
/*		$cache = Booker\Cache::GetCache($mod);
		if ($cache && )	//$cache->
*/
		if (0) { //TODO slot-status is cached
			//TODO get cached data
			$slotstatus = $X;
		} else {
			$slotstatus = 0;
			$bookerid = 0; //TODO booker identifier
			if (!self::ItemAvailable($mod,$utils,$item_id,$bookerid,$bs,$be)) {
				$slotstatus += 2;
			}
			if (self::ItemVacantCount($mod,$item_id,$bs,$be) == 0) {
				//TODO support forced-update i.e. ignore current booking
				$slotstatus += 4;
			}
/*			if (0) //slot is requested
				$slotstatus += 8;
				cache $slotstatus | 1 //look busy to others ALL SLOTS COVERED BY INTERVAL
				$cache->
*/
		}
		return $slotstatus;
	}

	//Try to match displayclass with an existing booker, or else return default class 1
	//c.f. Userops::GetDisplayClass()
	private function GetDisplayClass(&$mod, &$utils, $item_id, $user)
	{
		$sql = 'SELECT displayclass FROM '.$mod->BookerTable.' WHERE name=? OR publicid=?';
		$r = $mod->dbHandle->GetOne($sql,array($user,$user));
		if ($r)
			return (int)$r;
		return 1; //default
	}

	/*
	Get earliest-start and latest-end in $reqdata array, update 'slotlen' if missing
	*/
	private function RequestBounds(&$reqdata, $deflen)
	{
		$min = PHP_INT_MAX;
		$max = ~PHP_INT_MAX; //aka PHP_INT_MIN
		foreach ($reqdata as &$one) {
			$ss = (int)$one['slotstart'];
			if ($ss < $min)
				$min = $ss;
			if (empty($one['slotlen']))
				$one['slotlen'] = $deflen;
			$se = $ss + $one['slotlen'] - 1; //last second of the booking
			if ($se > $max)
				$max = $se;
		}
		unset($one);
		return array($min,$max);
	}

	/*
	ScheduleOne:
	Process a request for a single resource
	Updates DataTable and/or request status-flag, upstream must respond to the latter.
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@reqdata: reference to array of data from, or equivalent to [some of], a HistoryTable row
	@item_id: resource-identifier
	@bulk_id: repeat- or group-booking identifier, or 0
	@session_id: identifier for cache-interrogation
	@is_repeat: boolean whether this is for a repeat-booking
	Returns: boolean indicating success
	*/
	private function ScheduleOne(&$mod, &$utils, &$reqdata, $item_id, $bulk_id, $session_id, $is_repeat)
	{
		$idata = $utils->GetItemProperty($mod,$item_id,array('leadtype','leadcount'),TRUE);
		$idata = $idata + $utils->GetItemProperty($mod,$item_id,array('slottype','slotcount'),TRUE);
		$idata = $idata + $utils->GetItemProperty($mod,$item_id,array('bookcount','timezone'));
		$bs = $reqdata['slotstart'];
		$slen = $utils->GetInterval($mod,$item_id,'slot');
		if (empty($reqdata['slotlen']))
			$reqdata['slotlen'] = $slen;
		list($bs,$be) = $utils->TuneBlock($idata['slottype'],$idata['slotcount'],$bs,$bs + $reqdata['slotlen']);
		if (!$is_repeat) {
			//limit for advance-bookings?
			$limit = $utils->GetInterval($mod,$item_id,'lead',0);
			if ($limit > 0) {
				$limit += $utils->GetZoneTime('UTC'); //relative to now, not lodged-time
				if ($bs > $limit) { //too far ahead
					$reqdata['status'] = \Booker::STATDEFER;
					return FALSE;
				}
			}
		}
		$maxlen = $idata['bookcount'] * $slen;
		if ($maxlen > 0 && $be > $bs + $maxlen) {
			$reqdata['status'] = \Booker::STATBIG;
			return FALSE;
		}

		$bookerid = (int)$reqdata['booker_id'];
		//TODO use cache that's public
		$slotstatus = self::GetSlotStatus($mod,$utils,$session_id,$item_id,$bs,$be);
		$cando = (($slotstatus & 7) == 0); //slot not busy, and available, and not booked
		if ($cando) {
//TODO signature(s) for actual slot(s), not booking interval, in case we're doing a group member
			$sig = $item_id.$bs.$be; //signature = string form of long number
			if (!in_array($sig,$this->slotsdone)) {
				$this->slotsdone[] = $sig;
				//record booking
				$bkgid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
				$pay = !empty($reqdata['paid']);
				$stat = \Booker::STATOK; //TODO or STATNOTPAID etc
				$args = array(
					$bkgid,
					$bulk_id,
					$item_id,
					$bs, //slotstart
					$be-$bs, //slotlen
					$bookerid,
					$stat,
					$pay
				);
				$sql = 'INSERT INTO '.$mod->DataTable.
' (bkg_id,bulk_id,item_id,slotstart,slotlen,booker_id,status,paid) VALUES (?,?,?,?,?,?,?,?)';
				if ($utils->SafeExec($sql,$args)) {
					if ($reqdata['subgrpcount'] == 1) { //if we're doing a 1-item group, revert to specific resource
						$reqdata['item_id'] = $item_id;
					}
					$reqdata['approved'] = $utils->GetZoneTime($idata['timezone']);
					$reqdata['slotstart'] = $bs;
					$reqdata['slotlen'] = $be-$bs;
					$reqdata['status'] = $stat;
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
	private function FindCluster(&$mod, &$utils, $session_id, $likes, $lcount, $num, $first, $bs, $be, $roll=FALSE)
	{
//		$cache = Booker\Cache::GetCache($mod);
		$c = $num;
		$i = $first;
		$ret = array();
		while (1) {
			$item_id = $likes[$i];
			$status = self::GetSlotStatus($mod,$utils,$session_id,$item_id,$bs,$be);
//TODO support forced update of repeats
			if (($status & 7) == 0) { //slot not busy, and available, and not booked
/*				$cache->
				TODO set cache-flag busy and/or booked?
*/
				$ret[] = $item_id;
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
	@bs: UTC timestamp for booking start
	@be: ditto for booking end (NOT 1-past)
	@rescount: no. of individual resources to be booked
	@alloctype: one of the ALLOC* constants, determines preferred initial allocation of subgroup
	@allocdata: reference to supporting data for some values of @alloctype
	Returns: array of resource id's, or FALSE
	*/
	private function ClusterPick(&$mod, &$utils, $session_id, $likes, $bs, $be,
		$rescount, $alloctype, &$allocdata)
	{
		$lcount = count($likes);
		if ($lcount == 1) {
			if ($rescount == 1 && (self::GetSlotStatus($mod,$utils,$session_id,$likes[0],$bs,$be) & 7) == 0)
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
			$ret = self::FindCluster($mod,$utils,$session_id,$likes,$lcount,$rescount,$first,$bs,$be,TRUE);
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

	/*
	ScheduleMulti:
	Register as many as possible of bookings in @reqdata, for resource-group @item_id.
	Updates DataTable and/or request(s) status-flag, upstream must respond to the latter.

	If a group-booking-request involves just 1 resource (i.e. it's not really a
	group booking), the request's item_id is replaced by the one for the resource
	actually used.

	A simple but quite effective algorithm called 'first-fit decreasing' is the
	primary	allocator. Requests are sorted in decreasing order of resource-count,
	then each request is placed into the first 'cluster' (sequence of sequential
	available resources) that the request fits into. Finally, any unplaced requests
	are allocated wherever a vacancy is found.
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@reqdata: reference to array of row(s) of HistoryTable data (or contructed
	and sufficiently like such rows)
	@item_id: group identifier
	@session_id: identifier for cache-interrogation
	@bulk_id: repeat- or group-booking identifier, or 0
	@is_repeat: boolean whether this is for a repeat-booking
	Returns: boolean indicating complete success. @reqdata contents will usually
	be modified e.g. status values
	*/
	private function ScheduleMulti(&$mod, &$utils, &$reqdata, $item_id, $bulk_id, $session_id, $is_repeat)
	{
		$likes = self::MembersLike($mod,$item_id);
		if (!$likes)
			return FALSE;

		$idata = $utils->GetItemProperty($mod,$item_id,array('leadtype','leadcount'),TRUE);
		$idata = $idata + $utils->GetItemProperty($mod,$item_id,array('slottype','slotcount'),TRUE);
		$idata = $idata + $utils->GetItemProperty($mod,$item_id,array('bookcount','timezone','subgrpalloc','subgrpdata'));
		$slen = $utils->GetInterval($mod,$item_id,'slot');
		$maxlen = $idata['bookcount'] * $slen;
		if (!$is_repeat) {
			//limit for advance-bookings?
			$limit = $utils->GetInterval($mod,$item_id,'lead',0);
			if ($limit > 0) {
				$limit += $utils->GetZoneTime('UTC'); //relative to now, not lodged-time
			}
		} else {
			$limit = 0;
		}
		$full = FALSE;
		$allocdata = $idata['subgrpdata']; //cache
		$ret = TRUE;

		foreach ($reqdata as &$one) { //process decreasing subgrpcount
			if ($one['subgrpcount'] < 2) {
				if (!$full) {
					//simpler processing for remaining single-resource requests
					$bs = (int)$one['slotstart'];
					if (empty($one['slotlen'])) {
						$one['slotlen'] = $slen;
					}
					list($bs,$be) = $utils->TuneBlock($idata['slottype'],$idata['slotcount'],$bs,$bs+$one['slotlen']);
					if ($limit > 0) {
						if ($bs > $limit) { //too far ahead
							$one['status'] = \Booker::STATDEFER;
							$ret = FALSE;
							continue;
						}
					} elseif ($maxlen > 0) {
						if ($be > $bs + $maxlen) {
							$one['status'] = \Booker::STATBIG;
							$ret = FALSE;
							continue;
						}
					}
					$items = self::ClusterPick($mod,$utils,$session_id,$likes,$bs,$be,1,$idata['subgrpalloc'],$allocdata);
					if ($items) {
						if (!self::ScheduleOne($mod,$utils,$one,$items[0],$bulk_id,$session_id,$is_repeat)) {
							$ret = FALSE;
						}
					} else {
						$full = TRUE;
						switch ($one['status']) {
						 case \Booker::STATNEW:
							$one['status'] = \Booker::STATRETRY;
							break;
						}
						$ret = FALSE;
					}
				} else {
					switch ($one['status']) {
					 case \Booker::STATNEW:
						$one['status'] = \Booker::STATRETRY;
						break;
					}
					$ret = FALSE;
				}
			} else { //requested 2+ resources
				$bs = (int)$one['slotstart'];
				if (empty($one['slotlen'])) {
					$one['slotlen'] = $slen;
				}
				list($bs,$be) = $utils->TuneBlock($idata['slottype'],$idata['slotcount'],$bs,$bs+$one['slotlen']);
				if (!$is_repeat) {
					if ($limit > 0 && $bs > $limit) { //too far ahead
						$one['status'] = \Booker::STATDEFER;
						$ret = FALSE;
						continue;
					}
				} elseif ($maxlen > 0) {
					if ($be > $bs + $maxlen) {
						$one['status'] = \Booker::STATBIG;
						$ret = FALSE;
						continue;
					}
				}
				$items = self::ClusterPick($mod,$utils,$session_id,$likes,$bs,$be,
					$one['subgrpcount'],$idata['subgrpalloc'],$allocdata);
				if ($items) {
					//record booking
					$allsql = array();
					$allargs = array();
					$sql = 'INSERT INTO '.$mod->DataTable.
' (bkg_id,bulk_id,item_id,slotstart,slotlen,booker_id,status,paid) VALUES (?,?,?,?,?,?,?,?)';
					$bookerid = (int)$one['booker_id'];
					$pay = !empty($one['paid']);
					$stat = \Booker::STATOK; //TODO or STATNOTPAID etc
					foreach ($items as $memberid) {
						//signature = string form of long numbers
						$this->slotsdone[] = $memberid.$bs.$be;

						$allsql[] = $sql;
						$bkgid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
						$allargs[] = array(
							$bkgid,
							$bulk_id,
							$memberid,
							$bs, //slotstart
							$be-$bs+1, //slotlen
							$bookerid,
							$stat,
							$pay
						);
					}

					if ($utils->SafeExec($allsql,$allargs)) {
						$one['approved'] = $utils->GetZoneTime($idata['timezone']);
						$one['slotstart'] = $bs;
						$one['slotlen'] = $be-$bs+1;
						$one['status'] = $stat;
					} else {
						$one['status'] = \Booker::STATERR; //system error? RETRY?
						$ret = FALSE;
					}
/*					if ($cache && $cache->
						//TODO update cached slot(s) status
*/
				} else {
					$one['status'] = \Booker::STATRETRY;
					$ret = FALSE;
				}
			}
		}
		unset($one);

		if ($allocdata != $idata['subgrpdata']) {
			$sql = 'UPDATE '.$mod->ItemTable.' SET subgrpdata=? WHERE item_id=?';
			$utils->SafeExec($sql,array($allocdata,$item_id));
		}
/*		if ($cache && $cache->
		TODO clear any cached slotstatus data for this session
*/
		return $ret;
	}

	/*
	RecordRepeats:
	Interpret relevant repeat-booking for one specified resource into specific
	bookings in DataTable
	@mod: reference to current Booker module
	@utils: reference to Utils object
	@reps: reference to WhenRules-class object
	@blocks: reference to Blocks-class object
	@rows: priority-ordered associative array of RepeatTable data, for an item and all its ancestors
	@bs: UTC timestamp for beginning of 1st day of period to be processed
	@be: stamp for 1-past-end of last day of period (i.e. start of next day)
	Returns: boolean indicating complete success, TRUE if nothing found
	*/
	private function RecordRepeats(&$mod, &$utils, &$reps, &$blocks, $rows, $bs, $be)
	{
		$ret = TRUE;
		$parmstore = array();

		foreach ($rows as $bkgid=>$row) {
			$item_id = (int)$row['item_id'];
			if (!isset($parmstore[$item_id])) {
				//get enough data for TimeParms()
				$idata = $utils->GetItemProperty($mod,$item_id,array('slottype','slotcount'),TRUE);
				$idata = $idata + $utils->GetItemProperty($mod,$item_id,array('timezone','latitude','longitude'));
				$parmstore[$item_id] = $reps->TimeParms($idata);
				$parmstore[$item_id]['slottype'] = $idata['slottype'];
				$parmstore[$item_id]['slotcount'] = $idata['slotcount'];
			}
			$timeparms = $parmstore[$item_id];
			if (!$row['subgrpcount']) {
				if ($item_id < \Booker::MINGRPID)
					$row['subgrpcount'] = 1;
				else
					$row['subgrpcount'] = count($utils->GetGroupItems($mod,$item_id));
			}
			$res = $reps->AllIntervals($row['formula'],$bs,$be,$timeparms);
			if ($res) {
				list($starts,$ends) = $res;
				//TODO diff against slots already processed
				$blocks->MergeBlocks($starts,$ends);
				$session_id = Cache::GetKey(\Booker::SESSIONKEY);//identifier for cached slotstatus data
				foreach ($starts as $i=>$st) {
					//data to mimic a request, some HistoryTable fields
					//recreate whole data array cuz downstream messes with it
					//CHECKME ok to bundle all requests at different times?
					list($st,$nd) = $utils->TuneBlock($parmstore[$item_id]['slottype'],$parmstore[$item_id]['slotcount'],$st,$ends[$i]);
					$ps = ($row['paid']) ? \Booker::STATPAID : \Booker::STATNOTPAID; //TODO if free-to-use
					$reqdata = array(
					 'subgrpcount'=>(int)$row['subgrpcount'],
					 'booker_id'=>(int)$row['booker_id'],
					 'slotstart'=>$st,
					 'slotlen'=>$nd-$st,
					 'payment'=>$ps
					);
					if ($reqdata['subgrpcount'] < 2) {
						if (!self::ScheduleOne($mod,$utils,$reqdata,$item_id,$bkgid,$session_id,TRUE)) {
							$ret = FALSE;
						}
					} else {
						$reqdata = array($reqdata);
						if (!self::ScheduleMulti($mod,$utils,$reqdata,$item_id,$bkgid,$session_id,TRUE))
							$ret = FALSE;
					}
					//TODO modify stuff to reflect status values in $reqdata[]
				}
			}
		}
		return $ret;
	}

	/**
	ScheduleResource:
	Register as many as possible of bookings in @reqdata, for a single resource @item_id.
	The status field in each @reqdata member will be updated to indicate what
	precisely has been done, the subgroupcount field will be added if absent
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@item_id: item identifier
	@reqdata: reference to row of data from HistoryTable, or array of them
	Returns: boolean indicating complete success
	*/
	public function ScheduleResource(&$mod, &$utils, $item_id, &$reqdata)
	{
		$slen = $utils->GetInterval($mod,$item_id,'slot');
		if (is_array(reset($reqdata))) {
			list($bs,$be) = self::RequestBounds($reqdata,$slen);
			//sort reqdata on lodge-time
			usort($reqdata,function($a, $b)
			{
				$d = $a['lodged'] - $b['lodged'];
				return ($d != 0) ? $d : ($a['slotstart'] - $b['slotstart']);
			});
			$unarray = FALSE;
		} else {
			$bs = (int)$reqdata['slotstart'];
			if (empty($reqdata['slotlen']))
				$reqdata['slotlen'] = $slen;
			$be = $bs + $reqdata['slotlen'] - 1;
			$reqdata = array($reqdata);
			$unarray = TRUE;
		}
		self::UpdateRepeats($mod,$utils,$item_id,$bs,$be);

		$res = TRUE;
		$session_id = Cache::GetKey(\Booker::SESSIONKEY); //identifier for cached slotstatus data
		foreach ($reqdata as &$one) {
			$one['subgroupcount'] = 1; //force this
			if (!self::ScheduleOne($mod,$utils,$one,$item_id,0,$session_id,FALSE)) {
				$res = FALSE;
			}
		}
		unset($one);
/*	$cache = Cache::GetCache($mod);
		TODO clear any cached PUBLIC slotstatus data for this session
*/
		if ($unarray)
			$reqdata = $reqdata[0];
		return $res;
	}

	/**
	ScheduleGroup:
	Register as many as possible of bookings in @reqdata, for resource-group @item_id.
	The status field in each @reqdata member will be updated to reflect what was done,
	the subgroupcount field will have been added or updated if empty on arrival.
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@item_id: group identifier
	@reqdata: reference to array of some/all data from a HistoryTable row, or array of them
	Returns: boolean indicating complete success. @reqdata contents may be modified
	*/
	public function ScheduleGroup(&$mod, &$utils, $item_id, &$reqdata)
	{
		$slen = $utils->GetInterval($mod,$item_id,'slot'); //assumes no need for resource-specific slotlength
		if (is_array(reset($reqdata))) {
			list($bs,$be) = self::RequestBounds($reqdata,$slen);
			$count = -1;
			foreach ($reqdata as &$one) {
				if (empty($one['subgrpcount'])) {
					if ($count == -1)
						$count = count($utils->GetGroupItems($mod,$item_id));
					$one['subgrpcount'] = $count;
				}
			}
			unset($one);
			//sort reqdata on resources-count DESC, lodge-time ASC (favours bigger bookings)
			usort($reqdata,function($a, $b)
			{
				$d = $a['subgrpcount'] - $b['subgrpcount'];
				if ($d != 0)
					return -$d;
				$d = $a['lodged'] - $b['lodged'];
				return ($d != 0) ? $d : ($a['slotstart'] - $b['slotstart']);
			});
			$unarray = FALSE;
		} else {
			if (empty($reqdata['subgrpcount'])) {
				$reqdata['subgrpcount'] = count($utils->GetGroupItems($mod,$item_id));
			}
			$bs = (int)$reqdata['slotstart'];
			if (empty($reqdata['slotlen']))
				$reqdata['slotlen'] = $slen;
			$be = $bs + $reqdata['slotlen'] - 1;
			$reqdata = array($reqdata);
			$unarray = TRUE;
		}

		self::UpdateRepeats($mod,$utils,$item_id,$bs,$be);

		$session_id = Cache::GetKey(\Booker::SESSIONKEY); //identifier for cached slotstatus data
		$res = self::ScheduleMulti($mod,$utils,$reqdata,$item_id,$item_id,$session_id,FALSE);
		if ($unarray)
			$reqdata = $reqdata[0];
		return $res;
	}

	/**
	UpdateRepeats:
	Interpret any relevant repeat-booking for the specified resource(s) into
	 specific bookings in bookings table, and do consequential stuff
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@item_id: resource or group identifier
	@bs: UTC timestamp for block start, not necesssarily a day-start
	@be: ditto for block end
	Returns: nothing
	*/
	public function UpdateRepeats(&$mod, &$utils, $item_id, $bs, $be)
	{
		$args = $utils->GetItemGroups($mod,$item_id);
		array_unshift($args, $item_id); //proximity-ordered for checking
		$fillers = str_repeat('?,',count($args)-1);
		//downstream uses bkg_id,formula,booker_id,subgrpcount,paid
		$sql = 'SELECT * FROM '.$mod->RepeatTable.
		' WHERE item_id IN ('.$fillers.'?) AND active=1 ORDER BY item_id,bkg_id DESC';
		$db = $mod->dbHandle;
		//TODO $utils->SafeGet()
		$all = $db->GetAssoc($sql,$args);
/*TODO	if ($item_id >= \Booker::MINGRPID)
			$all = self::MembersLike($mod,$item_id);
		else
			$all = array($item_id);
*/
		if ($all) {
			uasort($all,function($a, $b) use ($args)
			{
				$ta = $a['item_id'];
				$tb = $b['item_id'];
				if ($ta != $tb) {
					$ka = array_search($ta,$args);
					$kb = array_search($tb,$args);
					if ($ka != $kb) {
						return ($ka-$kb);
					}
				}
				return ($b['paid'] - $a['paid']); //paid-first
			});

			$sql = 'UPDATE '.$mod->RepeatsTable.' SET checkedfrom=?,checkedto=? WHERE bkg_id=?';

			list($bs,$be) = $utils->BlockDays($bs,$be);
			$reps = new WhenRules($mod);
			$blocks = new Blocks();
//TODO proper handling of inherited repeat-descriptors c.f. Payment::WhenRuledBlocks()
			foreach ($all as $bkg_id=>$one) { //TODO unnecessary repetition?
				$st = $one['checkedfrom'];
				$nd = $one['checkedto'];
				$force = FALSE;
				if ($st <= 0) { //nothing processed
					$st = $bs;
					$nd = $be;
				} elseif ($bs >= $st && $be <= $nd) { //whole interval already processed
					continue;
				} elseif ($bs < $st && $be <= $nd) { //extend the interval already processed
					$nd = $st;
					$st = $bs;
				} elseif ($be > $nd && $bs >= $st) {
					$nd = $be;
					$st = $nd;
				} else { //extend both earlier and later
					$st = $bs;
					$nd = $be;
					$force = TRUE;
				}

				if ($force) {
$this->Crash();//TODO overwrite/replace/supplement existing bookings
				}

				if (self::RecordRepeats($mod,$utils,$reps,$blocks,$all,$st,$nd)) {
					//log earliest- and last-interpreted dates
					$st = min($st,$one['checkedfrom']);
					$nd = max($nd,$one['checkedto']);
					$utils->SafeExec($sql,array($st,$nd,$bkg_id));
				} else {
//$this->Crash();
					//TODO handle failure other than nothing-to-do
				}
			}
		}
	}

	/**
	ItemAvailable:
	Determine whether the item represented by @item_id is available for	use	over
	all of @bs..@be inclusive. If there's no relevant availability-condition,
	the item is regarded as available.
	@mod reference to current module-object
	@utils: reference to Utils object
	@item_id: resource or group identifier
	@bookerid: booker identifier, or 0 for any booker
	@bs: UTC timestamp for start of interval
	@be: ditto for end (NOT 1-past-end)
	Returns: boolean for resource or entire group, ?? for part-available group
	*/
	public function ItemAvailable(&$mod, &$utils, $item_id, $bookerid, $bs, $be)
	{
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group) {
			//TODO get resources in group, check them all
/*			$all = $utils->GetGroupItems($mod,$item_id);
			$fillers = str_repeat('?,',count($all)-1);
			$sql = 'SELECT avl_id FROM '.$mod->AvailTable.' WHERE item_id IN('.$fillers.'?)';
			$args = $all;
*/
		}
		$idata = $utils->GetItemProperty($mod,$item_id,array('available','timezone','latitude','longitude'));
		if ($idata['available']) {
			//rest of data for TimeParms()
			$idata = $idata + $utils->GetItemProperty($mod,$item_id,array('slottype','slotcount'),TRUE);
			$funcs = new WhenRules($mod);
			$timeparms = $funcs->TimeParms($idata);
			list($starts,$ends) = $funcs->AllIntervals($idata['available'],$bs,$be+1,$timeparms); //proximal-rule-only, no ancestor-merging
			//TODO deal with e.g. multi-day blocks when slotlen is <day  - ignore periods around midnight
		} else {
			$starts = array($bs);
			$ends = array($be);
		}
		if ($is_group) {
			//TODO decide how to report on results
		}
		if ($starts) {
			if (0) //TODO anything left over
				return FALSE;
		}
		if ($bookerid > 0) {
			if (0) //TODO $bookerid is permitted to use the item
				return FALSE;
		}
		return TRUE;
	}

	/**
	ItemVacantCount:
	Determine whether, or how many members of, the item represented by @item_id is/are
	already booked during part or all of @bs..@be inclusive
	@mod reference to current module-object
	@item_id: resource or group identifier
	@bs: UTC timestamp for start of interval
	@be: ditto for end (NOT 1-past-end)
	@bkgid: optional booking id to be ignored during the check, default FALSE
	(a valid id when updating an existing booking, FALSE when inserting a new one)
	Returns: count of UNused item(s) i.e. 0=[fully] booked, 1=non-booked resource,
	 >0=part/un-booked group
	*/
	public function ItemVacantCount(&$mod, $item_id, $bs, $be, $bkgid=FALSE)
	{
		$utils = new Utils();
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group) {
			$all = $utils->GetGroupItems($mod,$item_id);
			$fillers = str_repeat('?,',count($all)-1);
			$sql = 'SELECT DISTINCT item_id FROM '.$mod->DataTable.' WHERE item_id IN('.$fillers.'?)';
			$args = $all;
		} else {
			$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE item_id=?';
			$args = array($item_id);
		}
		if ($bkgid) {
			$sql .= ' AND bgk_id!=?';
			$args[] = (int)$bkgid;
		}
		//ignore possible 1-sec overlaps
		$sql .= ' AND slotstart < ? AND (slotstart + slotlen) > ?';
		$args[] = $be;
		$args[] = $bs;
		if ($is_group) {
			$c = count($all);
			$used = $utils->SafeGet($sql,$args,'col');
			if ($used)
				$c -= count($used);
			return $c;
		} else {
			$used = $utils->SafeGet($sql,$args,'one');
			return ($used) ? 0:1;
		}
	}
}
