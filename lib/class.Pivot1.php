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

class Pivot1 extends PivotBase
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
			for ($ic = 0; $ic < $this->calcscount; $ic++) {
				if ($this->colFuncs[$ic]) {
					continue;
				}
				$k = $this->colCalcs[$ic];
				if ($pivot) {
					if ($this->colCounts[$ic] && $row[$k]) {
						$parsedCount[$k0][$row[$split]][$row[$pivot]][$k]++;
					}
					$parsedSum[$k0][$row[$split]][$row[$pivot]][$k] += $row[$k];
					$parsedSplit[$row[$split]][$row[$pivot]][$k] = $k;
				} else {
					if ($this->colCounts[$ic] && $row[$k]) {
						$parsedCount[$k0][$row[$split]][$k]++;
					}
					$parsedSum[$k0][$row[$split]][$k] += $row[$k];
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
			$_out = $_lineTotal = array();
			if ($this->idMark) {
				$_out[parent::ID] = ++$ir;
			}
			if ($this->typeMark) {
				$_out[$this->typeName] = parent::TYPE_LINE;
			}

			$_out[$this->pivotOn[0]] = $p0;

			foreach (array_keys($parsedSplit) as $split) {
				$cols = $p0Sums[$split];

				foreach (array_keys($parsedSplit[$split]) as $col) {
					$colSums = $cols[$col];

					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = sprintf($tpl, $split, $col, $k);
						if ($this->colCounts[$ic]) {
							$value = $parsedCount[$p0][$split][$col][$k];
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
		unset($p0Sums);
		return array($out, $fullTotal);
	}
}
