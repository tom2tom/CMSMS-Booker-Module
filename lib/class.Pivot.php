<?php
/**
 * Pivot tables with PHP
 * https://github.com/gonzalo123/gam-pivot
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARANTIES !
 * USE IT AT YOUR OWN RISKS !
 *
 * @author Gonzalo Ayuso <gonzalo123@gmail.com>
 * @copyright under GPL 2 licence
 */
namespace Booker;

class Pivot
{
	const _ID = '_id';
	const FETCH_STRUCT = 1;
	const TYPE_LINE = 0;
	const TYPE_PIVOT_TOTAL_LEVEL1 = 1;
	const TYPE_PIVOT_TOTAL_LEVEL2 = 2;
	const TYPE_FULL_TOTAL = 3;

	private $_totalName;
	private $_typeMark = false;
	private $_pivotOn = null;
	private $_pivotTotal = false;
	private $_lineTotal = false;
	private $_fullTotal = false;
	private $_column = null;
	private $_columnValues = null;
	private $_recordset;
	private $_splits = array();

	public function __construct($recordset, $title = 'TOT')
	{
		$this->_recordset = $recordset;
		$this->_totalName = $title;
	}

	/**
	 * @param struct $recordset
	 * @return Pivot
	 */
	public static function factory($recordset)
	{
		return new self($recordset);
	}

	/**
	 * @param optional boolean $type default true
	 * @return Pivot
	 */
	public function typeMark($type = true)
	{
		$this->_typeMark = $type;
		return $this;
	}

	/**
	 * @param array $conf
	 * @return Pivot
	 */
	public function pivotOn($conf)
	{
		$this->_pivotOn = $conf;
		return $this;
	}

	/**
	 * @param optional boolean $bool default true
	 * @return Pivot
	 */
	public function pivotTotal($bool = true)
	{
		$this->_pivotTotal = $bool;
		return $this;
	}

	/**
	 * @param optional boolean $bool default true
	 * @return Pivot
	 */
	public function lineTotal($bool = true)
	{
		$this->_lineTotal = $bool;
		return $this;
	}

	/**
	 * @param optional boolean $bool
	 * @return Pivot
	 */
	public function fullTotal($bool = true)
	{
		$this->_fullTotal = $bool;
		return $this;
	}

	/**
	 * @param array $column
	 * @param array $columnValues
	 * @return Pivot
	 */
	public function addColumn($column, $columnValues)
	{
		$this->_column = $column;
		$this->_columnValues = $columnValues;
		return $this;
	}

	/**
	 * @param optional int FETCH_* $fetchtype default 0
	 * @return array
	 */
	public function fetch($fetchType = 0)
	{
		$tmp = $tmpCount = array();
		$split  = $this->_column[0];
		$column = $this->_column[1];

		foreach ($this->_recordset as $reg) {
			switch (count($this->_pivotOn)) {
				case 1:
					$k0 = $this->_getColumnItem($reg, 0);
					foreach ($this->_columnValues as $item) {
						if ($item instanceof Pivot_Callback) {
							break;
						} elseif ($item instanceof Pivot_Count) {
							$tmpCount[$k0][$reg[$split]][$reg[$column]][$item->getKey()]++;
						}
						$tmp[$k0][$reg[$split]][$reg[$column]][$item] += $reg[$item];
						$this->_splits[$reg[$split]][$reg[$column]][$item] = $item;
					}
					break;
				case 2:
					$k0 = $this->_getColumnItem($reg, 0);
					$k1 = $this->_getColumnItem($reg, 1);
					foreach ($this->_columnValues as $item) {
						if ($item instanceof Pivot_Callback) {
							break;
						} elseif ($item instanceof Pivot_Count) {
							$tmpCount[$k0][$k1][$reg[$split]][$reg[$column]][$item->getKey()] ++;
						}
						$tmp[$k0][$k1][$reg[$split]][$reg[$column]][$item] += $reg[$item];
						$this->_splits[$reg[$split]][$reg[$column]][$item] = $item;
					}
					break;
				case 3:
					$k0 = $this->_getColumnItem($reg, 0);
					$k1 = $this->_getColumnItem($reg, 1);
					$k2 = $this->_getColumnItem($reg, 2);
					foreach ($this->_columnValues as $item) {
						if ($item instanceof Pivot_Callback) {
							break;
						} elseif ($item instanceof Pivot_Count) {
							$tmpCount[$k0][$k1][$k2][$reg[$split]][$reg[$column]][$item->getKey()] ++;
						}
						$tmp[$k0][$k1][$k2][$reg[$split]][$reg[$column]][$item] += $reg[$item];
						$this->_splits[$reg[$split]][$reg[$column]][$item] = $item;
					}
					break;
			}
		}
		return $this->_buildOutput($tmp, $fetchType, $tmpCount);
	}

	private function _buildOutput($tmp, $fetchType, $tmpCount)
	{
		$out = array();
		$cont = 0;
		$fullTotal  = array();
		switch (count($this->_pivotOn)) {
			case 1:
				foreach ($tmp as $p0 => $p0Values) {
					$i++;
					$_out = $_lineTotal = array();
					$_out[self::_ID] = ++$cont;
					if ($this->_typeMark) {
						$_out['type'] = self::TYPE_LINE;
					}

					$_out[$this->_pivotOn[0]] = $p0;

					foreach (array_keys($this->_splits) as $split) {
						$cols = $p0Values[$split];

						foreach (array_keys($this->_splits[$split]) as $col) {
							$colValues = $cols[$col];
							foreach ($this->_columnValues as $_k) {
								$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;

								if ($_k instanceof Pivot_Count) {
									$value = $tmpCount[$p0][$split][$col][$k];
								} elseif ($_k instanceof Pivot_Callback) {
									$value = $_k->cbk($colValues);
								} else {
									$value = $colValues[$k];
								}

								$_out["{$split}_{$col}_{$k}"] = $value;
								if ($this->_lineTotal) {
									$_lineTotal[$k] += $value;
								}
								if ($this->_fullTotal) {
									$fullTotal[$split][$col][$k] += $value;
								}
							}
						}
					}
					if ($this->_lineTotal) {
						foreach ($this->_columnValues as $_k) {
							$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
							$value = ($_k instanceof Pivot_Callback) ?
								$_k->cbk($_lineTotal) : $_lineTotal[$k];
							$_out[$this->_totalName . " :: {$k}"] = $value;
						}
					}
					$out[] = $_out;
				}
				break;
			case 2:
				foreach ($tmp as $p0 => $p0Values) {
					$p0Total  = array();
					foreach ($p0Values as $p1 => $p1Values) {
						$_out = $_lineTotal = array();
						$_out[self::_ID] = ++$cont;
						if ($this->_typeMark) {
							$_out['type'] = self::TYPE_LINE;
						}
						$_out[$this->_pivotOn[0]] = $p0;
						$_out[$this->_pivotOn[1]] = $p1;

						foreach (array_keys($this->_splits) as $split) {
							$cols = $p1Values[$split];

							foreach (array_keys($this->_splits[$split]) as $col) {
								$colValues = $cols[$col];
								foreach ($this->_columnValues as $_k) {
									$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
									if ($_k instanceof Pivot_Count) {
										$value = $tmpCount[$p0][$p1][$split][$col][$k];
									} elseif ($_k instanceof Pivot_Callback) {
										$value = $_k->cbk($colValues);
									} else {
										$value = $colValues[$k];
									}
									$_out["{$split}_{$col}_{$k}"] = $value;
									if ($this->_lineTotal) {
										$_lineTotal[$k] += $value;
									}
									if ($this->_pivotTotal) {
										$p0Total[$split][$col][$k] += $value;
									}
									if ($this->_fullTotal) {
										$fullTotal[$split][$col][$k] += $value;
									}
								}
							}
						}
						if ($this->_lineTotal) {
							foreach ($this->_columnValues as $_k) {
								$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
								$value = ($_k instanceof Pivot_Callback) ?
									$_k->cbk($_lineTotal) : $_lineTotal[$k];
								$_out[$this->_totalName . " :: {$k}"] = $value;
							}
						}
						$out[] = $_out;
					}
					if ($this->_pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::_ID] = ++$cont;
						if ($this->_typeMark) {
							$_out['type'] = self::TYPE_PIVOT_TOTAL_LEVEL1;
						}
						$i = 0;
						foreach ($this->_pivotOn as $pivotOn) {
							if ($i == 0) {
								$_out[$pivotOn] = $this->_totalName . " ({$this->_pivotOn[0]})";
							} else {
								$_out[$pivotOn] = null;
							}
							$i++;
						}
						foreach ($p0Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								foreach ($this->_columnValues as $_k) {
									$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
									$value = ($_k instanceof Pivot_Callback) ?
										$_k->cbk($p0Total[$split][$col]) : $p0Total[$split][$col][$k];
									$_out["{$split}_{$col}_{$k}"] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->_lineTotal) {
							foreach ($this->_columnValues as $_k) {
								$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
								$value = ($_k instanceof Pivot_Callback) ?
									$_k->cbk($_lineTotal) : $_lineTotal[$k];
								$_out[$this->_totalName . " :: {$k}"] = $_lineTotal[$k];
							}
						}
						$out[] = $_out;
					}
				}
				break;
			case 3:
				foreach ($tmp as $p0 => $p0Values) {
					$p0Total  = array();
					foreach ($p0Values as $p1 => $p1Values) {
						foreach ($p1Values as $p2 => $p2Values) {
							$_out = $_lineTotal = array();
							$_out[self::_ID] = ++$cont;
							if ($this->_typeMark) {
								$_out['type'] = self::TYPE_LINE;
							}
							$_out[$this->_pivotOn[0]] = $p0;
							$_out[$this->_pivotOn[1]] = $p1;
							$_out[$this->_pivotOn[2]] = $p2;

							foreach (array_keys($this->_splits) as $split) {
								$cols = $p2Values[$split];

								foreach (array_keys($this->_splits[$split]) as $col) {
									$colValues = $cols[$col];
									foreach ($this->_columnValues as $_k) {
										$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
										if ($_k instanceof Pivot_Count) {
											$value = $tmpCount[$p0][$p1][$p2][$split][$col][$k];
										} elseif ($_k instanceof Pivot_Callback) {
											$value = $_k->cbk($colValues);
										} else {
											$value = $colValues[$k];
										}
										$_out["{$split}_{$col}_{$k}"] = $value;
										if ($this->_lineTotal) {
											$_lineTotal[$k] += $value;
										}
										if ($this->_pivotTotal) {
											$p0Total[$split][$col][$k] += $value;
											$p1Total[$split][$col][$k] += $value;
										}
										if ($this->_fullTotal) {
											$fullTotal[$split][$col][$k] += $value;
										}
									}
								}
							}
							if ($this->_lineTotal) {
								foreach ($this->_columnValues as $_k) {
									$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
									$value = ($_k instanceof Pivot_Callback) ?
										$_k->cbk($_lineTotal) : $_lineTotal[$k];
									$_out[$this->_totalName . "::{$k}"] = $value;
								}
							}
							$out[] = $_out;
						}
					}
					if ($this->_pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::_ID] = ++$cont;
						if ($this->_typeMark) {
							$_out['type'] = self::TYPE_PIVOT_TOTAL_LEVEL2;
						}
						$i = 0;
						foreach ($this->_pivotOn as $pivotOn) {
							if ($i == 0) {
								$_out[$pivotOn] = $this->_totalName . " ({$this->_pivotOn[0]})";
							} else {
								$_out[$pivotOn] = null;
							}
							$i++;
						}
						foreach ($p0Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								foreach ($this->_columnValues as $_k) {
									$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
									$value = ($_k instanceof Pivot_Callback) ?
										$_k->cbk($p0Total[$split][$col]) : $p0Total[$split][$col][$k];
									$_out["{$split}_{$col}_{$k}"] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->_lineTotal) {
							foreach ($this->_columnValues as $_k) {
								$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
								$value = ($_k instanceof Pivot_Callback) ?
									$_k->cbk($_lineTotal) : $_lineTotal[$k];
								$_out[$this->_totalName . " :: {$k}"] = $value;
							}
						}
						$out[] = $_out;
					}

					if ($this->_pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::_ID] = ++$cont;
						if ($this->_typeMark) {
							$_out['type'] = self::TYPE_PIVOT_TOTAL_LEVEL1;
						}
						$i = 0;
						foreach ($this->_pivotOn as $pivotOn) {
							if ($i == 0) {
								$_out[$pivotOn] = $this->_totalName . " ({$this->_pivotOn[0]}, {$this->_pivotOn[1]})";
							} else {
								$_out[$pivotOn] = null;
							}
							$i++;
						}
						foreach ($p1Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								foreach ($this->_columnValues as $_k) {
									$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
									$value = ($_k instanceof Pivot_Callback) ?
										$_k->cbk($p1Total[$split][$col]) : $p1Total[$split][$col][$k];
									$_out["{$split}_{$col}_{$k}"] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->_lineTotal) {
							foreach ($this->_columnValues as $_k) {
								$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
								$value = ($_k instanceof Pivot_Callback) ?
									$_k->cbk($_lineTotal) : $_lineTotal[$k];
								$_out[$this->_totalName . " :: {$k}"] = $value;
							}
						}
						$out[] = $_out;
					}
				}
				break;
		}
		if ($this->_fullTotal) {
			$_out = $_lineTotal = array();
			$_out[self::_ID] = ++$cont;
			if ($this->_typeMark) {
				$_out['type'] = self::TYPE_FULL_TOTAL;
			}
			$i = 0;
			foreach ($this->_pivotOn as $pivotOn) {
				if ($i == 0) {
					$_out[$pivotOn] = $this->_totalName;
				} else {
					$_out[$pivotOn] = null;
				}
				$i++;
			}
			foreach ($fullTotal as $split => $values) {
				foreach ($values as $col => $colValues) {
					foreach ($this->_columnValues as $_k) {
						$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
						$value = ($_k instanceof Pivot_Callback) ?
							$_k->cbk($fullTotal[$split][$col]) : $fullTotal[$split][$col][$k];
						$_out["{$split}_{$col}_{$k}"] = $value;
						$_lineTotal[$k] += $value;
					}
				}
			}
			if ($this->_lineTotal) {
				foreach ($this->_columnValues as $_k) {
					$k = ($_k instanceof Pivot_Callback || $_k instanceof Pivot_Count) ? $_k->getKey() : $_k;
					$value = ($_k instanceof Pivot_Callback) ? $_k->cbk($_lineTotal) : $_lineTotal[$k];
					$_out[$this->_totalName . " :: {$k}"] = $value;
				}
			}
			$out[] = $_out;
		}

		if ($out && $fetchType == self::FETCH_STRUCT) {
			return array(
				'splits' => $this->_splits,
				'data' => array_map('array_values', $out),
			);
		}
		return $out;
	}

	private function _getColumnItem($reg, $key)
	{
		return $reg[$this->_pivotOn[$key]];
	}

	public static function callback($key, $callable)
	{
		return new Pivot_Callback($key, $callable);
	}

	public static function count($key)
	{
		return new Pivot_Count($key);
	}
}

class Pivot_Count
{
	private $_key = null;

	public function __construct($key)
	{
		$this->_key = $key;
	}

	public function getKey()
	{
		return $this->_key;
	}
}

class Pivot_Callback
{
	private $_callable = null;
	private $_key = null;

	public function __construct($key, $callable)
	{
		$this->_callable = $callable;
		$this->_key = $key;
	}

	public function getKey()
	{
		return $this->_key;
	}

	public function cbk()
	{
		return call_user_func_array($this->_callable, func_get_args());
	}
}
