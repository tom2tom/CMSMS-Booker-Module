<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: bkritemops - functions for processing resources and groups
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

class bkritemops
{
	/*
	ClearBookings:
	Consequential stuff when deleting resource/group @item_id
	@mod: reference to current module-object
	@item_id: identifier of item to be processed
	*/
	private function ClearItem(&$mod,&$shares,$item_id)
	{
		$db = $mod->dbHandle;
		//resource-localize 'now'
		$idata = $shares->GetItemProperty($mod,$item_id,'timezone');
		$limit = $shares->GetZoneTime($idata['timezone']);
		$sql = 'SELECT * FROM '.$mod->DataTable.' WHERE item_id=? AND slotstart>=?';
		$rows = $shares->SafeGet($sql,array($item_id,$limit));
		if($rows)
		{
			foreach($rows as $one)
			{
			//TODO divert resource booking if possible & notify user
			}
		}
		if($shares->GetInterval($mod,$item_id,'keep') > 0) //past data still needed
		{
			$sql = array(
				'DELETE FROM '.$mod->DataTable.' WHERE item_id=? AND slotstart>=?',
				'UPDATE '.$mod->DataTable.' SET status='.Booker::STATGONE.' WHERE item_id=? AND slotstart<?',
				'UPDATE '.$mod->ItemTable.' SET active=-1 WHERE item_id=?'
			);
			$args = array(
				array($item_id,$limit),
				array($item_id,$limit),
				array($item_id)
			);
		}
		else
		{
			$sql = array(
				'DELETE FROM '.$mod->DataTable.' WHERE item_id=?',
				'DELETE FROM '.$mod->ItemTable.' WHERE item_id=?'
			);
			$args = array(
				array($item_id),
				array($item_id)
			);
		}
		$shares->SafeExec($sql,$args);
	}

	/*
	ClearRecursive:
	@mod reference to current module-object
	@shares reference to bkrshared-object
	@gid identifier of group to be cleared
	*/
	private function ClearRecursive(&$mod,&$shares,$gid)
	{
		$db = $mod->dbHandle;
		$members = $db->GetCol('SELECT child FROM '.$mod->GroupTable.' WHERE parent=? ORDER BY proximity DESC',array($gid));
		if($members)
		{
			foreach($members as $mid)
			{
				if($mid >= Booker::MINGRPID)
				{
					$idata = $shares->GetItemProperty($mod,$mid,'cleargroup');
					if(!empty($idata['cleargroup']))
						self::ClearRecursive($mod,$shares,$mid); //recurse
					else
						self::ClearItem($mod,$shares,$mid); //just clear the group itself
				}
				else
					self::ClearItem($mod,$shares,$mid);

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
	public function DeleteItem(&$mod,$item_id)
	{
		if(!is_array($item_id))
			$item_id = array($item_id);
		$funcs = new bkrshared();
		foreach($item_id as $one)
		{
			if($one >= Booker::MINGRPID)
			{
				$idata = $funcs->GetItemProperty($mod,$one,'cleargroup');
				if(!empty($idata['cleargroup']))
					self::ClearRecursive($mod,$funcs,$one);
				else
					self::ClearItem($mod,$funcs,$one);
				$funcs->OrderGroups($mod,$db); //cleanup remaining ordinals
			}
			else
			{
				self::ClearItem($mod,$funcs,$one);
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
/*	public function ExportItem(&$mod,$item_id,$members=TRUE)
	{
		$funcs = new bkrcsv();
		if(!is_array($item_id))
			$item_id = array($item_id);
		foreach($item_id as $one)
		{
//TODO merge multi-items into a single exported file
			list($res,$key) = $funcs->ExportItems($mod,$one);
//			if(!$res)
			{
//			handle error TODO
			}
			if($members)
			{
				$onemore = array(); //lazy recursion!!
				while($one >= Booker::MINGRPID)
				{
					$rows = $mod->dbHandle->GetCol('SELECT child FROM '.$mod->GroupTable.
					' WHERE parent=? ORDER BY proximity',array($one));
					foreach($rows as $one)
					{
						if($one >= Booker::MINGRPID)
							$onemore[] = $one;
						list($res,$key) = $funcs->ExportItems($mod,$one);
						if(!$res)
						{
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
	public function ToggleItemActive(&$mod,$item_id)
	{
		$qm = array();
		$args = array();
		if(!is_array($item_id))
		{
			$args[] = (int)$item_id;
			$qm[] = '?';
		}
		else
		{
			foreach($item_id as $one)
			{
				$args = (int)$one;
				$qm[] = '?';
			}
		}
		$seps = implode(',',$qm);
		$sql = 'SELECT COUNT(1) AS num FROM '.$mod->ItemTable.' WHERE item_id IN ('.$seps.') AND active=0';
		$inact = $db->GetOne($sql,$args);
		$newstate = ($inact === FALSE || (int)$inact !== 0) ? '1':'0';
		$sql = 'UPDATE '.$mod->ItemTable.' SET active='.$newstate.' WHERE item_id IN ('.$seps.')';
		$db->Execute($sql,$args);
	}
}
?>
