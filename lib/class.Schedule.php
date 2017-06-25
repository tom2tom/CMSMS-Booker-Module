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
	//c.f. Userops->GetDisplayClass()
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
	Updates DispTable (not Once/RepeatTable), and some of @reqdata,
	 upstream must respond to the latter.
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@reqdata: reference to array of data from, or equivalent to [some of], a OnceTable row
	@item_id: resource-identifier
	@session_id: identifier for cache-interrogation
	@is_repeat: boolean whether this is for a repeat-booking
	Returns: boolean indicating success
	*/
	private function ScheduleOne(&$mod, &$utils, &$reqdata, $item_id, $session_id, $is_repeat)
	{
		$idata = $utils->GetItemProperties($mod,$item_id,
			array('slottype','slotcount','leadtype','leadcount','bookcount','timezone'),TRUE);
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

		//TODO use cache that's public
		$slotstatus = self::GetSlotStatus($mod,$utils,$session_id,$item_id,$bs,$be);
		$cando = (($slotstatus & 7) == 0); //slot not busy, and available, and not booked
		if ($cando) {
//TODO signature(s) for actual slot(s), not booking interval, in case we're doing a group member
			$sig = $item_id.$bs.$be; //signature = string form of long number
			if (!in_array($sig,$this->slotsdone)) {
				$this->slotsdone[] = $sig;
				//record booking
				$sql = <<<EOS
INSERT INTO $mod->DispTable (data_id,bkg_id,booker_id,item_id,slotstart,slotlen,bulk) VALUES (?,?,?,?,?,?,?)
EOS;
				$did = $mod->dbHandle->GenID($mod->DispTable.'_seq');
				$bulk = ($is_repeat) ? 20:0;
				$args = array(
					$did,
					(int)$reqdata['bkg_id'],
					(int)$reqdata['booker_id'],
					$item_id,
					$bs, //slotstart
					$be-$bs, //slotlen, 'used interval'
					$bulk
				);
				if ($utils->SafeExec($sql,$args)) {
					$reqdata['slotstart'] = $bs;
					$reqdata['slotlen'] = $be-$bs;
					$reqdata['status'] = \Booker::STATOK; //upsteam may adjust this
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
	Get array of id's of resources in group @gid, sorted on recorded 'likeness'
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
	Get indices in @imin..@imax in @likes, for at least @mincount and at most @prefcount
	adjacent bookable items
	Returns: indices array or FALSE
	*/
	private function BigCluster(&$mod, &$utils, $session_id, $likes, $imin, $imax, $mincount, $prefcount, &$ret, $bs, $be)
	{
		for ($i=$imin; $i<=$imax; $i++) {
			$c = 0;
			$found = array();
			while ($i<=$imax) {
				$item_id = $likes[$i];
				$status = self::GetSlotStatus($mod,$utils,$session_id,$item_id,$bs,$be);
				if (($status & 7) == 0) { //slot not busy, and available, and not booked
					$found[] = $i;
					if (++$c == $prefcount) {
						$ret = $found;
						return TRUE;
					}
					$i++;
				} else {
					$c = count($found);
					if ($c >= $mincount && $c > count($ret)) {
						$ret = $found;
					}
					break; //start again
				}
			}
		}
		return FALSE;
	}

	/*
	Get indices in @likes (which has @lcount members) for at least @mincount and
	at most @prefcount adjacent bookable items
	Returns: indices array or FALSE
	*/
	private function SizedCluster(&$mod, &$utils, $session_id, $likes, $lcount, $mincount, $prefcount, $up, $down, $bs, $be)
	{
		$e = $lcount - 1;
		$upfirst = ($e - $up) >= $down;
		if ($upfirst) {
			$imin = $up;
			$imax = $e;
		} else {
			$imin = 0;
			$imax = $down;
		}
		$ret = array(); //best-found
		if (self::BigCluster($mod,$utils,$session_id,$likes,$imin,$imax,$mincount,$prefcount,$ret,$bs,$be)) {
			return $ret;
		}
		if ($upfirst) {
			$imin = 0;
			$imax = $down;
		} else {
			$imin = $up;
			$imax = $e;
		}
		if (self::BigCluster($mod,$utils,$session_id,$likes,$imin,$imax,$mincount,$prefcount,$ret,$bs,$be)) {
			return $ret;
		}
		return FALSE;
	}

	/*
	Select up to @num bookable items from @likes (which has @lcount members),
	which items are proximal to indices @up and @down (which are 0..count($likes)-1)
	Returns: array of item id's (maybe with < $num members), or FALSE
	*/
	private function NearCluster(&$mod, &$utils, $session_id, $likes, $lcount, $num, $up, $down, $gapped, $bs, $be)
	{
		$e = $lcount - 1;
		$upnext = ($down == 0 || ($up < $e && mt_rand(0, 3) > 1));
		$ret = array();
		while (1) {
			$i = ($upnext) ? $up : $down;
			$item_id = $likes[$i];
			$status = self::GetSlotStatus($mod,$utils,$session_id,$item_id,$bs,$be);
			if (($status & 7) == 0) { //slot not busy, and available, and not booked
				$ret[] = $item_id;
				if (--$num == 0) {
					return $ret;
				}
			} elseif (!$gapped) {
				return $ret;
			}
			if ($upnext) {
				if (--$down >= 0) {
					$upnext = FALSE;
				} elseif (++$up > $e) {
					return $ret;
				}
			} else {
				if (++$up < $e) {
					$upnext = TRUE;
				} elseif (--$down < 0) {
					return $ret;
				}
			}
		}
		return FALSE;
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

		$first = TRUE;
		while (1) {
			$found = self::NearCluster($mod,$utils,$session_id,$likes,$lcount,$rescount,$F,$F,FALSE,$bs,$be);
			$fc = count($found);
			if ($fc < $rescount) {
				if ($fc > 0) {
					$up = min($lcount-1,end($found)+1);
					$down = max(0,reset($found)-1);
				} else {
					$up = min($lcount-1,$F+1);
					$down = max(0,$F-1);
				}
				if ($first) {
					//try for another cluster larger than $fc
					$xf = self::SizedCluster($mod,$utils,$session_id,$likes,$lcount,$fc+1,$rescount,$up,$down,$bs,$be);
					if ($xf) {
						$F = (int)((reset($xf) + end($xf)) / 2);
						$first = FALSE;
						continue; //start again
					}
				}
				$mc = $rescount - $fc;
				$xf = self::NearCluster($mod,$utils,$session_id,$likes,$lcount,$mc,$up,$down,TRUE,$bs,$be);
				if ($xf) {
					$found = array_merge($found,$xf); //duplicates filtered during sort
				}
			}
			if ($found) {
				//sort like $likes
				$ret = array();
				foreach ($found as $item_id) {
					$k = array_search($item_id,$likes);
					$ret[$k] = $item_id;
				}
				ksort($ret,SORT_NUMERIC);
				return $ret;
			} else {
				break;
			}
		}
		return FALSE;
	}

	/*
	ScheduleMulti:
	Register as many as possible of bookings in @reqdata, for resource-group @item_id.
	Updates DispTable (not OnceTable/RepeatTable) and probably some of @reqdata,
	upstream must respond to the latter and deal with other table(s).

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
	@reqdata: reference to array of row(s) of OnceTable data, or constructed-equivalent
	@item_id: group identifier
	@session_id: identifier for cache-interrogation
	@is_repeat: boolean whether this is for a repeat-booking
	Returns: boolean indicating complete success. @reqdata contents will usually
	be modified e.g. status values
	*/
	private function ScheduleMulti(&$mod, &$utils, &$reqdata, $item_id, $session_id, $is_repeat)
	{
		$likes = self::MembersLike($mod,$item_id);
		if (!$likes) {
			return FALSE;
		}

		$idata = $utils->GetItemProperties($mod,$item_id,
			array('slottype','slotcount','leadtype','leadcount','bookcount','timezone','subgrpalloc','subgrpdata'),TRUE);
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
						if (self::ScheduleOne($mod,$utils,$one,$items[0],$session_id,$is_repeat)) {
							$one['item_id'] = $items[0];
						} else {
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
					$bkgid = (int)$one['bkg_id'];
					$bookerid = (int)$one['booker_id'];
					if ($one['subgrpcount'] > 1) {
						$bulk = ($is_repeat) ? 21:1;
					} else {
						$bulk = ($is_repeat) ? 20:0;
					}
					$sql = <<<EOS
INSERT INTO $mod->DispTable (data_id,bkg_id,booker_id,item_id,slotstart,slotlen,bulk) VALUES (?,?,?,?,?,?,?)
EOS;
					foreach ($items as $memberid) {
						//signature = string form of long numbers
						$this->slotsdone[] = $memberid.$bs.$be;

						$allsql[] = $sql;
						$did = $mod->dbHandle->GenID($mod->DispTable.'_seq');
						$allargs[] = array(
							$did,
							$bkgid,
							$bookerid,
							$memberid,
							$bs, //slotstart
							$be-$bs, //slotlen, 'used interval'
							$bulk
						);
					}

					if ($utils->SafeExec($allsql,$allargs)) {
						$one['slotstart'] = $bs;
						$one['slotlen'] = $be-$bs;
						$one['status'] = \Booker::STATOK; //upstream may adjust this
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
	bookings in DispTable
	@mod: reference to current Booker module
	@utils: reference to Utils object
	@reps: reference to WhenRules-class object
	@blocks: reference to Blocks-class object
	@row: array, a row of RepeatTable data
	@bs: UTC timestamp for beginning of 1st day of period to be processed
	@be: stamp for end of last day of period
	Returns: boolean indicating complete success, TRUE if nothing found
	*/
	private function RecordRepeats(&$mod, &$utils, &$reps, &$blocks, $row, $bs, $be)
	{
		$item_id = (int)$row['item_id'];
		//get enough data for TimeParms()
		$idata = $utils->GetItemProperties($mod,$item_id,
			array('slottype','slotcount','timezone','latitude','longitude'),TRUE);
		$timeparms = $reps->TimeParms($idata);

		$ret = TRUE;
		$res = $reps->AllIntervals($row['formula'],$bs,$be,$timeparms);
		if ($res) {
			list($starts,$ends) = $res;
			//TODO support forced update of all data in interval
			//TODO diff against slots already processed
			$blocks->MergeBlocks($starts,$ends);
			if (!$row['subgrpcount']) {
				if ($item_id < \Booker::MINGRPID)
					$row['subgrpcount'] = 1;
				else
					$row['subgrpcount'] = count($utils->GetGroupItems($mod,$item_id));
			}
			$session_id = Cache::GetKey(\Booker::SESSIONKEY);//identifier for cached slotstatus data
			foreach ($starts as $i=>$st) {
				//data array to mimic a request, like some of a OnceTable row
				//recreate whole array inside loop cuz downstream messes with it
				list($st,$nd) = $utils->TuneBlock($idata['slottype'],$idata['slotcount'],$st,$ends[$i]);
				$reqdata = array(
				 'bkg_id'=>(int)$row['bkg_id'],
				 'booker_id'=>(int)$row['booker_id'],
				 'subgrpcount'=>(int)$row['subgrpcount'],
				 'slotstart'=>$st,
				 'slotlen'=>$nd-$st,
				);
				if ($reqdata['subgrpcount'] < 2) {
					if (!self::ScheduleOne($mod,$utils,$reqdata,$item_id,$session_id,TRUE)) {
						$ret = FALSE;
					}
				} else {
					$reqdata = array($reqdata);
					if (!self::ScheduleMulti($mod,$utils,$reqdata,$item_id,$session_id,TRUE)) {
						$ret = FALSE;
					}
				}
				//CHECKME modify RepeatTable stuff to reflect $reqdata['status'] value
				//upstream modifies checkedfrom, checkedto
			}
		}
		return $ret;
	}

	/**
	ScheduleResource:
	Register as many as possible of (onetime) bookings in @reqdata, for a single resource @item_id.
	The status field in each @reqdata member will be updated to indicate what
	precisely has been done, the subgrpcount field will be added if absent
	@mod: reference to current Booker module object
	@utils: reference to Booker\Utils object
	@item_id: item identifier
	@reqdata: reference to some/all data from a OnceTable row or constructed-equivalent, or array of them
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
			$one['subgrpcount'] = 1; //force this
			if (!self::ScheduleOne($mod,$utils,$one,$item_id,$session_id,FALSE)) {
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
	Register as many as possible of (onetime) bookings in @reqdata, for resource-group @item_id.
	The status field in each @reqdata member will be updated to reflect what was done,
	the subgrpcount field will have been added or updated if empty on arrival.
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@item_id: group identifier
	@reqdata: reference to array of some/all data from a OnceTable row or constructed-equivalent, or array of them
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
		$res = self::ScheduleMulti($mod,$utils,$reqdata,$item_id,$session_id,FALSE);
		if ($unarray)
			$reqdata = $reqdata[0];
		return $res;
	}

	/**
	UpdateRepeats:
	Interpret relevant repeat-booking(s) for the specified resource(s) into
	 specific bookings in DispTable, and do consequential stuff
	@mod: reference to current Booker module
	@utils: reference to Utils-class object
	@item: resource or group identifier (a.k.a. item_id), or array of them
	@bs: UTC timestamp for block start, not necesssarily a day-start
	@be: ditto for block end
	@force: optional boolean whether to ignore (hence refresh) existing bookings in @bs..@be, default FALSE
	Returns: nothing
	*/
	public function UpdateRepeats(&$mod, &$utils, $item, $bs, $be, $force=FALSE)
	{
		$db = $mod->dbHandle;
		//downstream uses bkg_id,formula,booker_id,subgrpcount,paid
		$sql = 'SELECT * FROM '.$mod->RepeatTable.' WHERE active=1 ORDER BY item_id,bkg_id DESC';
		//TODO $utils->SafeGet()
		$all = $db->GetArray($sql);
		if (!$all) {
			return;
		}

		if (!is_array($item)) {
			$item = array($item);
		}
		$processed = array();
		list($bs,$be) = $utils->BlockWholeDays($bs,$be);
		$whens = new WhenRules($mod);
		$blocks = new Blocks();

		foreach ($item as $item_id) {
			if (in_array($item_id,$processed)) {
				continue;
			}
			$ancestors = $utils->GetItemGroups($mod,$item_id);
			array_unshift($ancestors,$item_id); //proximity-ordered for checking
			foreach ($ancestors as $a_id) {
				if (in_array($a_id,$processed)) {
					continue;
				}
				usort($all,function($a, $b) use ($ancestors)
				{
					$ta = $a['item_id'];
					$tb = $b['item_id'];
					if ($ta != $tb) {
						$ka = array_search($ta,$ancestors);
						$kb = array_search($tb,$ancestors);
						if ($ka != $kb) {
							return ($ka-$kb);
						}
					}
					return ($b['statpay'] - $a['statpay']); //TODO paid-first, maybe compare ['status']
				});

				foreach ($all as &$row) {
					if (!in_array($row['item_id'],$ancestors)) {
						continue;
					}
					if ($row['item_id'] == $a_id) {
						//TODO handle $force == TRUE
						$force1 = $force;
						$st = $row['checkedfrom'];
						$nd = $row['checkedto'];
						if ($st == 0 || $st > $bs || $nd < $be) {
							//record extra slots in $bs..$be for $a_id and descendants
							if ($st <= 0) { //nothing processed
								$stc = $bs;
								$ndc = $be;
							} elseif ($bs >= $st && $be <= $nd) { //whole interval already processed
								continue;
							} elseif ($bs < $st && $be <= $nd) { //extend the interval already processed
								$ndc = $st;
								$stc = $bs;
							} elseif ($be > $nd && $bs >= $st) {
								$stc = $nd;
								$ndc = $be;
							} else { //extend both earlier and later
								$stc = $bs;
								$ndc = $be;
								$force1 = TRUE;
							}
							//TODO handle $force1 == TRUE
							$res = self::RecordRepeats($mod,$utils,$whens,$blocks,$row,$stc,$ndc);
							if ($res) {
								if ($st > 0) {
									$row['checkedfrom'] = min($st,$bs);
								} else {
									$row['checkedfrom'] = $bs;
								}
								$row['checkedto'] = max($nd,$be);
								$row['update'] = TRUE; //flag to update tabled checked* values later
/*								if (0) { //TODO all of $bs..$be now booked for $item_id
									unset ($row);
									$processed[] = $a_id;
									break 2;
								}
*/
							}
						}
					}
				}
				unset ($row);
				$processed[] = $a_id;
/*				if (0) { //TODO all of $bs..$be now booked for $item_id
					break;
				}
*/
			}
		}

		$sql = '';
		foreach ($all as &$row) {
			if (isset($row['update'])) {
				if ($sql == '') {
					$sql = 'UPDATE '.$mod->RepeatTable.' SET checkedfrom=?,checkedto=? WHERE bkg_id=?';
				}
				$db->Execute($sql,array($row['checkedfrom'],$row['checkedto'],$row['bkg_id']));
				//TODO $utils->SafeExec()
			}
		}
		unset($row);
	}

	/**
	ItemAvailable:
	Determine whether the item represented by @item_id is available for use over
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
		$idata = $utils->GetItemProperties($mod,$item_id,
			array('available','slottype','slotcount','timezone','latitude','longitude'),TRUE);
		if ($idata['available']) {
			$funcs = new WhenRules($mod);
			$timeparms = $funcs->TimeParms($idata);
			list($starts,$ends) = $funcs->AllIntervals($idata['available'],$bs,$be,$timeparms); //proximal-rule-only, no ancestor-merging
			//TODO deal with e.g. multi-day blocks when slotlen is <day  - ignore periods around midnight
		} else {
			$starts = array($bs);
			$ends = array($be);
		}
		if ($is_group) {
			//TODO decide how to report on results
		}
		if ($starts) {
			//check for mismatch
			if (count($starts) > 1 //some intermediate exclusion
			 || $bs != $starts[0]
			 || $be != $ends[0]) {
				return FALSE;
			}
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
			$sql = 'SELECT DISTINCT item_id FROM '.$mod->DispTable.' WHERE item_id IN('.$fillers.'?)';
			$args = $all;
		} else {
			$sql = 'SELECT bkg_id FROM '.$mod->DispTable.' WHERE item_id=?';
			$args = array($item_id);
		}
		if ($bkgid) {
			$sql .= ' AND bgk_id!=?';
			$args[] = (int)$bkgid;
		}
		$sql .= ' AND slotstart < ? AND (slotstart + slotlen) > ?';
		//ignore possible 1-sec overlaps
		$args[] = $be+1;
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

	/**
	CountBooked:
	Determine whether, or how many members of, the item represented by @item_id is/are
	already booked by @bkrid, during part or all of @bs..@be inclusive
	@mod reference to current module-object
	@item_id: resource or group identifier
	@bs: UTC timestamp for start of interval
	@be: ditto for end (NOT 1-past-end)
	@bkrid: optional booker id or array of them or FALSE for any booker, default FALSE
	Returns: associative array or FALSE
	*/
	public function CountBooked(&$mod, $item_id, $bs, $be, $bkrid=FALSE)
	{
		$utils = new Utils();
		$is_group = ($item_id >= \Booker::MINGRPID);
		if ($is_group) {
			$all = $utils->GetGroupItems($mod,$item_id);
			$fillers = str_repeat('?,',count($all)-1);
			$sql = 'SELECT bkg_id,booker_id,item_id FROM '.$mod->DispTable.' WHERE item_id IN('.$fillers.'?)';
			$args = $all;
		} else {
			$sql = 'SELECT bkg_id,booker_id,item_id FROM '.$mod->DispTable.' WHERE item_id=?';
			$args = array($item_id);
		}
		if (is_array($bkrid)) {
			$fillers = str_repeat('?,',count($bkrid)-1);
			$sql .= ' AND booker_id IN('.$fillers.'?)';
			$args = array_merge($args,$bkrid);
		} elseif ($bkrid) {
			$sql .= ' AND booker_id=?';
			$args[] = (int)$bkrid;
		}
		$sql .= ' AND slotstart < ? AND (slotstart + slotlen) > ? ORDER BY item_id,booker_id';
		//ignore possible 1-sec overlaps
		$args[] = $be+1;
		$args[] = $bs;
		return $utils->SafeGet($sql,$args);
	}
}
