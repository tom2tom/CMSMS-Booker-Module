<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Clearcache_task handles system cron event - cleanup old cache items
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Clearcache_task implements \CmsRegularTask
{
	const MODNAME = 'Booker';

	public function get_name()
	{
		return get_class($this);
	}

	public function get_description()
	{
		$mod = \cms_utils::get_module(self::MODNAME);
		return $mod->Lang('task_clearcache');
	}

	private function FileCacheDir(&$mod)
	{
		$config = \cmsms()->GetConfig();
		$dir = $config['uploads_path'];
		$rel = $mod->GetPreference('pref_uploadsdir');
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
		$mod = \cms_utils::get_module(self::MODNAME);
		$dir = $this->FileCacheDir($mod);
		if ($dir) {
			foreach (new DirectoryIterator($dir) as $fInfo) {
				$fn = $fInfo->getFilename();
				if (strpos($fn,\Booker::CARTKEY) === 0)
					return TRUE;
			}
		}
		//if file-cache N/A, check for database-cache
		$sql = 'SELECT cache_id FROM '.cms_db_prefix().'module_bkr_cache';
		$res = $mod->dbHandle->GetOne($sql);
		return ($res != FALSE);
	}

	public function execute($time='')
	{
		if (!$time)
			$time = time();
		$time -= 43200; //half-day cache retention-period (as seconds)
		$mod = \cms_utils::get_module(self::MODNAME);
		$dir = $this->FileCacheDir($mod);
		if ($dir) {
			foreach (new DirectoryIterator($dir) as $fInfo) {
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
		$sql = 'DELETE FROM '.cms_db_prefix().'module_bkr_cache WHERE savetime+lifetime <?';
		$mod->dbHandle->Execute($sql,array($time));
		return TRUE;
	}

	public function on_success($time='')
	{
	}

	public function on_failure($time='')
	{
	}
}
