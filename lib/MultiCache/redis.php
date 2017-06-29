<?php
/*
 * Redis extension:
 * https://github.com/phpredis/phpredis
 */
namespace MultiCache;

class Cache_redis extends CacheBase implements CacheInterface
{
	protected $client;

	/*
	$config members: any of
	'host' => string
	'port'  => int
	'password' => string
	'database' => int
	'timeout' => float seconds
	*/
	public function __construct($config=[])
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
			unset($this->client);
		}
		throw new \Exception('no redis storage');
	}

	public function use_driver()
	{
		return class_exists('Redis');
	}

	public function connectServer()
	{
		$params = array_merge([
			'host' => '127.0.0.1',
			'port'  => 6379,
			'password' => '',
			'database' => 0,
			'timeout' => 0.0,
			], $this->config);

		$this->client = new \Redis();
		if (!$this->client->connect($params['host'],(int)$params['port'],(float)$params['timeout'])) {
			return FALSE;
		} elseif ($params['password'] && !$this->client->auth($params['password'])) {
			return FALSE;
		}
		if ($params['database']) {
			return $this->client->select((int)$params['database']);
		}
		return TRUE;
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		if (!$this->_has($keyword)) {
			$ret = $this->client->set($keyword, $value, ['xx', 'ex' => $lifetime]);
			return $ret;
		}
		return FALSE;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		$ret = $this->client->set($keyword, $value, ['xx', 'ex' => $lifetime]);
		if ($ret === FALSE) {
			$ret = $this->client->set($keyword, $value, $lifetime);
		}
		return $ret;
	}

	public function _get($keyword)
	{
		$value = $this->client->get($keyword);
		if ($value !== FALSE) {
			return $value;
		}
		return NULL;
	}

	public function _getall($filter)
	{
//TODO filtering
		return NULL; //TODO allitems;
	}

	public function _has($keyword)
	{
		return ($this->client->exists($keyword) != NULL);
	}

	public function _delete($keyword)
	{
		$this->client->delete($keyword);
		return TRUE;
	}

	public function _clean($filter)
	{
//TODO filtering
		$this->client->flushDB();
	}

}
