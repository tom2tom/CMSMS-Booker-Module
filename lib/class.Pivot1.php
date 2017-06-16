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

	protected function parse()
	{
		$parsed = $parsedCount = array();
		$split  = $this->column[0];
		$column = (!empty($this->column[1])) ? $this->column[1] : null; //CHECKME column[0] ?

		foreach ($this->recordset as &$row) {
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
		}
		unset($row);
		return array($parsed,$parsedCount);
	}

	protected function build($parsed, $parsedCount, $subtitle, $tpl, &$fullTotal, &$out)
	{
		$ir = 0;

		foreach ($parsed as $p0 => &$p0Values) {
			$_out = $_lineTotal = array();
			if (0) {
				$_out[parent::ID] = ++$ir;
			}
			if ($this->typeMark) {
				$_out[$this->typelName] = parent::TYPE_LINE;
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
		unset($p0Values);
		return $ir;
	}
}
