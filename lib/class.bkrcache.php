<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: cache
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

class bkrcache
{
	private static $cache = NULL; //cache object
	/**
	GetCache:
	@storage: optional cache-type name, one (or more, ','-separated) of
		auto,shmop,apc,memcached,wincache,xcache,memcache,redis,database
		default = 'auto'
	@settings: optional array of general and cache-type-specific parameters,
		(e.g. see default array in this func)
		default empty
	Returns: cache-object (after creating it if not already done) or NULL
	*/
	public function GetCache($storage='auto',$settings=array())
	{
//		if($this->cache == NULL && isset($_SESSION['bkrcache']))
//			$this->cache = $_SESSION['bkrcache'];
		if($this->cache)
			return $this->cache;

		$config = cmsms()->GetConfig();
		$url = $config['root_url'];
		$settings = array_merge(
			array(
				'memcache' => array(
					array($url,11211,1)
				),
				'redis' => array(
					'host' => $url,
					'port' => '',
					'password' => '',
					'database' => '',
					'timeout' => ''
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
			$storage = 'shmop,apc,memcached,wincache,xcache,memcache,redis,database';

		$path = dirname(__FILE__).DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR;
		require($path.'interface.FastCache.php');
		require($path.'FastCacheBase.php');

		$types = explode(',',$storage);
		foreach($types as $one)
		{
			$one = trim($one);
			require($path.$one.'.php');
			$class = 'pwfCache_'.$one;
			try
			{
				$cache = new $class($settings);
			}
			catch(Exception $e)
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
