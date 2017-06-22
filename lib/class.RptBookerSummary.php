<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: RptBookerSummary - generates content for a specific-format report
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class RptBookerSummary extends Report
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
		return array('bkrovr', $this->PublicTitle());
	}

	/**
	Get displayable title for this report
	@after: optional timestamp in first month of report-interval, default FALSE
	@before: optional timestamp in last month of report-interval, default FALSE
	Returns: string
	*/
	public function PublicTitle($after = FALSE, $before = FALSE)
	{
		return $this->CreateTitle('title_booker', 'title_bookings', $after, $before);
	}

	/**
	Get relevant bookings-data & related
	@showfrom: optional timestamp for beginning of report-interval, default FALSE
	@showto: optional timestamp for end of report-interval, default FALSE
	Returns: associative array
	*/
	public function GetReportData($showfrom = FALSE, $showto = FALSE)
	{
		$sql = <<<EOS
SELECT booker_id,(bulk=0) AS singl,(bulk=1) AS grp,(bulk>=20) AS rept,1 AS bkg,slotstart,slotlen
FROM {$this->mod->DispTable}
WHERE displayed>0
EOS;
		$args = array();
		if ($showfrom) {
			$sql .= ' AND slotstart >= ?';
			$args[] = $showfrom;
		}
		if ($showto) {
			$sql .= ' AND (slotstart + slotlen) <= ?';
			$args[] = $showto;
		}
		$sql .= ' ORDER BY booker_id,slotstart';
		$data = $this->mod->dbHandle->GetArray($sql, $args);

		if ($data) {
			$dt = new \DateTime('@0', NULL);
			$ic = count($data);
			for ($i = 0; $i < $ic; $i++) {
				$dt->setTimestamp($data[$i]['slotstart']);
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
		$pivoton = array('booker_id','month');
		$group = null;
		$groupvalue = array(array('singl','count'),array('rept','count'),'bkg','slotlen');
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
		$rs = $this->mod->dbHandle->SelectLimit('SELECT item_id FROM '.$this->mod->ItemTable.' WHERE item_id<'.Booker::MINGRPID,1,1);
		if (!$rs->EOF) {
			$row = $rs->FetchRow();
			list($t,$slen) = $this->SlotParameters($row['item_id']);
		} else {
			//TODO better default from module preferences
			$t = $this->Lang('title_hours');
			$slen = 3600;
		}
		$rs->Close();

		$translates = array(
			'booker_id' => $this->mod->Lang('title_name'),
			'bkg' => $this->mod->Lang('count'),
			'grp' => $this->mod->Lang('bkgtype_grouped'),
			'month' => $this->mod->Lang('title_month'),
			'rept' => $this->mod->Lang('bkgtype_repeated'),
			'singl' => $this->mod->Lang('bkgtype_single'),
			'slotlen' => $t
		);
		$months = array();
		foreach (explode(',',$this->mod->Lang('longmonths')) as $k => $val) {
			$months['M'.$k] = $val;
		}
		$translates += $months;

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
			$t = $this->mod->Lang('tip_seetype', $this->mod->Lang('booking_multi'));
			$icon_view = $theme->DisplayImage('icons/system/view.gif', $t, '', '', 'systemicon');
		}
		$sql = <<<EOS
SELECT B.booker_id,COALESCE(A.name,B.name,'') AS name,B.publicid
FROM {$this->mod->BookerTable} B
LEFT JOIN {$this->mod->AuthTable} A ON B.publicid=A.publicid
ORDER BY B.booker_id
EOS;
		$translates = $this->mod->dbHandle->GetAssoc($sql);
		if ($translates) {
			$this->utils->GetUserProperties($this->mod,$translates);
			foreach ($translates as &$row) {
				if ($row['name']) {
					$row = $row['name'];
				} elseif ($row['publicid']) {
					$row = $row['publicid'];
				} else {
					$row = '&lt;'.$this->mod->Lang('noname').'&gt;';
				}
			}
			unset($row);
		}
		$subtotal = $this->mod->Lang('subtotal');

		$output = array();
		$ic = count($pivoted);
		for ($i = 0; $i < $ic; $i++) {
			$row = $pivoted[$i];
			$dataline = ($row['type'] == PivotBase::TYPE_LINE);
			unset($row['type']);
			$bid = $row['booker_id'];
			if ($dataline) {
				$current = $translates[$bid]; //booker name
				$row['booker_id'] = $current;
				$row['month'] = $months[$row['month']];
			} elseif (strpos($bid, $subtotal) !== FALSE) {
				$row['booker_id'] = str_replace('booker_id',$current,$bid);
			}
			//interpret *\'slotlen'
			foreach ($works as $t) {
				if (isset($row[$t])) {
					$row[$t] = round(($row[$t] / $slen),1);
				}
			}

			if ($display) {
				$oneset = new \stdClass();
				$oneset->fields = array_values($row);
				$oneset->view = ($dataline) ? $this->mod->CreateLink($id, $linkaction, '', $icon_view,
					array('filter' => 1,'booker_id' => $bid)) : NULL; //TODO all link $params[]
				$output[] = $oneset;
			} else {
				$output[] = array_values($row);
			}
		}
		return array($coltitles,$output);
	}
}
