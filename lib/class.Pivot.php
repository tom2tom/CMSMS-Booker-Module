<?php
/**
 * Pivot tables with PHP
 * Adapted from sources at https://github.com/gonzalo123/gam-pivot
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARRANTY !
 * USE IT AT YOUR OWN RISK !
 *
 * @copyright Gonzalo Ayuso <gonzalo123@gmail.com>
 * @license GPL 2
 */
namespace Booker;

class Pivot
{
	const ID = '_id';
	const FETCH_STRUCT = 1;
	const TYPE_LINE = 0;
	const TYPE_PIVOT_TOTAL_LEVEL1 = 1;
	const TYPE_PIVOT_TOTAL_LEVEL2 = 2;
	const TYPE_FULL_TOTAL = 3;

	protected $_recordset;
	protected $_pivotOn;
	protected $_pivotcount;
	protected $_column;
	protected $_columnValues;
	protected $_columnPivots;
	protected $_columnCounts;
	protected $_colvalcount;
	protected $_lineTotal;
	protected $_pivotTotal;
	protected $_fullTotal;
	protected $_typeMark;
	protected $_totalName;
	protected $_typeName;
	protected $_splits = array();

	/**
	 * @param array of associative arrays $recordset
	 * @param single, or 1..3-member array of, @recordset key name(s) $pivoton
	 * @param optional single, or array of, @recordset key name(s) whose values
	 *  are to be grouped $group default null
	 * @param optional single, or array of, $@recordset key name(s) whose values
	 *  are to be summed in each group, and/or calculated key(s) $groupvalue default null
	 * @param optional boolean $showtype default false
	 * @param optional boolean $linetotal default false
	 * @param optional boolean $pivottotal default false
	 * @param optional boolean $fulltotal default false
	 * @param optional string $total title default 'TOT'
	 * @param optional string $type title default 'type'
	 */
	public function __construct($recordset, $pivoton,
		$group = null, $groupvalue = null, $showtype = false,
		$linetotal = false, $pivottotal = false, $fulltotal = false,
		$total = 'TOT', $type = 'type')
	{
		$this->_recordset = $recordset;
		$this->_pivotOn = (is_array($pivoton)) ? $pivoton : array($pivoton);
		$this->_pivotcount = count($this->_pivotOn);
		$this->_column = (is_array($group)) ? $group : (($group) ? array($group) : null);
		$this->_columnValues = (is_array($groupvalue)) ? $groupvalue : (($groupvalue) ? array($groupvalue) : null);
		$this->_colvalcount = ($groupvalue) ? count($this->_columnValues) : 0;
		$this->_columnPivots = array_fill(0, $this->_colvalcount, 0);
		$this->_columnCounts = array_fill(0, $this->_colvalcount, 0);
		for ($z = 0; $z < $this->_colvalcount; $z++) {
			$item = $this->_columnValues[$z];
			if (is_array($item)) {
				$this->_columnValues[$z] = $item[0];
				if ($item[1] == 'count') {
					$this->_columnCounts[$z] = 1;
				} elseif (is_callable($item[1], false, $callable_name)) {
					if ($callable_name) {
						$this->_columnPivots[$z] = $callable_name;
					} else {
						$this->_columnPivots[$z] = $item[1]; //anonymous?
					}
				}
			}
		}
		$this->_lineTotal = $linetotal;
		$this->_pivotTotal = $pivottotal;
		$this->_fullTotal = $fulltotal;
		$this->_typeMark = $showtype;
		$this->_totalName = $total;
		$this->_typeName = $type;
	}

	/**
	 * @param optional int FETCH_* $fetchtype default 0
	 * @return associative array
	 */
	public function fetch($fetchType = 0)
	{
		$tmp = $tmpCount = array();
		$split  = $this->_column[0];
		$column = (!empty($this->_column[1])) ? $this->_column[1] : null; //CHECKME column[0] ?

		foreach ($this->_recordset as $reg) {
			switch ($this->_pivotcount) {
				case 1:
					$k0 = $reg[$this->_pivotOn[0]];
					for ($z = 0; $z < $this->_colvalcount; $z++) {
						if ($this->_columnPivots[$z]) {
							break;
						}
						if ($column) {
							$k = $this->_columnValues[$z];
							if ($this->_columnCounts[$z]) {
								$tmpCount[$k0][$reg[$split]][$reg[$column]][$k]++;
							}
							$tmp[$k0][$reg[$split]][$reg[$column]][$k] += $reg[$k];
							$this->_splits[$reg[$split]][$reg[$column]][$k] = $k;
						} else {
							$k = 'TODO';
						}
					}
					break;
				case 2:
					$k0 = $reg[$this->_pivotOn[0]];
					$k1 = $reg[$this->_pivotOn[1]];
					for ($z = 0; $z < $this->_colvalcount; $z++) {
						if ($this->_columnPivots[$z]) {
							break;
						}
						if ($column) {
							$k = $this->_columnValues[$z];
							if ($this->_columnCounts[$z]) {
								$tmpCount[$k0][$k1][$reg[$split]][$reg[$column]][$k] ++;
							}
							$tmp[$k0][$k1][$reg[$split]][$reg[$column]][$k] += $reg[$k];
							$this->_splits[$reg[$split]][$reg[$column]][$k] = $k;
						} else {
							$k = 'TODO';
						}
					}
					break;
				case 3:
					$k0 = $reg[$this->_pivotOn[0]];
					$k1 = $reg[$this->_pivotOn[1]];
					$k2 = $reg[$this->_pivotOn[2]];
					for ($z = 0; $z < $this->_colvalcount; $z++) {
						if ($this->_columnPivots[$z]) {
							break;
						}
						if ($column) {
							$k = $this->_columnValues[$z];
							if ($this->_columnCounts[$z]) {
								$tmpCount[$k0][$k1][$k2][$reg[$split]][$reg[$column]][$k] ++;
							}
							$tmp[$k0][$k1][$k2][$reg[$split]][$reg[$column]][$k] += $reg[$k];
							$this->_splits[$reg[$split]][$reg[$column]][$k] = $k;
						} else {
							$k = 'TODO';
						}
					}
					break;
			}
		}
		// build output
		$out = array();
		$cont = 0;
		$fullTotal  = array();
		$title2 = $this->_totalName . '::';
		switch ($this->_pivotcount) {
			case 1:
				foreach ($tmp as $p0 => $p0Values) {
					$_out = $_lineTotal = array();
					$_out[self::ID] = ++$cont;
					if ($this->_typeMark) {
						$_out[$this->_typelName] = self::TYPE_LINE;
					}

					$_out[$this->_pivotOn[0]] = $p0;

					foreach (array_keys($this->_splits) as $split) {
						$cols = $p0Values[$split];

						foreach (array_keys($this->_splits[$split]) as $col) {
							$colValues = $cols[$col];
							for ($z = 0; $z < $this->_colvalcount; $z++) {
								$k = $this->_columnValues[$z];
								if ($this->_columnPivots[$z]) {
									$value = call_user_func($this->_columnPivots[$z], $colValues);
								} elseif ($this->_columnCounts[$z]) {
									$value = $tmpCount[$p0][$split][$col][$k];
								} else {
									$value = $colValues[$k];
								}

								$_out["{$split}::{$col}::{$k}"] = $value;
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
						for ($z = 0; $z < $this->_colvalcount; $z++) {
							$k = $this->_columnValues[$z];
							if ($this->_columnPivots[$z]) {
								$value = call_user_func($this->_columnPivots[$z], $_lineTotal);
							} else {
								$value = $_lineTotal[$k];
							}
							$_out[$title2 . $k] = $value;
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
						$_out[self::ID] = ++$cont;
						if ($this->_typeMark) {
							$_out[$this->_typelName] = self::TYPE_LINE;
						}
						$_out[$this->_pivotOn[0]] = $p0;
						$_out[$this->_pivotOn[1]] = $p1;

						foreach (array_keys($this->_splits) as $split) {
							$cols = $p1Values[$split];

							foreach (array_keys($this->_splits[$split]) as $col) {
								$colValues = $cols[$col];
								for ($z = 0; $z < $this->_colvalcount; $z++) {
									$k = $this->_columnValues[$z];
									if ($this->_columnPivots[$z]) {
										$value = call_user_func($this->_columnPivots[$z], $colValues);
									} elseif ($this->_columnCounts[$z]) {
										$value = $tmpCount[$p0][$p1][$split][$col][$k];
									} else {
										$value = $colValues[$k];
									}
									$_out["{$split}::{$col}::{$k}"] = $value;
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
							for ($z = 0; $z < $this->_colvalcount; $z++) {
								$k = $this->_columnValues[$z];
								if ($this->_columnPivots[$z]) {
									$value = call_user_func($this->_columnPivots[$z], $_lineTotal);
								} else {
									$value = $_lineTotal[$k];
								}
								$_out[$title2 . $k] = $value;
							}
						}
						$out[] = $_out;
					}
					if ($this->_pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::ID] = ++$cont;
						if ($this->_typeMark) {
							$_out[$this->_typelName] = self::TYPE_PIVOT_TOTAL_LEVEL1;
						}
						for ($i = 0; $i < $this->_pivotcount; $i++) {
							$pivotOn = $this->_pivotOn[$i];
							if ($i == 0) {
								$_out[$pivotOn] = $this->_totalName . " ({$pivotOn})";
							} else {
								$_out[$pivotOn] = null;
							}
						}
						foreach ($p0Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								for ($z = 0; $z < $this->_colvalcount; $z++) {
									$k = $this->_columnValues[$z];
									if ($this->_columnPivots[$z]) {
										$value = call_user_func($this->_columnPivots[$z], $p0Total[$split][$col]);
									} else {
										$value = $p0Total[$split][$col][$k];
									}
									$_out["{$split}::{$col}::{$k}"] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->_lineTotal) {
							for ($z = 0; $z < $this->_colvalcount; $z++) {
								$k = $this->_columnValues[$z];
								if ($this->_columnPivots[$z]) {
									$value = call_user_func($this->_columnPivots[$z], $_lineTotal);
								} else {
									$value = $_lineTotal[$k];
								}
								$_out[$title2 . $k] = $_lineTotal[$k];
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
							$_out[self::ID] = ++$cont;
							if ($this->_typeMark) {
								$_out[$this->_typelName] = self::TYPE_LINE;
							}
							$_out[$this->_pivotOn[0]] = $p0;
							$_out[$this->_pivotOn[1]] = $p1;
							$_out[$this->_pivotOn[2]] = $p2;

							foreach (array_keys($this->_splits) as $split) {
								$cols = $p2Values[$split];

								foreach (array_keys($this->_splits[$split]) as $col) {
									$colValues = $cols[$col];
									for ($z = 0; $z < $this->_colvalcount; $z++) {
										$k = $this->_columnValues[$z];
										if ($this->_columnPivots[$z]) {
											$value = call_user_func($this->_columnPivots[$z], $colValues);
										} elseif ($this->_columnCounts[$z]) {
											$value = $tmpCount[$p0][$p1][$p2][$split][$col][$k];
										} else {
											$value = $colValues[$k];
										}
										$_out["{$split}::{$col}::{$k}"] = $value;
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
								for ($z = 0; $z < $this->_colvalcount; $z++) {
									$k = $this->_columnValues[$z];
									if ($this->_columnPivots[$z]) {
										$value = call_user_func($this->_columnPivots[$z], $_lineTotal);
									} else {
										$value = $_lineTotal[$k];
									}
									$_out[$title2 . $k] = $value;
								}
							}
							$out[] = $_out;
						}
					}
					if ($this->_pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::ID] = ++$cont;
						if ($this->_typeMark) {
							$_out[$this->_typelName] = self::TYPE_PIVOT_TOTAL_LEVEL2;
						}
						for ($i = 0; $i < $this->_pivotcount; $i++) {
							$pivotOn = $this->_pivotOn[$i];
							if ($i == 0) {
								$_out[$pivotOn] = $this->_totalName . " ({$pivotOn})";
							} else {
								$_out[$pivotOn] = null;
							}
						}
						foreach ($p0Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								for ($z = 0; $z < $this->_colvalcount; $z++) {
									$k = $this->_columnValues[$z];
									if ($this->_columnPivots[$z]) {
										$value = call_user_func($this->_columnPivots[$z], $p0Total[$split][$col]);
									} else {
										$value = $p0Total[$split][$col][$k];
									}
									$_out["{$split}::{$col}::{$k}"] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->_lineTotal) {
							for ($z = 0; $z < $this->_colvalcount; $z++) {
								$k = $this->_columnValues[$z];
								if ($this->_columnPivots[$z]) {
									$value = call_user_func($this->_columnPivots[$z], $_lineTotal);
								} else {
									$value = $_lineTotal[$k];
								}
								$_out[$title2 . $k] = $value;
							}
						}
						$out[] = $_out;
					}

					if ($this->_pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::ID] = ++$cont;
						if ($this->_typeMark) {
							$_out[$this->_typelName] = self::TYPE_PIVOT_TOTAL_LEVEL1;
						}
						for ($i = 0; $i < $this->_pivotcount; $i++) {
							$pivotOn = $this->_pivotOn[$i];
							if ($i == 0) {
								$_out[$pivotOn] = $this->_totalName . " ({$pivotOn}, {$this->_pivotOn[1]})";
							} else {
								$_out[$pivotOn] = null;
							}
						}
						foreach ($p1Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								for ($z = 0; $z < $this->_colvalcount; $z++) {
									$k = $this->_columnValues[$z];
									if ($this->_columnPivots[$z]) {
										$value = call_user_func($this->_columnPivots[$z], $p1Total[$split][$col]);
									} else {
										$value = $p1Total[$split][$col][$k];
									}
									$_out["{$split}::{$col}::{$k}"] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->_lineTotal) {
							for ($z = 0; $z < $this->_colvalcount; $z++) {
								$k = $this->_columnValues[$z];
								if ($this->_columnPivots[$z]) {
									$value = call_user_func($this->_columnPivots[$z], $_lineTotal);
								} else {
									$value = $_lineTotal[$k];
								}
								$_out[$title2 . $k] = $value;
							}
						}
						$out[] = $_out;
					}
				}
				break;
		}

		if ($this->_fullTotal) {
			$_out = $_lineTotal = array();
			$_out[self::ID] = ++$cont;
			if ($this->_typeMark) {
				$_out[$this->_typelName] = self::TYPE_FULL_TOTAL;
			}
			for ($i = 0; $i < $this->_pivotcount; $i++) {
				$pivotOn = $this->_pivotOn[$i];
				if ($i == 0) {
					$_out[$pivotOn] = $this->_totalName;
				} else {
					$_out[$pivotOn] = null;
				}
			}
			foreach ($fullTotal as $split => $values) {
				foreach ($values as $col => $colValues) {
					for ($z = 0; $z < $this->_colvalcount; $z++) {
						$k = $this->_columnValues[$z];
						if ($this->_columnPivots[$z]) {
							$value = call_user_func($this->_columnPivots[$z], $fullTotal[$split][$col]);
						} else {
							$value = $fullTotal[$split][$col][$k];
						}
						$_out["{$split}::{$col}::{$k}"] = $value;
						$_lineTotal[$k] += $value;
					}
				}
			}
			if ($this->_lineTotal) {
				for ($z = 0; $z < $this->_colvalcount; $z++) {
					$k = $this->_columnValues[$z];
					if ($this->_columnPivots[$z]) {
						$value = call_user_func($this->_columnPivots[$z], $_lineTotal);
					} else {
						$value = $_lineTotal[$k];
					}
					$_out[$title2 . $k] = $value;
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
}

