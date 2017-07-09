<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: RptBookerCredit - generates content for a specific-format report
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class RptBookerCredit extends Report
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
		return ['bkrcred', $this->PublicTitle()];
	}

	/**
	Get displayable title for this report
	@after: optional timestamp in first month of report-interval, default FALSE
	@before: optional timestamp in last month of report-interval, default FALSE
	Returns: string
	*/
	public function PublicTitle($after = FALSE, $before = FALSE)
	{
		return $this->utils->CreateTitle($this->mod, 'title_booker', 'title_credit', $after, $before);
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
SELECT booker_id,status,latest FROM {$this->mod->CreditTable} WHERE 1
EOS;
		$args = [];
		if ($showfrom) {
			$sql .= ' AND updated >= ?';
			$args[] = $showfrom;
		}
		if ($showto) {
			$sql .= ' AND (updated <= ?';
			$args[] = $showto;
		}
		$sql .= ' ORDER BY booker_id,status';

		$data = $this->mod->dbHandle->GetArray($sql,$args);
		if ($data) {
			$funcs = new Crypter($this->mod);
			$ic = count($data);
			for ($i = 0; $i < $ic; $i++) {
				$data[$i]['latest'] = (float)$funcs->uncloak_value($data[$i]['latest']);
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
		$pivoton = ['booker_id'];
		$group = 'status';
		$groupvalue = ['latest'];
		$funcs = new Pivot1($data, $pivoton, $group, $groupvalue,
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
		$translates = [
			'booker_id'=>$this->mod->Lang('title_name'),
			'latest'=>$this->mod->Lang('title_amount'),
			'status'=>$this->mod->Lang('status'),
			//TODO others
		];

		$row = reset($pivoted);
		//interpet titles
		$coltitles = [];
//		$works = array();
		foreach ($row as $t => $val) {
			$parts = explode('\\', $t);
			foreach ($parts as $k => &$val) {
				switch ($val) {
				 case 'type':
					$parts = FALSE; //type field won't be displayed
					break 2;
//				 case 'status':
//					$works[] = $t;
//					break;
				}
				if (array_key_exists($val,$translates)) {
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
SELECT B.booker_id,COALESCE(A.name,B.name,'') AS name,A.publicid
FROM {$this->mod->BookerTable} B
LEFT JOIN {$this->mod->AuthTable} A ON B.auth_id=A.id
ORDER BY B.booker_id
EOS;
		$translates = $this->utils->PlainGet($this->mod,$sql,[],'assoc');
		if ($translates) {
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
		$types = [
			\Booker::CREDITADDED => $this->mod->Lang('stat_new'),
			\Booker::CREDITUSED => $this->mod->Lang('balance'),
			\Booker::CREDITEXPIRED => $this->mod->Lang('expired')
		];
		$subtotal = $this->mod->Lang('subtotal');
//		$total = $this->mod->Lang('total');

		$output = [];
		$ic = count($pivoted);
		for ($i = 0; $i < $ic; $i++) {
			$row = $pivoted[$i];
			$dataline = ($row['type'] == PivotBase::TYPE_LINE);
			unset($row['type']);
			$bid = $row['booker_id'];
			if ($dataline) {
				$current = $translates[$bid]; //booker name
				$row['booker_id'] = $current;
				$cstat = $types[$row['status']];
				$row['status'] = $cstat;
			} elseif (strpos($bid, $subtotal) !== FALSE) {
				$row['booker_id'] = str_replace(['booker_id','status'],[$current,$cstat],$bid);
/*				foreach ($works as $t) {
					$row[$t] = NULL;
				}
			} elseif (strpos($bid, $total) !== FALSE) {
				foreach ($works as $t) {
					$row[$t] = NULL;
				}
*/
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
		return [$coltitles,$output];
	}
}
