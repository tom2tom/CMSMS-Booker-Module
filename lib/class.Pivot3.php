<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Pivot3 - subclass for pivot table creation-pivot>
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Pivot3 extends PivotBase
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
		$p1 = $this->colPivot[1];
		$p2 = $this->colPivot[2];

		if ($this->groupscount == 0) {
			foreach ($this->Data as &$row) {
				$k0 = $row[$p0];
				$k1 = $row[$p1];
				$k2 = $row[$p2];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$Buckets[$k] = $k;
					$v = $row[$k];
					if ($this->colCounts[$ic]) {
						$v = ($v) ? 1 : 0;
					}
					if (isset($bktValues[$k0][$k1][$k2][$k])) {
						$bktValues[$k0][$k1][$k2][$k] += $v;
					} else {
						$bktValues[$k0][$k1][$k2][$k] = $v;
					}
				}
			}
		} elseif ($this->groupscount == 1) {
			$g0 = $this->colGrouped[0];
			foreach ($this->Data as &$row) {
				$k0 = $row[$p0];
				$k1 = $row[$p1];
				$k2 = $row[$p2];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$Buckets[$row[$g0]][$k] = $k;
					$v = $row[$k];
					if ($this->colCounts[$ic]) {
						$v = ($v) ? 1 : 0;
					}
					if (isset($bktValues[$k0][$k1][$k2][$row[$g0]][$k])) {
						$bktValues[$k0][$k1][$k2][$row[$g0]][$k] += $v;
						} else {
						$bktValues[$k0][$k1][$k2][$row[$g0]][$k] = $v;
					}
				}
			}
		} else { //2 group-columns
			$g0 = $this->colGrouped[0];
			$g1 = $this->colGrouped[1];
			foreach ($this->Data as &$row) {
				$k0 = $row[$p0];
				$k1 = $row[$p1];
				$k2 = $row[$p2];
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$Buckets[$row[$g0]][$row[$g1]][$k] = $k;
					$v = $row[$k];
					if ($this->colCounts[$ic]) {
						$v = ($v) ? 1 : 0;
					}
					if (isset($bktValues[$k0][$k1][$k2][$row[$g0]][$row[$g1]][$k])) {
						$bktValues[$k0][$k1][$k2][$row[$g0]][$row[$g1]][$k] += $v;
					} else {
						$bktValues[$k0][$k1][$k2][$row[$g0]][$row[$g1]][$k] = $v;
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
			if ($this->pivotTotal) {
				$p1Totals = array();
				if ($this->groupscount == 0) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$p1Totals[$k] = 0;
					}
				} else {
					foreach (array_keys($Buckets) as $s0) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$p1Totals[$s0][$k] = 0;
						}
					}
				}
			}

			foreach ($k0Values as $k1 => &$k1Values) {
				if ($this->pivotTotal) {
					$p2Totals = array();
					if ($this->groupscount == 0) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$p2Totals[$k] = 0;
						}
					} elseif ($this->groupscount == 1) {
						foreach (array_keys($Buckets) as $s0) {
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->colCalcs[$ic];
								$p2Totals[$s0][$k] = 0;
							}
						}
					} else {
						foreach (array_keys($Buckets) as $s0) {
							foreach (array_keys($Buckets[$s0]) as $s1) {
								for ($ic = 0; $ic < $this->calcscount; $ic++) {
									$k = $this->colCalcs[$ic];
									$p2Totals[$s0][$s1][$k] = 0;
								}
							}
						}
					}
				}

				foreach ($k1Values as $k2 => &$k2Values) {
					$out1 = array();
					if ($this->typeMark) {
						$out1[$this->typeName] = parent::TYPE_LINE;
					}

					$out1[$p0] = $k0;
					$out1[$p1] = $k1;
					$out1[$p2] = $k2;

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
							$v = $k0Values[$k1][$k2][$k];
							if ($this->colFuncs[$ic]) {
								$v = call_user_func($this->colFuncs[$ic], $v, $k0Values);
							}
							$out1[$t] = $v;
							if ($this->lineTotal) {
								$lineTotals[$k] += $v;
							}
							if ($this->pivotTotal) {
								$p1Totals[$k] += $v;
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
								if ($this->pivotTotal) {
									$p1Totals[$s0][$k] += $v;
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
									if ($this->pivotTotal) {
										$p1Totals[$s0][$k] += $v;
										$p2Totals[$s0][$s1][$k] += $v;
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
				unset($k2Values);
			}
			unset($k1Values);

			if ($this->pivotTotal) {
				$out1 = array();
				if ($this->typeMark) {
					$out1[$this->typeName] = parent::TYPE_PIVOT_TOTAL_LEVEL2;
				}

				$out1[$p0] = $this->subtotalName . " ($p1)";
				$out1[$p1] = ''; //null;
				$out1[$p2] = ''; //null;

				if ($this->lineTotal) {
					$lineTotals =array();
				}

				if ($this->groupscount == 0) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = sprintf($keytemplate, $k);
						$v = $p1Totals[$k];
						if ($this->colFuncs[$ic]) {
							$v = call_user_func($this->colFuncs[$ic], $v, $p1Totals);
						}
						$out1[$t] = $v;
						if ($this->lineTotal) {
							if (isset($lineTotals[$k])) {
								$lineTotals[$k] += $v;
							} else {
								$lineTotals[$k] = $v;
							}
						}
					}
				} elseif ($this->groupscount == 1) {
					foreach ($p1Totals as $s0 => $p2Totals) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$t = sprintf($keytemplate, $s0, $k);
							$v = $p2Totals[$k];
							if ($this->colFuncs[$ic]) {
								$v = call_user_func($this->colFuncs[$ic], $v, $p2Totals);
							}
							$out1[$t] = $v;
							if ($this->lineTotal) {
								if (isset($lineTotals[$k])) {
									$lineTotals[$k] += $v;
								} else {
									$lineTotals[$k] = $v;
								}
							}
						}
					}
				} else {
					foreach ($p1Totals as $s0 => $p2Totals) {
						foreach ($p2Totals as $s1 => $k2Totals) {
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->colCalcs[$ic];
								$t = sprintf($keytemplate, $s0, $s1, $k);
								$v = $k2Totals[$k];
								if ($this->colFuncs[$ic]) {
									$v = call_user_func($this->colFuncs[$ic], $v, $k2Totals);
								}
								$out1[$t] = $v;
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

				$out1 = array();
				if ($this->typeMark) {
					$out1[$this->typeName] = parent::TYPE_PIVOT_TOTAL_LEVEL1;
				}

				$out1[$p0] = $this->subtotalName . " ($p0, $p1)";
				$out1[$p1] = ''; //null;
				$out1[$p2] = ''; //null;

				if ($this->lineTotal) {
					$lineTotals =array();
				}

				if ($this->groupscount == 0) {
//TODO
				} elseif ($this->groupscount == 1) {
//TODO
				} else {
					foreach ($p2Totals as $s0 => $k2Totals) {
						foreach ($k2Totals as $s1 => $k3Totals) {
							for ($ic = 0; $ic < $this->calcscount; $ic++) {
								$k = $this->colCalcs[$ic];
								$t = sprintf($keytemplate, $s0, $s1, $k);
								$v = $k3Totals[$k];
								if ($this->colFuncs[$ic]) {
									$v = call_user_func($this->colFuncs[$ic], $v, $k3Totals);
								}
								$out1[$t] = $v;
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
		}
		unset($k0Values);
		return array($out, $fullTotals);
	}
}
