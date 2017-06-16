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
	const ID = '_id'; //enumerator-title key
	const SEP = '\\'; //title-element separator
	//returned data format specifiers
	const FETCH_STRUCT = 1;
	//data-line type/content specifiers
	const TYPE_LINE = 0;
	const TYPE_PIVOT_TOTAL_LEVEL1 = 1;
	const TYPE_PIVOT_TOTAL_LEVEL2 = 2;
	const TYPE_FULL_TOTAL = 3;

	protected $recordset;
	protected $pivotOn;
	protected $pivotscount;
	protected $colGrouped;
	protected $colCalcs;
	protected $colFuncs;
	protected $colCounts;
	protected $calcscount;
	protected $idMark;
	protected $typeMark;
	protected $lineTotal;
	protected $pivotTotal;
	protected $fullTotal;
	protected $totalName;
	protected $typeName;

	/**
	 * @recordset array of associative arrays to be processed
	 * @pivoton single, or 1..3-member array of, @recordset key name(s)
	 * @group optional single, or 1..2-member array of, @recordset key name(s)
	 *  whose values are to be grouped, default null
	 * @groupvalue optional single, or array of, $@recordset key name(s)
	 *  whose values are to be summed in each group, and/or calculated key(s),
	 *  default null
	 * @showtype optional boolean, default false
	 * @linetotal optional boolean, default false
	 * @pivottotal optional boolean, default false
	 * @fulltotal optional boolean, default false
	 * @total optional string title, default 'TOT'
	 * @type optional string title, default 'type'
	 */
	public function __construct($recordset, $pivoton,
		$group = null, $groupvalue = null, $showtype = false,
		$linetotal = false, $pivottotal = false, $fulltotal = false,
		$total = 'TOT', $type = 'type')
	{
		$this->recordset = $recordset;
		$this->pivotOn = (is_array($pivoton)) ? $pivoton : array($pivoton);
		$this->pivotscount = count($this->pivotOn);
		$this->colGrouped = (is_array($group)) ? $group : (($group) ? array($group) : null);
		$this->colCalcs = (is_array($groupvalue)) ? $groupvalue : (($groupvalue) ? array($groupvalue) : null);
		$this->calcscount = ($groupvalue) ? count($this->colCalcs) : 0;
		$this->colFuncs = array_fill(0, $this->calcscount, 0);
		$this->colCounts = array_fill(0, $this->calcscount, 0);
		for ($ic = 0; $ic < $this->calcscount; $ic++) {
			$item = $this->colCalcs[$ic];
			if (is_array($item)) {
				$this->colCalcs[$ic] = $item[0];
				if ($item[1] == 'count') {
					$this->colCounts[$ic] = 1;
				} elseif (is_callable($item[1], false, $callable_name)) {
					if ($callable_name) {
						$this->colFuncs[$ic] = $callable_name;
					} else {
						$this->colFuncs[$ic] = $item[1]; //anonymous?
					}
				}
			}
		}
		$this->idMark = false;
		$this->typeMark = $showtype;
		$this->lineTotal = $linetotal;
		$this->pivotTotal = $pivottotal;
		$this->fullTotal = $fulltotal;
		$this->totalName = $total;
		$this->typeName = $type;
	}

	/*
	Calculate relevant field-sum and field-count values (no callback calculations)
	Subclass this for specific pivotfields-counts
	Returns: 3-member array
	*/
	protected function aggregate()
	{
		return array($parsedSum, $parsedCount, $parsedSplit);
	}

	/*
	Subclass this for specific pivotfields-counts
	@parsedSum: array returned by aggregate()
	@parsedCount: array returned by aggregate()
	@parsedSplit: array returned by aggregate()
	@subtitle:
	@tpl:
	Returns: 2-member array
	*/
	protected function populate($parsedSum, $parsedCount, $parsedSplit, $subtitle, $tpl)
	{
		return array($out, $fullTotals);
	}

	/**
	 * Get pivoted data per self::recordset and @fetchtype
	 * @fetchtype optional int FETCH_*, default 0
	 * Returns: associative array
	 */
	public function fetch($fetchType = 0)
	{
		list($parsedSum, $parsedCount, $parsedSplit) = $this->aggregate();
		//output-key formatters
		$subtitle = $this->totalName . self::SEP;
		$tpl = '%s'.self::SEP.'%s'.self::SEP.'%s';

		list($out, $fullTotals) = $this->populate($parsedSum, $parsedCount, $parsedSplit, $subtitle, $tpl);

		if ($this->fullTotal) {
			$_out = $lineTotals = array();
			if ($this->idMark) {
				$_out[self::ID] = count($out) + 1;
			}
			if ($this->typeMark) {
				$_out[$this->typeName] = self::TYPE_FULL_TOTAL;
			}
			for ($ip = 0; $ip < $this->pivotscount; $ip++) {
				$pivotOn = $this->pivotOn[$ip];
				if ($ip == 0) {
					$_out[$pivotOn] = $this->totalName;
				} else {
					$_out[$pivotOn] = ''; //null;
				}
			}

			foreach ($fullTotals as $split => $values) {
				foreach ($values as $col => $colSums) {
					for ($ic = 0; $ic < $this->calcscount; $ic++) {
						$k = $this->colCalcs[$ic];
						$t = sprintf($tpl, $split, $col, $k);
						if ($this->colFuncs[$ic]) {
							$value = call_user_func($this->colFuncs[$ic], $fullTotals[$split][$col]);
						} else {
							$value = $fullTotals[$split][$col][$k];
						}
						$_out[$t] = $value;
						if ($this->lineTotal) {
							$lineTotals[$k] += $value;
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

		if ($out && $fetchType == self::FETCH_STRUCT) {
			return array(
				'splits' => $parsedSplit,
				'data' => array_map('array_values', $out),
			);
		}
		return $out;
	}
}

