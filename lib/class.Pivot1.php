<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Pivot1 - subclass for pivot table creation
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

	protected function populate($keytemplate)
	{
		// calculate relevant field-sums and field-counts (no callback calculations yet)
		$Buckets = $bktValues = array();
		$p0 = $this->colPivot[0];

		if ($this->groupscount == 0) {
			foreach ($this->Data as &$row) {
				$k0 = $row[$p0];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$Buckets[$k] = $k;
					$v = $row[$k];
					if ($this->colCounts[$ic]) {
						$v = ($v) ? 1 : 0;
					}
					if (isset($bktValues[$k0][$k])) {
						$bktValues[$k0][$k] += $v;
					} else {
						$bktValues[$k0][$k] = $v;
					}
				}
			}
		} elseif ($this->groupscount == 1) {
			$g0 = $this->colGrouped[0];
			foreach ($this->Data as &$row) {
				$k0 = $row[$p0];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$Buckets[$row[$g0]][$k] = $k;
					$v = $row[$k];
					if ($this->colCounts[$ic]) {
						$v = ($v) ? 1 : 0;
					}
					if (isset($bktValues[$k0][$row[$g0]][$k])) {
						$bktValues[$k0][$row[$g0]][$k] += $v;
					} else {
						$bktValues[$k0][$row[$g0]][$k] = $v;
					}
				}
			}
		} else { //2 group-columns
			$g0 = $this->colGrouped[0];
			$g1 = $this->colGrouped[1];
			foreach ($this->Data as &$row) {
				$k0 = $row[$p0];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$Buckets[$row[$g0]][$row[$g1]][$k] = $k;
					$v = $row[$k];
					if ($this->colCounts[$ic]) {
						$v = ($v) ? 1 : 0;
					}
					if (isset($bktValues[$k0][$row[$g0]][$row[$g1]][$k])) {
						$bktValues[$k0][$row[$g0]][$row[$g1]][$k] += $v;
					} else {
						$bktValues[$k0][$row[$g0]][$row[$g1]][$k] = $v;
					}
				}
			}
		}
		unset($row);

		$out = array();

		if ($this->fullTotal) {
			$fullTotals = array();
			if ($this->groupscount == 0) {
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$fullTotals[$k] = 0;
				}
			} elseif ($this->groupscount == 1) {
				foreach (array_keys($Buckets) as $k0) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$fullTotals[$k0][$k] = 0;
					}
				}
			} else {
				foreach (array_keys($Buckets) as $k0) {
					foreach (array_keys($Buckets[$k0]) as $k1) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$fullTotals[$k0][$k1][$k] = 0;
						}
					}
				}
			}
		} else {
			$fullTotals = false;
		}

		foreach ($bktValues as $k0 => &$k0Values) {
			$out1 = array();
			if ($this->typeMark) {
				$out1[$this->typeName] = parent::TYPE_LINE;
			}

			$out1[$p0] = $k0;

			if ($this->lineTotal) {
				$lineTotals = array();
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$lineTotals[$k] = 0;
				}
			}

			if ($this->groupscount == 0) {
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$t = sprintf($keytemplate, $k);
					$v = $k0Values[$k];
					if ($this->colFuncs[$ic]) {
						$v = call_user_func($this->colFuncs[$ic], $v, $k0Values);
					}
					$out1[$t] = $v;
					if ($this->lineTotal) {
						$lineTotals[$k] += $v;
					}
					if ($this->fullTotal) {
						$fullTotals[$k] += $v;
					}
				}
			} elseif ($this->groupscount == 1) {
					foreach (array_keys($Buckets) as $s0) {
						$k1Values = $k0Values[$s0];
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = sprintf($keytemplate, $s0, $k);
						$v = $k1Values[$k];
						if ($this->colFuncs[$ic]) {
							$v = call_user_func($this->colFuncs[$ic], $v, $k1Values);
						}
						$out1[$t] = $v;
						if ($this->lineTotal) {
							$lineTotals[$k] += $v;
						}
						if ($this->fullTotal) {
							$fullTotals[$s0][$k] += $v;
						}
					}
				}
			} else { //2 group-columns
				foreach (array_keys($Buckets) as $s0) {
					$k1Values = $k0Values[$s0];
					foreach (array_keys($Buckets[$s0]) as $s1) {
						$k2Values = $k1Values[$s1];
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$t = sprintf($keytemplate, $s0, $s1, $k);
							$v = $k2Values[$k];
							if ($this->colFuncs[$ic]) {
								$v = call_user_func($this->colFuncs[$ic], $v, $k2Values);
							}
							$out1[$t] = $v;
							if ($this->lineTotal) {
								$lineTotals[$k] += $v;
							}
							if ($this->fullTotal) {
								$fullTotals[$s0][$s1][$k] += $v;
							}
						}
					}
				}
			}

			if ($this->lineTotal) {
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$t = $this->totalName . self::SEP . $k;
					$v = $lineTotals[$k];
					if ($this->colFuncs[$ic]) {
						$v = call_user_func($this->colFuncs[$ic], $v, $lineTotals);
					}
					$out1[$t] = $v;
				}
			}
			$out[] = $out1;
		}
		unset($k0Values);
		return array($out, $fullTotals);
	}
}