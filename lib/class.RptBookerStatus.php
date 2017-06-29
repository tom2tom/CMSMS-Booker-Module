<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: RptBookerStatus - generates content for a specific-format report
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class RptBookerStatus extends Report
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
		return array('bkrstat', $this->PublicTitle());
	}

	/**
	Get displayable title for this report
	@after: optional timestamp in first month of report-interval, default FALSE
	@before: optional timestamp in last month of report-interval, default FALSE
	Returns: string
	*/
	public function PublicTitle($after = FALSE, $before = FALSE)
	{
		$t = $this->mod->Lang('title_booker').' '.$this->mod->Lang('title_bookings');
		return $this->CreateTitle($t, 'status', $after, $before);
	}

	/**
	Get relevant bookings-data & related
	@showfrom: optional timestamp for beginning of report-interval, default FALSE
	@showto: optional timestamp for end of report-interval, default FALSE
	Returns: associative array
	*/
	public function GetReportData($showfrom = FALSE, $showto = FALSE)
	{
		$s = \Booker::STATNONE;
		$p = \Booker::STATFREE;
		$m = \Booker::STATMAXREQ;
		$x = \Booker::STATSELFREC;
		$sql = <<<EOS
SELECT D.booker_id,D.slotstart,
COALESCE (O.status,R.status,{$s}) AS status, COALESCE(O.statpay,R.statpay,{$p}) AS statpay,
1 AS bkg
FROM {$this->mod->DispTable} D
LEFT JOIN {$this->mod->OnceTable} O ON D.bkg_id=O.bkg_id
LEFT JOIN {$this->mod->RepeatTable} R ON D.bkg_id=R.bkg_id
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
		$sql .= " HAVING status<={$m} OR status>{$x} ORDER BY D.booker_id,D.slotstart";
		return $this->mod->dbHandle->GetArray($sql,$args);
	}

	/**
	@data: non-empty array returned from GetReportData()
	Returns: array
	*/
	public function PivotReportData($data)
	{
		$pivoton = array('booker_id','status','statpay');
		$group = null;
		$groupvalue = array('bkg');
		$funcs = new Pivot3($data, $pivoton, $group, $groupvalue,
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
	 [1] = if @display == TRUE,
	   array of stdClass objects, each with member ->fields and xhtml-link-element ->view
	   otherwise, array of values
	*/
	public function PostProcessData($pivoted, $id, $linkaction, $display = TRUE)
	{
		$translates = array(
			'booker_id'=>$this->mod->Lang('title_name'),
			'bkg'=>$this->mod->Lang('count'),
			'status'=>$this->mod->Lang('status'),
			'statpay'=>$this->mod->Lang('title_payment')
		);

		$row = reset($pivoted);
		//interpet titles, and log row-indices of 'status' fields
		$coltitles = array();
		$works = array();
		foreach ($row as $t => $val) {
			$parts = explode('\\', $t);
			foreach ($parts as $k => &$val) {
				switch ($val) {
				 case 'type':
					$parts = FALSE; //type field won't be displayed
					break 2;
				 case 'status':
				 case 'statpay':
					$works[] = $t;
					break;
				}
				if (array_key_exists($val, $translates)) {
					$val = $translates[$val];
				}
			}
			unset($val);
			if ($parts) {
				$coltitles[] = implode('<br />', $parts);
			}
		}

/*		if ($display) {
			$theme = ($this->mod->before20) ? cmsms()->get_variable('admintheme') :
				cms_utils::get_theme_object();
			$t = $this->mod->Lang('tip_seetype', $this->mod->Lang('booker'));
			$icon_view = $theme->DisplayImage('icons/system/view.gif', $t, '', '', 'systemicon');
		}
*/
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
		$funcs = new Status();
		$types = array_flip($funcs->GetStatusChoices($this->mod, 15)); //TODO mode 1+2+4+8
		$subtotal = $this->mod->Lang('subtotal');
		$total = $this->mod->Lang('total');

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
				$sid = $row['status'];
				//interpret 'status*'
				foreach ($works as $t) {
					if (isset($row[$t]) && isset($types[$row[$t]])) {
						$row[$t] = $types[$row[$t]];
					}
				}
			} elseif (strpos($bid, $subtotal) !== FALSE) {
				$row['booker_id'] = str_replace(array('booker_id','status'),array($current,$types[$sid]),$bid);
//				foreach ($works as $t) {
//					$row[$t] = NULL;
//				}
//			} elseif (strpos($bid, $total) !== FALSE) {
//				foreach ($works as $t) {
//					$row[$t] = NULL;
//				}
			}

			if ($display) {
				$oneset = new \stdClass();
				$oneset->fields = array_values($row);
//				$oneset->view = ($dataline) ? $this->mod->CreateLink($id, $linkaction, '', $icon_view,
//					array('filter' => 1,'booker_id' => $bid)) : NULL; //TODO all link $params[] for payments
				$oneset->view = NULL;
				$output[] = $oneset;
			} else {
				$output[] = array_values($row);
			}
		}
		return array($coltitles,$output);
	}
}
