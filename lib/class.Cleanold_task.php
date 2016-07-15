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
		$mod = cms_utils::get_module(self::MODNAME);
		return $mod->Lang('task_cleanold');
	}

	public function test($time='')
	{
/* TODO
		$mod = cms_utils::get_module(self::MODNAME);
		if (!($mod->GetPreference('logsends')
		  || $mod->GetPreference('logdeliveries')))
			return FALSE;
		$days = (int)$mod->GetPreference('logdays');
		if ($days <= 0)
			return FALSE;
		if (!$time)
			$time = time();
		$last_cleared = $mod->GetPreference('datalastclear',0);
		return ($time >= $last_cleared + $days*86400);
*/
		return FALSE;
	}

	public function execute($time='')
	{
		if (!$time)
			$time = time();
//TODO	smsg_utils::clean_log(NULL,$time);
		return TRUE;
	}

	public function on_success($time='')
	{
		if (!$time)
			$time = time();
		$mod = cms_utils::get_module(self::MODNAME);
		$mod->SetPreference('datalastclear',$time);
	}

	public function on_failure($time='')
	{
	}
}