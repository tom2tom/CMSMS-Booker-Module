<?php
/*
 * Predis extension:
 * https://github.com/nrk/predis
 */
namespace MultiCache;

class Cache_predis extends CacheBase implements CacheInterface
{
	protected $client;

	/*
	$config members: any of
	'host' => string
	'port'  => int
	'password' => string
	'database' => int
	'timeout' => float seconds
	'read_write_timeout' => float seconds
	*/
	public function __construct($config = array())
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
			unset($this->client);
		}
		throw new \Exception('no predis storage');
	}

	public function use_driver()
	{
		if (extension_loaded('Redis')) {
			return FALSE; //native Redis extension is installed, prefer Redis to increase performance
		}
        return class_exists('Predis\Client');
	}

	public function connectServer()
	{
		$params = array_merge(array(
			'host' => '127.0.0.1',
			'port'  => 6379,
			'password' => '',
			'database' => 0
			), $this->config);

		$c = array('host' => $params['host']);

		if ($params['port']) {
			$c['port'] = (int)$params['port'];
		}

		if ($params['password']) {
			$c['password'] = $params['password'];
		}

		if ($params['database']) {
			$c['database'] = (int)$params['database'];
		}

		$p = isset($params['timeout']) ? $params['timeout'] : '';
		if ($p) {
			$c['timeout'] = (float)$p;
		}

		$p = isset($params['read_write_timeout']) ? $params['read_write_timeout'] : '';
		if ($p) {
			$c['read_write_timeout'] = (float)$p;
		}

		$this->client = new \Predis\Client($c);
		return $this->client !== NULL;
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		if (!$this->_has($keyword)) {
			$ret = $this->client->set($keyword, $value, array('xx', 'ex' => $lifetime));
			return $ret;
		}
		return FALSE;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		$ret = $this->client->set($keyword, $value, array('xx', 'ex' => $lifetime));
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
		return $this->client->delete($keyword);
	}

	public function _clean($filter)
	{
//TODO filtering
		$this->client->flushDB();
	}

}
