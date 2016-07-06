<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Cache
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Cache
{
	private static $cache = NULL; //intra-request cache-cache object
	/**
	GetCache:
    @mod: reference to Booker-module object
	@storage: optional cache-type name, one (or more, ','-separated) of
		auto,shmop,apc,memcached,wincache,xcache,memcache,redis,database
		default = 'auto'
	@settings: optional array of general and cache-type-specific parameters,
		(e.g. see default array in this func)
		default empty
	Returns: cache-object (after creating it if not already done) or NULL
	*/
	public function GetCache(&$mod,$storage='auto',$settings=array())
	{
//		if($this->cache == NULL && isset($_SESSION['bkrcache']))
//			$this->cache = $_SESSION['bkrcache'];
		if($this->cache)
			return $this->cache;

		$config = cmsms()->GetConfig();
		$url = (empty($_SERVER['HTTPS'])) ? $config['root_url'] : $config['ssl_url'];

        $basedir = $mod->GetPreference('pref_uploadsdir');
		if(!$basedir)
			$basedir = $config['uploads_path'];

		$settings = array_merge(
			array(
/*				'memcache' => array(
					array('host'=>$url,'port'=>11211)
				),
				'memcached' => array(
					array('host'=>$url,'port'=>11211,'persist'=>1)
				),
*/
				'redis' => array(
					'host' => $url
				),
				'predis' => array(
					'host' => $url
				),
				'file' => array(
					'path' => $basedir
				),
				'database' => array(
					'table' => cms_db_prefix().'module_bkr_cache'
				)
			), $settings);

		if($storage)
			$storage = strtolower($storage);
		else
			$storage = 'auto';
		if(strpos($storage,'auto') !== FALSE)
			$storage = 'shmop,apc,wincache,xcache,redis,predis,file,database';
//			$storage = 'shmop,apc,memcached,wincache,xcache,memcache,redis,database';
		$path = __DIR__.DIRECTORY_SEPARATOR.'MultiCache'.DIRECTORY_SEPARATOR;
		require($path.'CacheInterface.php');
		require($path.'CacheBase.php');

		$types = explode(',',$storage);
		foreach($types as $one)
		{
			$one = trim($one);
			require($path.$one.'.php');
			$class = 'MultiCache\Cache_'.$one;
			if(!isset($settings[$one]))
				$settings[$one] = array();
			try
			{
				$cache = new $class($settings[$one]);
			}
			catch(\Exception $e)
			{
				continue;
			}
//			$_SESSION['bkrcache'] = $cache;
			$this->cache = $cache;
			return $this->cache;
		}
		return NULL;
	}

	public function ClearCache()
	{
//		unset($_SESSION['bkrcache']);
		unset($this->cache);
		$this->cache = NULL;
	}

}
?>
