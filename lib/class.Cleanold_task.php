<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Cleanold_task handles system cron event - cleanup ...
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Cleanold_task implements \CmsRegularTask
{
	const MODNAME = 'Booker';

	public function get_name()
	{
		return get_class($this);
	}

	public function get_description()
	{
		$mod = \cms_utils::get_module(self::MODNAME);
		return $mod->Lang('task_cleanold');
	}

	public function test($time='')
	{
		$mod = \cms_utils::get_module(self::MODNAME);
		//TODO possible DispTable::displayed instead of Once/Repeat? 
		$sql = 'SELECT 1 AS gone FROM '.$mod->OnceTable.' WHERE active=0 UNION SELECT 1 AS gone FROM '.$mod->RepeatTable.' WHERE active=0';
		$res = $mod->dbHandle->GetOne($sql);
		return ($res != FALSE);
	}

	public function execute($time='')
	{
		$mod = \cms_utils::get_module(self::MODNAME);
		$sql = <<<EOS
SELECT O.bkg_id,I.item_id,I.timezone FROM $mod->OnceTable O
JOIN $mod->ItemTable I ON O.item_id=I.item_id
WHERE O.active=0
EOS;
		$rows = $mod->dbHandle->GetArray($sql);
		if ($rows) {
			$sql = 'DELETE FROM '.$mod->OnceTable.' WHERE bkg_id=';
			$utils = new Utils();
			foreach ($rows as $one) {
				$st = $utils->GetZoneTime($one['timezone']);
				$base = PHP_INT_MAX - 10000000; //TODO;
				$len = $utils->GetInterval($mod,$one['item_id'],'keep',0);
				if (0) { //$st > $base + $len) {
					$mod->dbHandle->Execute($sql.$one['bkg_id']);
				}
			}
		}
		//TODO also $mod->RepeatTable
		//TODO also consequent $mod->DispTable records
		return TRUE;
	}

	public function on_success($time='')
	{
	}

	public function on_failure($time='')
	{
	}
}
