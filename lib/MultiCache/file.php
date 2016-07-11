<?php

namespace MultiCache;

class Cache_file extends CacheBase implements CacheInterface
{
	protected $basepath; //has trailing separator

	public function __construct($config = array())
	{
		parent::__construct($config);
		if ($this->use_driver()) {
			if ($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no file storage');
	}

	public function use_driver()
	{
		return !empty($this->config['path']);
	}
	
	public function connectServer()
	{
		$dir = trim(rtrim($this->config['path'],'/\\ \t'));
		if (!$dir) {
			return FALSE;
		}
		$dir = str_replace(array('/','\\'),array(DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR),$dir);
		 //hacky check for relative-path
		$real = realpath(__DIR__); // gets /path/to/here or X:\path\to\here
		if (($dir[0] != DIRECTORY_SEPARATOR && $real[0] == DIRECTORY_SEPARATOR)
		|| ($dir[1] != ':' && $real[1] == ':')) {
			$dir = __DIR__.DIRECTORY_SEPARATOR.$dir;
		}
		$dir .= DIRECTORY_SEPARATOR.'file_cache';
		if (!is_dir($dir)) {
			if (!(@mkdir($dir) && file_exists($dir)))
				return FALSE;
		}
		$this->basepath = $dir.DIRECTORY_SEPARATOR;
		return TRUE;
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		$fp = $this->basepath.$this->filename($keyword);
		if (!file_exists($fp)) {
			return $this->writefile($fp,$value);
		}
		return FALSE;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		$fp = $this->basepath.$this->filename($keyword);
		return $this->writefile($fp,$value);
	}

	public function _get($keyword)
	{
		$fp = $this->basepath.$this->filename($keyword);
		if (is_file($fp)) {
			$value = $this->readfile($fp);
			if ($value !== FALSE) {
				return $value;
			}
		}
		return NULL;
	}

	public function _getall($filter)
	{
		$items = array();
		$files = glob($this->basepath.'*',GLOB_NOSORT);
		foreach($files as $fp) {
			if (is_file($fp)) {
				$keyword = $this->keyword($fp);
				$value = $this->_get($keyword);
				$again = is_object($value); //get it again, in case the filter played with it!
				if ($this->filterKey($filter,$keyword,$value)) {
					if ($again) {
						$value = $this->_get($keyword);
					}
					if ($value !== NULL) {
						$items[$keyword] = $value;
					}
				}
			}
		}
		return $vals;
	}

	public function _has($keyword)
	{
		$fp = $this->basepath.$this->filename($keyword);
		return file_exists($fp);
	}

	public function _delete($keyword)
	{
		$fp = $this->basepath.$this->filename($keyword);
		if (is_file($fp)) {
			return @unlink($fp);
		}
		return FALSE;
	}

	public function _clean($filter)
	{
		$ret = TRUE;
		$files = glob($this->basepath.'*',GLOB_NOSORT);
		foreach($files as $fp) {
			if (is_file($fp)) {
				$keyword = $this->keyword($fp);
				$value = $this->_get($keyword);
				if ($this->filterKey($filter,$keyword,$value)) {
					$ret = $ret && @unlink($fp);
				}
			}
		}
		return $ret;
	}

	/*
	$keyword may include a namespace, which can look like a filepath
	*/
	private function filename($keyword)
	{
		return str_replace('\\','|%|',$keyword);
	}

	private function keyword($filepath)
	{
		return str_replace('|%|','\\',basename($filepath));
	}

	private function readfile($filepath)
	{
		$h = @fopen($filepath,'rb');
		if ($h) {
			$content = @fread($h,filesize($filepath));
			@fclose($h);
			return unserialize($content);
		}
		return FALSE;
	}

	private function writefile($filepath, $content)
	{
		$h = @fopen($filepath,'wb');
		if ($h) {
			$ret = @fwrite($h,serialize($content));
			$ret = $ret && @fclose($h);
			return $ret;
		}
		return FALSE;
	}

}
