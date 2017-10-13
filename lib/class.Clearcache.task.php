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

	private function FileCacheDir(&$mod)
	{
		$config = \cmsms()->GetConfig();
		$dir = $config['uploads_path'];
		$mod = $this->get_module();
		$rel = $mod->GetPreference('uploadsdir');
		if ($rel) {
			$dir .= DIRECTORY_SEPARATOR.$rel;
		}
		$dir .= DIRECTORY_SEPARATOR.'file_cache';
		if (is_dir($dir)) {
			return $dir;
		}
		return FALSE;
	}

	public function test($time='')
	{
		$mod = $this->get_module();
		$dir = $this->FileCacheDir($mod);
		if ($dir) {
			foreach (new \DirectoryIterator($dir) as $fInfo) {
				$fn = $fInfo->getFilename();
				if (strpos($fn,\Booker::CARTKEY) === 0)
					return TRUE;
			}
		}
		//if file-cache N/A, check for database-cache
		$pre = \cms_db_prefix();
		$sql = 'SELECT cache_id FROM '.$pre.'module_bkr_cache';
		$res = \cmsms()->GetDB()->GetOne($sql);
		return ($res != FALSE);
	}

	public function execute($time='')
	{
		if (!$time)
			$time = time();
		$time -= 43200; //half-day cache retention-period (as seconds)
		$mod = $this->get_module();
		$dir = $this->FileCacheDir($mod);
		if ($dir) {
			foreach (new \DirectoryIterator($dir) as $fInfo) {
				if ($fInfo->isFile() && !$fInfo->isDot()) {
					$fn = $fInfo->getFilename();
					if (strpos($fn,\Booker::CARTKEY) === 0) {
						$mtime = $fInfo->getMTime();
						if ($mtime < $time) {
							@unlink($dir.DIRECTORY_SEPARATOR.$fn);
						}
					}
				}
			}
		}
		$pre = \cms_db_prefix();
		$sql = 'DELETE FROM '.$pre.'module_bkr_cache WHERE savetime+lifetime <?';
		\cmsms()->GetDB()->Execute($sql,[$time]);
		return TRUE;
	}

	public function on_success($time='')
	{
	}

	public function on_failure($time='')
	{
	}
}
