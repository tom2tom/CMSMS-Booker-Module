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
	const MAXKEY = 48; //longest allowable cache key

	private static $cache = NULL; //intra-request cache-cache object

	/**
	  GetCache:
	  @mod: reference to Booker-module object
	  @storage: optional cache-type name, one (or more, ','-separated) of
	  yac,apc,apcu,wincache,xcache,memcache,redis,predis,file,database
	  default = 'auto' to try all of the above, in that order
	  @settings: optional array of general and cache-type-specific parameters,
	  (e.g. see default array in this func)
	  default empty
	  Returns: cache-object (after creating it if not already done) or NULL
	 */
	public static function GetCache(&$mod, $storage='auto', $settings=array())
	{
//		if (self::$cache == NULL && isset($_SESSION['bkrcache']))
//			self::$cache = $_SESSION['bkrcache'];
		if (self::$cache)
			return self::$cache;

		$path = __DIR__ . DIRECTORY_SEPARATOR . 'MultiCache' . DIRECTORY_SEPARATOR;
		require_once($path.'CacheInterface.php'); //prevent repeated creation crash
		require_once($path.'CacheBase.php');

		$config = \cmsms()->GetConfig();
		$url = (empty($_SERVER['HTTPS'])) ? $config['root_url'] : $config['ssl_url'];

		$basedir = $config['uploads_path'];
		if (is_dir($basedir)) {
			$rel = $mod->GetPreference('uploadsdir');
			if ($rel) {
				$basedir .= DIRECTORY_SEPARATOR . $rel;
			}
		} else
			$basedir = '';

		$settings = array_merge(
			array(
 			'memcache' => array(
			  array('host'=>'127.0.0.1','port'=>11211)
			),
/*			  'memcached' => array(
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
				'table' => cms_db_prefix() . 'module_bkr_cache'
			)
			), $settings);

		if ($storage) {
			$storage = strtolower($storage);
		} else
			$storage = 'auto';
		if (strpos($storage, 'auto') !== FALSE)
			$storage = 'yac,apc,apcu,wincache,xcache,memcache,redis,predis,file,database';

		$types = explode(',', $storage);
		foreach ($types as $one) {
			$one = trim($one);
			if (!isset($settings[$one]))
				$settings[$one] = array();
			if (empty($settings[$one]['namespace']))
				$settings[$one]['namespace'] = $mod->GetName();
			$class = 'MultiCache\Cache_' . $one;
			try {
				require($path.$one.'.php');
				$cache = new $class($settings[$one]);
			} catch (\Exception $e) {
				continue;
			}
//			$_SESSION['bkrcache'] = $cache;
			self::$cache = $cache;
			return self::$cache;
		}
		return NULL;
	}

	public static function ClearCache()
	{
//		unset($_SESSION['bkrcache']);
		unset(self::$cache);
		self::$cache = NULL;
	}

	public static function GetKey($seed='')
	{
		if (!$seed)
			$seed = 'ANYTYPE';
		$key = uniqid($seed); //keeps seed as prefix
		return substr($key,0,self::MAXKEY);
	}
}
