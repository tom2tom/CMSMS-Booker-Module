<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: RptIntervalPayments - generates content for a specific-format report
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class RptIntervalPayments extends Report
{
	public function __construct(&$mod, &$utils)
	{
		$this->mod = $mod;
		$this->utils = $utils;
	}

	/**
	Get internal-use title/key, and un-ranged displayable title, for this report
	Returns: 2-member array of strings,
	 [0] = internal
	 [1] = public
	*/
	public function Titles()
	{
		return array('rngpay', $this->PublicTitle());
	}

	/**
	Get displayable title for this report
	@after: optional timestamp in first month of report-interval, default FALSE
	@before: optional timestamp in last month of report-interval, default FALSE
	Returns: string
	*/
	public function PublicTitle($after = FALSE, $before = FALSE)
	{
		return $this->CreateTitle('title_booking', 'title_payments', $after, $before);
	}

	/**
	Get relevant bookings-data & related
	@showfrom: optional timestamp for beginning of report-interval, default FALSE
	@showto: optional timestamp for end of report-interval, default FALSE
	Returns: associative array
	*/
	public function GetReportData($showfrom = FALSE, $showto = FALSE)
	{
		//TODO support RepeatsTable too - algorithm for splitting payment across bookings
		$sql = <<<EOS
SELECT D.slotstart,O.fee,O.feepaid,1 AS bkg
FROM {$this->mod->DispTable} D
JOIN {$this->mod->OnceTable} O ON D.bkg_id=O.bkg_id
WHERE D.displayed>0
EOS;
		$args = array();
		if ($showfrom) {
			$sql .= ' AND D.slotstart >= ?';
			$args[] = $showfrom;
		}
		if ($showto) {
			$sql .= ' AND (D.slotstart + D.slotlen) <= ?';
			$args[] = $showto;
		}
		$sql .= ' ORDER BY D.slotstart';
		$data = $this->mod->dbHandle->GetArray($sql, $args);

		if ($data) {
			$dt = new \DateTime('@0', NULL);
			$ic = count($data);
			for ($i = 0; $i < $ic; $i++) {
				$dt->setTimestamp($data[$i]['slotstart']);
				$data[$i]['year'] = $dt->format('Y');
				$data[$i]['month'] = 'M'.(string)($dt->format('n')-1);
			}
		}
		return $data;
	}

	/**
	@data: non-empty array returned from GetReportData()
	Returns: array
	*/
	public function PivotReportData($data)
	{
		$pivoton = array('year','month');
		$group = null;
		$groupvalue = array('bkg','fee','feepaid');
		$funcs = new Pivot2($data, $pivoton, $group, $groupvalue,
			TRUE, //include relevant Pivotbase::TYPE_*
			FALSE, //exclude  per-line subtotals
			TRUE, //include pivoted-field subtotals
			TRUE, //include whole-table totals
			$this->mod->Lang('total'),
			$this->mod->Lang('subtotal')
		);
		return $funcs->fetch();
	}

	/**
	@pivoted: non-empty array returned from PivotReportData()
	@id: module id for link-construction (when @display == TRUE)
	@linkaction: for link-construction (when @display == TRUE)
	@display: optional boolean whether output is for screen-display
	 (as opposed to export), default TRUE
	Returns: 2-member array,
	 [0] = array of strings for table-column titles
	 [1] = array of stdClass objects, each with member ->fields, and if
	   @display == TRUE, xhtml-link-element ->view
	*/
	public function PostProcessData($pivoted, $id, $linkaction, $display = TRUE)
	{
		$translates = array(
			'bkg'=>$this->mod->Lang('count'),
			'fee'=>$this->mod->Lang('title_fees'),
			'feepaid'=>$this->mod->Lang('title_payments'),
			'month'=>$this->mod->Lang('title_month')
		);
		$months = array();
		foreach (explode(',',$this->mod->Lang('longmonths')) as $k => $val) {
			$months['M'.$k] = $val;
		}
		$translates += $months;

		$row = reset($pivoted);
		//interpet titles, and log row-indices of 'fee' fields
		$works = array();
		foreach ($row as $t => $val) {
			$parts = explode('\\', $t);
			foreach ($parts as $k => &$val) {
				switch ($val) {
				 case 'type':
					$parts = FALSE; //type field won't be displayed
					break 2;
				 case 'fee':
				 case 'feepaid':
					$works[] = $t;
					break;
				}
				if ($val[0] == 'M') {
					unset($parts[$k]);
				} elseif (array_key_exists($val, $translates)) {
					$val = $translates[$val];
				}
			}
			unset($val);
			if ($parts) {
				$coltitles[] = implode('<br />', $parts);
			}
		}

		if ($display) {
			$theme = ($this->mod->before20) ? cmsms()->get_variable('admintheme') :
				cms_utils::get_theme_object();
			$t = $this->mod->Lang('tip_seetype',$this->mod->Lang('month'));
			$icon_view = $theme->DisplayImage('icons/system/view.gif',$t,'','','systemicon');
		}
		$subtotal = $this->mod->Lang('subtotal');

		$output = array();
		$ic = count($pivoted);
		for ($i = 0; $i < $ic; $i++) {
			$row = $pivoted[$i];
			$dataline = ($row['type'] == PivotBase::TYPE_LINE);
			unset($row['type']);
			$yid = $row['year'];
			if ($dataline) {
				$current = $yid;
				$row['month'] = $months[$row['month']];
			} elseif (strpos($yid,$subtotal) !== FALSE) {
				$row['year'] = str_replace('year',$current,$yid);
			}
			//interpret 'fee*'
			foreach ($works as $t) {
				if (isset($row[$t])) {
					$row[$t] = number_format($row[$t],2); //TODO generalize
				}
			}

			if ($display) {
				$oneset = new \stdClass();
				$oneset->fields = array_values($row);
				$oneset->view = ($dataline) ? $this->mod->CreateLink($id, $linkaction, '', $icon_view,
					array('filter' => 1)) : NULL; //TODO all link $params[]
				$output[] = $oneset;
			} else {
				$output[] = array_values($row);
			}
		}
		return array($coltitles,$output);
	}
}
