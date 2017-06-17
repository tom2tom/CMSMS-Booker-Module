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

class Pivot2 extends PivotBase
{
	public function __construct()
	{
		call_user_func_array('parent::__construct', func_get_args());
	}

	protected function populate($shortform, $keytemplate, $subtitle)
	{
		// calculate relevant field-sums and field-counts (no callback calculations yet)
		$parsedSum = $parsedCount = $parsedSplit = array();
		$split  = $this->colGrouped[0]; //top-level pivot

		if ($shortform) {
			foreach ($this->recordset as &$row) {
				$k0 = $row[$this->pivotOn[0]];
				$k1 = $row[$this->pivotOn[1]];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					if ($this->colCounts[$ic]) {
						if (isset($parsedCount[$k0][$k1][$row[$split]][$k])) {
							if ($row[$k]) {
								$parsedCount[$k0][$k1][$row[$split]][$k]++;
							}
						} else {
							$parsedCount[$k0][$k1][$row[$split]][$k] = (($row[$k]) ? 1 : 0);
						}
					}
					$v = ($this->colFuncs[$ic]) ? 0 : $row[$k];
					if (isset($parsedSum[$k0][$k1][$row[$split]][$k])) {
						$parsedSum[$k0][$k1][$row[$split]][$k] += $v;
					} else {
						$parsedSum[$k0][$k1][$row[$split]][$k] = $v;
					}
					$parsedSplit[$row[$split]][$k] = $k;
				}
			}
		} else {
			$pivot = $this->colGrouped[1];
			foreach ($this->recordset as &$row) {
				$k0 = $row[$this->pivotOn[0]];
				$k1 = $row[$this->pivotOn[1]];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					if ($this->colCounts[$ic]) {
						if (isset($parsedCount[$k0][$k1][$row[$split]][$row[$pivot]][$k])) {
							if ($row[$k]) {
								$parsedCount[$k0][$k1][$row[$split]][$row[$pivot]][$k]++;
							}
						} else {
							$parsedCount[$k0][$k1][$row[$split]][$row[$pivot]][$k] = (($row[$k]) ? 1 : 0);
						}
					}
					$v = ($this->colFuncs[$ic]) ? 0 : $row[$k];
					if (isset($parsedSum[$k0][$k1][$row[$split]][$row[$pivot]][$k])) {
						$parsedSum[$k0][$k1][$row[$split]][$row[$pivot]][$k] += $v;
					} else {
						$parsedSum[$k0][$k1][$row[$split]][$row[$pivot]][$k] = $v;
					}
					$parsedSplit[$row[$split]][$row[$pivot]][$k] = $k;
				}
			}
		}
		unset($row);

		$out = $fullTotals = array();

		foreach ($parsedSum as $p0 => &$p0Sums) {
			if ($this->pivotTotal) {
				$p0Total = array();
			}

			foreach ($p0Sums as $p1 => &$p1Sums) {
				$_out = array();
				if ($this->typeMark) {
					$_out[$this->typeName] = parent::TYPE_LINE;
				}

				$_out[$this->pivotOn[0]] = $p0;
				$_out[$this->pivotOn[1]] = $p1;

				foreach (array_keys($parsedSplit) as $split) {
					$cols = $p1Sums[$split];

					if ($this->lineTotal) {
						$lineTotals = array();
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$lineTotals[$k] = 0;
						}
					}

					foreach (array_keys($parsedSplit[$split]) as $col) {
						$colSums = $cols[$col]; //scalar ($shortform) or array (!$shortform)
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							if ($shortform) {
								$t = sprintf($keytemplate, $split, $k);
								if ($this->colCounts[$ic]) {
									$v = $parsedCount[$p0][$p1][$split][$k];
								} elseif ($this->colFuncs[$ic]) {
									$v = call_user_func($this->colFuncs[$ic], $colSums);
								} else {
									$v = $cols[$k];
								}
							} else {
								$t = sprintf($keytemplate, $split, $col, $k);
								if ($this->colCounts[$ic]) {
									$v = $parsedCount[$p0][$p1][$split][$col][$k];
								} elseif ($this->colFuncs[$ic]) {
									$v = call_user_func($this->colFuncs[$ic], $colSums);
								} else {
									$v = $colSums[$k];
								}
							}
							$_out[$t] = $v;
							if ($this->lineTotal) {
								$lineTotals[$k] += $v;
							}
							if ($this->pivotTotal) {
								if (isset($p0Total[$split][$col][$k])) {
									$p0Total[$split][$col][$k] += $v;
								} else {
									$p0Total[$split][$col][$k] = $v;
								}
							}
							if ($this->fullTotal) {
								if (isset($fullTotals[$split][$col][$k])) {
									$fullTotals[$split][$col][$k] += $v;
								} else {
									$fullTotals[$split][$col][$k] = $v;
								}
							}
						}
					}
				}

				if ($this->lineTotal) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = $subtitle . $k;
						if ($this->colFuncs[$ic]) {
							$_out[$t] = call_user_func($this->colFuncs[$ic], $lineTotals);
						} else {
							$_out[$t] = $lineTotals[$k];
						}
					}
				}
				$out[] = $_out;
			}
			unset($p1Sums);

			if ($this->pivotTotal) {
				$_out = array();
				if ($this->typeMark) {
					$_out[$this->typeName] = parent::TYPE_PIVOT_TOTAL_LEVEL1;
				}
				for ($ip = 0; $ip < $this->pivotscount; $ip++) {
					$pivotOn = $this->pivotOn[$ip];
					if ($ip == 0) {
						$_out[$pivotOn] = $this->totalName . " ({$pivotOn})";
					} else {
						$_out[$pivotOn] = ''; //null;
					}
				}

				if ($this->lineTotal) {
					$lineTotals = array();
				}

				foreach ($p0Total as $split => $vs) {
					foreach ($vs as $col => $colSums) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$t = sprintf($keytemplate, $split, $col, $k);
							if ($this->colFuncs[$ic]) {
								$v = call_user_func($this->colFuncs[$ic], $p0Total[$split][$col]);
							} else {
								$v = $p0Total[$split][$col][$k];
							}
							$_out[$t] = $v;
							if ($this->lineTotal) {
								if (isset($lineTotals[$k])) {
									$lineTotals[$k] += $v;
								} else {
									$lineTotals[$k] = $v;
								}
							}
						}
					}
				}

				if ($this->lineTotal) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = $subtitle . $k;
						if ($this->colFuncs[$ic]) {
							$_out[$t] = call_user_func($this->colFuncs[$ic], $lineTotals);
						} else {
							$_out[$t] = $lineTotals[$k];
						}
					}
				}
				$out[] = $_out;
			}
		}
		unset($p0Sums);
		return array($out, $fullTotals);
	}
}
