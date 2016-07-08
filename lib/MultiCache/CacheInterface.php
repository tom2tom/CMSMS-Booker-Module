<?php
namespace MultiCache;

interface CacheInterface {

	function __construct($config = []);

	/*
	 * Check whether this cache driver can be used
	 */
	function use_driver();

	/*
	 * Set
	 * Upsert an item in cache
	 */
	function _newsert($keyword, $value, $lifetime = FALSE);
	function _upsert($keyword, $value, $lifetime = FALSE);

	/*
	 * Get
	 * Return cached value or NULL
	 */
	function _get($keyword);
	/*
	 * Getall
	 * Return array of cached key::value or NULL, optionally filtered
	 * $filter may be:
	 *  a regex to match against cache keywords, must NOT be end-user definable (injection-risk)
	 *  the prefix of wanted keywords or a whole keyword
	 *  a callable with keyword as argument and returning TRUE if the keyword
     *  is wanted, must NOT be end-user definable (injection-risk)
	 */
	function _getall($filter);

	/*
	 * Has
	 * Check whether an item is cached
	 */
	function _has($keyword);

	/*
	 * Delete
	 * Delete a cached value
	 */
	function _delete($keyword);

	/*
	 * Clean
	 * Clean up whole cache, optionally filtered
	 * $filter may be:
	 *  a regex to match against cache keywords, must NOT be end-user definable (injection-risk)
	 *  the prefix of wanted keywords or a whole keyword
	 *  a callable with keyword as argument and returning TRUE if the keyword
     *  is wanted, must NOT be end-user definable (injection-risk)
	 */
	function _clean($filter);
}
?>
