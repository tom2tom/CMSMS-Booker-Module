<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: PivotBase - pivot table functions
# Inspired by gam-pivot <https://github.com/gonzalo123/gam-pivot>
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class PivotBase
{
	const SEP = '\\'; //title-element separator
	//field-value grouping-operation specifiers
	const GRP_SUM = 0;
	const GRP_COUNT = 1;
	const GRP_ANY = 2;
	const GRP_MAX = 3;
	const GRP_MIN = 4;
	const GRP_FIRST = 5;
	const GRP_LAST = 6;
	const GRP_CALL = 7;
	//data-line type/content specifiers
	const TYPE_LINE = 0;
	const TYPE_PIVOT_TOTAL_LEVEL1 = 1;
	const TYPE_PIVOT_TOTAL_LEVEL2 = 2;
	const TYPE_FULL_TOTAL = 3;

	public $Data;
	public $colPivot;
	public $colGrouped;
	public $colCalcs;
	protected $colOps; //self::GRP* enums for fields whose values are to be 'internally' processed
	protected $colFuncs; //callables for GRP_CALL value determination
	protected $pivotscount;
	protected $groupscount;
	protected $calcscount;
	protected $typeMark;
	protected $lineTotal;
	protected $pivotTotal;
	protected $fullTotal;
	protected $totalName;
	protected $subtotalName;
	protected $typeName;

	/**
	 * @data array of associative arrays to be processed
	 * @pivoton single, or 1..3-member array of, @data key name(s)
	 * @group optional single, or 1..2-member array of, @data key name(s)
	 *  whose values are to be grouped, default null (hence last @pivoton value is used)
	 * @groupvalue optional single, or array of, 'process instructions' for the
	 *  @data key name(s) whose values are to be used in each group, and/or
	    calculated key(s) derived from the corresponding row of @data, default null
	 *  Those instruction(s) may each be a scalar (in which case the field
	 *  values will be summed), or a 2-member array. In the latter case, the
	 *  1st member is the field-key, and the 2nd indicates how the field values
	 *  are to be processed, EITHER:
	 *  one of strings 'sum' (optional, defult),'count','any','max','min','first','last' OR
	 *  a callable like func(a,b), where a = sum of field values, b = array of all corresponding sums
	 * @showtype optional boolean, default false
	 * @linetotal optional boolean, default false
	 * @pivottotal optional boolean, default false
	 * @fulltotal optional boolean, default false
	 * @total optional string title, default 'TOT'
	 * @subtotal optional string title, default 'SUBTOT'
	 * @type optional string title, default 'type'
	 */
	public function __construct($data, $pivoton,
		$group = null, $groupvalue = null, $showtype = false,
		$linetotal = false, $pivottotal = false, $fulltotal = false,
		$total = 'TOT', $subtotal = 'SUBTOT', $type = 'type')
	{
		$this->setdata($data, $pivoton, $group, $groupvalue);
		$this->typeMark = $showtype;
		$this->lineTotal = $linetotal;
		$this->pivotTotal = $pivottotal;
		$this->fullTotal = $fulltotal;
		$this->totalName = $total;
		$this->subtotalName = $subtotal;
		$this->typeName = $type;
	}

	/**
	 * See __construct()
	 */
	public function setdata($data, $pivoton, $group = null, $groupvalue = null)
	{
		$this->Data = $data;
		$this->colPivot = (is_array($pivoton)) ? $pivoton : array($pivoton);
		$this->colGrouped = (is_array($group)) ? $group : (($group) ? array($group) : null);
		$this->colCalcs = (is_array($groupvalue)) ? $groupvalue : (($groupvalue) ? array($groupvalue) : null);
	}

	/*
	Subclass this for specific $pivotscount values
	@keytemplate:
	@subtitle:
	Returns: 2-member array
	*/
	protected function populate($keytemplate)
	{
		return array($out, $fullTotals);
	}

	/**
	 * Get pivoted data derived from self::Data
	 * Returns: associative array or empty array
	 */
	public function fetch()
	{
		//TODO confirm all colGrouped (if any) are self::Data keys
		$this->pivotscount = count($this->colPivot);
		$this->calcscount = ($this->colCalcs) ? count($this->colCalcs) : 0;
		$this->colOps = array();
		$this->colFuncs = array_fill(0, $this->calcscount, 0);
		for ($ic = 0; $ic < $this->calcscount; $ic++) {
			$item = $this->colCalcs[$ic];
			if (is_array($item)) {
				$this->colCalcs[$ic] = $item[0];
				switch ($item[1]) {
					case 'count':
						$v = self::GRP_COUNT;
					case 'sum':
						$v = self::GRP_SUM;
						break;
					case 'any':
						$v = self::GRP_ANY;
						break;
					case 'max':
						$v = self::GRP_MAX;
						break;
					case 'min':
						$v = self::GRP_MIN;
						break;
					case 'first':
						$v = self::GRP_FIRST;
						break;
					case 'last':
						$v = self::GRP_LAST;
						break;
					default:
						if (is_callable($item[1], false, $callable_name)) {
							$v = self::GRP_CALL;
							if ($callable_name) {
								$this->colFuncs[$ic] = $callable_name;
							} else {
								$this->colFuncs[$ic] = $item[1]; //anonymous?
							}
						} else {
							$v = self::GRP_SUM;
						}
						break;
				}
			} else {
				$v = self::GRP_SUM;
			}
			$this->colOps[$ic] = $v;
		}

		$this->groupscount = count($this->colGrouped);
		//output-formatter
		switch ($this->groupscount) {
			case 0;
				$keytemplate = '%s';
				break;
			case 1:
				$keytemplate = '%s' . self::SEP . '%s';
				break;
			default:
				$keytemplate = '%s' . self::SEP . '%s' . self::SEP . '%s';
				break;
		}

		list($out, $fullTotals) = $this->populate($keytemplate);
		if (!$out) {
			return array();
		}

		if ($this->fullTotal) {
			$out1 = array();
			if ($this->typeMark) {
				$out1[$this->typeName] = self::TYPE_FULL_TOTAL;
			}
			for ($ip = 0; $ip < $this->pivotscount; $ip++) {
				$t = $this->colPivot[$ip];
				if ($ip == 0) {
					$out1[$t] = $this->totalName;
				} else {
					$out1[$t] = ''; //null;
				}
			}

			if ($this->lineTotal) {
				$lineTotals = array();
			}

			if ($this->groupscount == 0) {
				for ($ic = 0; $ic < $this->calcscount; $ic++) {
					$k = $this->colCalcs[$ic];
					$t = sprintf($keytemplate, $k);
					$v = $fullTotals[$k];
					if ($this->colFuncs[$ic]) {
						$v = call_user_func($this->colFuncs[$ic], $v, $fullTotals);
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
				foreach ($fullTotals as $s0 => $fullGroup) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = sprintf($keytemplate, $g0, $k);
						$v = $fullGroup[$k];
						if ($this->colFuncs[$ic]) {
							$v = call_user_func($this->colFuncs[$ic], $v, $fullGroup);
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
			} else { //2 group-columns
				foreach ($fullTotals as $s0 => $fullGroup) {
					foreach ($fullGroup as $s1 => $colSums) {
						for ($ic = 0; $ic < $this->calcscount; $ic++) {
							$k = $this->colCalcs[$ic];
							$t = sprintf($keytemplate, $g0, $g1, $k);
							$v = $colSums[$k];
							if ($this->colFuncs[$ic]) {
								$v = call_user_func($this->colFuncs[$ic], $v, $colSums);
							}
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
		return $out;
	}
}
