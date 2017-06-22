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
		//TODO support RepeatsTable too - algorithm for payment-splitting across bookings
		$sql = <<<EOS
SELECT D.slotstart,O.fee,O.feepaid,1 AS bkg
FROM $this->DispTable D
JOIN $this->OnceTable O ON D.bkg_id=O.bkg_id
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
		);

		$row = reset($pivoted);
		//interpet titles, and log row-indices of slotlen fields
		$works = array();
		foreach ($row as $t => $val) {
			$parts = explode('\\', $t);
			foreach ($parts as $k => &$val) {
				switch ($val) {
				 case 'type':
					$parts = FALSE; //type field won't be displayed
					break 2;
				 case 'slotlen':
					$works[] = $t;
					break;
				}
				if (0) {
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
			$t = $this->mod->Lang('tip_seetype', $this->mod->Lang('payments'));
			$icon_view = $theme->DisplayImage('icons/system/view.gif', $t, '', '', 'systemicon');
		}
		$translates = $this->mod->dbHandle->GetAssoc();
		foreach ($translates as &$t) {
		{
			if (0) {
			}
		}
		unset($t);
		$subtotal = $this->mod->Lang('subtotal');

		$output = array();
		$ic = count($pivoted);
		for ($i = 0; $i < $ic; $i++) {
			$row = $pivoted[$i];
			$dataline = ($row['type'] == PivotBase::TYPE_LINE);
			unset($row['type']);
			if ($dataline) {
			} elseif (0) {
			}
			//interpret
			foreach ($works as $t) {
				if (0) {
					$row[$t] = 0;
				}
			}

			if ($display) {
				$oneset = new \stdClass();
				$oneset->fields = array_values($row);
				$oneset->view = ($dataline) ? $this->mod->CreateLink($id, $linkaction, '', $icon_view,
					array('filter' => 1, 'item_id' => $iid)) : NULL; //TODO all link $params[]
				$output[] = $oneset;
			} else {
				$output[] = array_values($row);
			}
		}
		return array($coltitles,$output);
	}
}
