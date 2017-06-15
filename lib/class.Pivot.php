<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: PivotBase - pivot table functions
# Adapted from gam-pivot <https://github.com/gonzalo123/gam-pivot>
# Copyright (C) Gonzalo Ayuso <gonzalo123@gmail.com>
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class PivotBase
{
	const ID = '_id';
	const SEP = '..';
	const FETCH_STRUCT = 1;
	const TYPE_LINE = 0;
	const TYPE_PIVOT_TOTAL_LEVEL1 = 1;
	const TYPE_PIVOT_TOTAL_LEVEL2 = 2;
	const TYPE_FULL_TOTAL = 3;

	protected $recordset;
	protected $pivotOn;
	protected $pivotscount;
	protected $column;
	protected $columnCalcs;
	protected $columnPivots;
	protected $columnCounts;
	protected $calcscount;
	protected $typeMark;
	protected $lineTotal;
	protected $pivotTotal;
	protected $fullTotal;
	protected $totalName;
	protected $typeName;
	protected $splits = array();

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
		$this->recordset = $recordset;
		$this->pivotOn = (is_array($pivoton)) ? $pivoton : array($pivoton);
		$this->pivotscount = count($this->pivotOn);
		$this->column = (is_array($group)) ? $group : (($group) ? array($group) : null);
		$this->columnCalcs = (is_array($groupvalue)) ? $groupvalue : (($groupvalue) ? array($groupvalue) : null);
		$this->calcscount = ($groupvalue) ? count($this->columnCalcs) : 0;
		$this->columnPivots = array_fill(0, $this->calcscount, 0);
		$this->columnCounts = array_fill(0, $this->calcscount, 0);
		for ($ic = 0; $ic < $this->calcscount; $ic++) {
			$item = $this->columnCalcs[$ic];
			if (is_array($item)) {
				$this->columnCalcs[$ic] = $item[0];
				if ($item[1] == 'count') {
					$this->columnCounts[$ic] = 1;
				} elseif (is_callable($item[1], false, $callable_name)) {
					if ($callable_name) {
						$this->columnPivots[$ic] = $callable_name;
					} else {
						$this->columnPivots[$ic] = $item[1]; //anonymous?
					}
				}
			}
		}
		$this->typeMark = $showtype;
		$this->lineTotal = $linetotal;
		$this->pivotTotal = $pivottotal;
		$this->fullTotal = $fulltotal;
		$this->totalName = $total;
		$this->typeName = $type;
	}

	/*
	Sub-class this
	*/
	protected function parse()
	{
/*		$parsed = $parsedCount = array();
		$split  = $this->column[0];
		$column = (!empty($this->column[1])) ? $this->column[1] : null; //CHECKME column[0] ?

		foreach ($this->recordset as $row) {
			switch ($this->pivotscount) {
				case 1:
					$k0 = $row[$this->pivotOn[0]];
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						if ($this->columnPivots[$ic]) {
							break;
						}
						if ($column) {
							$k = $this->columnCalcs[$ic];
							if ($this->columnCounts[$ic]) {
								$parsedCount[$k0][$row[$split]][$row[$column]][$k]++;
							}
							$parsed[$k0][$row[$split]][$row[$column]][$k] += $row[$k];
							$this->splits[$row[$split]][$row[$column]][$k] = $k;
						} else {
							$k = 'TODO';
						}
					}
					break;
				case 2:
					$k0 = $row[$this->pivotOn[0]];
					$k1 = $row[$this->pivotOn[1]];
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						if ($this->columnPivots[$ic]) {
							break;
						}
						if ($column) {
							$k = $this->columnCalcs[$ic];
							if ($this->columnCounts[$ic]) {
								$parsedCount[$k0][$k1][$row[$split]][$row[$column]][$k] ++;
							}
							$parsed[$k0][$k1][$row[$split]][$row[$column]][$k] += $row[$k];
							$this->splits[$row[$split]][$row[$column]][$k] = $k;
						} else {
							$k = 'TODO';
						}
					}
					break;
				case 3:
					$k0 = $row[$this->pivotOn[0]];
					$k1 = $row[$this->pivotOn[1]];
					$k2 = $row[$this->pivotOn[2]];
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						if ($this->columnPivots[$ic]) {
							break;
						}
						if ($column) {
							$k = $this->columnCalcs[$ic];
							if ($this->columnCounts[$ic]) {
								$parsedCount[$k0][$k1][$k2][$row[$split]][$row[$column]][$k] ++;
							}
							$parsed[$k0][$k1][$k2][$row[$split]][$row[$column]][$k] += $row[$k];
							$this->splits[$row[$split]][$row[$column]][$k] = $k;
						} else {
							$k = 'TODO';
						}
					}
					break;
			}
		}
		return array($parsed,$parsedCount);
*/
	}

	/*
	Sub-class this
	*/
	protected function build($parsed, $parsedCount, $subtitle, $tpl, &$fullTotal, &$out)
	{
/*
		$ir = 0;

		switch ($this->pivotscount) {
			case 1:
				foreach ($parsed as $p0 => $p0Values) {
					$_out = $_lineTotal = array();
					$_out[self::ID] = ++$ir;
					if ($this->typeMark) {
						$_out[$this->typelName] = self::TYPE_LINE;
					}

					$_out[$this->pivotOn[0]] = $p0;

					foreach (array_keys($this->splits) as $split) {
						$cols = $p0Values[$split];

						foreach (array_keys($this->splits[$split]) as $col) {
							$colValues = $cols[$col];
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->columnCalcs[$ic];
								$t = sprintf($tpl, $split, $col, $k);
								if ($this->columnPivots[$ic]) {
									$value = call_user_func($this->columnPivots[$ic], $colValues);
								} elseif ($this->columnCounts[$ic]) {
									$value = $parsedCount[$p0][$split][$col][$k];
								} else {
									$value = $colValues[$k];
								}
								$_out[$t] = $value;
								if ($this->lineTotal) {
									$_lineTotal[$k] += $value;
								}
								if ($this->fullTotal) {
									$fullTotal[$split][$col][$k] += $value;
								}
							}
						}
					}
					if ($this->lineTotal) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->columnCalcs[$ic];
							$t = $subtitle . $k;
							if ($this->columnPivots[$ic]) {
								$_out[$t] = call_user_func($this->columnPivots[$ic], $_lineTotal);
							} else {
								$_out[$t] = $_lineTotal[$k];
							}
						}
					}
					$out[] = $_out;
				}
				break;
			case 2:
				foreach ($parsed as $p0 => $p0Values) {
					$p0Total = array();
					foreach ($p0Values as $p1 => $p1Values) {
						$_out = $_lineTotal = array();
						$_out[self::ID] = ++$ir;
						if ($this->typeMark) {
							$_out[$this->typelName] = self::TYPE_LINE;
						}
						$_out[$this->pivotOn[0]] = $p0;
						$_out[$this->pivotOn[1]] = $p1;

						foreach (array_keys($this->splits) as $split) {
							$cols = $p1Values[$split];

							foreach (array_keys($this->splits[$split]) as $col) {
								$colValues = $cols[$col];
								for ($ic = 0; $ic < $this->calcscount; $ic++) {
									$k = $this->columnCalcs[$ic];
									$t = sprintf($tpl, $split, $col, $k);
									if ($this->columnPivots[$ic]) {
										$value = call_user_func($this->columnPivots[$ic], $colValues);
									} elseif ($this->columnCounts[$ic]) {
										$value = $parsedCount[$p0][$p1][$split][$col][$k];
									} else {
										$value = $colValues[$k];
									}
									$_out[$t] = $value;
									if ($this->lineTotal) {
										$_lineTotal[$k] += $value;
									}
									if ($this->pivotTotal) {
										$p0Total[$split][$col][$k] += $value;
									}
									if ($this->fullTotal) {
										$fullTotal[$split][$col][$k] += $value;
									}
								}
							}
						}
						if ($this->lineTotal) {
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->columnCalcs[$ic];
								$t = $subtitle . $k;
								if ($this->columnPivots[$ic]) {
									$_out[$t] = call_user_func($this->columnPivots[$ic], $_lineTotal);
								} else {
									$_out[$t] = $_lineTotal[$k];
								}
							}
						}
						$out[] = $_out;
					}
					if ($this->pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::ID] = ++$ir;
						if ($this->typeMark) {
							$_out[$this->typelName] = self::TYPE_PIVOT_TOTAL_LEVEL1;
						}
						for ($ip = 0; $ip < $this->pivotscount; $ip++) {
							$pivotOn = $this->pivotOn[$ip];
							if ($ip == 0) {
								$_out[$pivotOn] = $this->totalName . " ({$pivotOn})";
							} else {
								$_out[$pivotOn] = null;
							}
						}
						foreach ($p0Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								for ($ic = 0; $ic < $this->calcscount; $ic++) {
									$k = $this->columnCalcs[$ic];
									$t = sprintf($tpl, $split, $col, $k);
									if ($this->columnPivots[$ic]) {
										$value = call_user_func($this->columnPivots[$ic], $p0Total[$split][$col]);
									} else {
										$value = $p0Total[$split][$col][$k];
									}
									$_out[$t] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->lineTotal) {
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->columnCalcs[$ic];
								$t = $subtitle . $k;
								if ($this->columnPivots[$ic]) {
									$_out[$t] = call_user_func($this->columnPivots[$ic], $_lineTotal);
								} else {
									$_out[$t] = $_lineTotal[$k];
								}
							}
						}
						$out[] = $_out;
					}
				}
				break;
			case 3:
				foreach ($parsed as $p0 => $p0Values) {
					$p0Total = array();
					foreach ($p0Values as $p1 => $p1Values) {
						foreach ($p1Values as $p2 => $p2Values) {
							$_out = $_lineTotal = array();
							$_out[self::ID] = ++$ir;
							if ($this->typeMark) {
								$_out[$this->typelName] = self::TYPE_LINE;
							}
							$_out[$this->pivotOn[0]] = $p0;
							$_out[$this->pivotOn[1]] = $p1;
							$_out[$this->pivotOn[2]] = $p2;

							foreach (array_keys($this->splits) as $split) {
								$cols = $p2Values[$split];

								foreach (array_keys($this->splits[$split]) as $col) {
									$colValues = $cols[$col];
									for ($ic = 0; $ic < $this->calcscount; $ic++) {
										$k = $this->columnCalcs[$ic];
										$t = sprintf($tpl, $split, $col, $k);
										if ($this->columnPivots[$ic]) {
											$value = call_user_func($this->columnPivots[$ic], $colValues);
										} elseif ($this->columnCounts[$ic]) {
											$value = $parsedCount[$p0][$p1][$p2][$split][$col][$k];
										} else {
											$value = $colValues[$k];
										}
										$_out[$t] = $value;
										if ($this->lineTotal) {
											$_lineTotal[$k] += $value;
										}
										if ($this->pivotTotal) {
											$p0Total[$split][$col][$k] += $value;
											$p1Total[$split][$col][$k] += $value;
										}
										if ($this->fullTotal) {
											$fullTotal[$split][$col][$k] += $value;
										}
									}
								}
							}
							if ($this->lineTotal) {
								for ($ic = 0; $ic < $this->calcscount; $ic++) {
									$k = $this->columnCalcs[$ic];
									$t = $subtitle . $k;
									if ($this->columnPivots[$ic]) {
										$_out[$t] = call_user_func($this->columnPivots[$ic], $_lineTotal);
									} else {
										$_out[$t] = $_lineTotal[$k];
									}
								}
							}
							$out[] = $_out;
						}
					}
					if ($this->pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::ID] = ++$ir;
						if ($this->typeMark) {
							$_out[$this->typelName] = self::TYPE_PIVOT_TOTAL_LEVEL2;
						}
						for ($ip = 0; $ip < $this->pivotscount; $ip++) {
							$pivotOn = $this->pivotOn[$ip];
							if ($ip == 0) {
								$_out[$pivotOn] = $this->totalName . " ({$pivotOn})";
							} else {
								$_out[$pivotOn] = null;
							}
						}
						foreach ($p0Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								for ($ic = 0; $ic < $this->calcscount; $ic++) {
									$k = $this->columnCalcs[$ic];
									$t = sprintf($tpl, $split, $col, $k);
									if ($this->columnPivots[$ic]) {
										$value = call_user_func($this->columnPivots[$ic], $p0Total[$split][$col]);
									} else {
										$value = $p0Total[$split][$col][$k];
									}
									$_out[$t] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->lineTotal) {
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->columnCalcs[$ic];
								$t = $subtitle . $k;
								if ($this->columnPivots[$ic]) {
									$_out[$t] = call_user_func($this->columnPivots[$ic], $_lineTotal);
								} else {
									$_out[$t] = $_lineTotal[$k];
								}
							}
						}
						$out[] = $_out;
					}

					if ($this->pivotTotal) {
						$_out = $_lineTotal = array();
						$_out[self::ID] = ++$ir;
						if ($this->typeMark) {
							$_out[$this->typelName] = self::TYPE_PIVOT_TOTAL_LEVEL1;
						}
						for ($ip = 0; $ip < $this->pivotscount; $ip++) {
							$pivotOn = $this->pivotOn[$ip];
							if ($ip == 0) {
								$_out[$pivotOn] = $this->totalName . " ({$pivotOn}, {$this->pivotOn[1]})";
							} else {
								$_out[$pivotOn] = null;
							}
						}
						foreach ($p1Total as $split => $values) {
							foreach ($values as $col => $colValues) {
								for ($ic = 0; $ic < $this->calcscount; $ic++) {
									$k = $this->columnCalcs[$ic];
									$t = sprintf($tpl, $split, $col, $k);
									if ($this->columnPivots[$ic]) {
										$value = call_user_func($this->columnPivots[$ic], $p1Total[$split][$col]);
									} else {
										$value = $p1Total[$split][$col][$k];
									}
									$_out[$t] = $value;
									$_lineTotal[$k] += $value;
								}
							}
						}
						if ($this->lineTotal) {
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->columnCalcs[$ic];
								$t = $subtitle . $k;
								if ($this->columnPivots[$ic]) {
									$_out[$t] = call_user_func($this->columnPivots[$ic], $_lineTotal);
								} else {
									$_out[$t] = $_lineTotal[$k];
								}
							}
						}
						$out[] = $_out;
					}
				}
				break;
		}
		return $ir;
*/
	}

	/**
	 * @param optional int FETCH_* $fetchtype default 0
	 * @return associative array
	 */
	public function fetch($fetchType = 0)
	{
		list($parsed, $parsedCount) = $this->parse();

		$subtitle = $this->totalName . self::SEP;
		$tpl = '%s'.self::SEP.'%s'.self::SEP.'%s';
		$fullTotal = array();
		$out = array();

		$ir = $this->build($parsed, $parsedCount, $subtitle, $tpl, $fullTotal, $out);

		if ($this->fullTotal) {
			$_out = $_lineTotal = array();
			$_out[self::ID] = ++$ir;
			if ($this->typeMark) {
				$_out[$this->typelName] = self::TYPE_FULL_TOTAL;
			}
			for ($ip = 0; $ip < $this->pivotscount; $ip++) {
				$pivotOn = $this->pivotOn[$ip];
				if ($ip == 0) {
					$_out[$pivotOn] = $this->totalName;
				} else {
					$_out[$pivotOn] = null;
				}
			}
			foreach ($fullTotal as $split => $values) {
				foreach ($values as $col => $colValues) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->columnCalcs[$ic];
						$t = sprintf($tpl, $split, $col, $k);
						if ($this->columnPivots[$ic]) {
							$value = call_user_func($this->columnPivots[$ic], $fullTotal[$split][$col]);
						} else {
							$value = $fullTotal[$split][$col][$k];
						}
						$_out[$t] = $value;
						$_lineTotal[$k] += $value;
					}
				}
			}
			if ($this->lineTotal) {
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->columnCalcs[$ic];
					$t = $subtitle . $k;
					if ($this->columnPivots[$ic]) {
						$_out[$t] = call_user_func($this->columnPivots[$ic], $_lineTotal);
					} else {
						$_out[$t] = $_lineTotal[$k];
					}
				}
			}
			$out[] = $_out;
		}

		if ($out && $fetchType == self::FETCH_STRUCT) {
			return array(
				'splits' => $this->splits,
				'data' => array_map('array_values', $out),
			);
		}
		return $out;
	}
}

