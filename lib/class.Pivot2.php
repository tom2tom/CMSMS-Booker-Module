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

	protected function aggregate()
	{
		$parsedSum = $parsedCount = $parsedSplit = array();
		$split  = $this->colGrouped[0]; //top-level pivot
		//TODO support extra pivot-column(s)
		$pivot = (!empty($this->colGrouped[1])) ? $this->colGrouped[1] : null;

		foreach ($this->recordset as &$row) {
			$k0 = $row[$this->pivotOn[0]];
			$k1 = $row[$this->pivotOn[1]];
			for ($ic = 0; $ic < $this->calcscount; $ic++) {
				if ($this->colFuncs[$ic]) {
					continue;
				}
				$k = $this->colCalcs[$ic];
				if ($pivot) {
					if ($this->colCounts[$ic] && $row[$k]) {
						$parsedCount[$k0][$k1][$row[$split]][$row[$pivot]][$k]++;
					}
					$parsedSum[$k0][$k1][$row[$split]][$row[$pivot]][$k] += $row[$k];
					$parsedSplit[$row[$split]][$row[$pivot]][$k] = $k;
				} else {
					if ($this->colCounts[$ic] && $row[$k]) {
						$parsedCount[$k0][$k1][$row[$split]][$k]++;
					}
					$parsedSum[$k0][$k1][$row[$split]][$k] += $row[$k];
					$parsedSplit[$row[$split]][$k] = $k;
				}
			}
		}
		unset($row);
		return array($parsedSum, $parsedCount, $parsedSplit);
	}

	protected function build($parsedSum, $parsedCount, $parsedSplit, $subtitle, $tpl)
	{
		$ir = 0;
		$out = $fullTotal = array();

		foreach ($parsedSum as $p0 => &$p0Sums) {
			$p0Total = array();

			foreach ($p0Sums as $p1 => &$p1Sums) {
				$_out = $_lineTotal = array();
				if ($this->idMark) {
					$_out[parent::ID] = ++$ir;
				}
				if ($this->typeMark) {
					$_out[$this->typeName] = parent::TYPE_LINE;
				}

				$_out[$this->pivotOn[0]] = $p0;
				$_out[$this->pivotOn[1]] = $p1;

				foreach (array_keys($parsedSplit) as $split) {
					$cols = $p1Sums[$split];

					foreach (array_keys($parsedSplit[$split]) as $col) {
						$colSums = $cols[$col];
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$t = sprintf($tpl, $split, $col, $k);
							if ($this->colCounts[$ic]) {
								$value = $parsedCount[$p0][$p1][$split][$col][$k];
								if (!$value) {
									$value = 0;
								}
							} elseif ($this->colFuncs[$ic]) {
								$value = call_user_func($this->colFuncs[$ic], $colSums);
							} else {
								$value = $colSums[$k];
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
						$k = $this->colCalcs[$ic];
						$t = $subtitle . $k;
						if ($this->colFuncs[$ic]) {
							$_out[$t] = call_user_func($this->colFuncs[$ic], $_lineTotal);
						} else {
							$_out[$t] = $_lineTotal[$k];
						}
					}
				}
				$out[] = $_out;
			}
			unset($p1Sums);

			if ($this->pivotTotal) {
				$_out = $_lineTotal = array();
				if ($this->idMark) {
					$_out[parent::ID] = ++$ir;
				}
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
				foreach ($p0Total as $split => $values) {
					foreach ($values as $col => $colSums) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$t = sprintf($tpl, $split, $col, $k);
							if ($this->colFuncs[$ic]) {
								$value = call_user_func($this->colFuncs[$ic], $p0Total[$split][$col]);
							} else {
								$value = $p0Total[$split][$col][$k];
							}
							$_out[$t] = $value;
							if ($this->lineTotal) {
								$_lineTotal[$k] += $value;
							}
						}
					}
				}

				if ($this->lineTotal) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = $subtitle . $k;
						if ($this->colFuncs[$ic]) {
							$_out[$t] = call_user_func($this->colFuncs[$ic], $_lineTotal);
						} else {
							$_out[$t] = $_lineTotal[$k];
						}
					}
				}
				$out[] = $_out;
			}
		}
		unset($p0Sums);
		return array($out, $fullTotal);
	}
}
