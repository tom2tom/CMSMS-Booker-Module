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
	Sub-class this for specific pivotfields-counts
	*/
	protected function parse()
	{
		return array($parsed,$parsedCount);
	}

	/*
	Sub-class this for specific pivotfields-counts
	*/
	protected function build($parsed, $parsedCount, $subtitle, $tpl, &$fullTotal, &$out)
	{
		return $ir;
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

