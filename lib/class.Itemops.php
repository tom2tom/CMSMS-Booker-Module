<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Itemops - functions for processing resources and groups
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Itemops
{
	/*
	ClearBookings:
	Consequential stuff when deleting resource/group @item_id
	@mod: reference to current module-object
	@item_id: identifier of item to be processed
	*/
	private function ClearItem(&$mod, &$utils, $item_id)
	{
		//TODO also process OnceTable
		$db = $mod->dbHandle;
		//resource-localize 'now'
		$idata = $utils->GetItemProperties($mod,$item_id,'timezone');
		$limit = $utils->GetZoneTime($idata['timezone']);
		$sql = 'SELECT * FROM '.$mod->DispTable.' WHERE item_id=? AND slotstart>=?';
		$rows = $utils->SafeGet($sql,array($item_id,$limit));
		if ($rows) {
			foreach ($rows as $one) {
			//TODO divert resource booking if possible & notify user
			}
		}
		if ($utils->GetInterval($mod,$item_id,'keep') > 0) { //past data still needed
			$sql = array(
				'DELETE FROM '.$mod->DispTable.' WHERE item_id=? AND slotstart>=?',
				'UPDATE '.$mod->DispTable.' SET status='.\Booker::STATGONE.' WHERE item_id=? AND slotstart<?',
				'UPDATE '.$mod->ItemTable.' SET active=-1 WHERE item_id=?'
			);
			$args = array(
				array($item_id,$limit),
				array($item_id,$limit),
				array($item_id)
			);
		} else {
			$sql = array(
				'DELETE FROM '.$mod->DispTable.' WHERE item_id=?',
				'DELETE FROM '.$mod->ItemTable.' WHERE item_id=?'
			);
			$args = array(
				array($item_id),
				array($item_id)
			);
		}
		$utils->SafeExec($sql,$args);
	}

	/*
	ClearRecursive:
	@mod: reference to current module-object
	@utils: reference to Booker\Utils-object
	@gid: identifier of group to be cleared
	*/
	private function ClearRecursive(&$mod, &$utils, $gid)
	{
		$db = $mod->dbHandle;
		$members = $db->GetCol('SELECT child FROM '.$mod->GroupTable.' WHERE parent=? ORDER BY likeorder DESC',array($gid));
		if ($members) {
			foreach ($members as $mid) {
				if ($mid >= \Booker::MINGRPID) {
					$idata = $utils->GetItemProperties($mod,$mid,'cleargroup');
					if (!empty($idata['cleargroup']))
						self::ClearRecursive($mod,$utils,$mid); //recurse
					else
						self::ClearItem($mod,$utils,$mid); //just clear the group itself
				} else
					self::ClearItem($mod,$utils,$mid);

			//TODO $utils->SafeExec()
				$db->Execute('DELETE FROM '.$mod->GroupTable.' WHERE child=? AND parent=?',array($mid,$gid));
			}
			unset($members);
		}
	}

	/**
	DeleteItem:
	@mod: reference to current module-object
	@item_id: identifier of resource or group to be cleared, or array of them
	*/
	public function DeleteItem(&$mod, $item_id)
	{
		if (!is_array($item_id))
			$item_id = array($item_id);
		$utils = new Utils();
		foreach ($item_id as $one) {
			if ($one >= \Booker::MINGRPID) {
				$idata = $utils->GetItemProperties($mod,$one,'cleargroup');
				if (!empty($idata['cleargroup']))
					self::ClearRecursive($mod,$utils,$one);
				else
					self::ClearItem($mod,$utils,$one);
				$utils->OrderGroups($mod); //cleanup remaining ordinals
			} else {
				self::ClearItem($mod,$utils,$one);
			}
		}
	}

	/* *
	ExportItem:
	Export properties of @item_id as .csv file (downloaded or in modfule uploads dir)
	@mod: reference to current module-object
	@item_id: identifier of resource or group to be processed, or array of them
	@members: optional boolean, whether to also export data for members of each group, default TRUE
	*/
/*	public function ExportItem(&$mod, $item_id, $members=TRUE)
	{
		$funcs = new Export();
		if (!is_array($item_id))
			$item_id = array($item_id);
		foreach ($item_id as $one) {
//TODO merge multi-items into a single exported file
			list($res,$key) = $funcs->ExportItems($mod,$one);
//			if (!$res) {
//			handle error TODO
//			}
			if ($members) {
				$onemore = array(); //lazy recursion!!
				while ($one >= \Booker::MINGRPID) {
					$rows = $mod->dbHandle->GetCol('SELECT child FROM '.$mod->GroupTable.
					' WHERE parent=? ORDER BY likeorder',array($one));
					foreach ($rows as $one) {
						if ($one >= \Booker::MINGRPID)
							$onemore[] = $one;
						list($res,$key) = $funcs->ExportItems($mod,$one);
						if (!$res) {
//						handle error TODO
						}
					}
					$one = array_shift($onemore); //NULL is ok
				}
			}
		}
	}
*/
	/**
	ToggleItemActive:
	Toggle activation-state of @item_id
	@mod: reference to current module-object
	@item_id: identifier of resource or group to be [de]activated, or array of them
	See also: Booker::_ActivateItem()
	*/
	public function ToggleItemActive(&$mod, $item_id)
	{
		$args = array();
		if (!is_array($item_id)) {
			$args[] = (int)$item_id;
			$fillers = '?';
		} else {
			foreach ($item_id as $one)
				$args[] = (int)$one;
			$fillers = str_repeat('?,',count($item_id)-1).'?';
		}
		$sql = 'SELECT COUNT(1) AS num FROM '.$mod->ItemTable.' WHERE item_id IN ('.$fillers.') AND active=0';
		$inact = $db->GetOne($sql,$args);
		$newstate = ($inact === FALSE || (int)$inact !== 0) ? '1':'0';
		$sql = 'UPDATE '.$mod->ItemTable.' SET active='.$newstate.' WHERE item_id IN ('.$fillers.') AND active<>2';
		//TODO $utils->SafeExec()
		$db->Execute($sql,$args);
	}
}
