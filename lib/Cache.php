<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Cache
# For driver comparisons, see e.g.
#  http://we-love-php.blogspot.com.au/2013/02/php-caching-shm-apc-memcache-mysql-file-cache.html
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
//		if(self::$cache == NULL && isset($_SESSION['bkrcache']))
//			self::$cache = $_SESSION['bkrcache'];
		if(self::$cache)
			return self::$cache;

		$path = __DIR__.DIRECTORY_SEPARATOR.'MultiCache'.DIRECTORY_SEPARATOR;
		require($path.'CacheInterface.php');
		require($path.'CacheBase.php');

		$config = cmsms()->GetConfig();
		$url = (empty($_SERVER['HTTPS'])) ? $config['root_url'] : $config['ssl_url'];

		$basedir = $config['uploads_path'];
		if(is_dir($basedir))
		{
			$rel = $mod->GetPreference('pref_uploadsdir');
			if($rel)
			{
				$basedir .= DIRECTORY_SEPARATOR.$rel;
			}
		}
		else
			$basedir = '';

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
					'host' => $url //TODO CHECKME
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
		{
			$storage = strtolower($storage);
			$storage = str_replace('apcu','apc',$storage); //same driver used
		}
		else
			$storage = 'auto';
		if(strpos($storage,'auto') !== FALSE)
			$storage = 'yac,apc,wincache,xcache,redis,predis,file,database';

		$types = explode(',',$storage);
		foreach($types as $one)
		{
			$one = trim($one);
			$class = 'MultiCache\Cache_'.$one;
			if(!isset($settings[$one]))
				$settings[$one] = array();
			try
			{
				require($path.$one.'.php');
				$cache = new $class($settings[$one]);
			}
			catch(\Exception $e)
			{
				continue;
			}
//			$_SESSION['bkrcache'] = $cache;
			self::$cache = $cache;
			return self::$cache;
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