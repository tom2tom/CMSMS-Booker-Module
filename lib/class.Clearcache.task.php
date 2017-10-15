<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: ClearcacheTask handles system cron event - cleanup old cache items
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

namespace Booker;

class ClearcacheTask implements \CmsRegularTask
{
	public function get_name()
	{
		return get_class();
	}

	protected function &get_module()
	{
		return \ModuleOperations::get_instance()->get_module_instance('Booker', '', TRUE);
	}

	public function get_description()
	{
		return $this->get_module()->Lang('taskdescription_clearcache');
	}

	public function test($time='')
	{
		if (!$time) {
			$time = time();
		}
		$last_cleared = $this->get_module()->GetPreference('lastclearcache');
		return ($time >= $last_cleared + 43200);
	}

	public function execute($time='')
	{
		if (!$time) {
			$time = time();
		}
		$funcs = new \Async\Cache();
		$cache = $funcs->Get();
		if ($cache instanceof \Async\MultiCache\FileCache) {
			$arr = glob($cache->basepath.'_cache_'.PWFBrowse::ASYNCSPACE.'*', GLOB_NOSORT);
			if ($arr) {
				$time -= 84600; //1-day cache retention-period (as seconds)
				clearstatcache();
				foreach ($arr as $fn) {
					$fp = $cache->basepath.$fn;
					if (filemtime($fp) < $time) {
						@unlink($fp);
					}
				}
			}
		} elseif ($cache instanceof \Async\MultiCache\DbaseCache) {
			$sql = 'DELETE FROM '.$cache->table.' WHERE keyword LIKE \'_cache_'.PWFBrowse::ASYNCSPACE.'%\' AND savetime+lifetime < '.$time;
			\cmsms()->GetDB()->Execute($sql);
		}
		//TODO maybe mutexes too e.g. flock files fopen files, db records
		$this->get_module()->SetPreference('lastclearcache', $time + 84600);
		return TRUE;
	}

	public function on_success($time='')
	{
	}

	public function on_failure($time='')
	{
	}
}
