<?php
namespace MultiCache;

interface CacheInterface {

	 function __construct($config = array());
//	 function __destruct();

	/*
	 * Check if this Cache driver is available for server or not
	 */
	 function checkdriver();

	/*
	 * SET
	 * set a obj to cache
	 */
	 function driver_set($keyword, $value = "", $time = 300, $option = array());

	/*
	 * GET
	 * return null or value of cache
	 */
	 function driver_get($keyword, $option = array());

	 function driver_getall($option = array());

	/*
	 * Stats
	 * Show stats of caching
	 * Return array ("info","size","data")
	 */
	 function driver_stats($option = array());

	/*
	 * Delete
	 * Delete a cache
	 */
	 function driver_delete($keyword, $option = array());

	/*
	 * clean
	 * Clean up whole cache
	 */
	 function driver_clean($option = array());
}
?>
