<?php

namespace MultiCache;

interface CacheInterface
{
	public function __construct($config=array());

	/*
	 * Check whether this cache driver can be used
	 */
	public function use_driver();

	/*
	 * Set
	 * Upsert an item in cache
	 */
	public function _newsert($keyword, $value, $lifetime = FALSE);
	public function _upsert($keyword, $value, $lifetime = FALSE);

	/*
	 * Get
	 * Return cached value or NULL
	 */
	public function _get($keyword);
	/*
	 * Getall
	 * Return array of cached key::value or NULL, optionally filtered
	 * $filter may be:
	 *  - FALSE
	 *  - a regex to match against cache keywords, must NOT be end-user supplied (injection-risk)
	 *  - the prefix of wanted keywords or a whole keyword
	 *  - a callable with arguments (keyword,value), and returning boolean representing wanted,
	 *      must NOT be end-user supplied (due to injection-risk)
	 */
	public function _getall($filter);

	/*
	 * Has
	 * Check whether an item is cached
	 */
	public function _has($keyword);

	/*
	 * Delete
	 * Delete a cached value
	 */
	public function _delete($keyword);

	/*
	 * Clean
	 * Clean up whole cache, optionally filtered
	 * $filter may be:
	 *  - FALSE
	 *  - a regex to match against cache keywords, must NOT be end-user supplied (injection-risk)
	 *  - the prefix of wanted keywords or a whole keyword
	 *  - a callable with arguments (keyword,value), and returning boolean representing wanted,
	 *      must NOT be end-user supplied (due to injection-risk)
	 */
	public function _clean($filter);
}
