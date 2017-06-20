<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: processreport
# Display bookings summary-information
# include-file for a specific type of report
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
// see https://gonzalo123.com/2010/01/24/pivot-tables-in-php for related examples

$dt = new DateTime('@0',NULL);

$t = $this->Lang('title_item');
$t2 = $this->Lang('title_overview');
/*TODO supplement with interval-description if relevant, stamps 'showfrom','showto'
S to E, S and after, E and before
c.f. $this->GetPreference('dateformat') or $idata['dateformat']
*/
$tplvars['title'] = $this->Lang('report_title',$t,$t2);

//TODO support start/end time-limit(s) per $params['startchooser','endchooser'] if relevant
$sql =<<<EOS
SELECT item_id,1 AS bkg,(bulk=0) AS singl,(bulk=1) AS grp,(bulk>=20) AS rept,slotstart,slotlen
FROM $this->DispTable
WHERE displayed>0
ORDER BY item_id,slotstart
EOS;
//$args = array();
$data = $db->GetArray($sql);

if ($data) {
	$iid = $data[0]['item_id']; //for time-interval determination
	$ic = count($data);
	for ($i = 0; $i < $ic; $i++) {
		$dt->setTimestamp($data[$i]['slotstart']);
		$data[$i]['month'] = 'M'.(string)($dt->format('n')-1);
		$data[$i]['weekday'] = 'D'.$dt->format('w');
	}

	$pivoton = array('item_id','month');
	$group = null;
	$groupvalue = array(array('singl','count'),array('grp','count'),array('rept','count'),'bkg','slotlen');
	$total = $this->Lang('total');
	$subtotal = $this->Lang('subtotal');

	$funcs = new Booker\Pivot2($data, $pivoton, $group, $groupvalue,
		TRUE, //include relevant Pivotbase::TYPE_*
		FALSE, //exclude  per-line subtotals
		TRUE, //include pivoted-field subtotals
		TRUE, //include whole-table totals 
		$total,
		$subtotal
	);
	$pivoted = $funcs->fetch();
	unset($data);

	if ($pivoted) {
		$slen = $utils->GetInterval($this,$iid,'slot');
		if ($slen <= 86400) {
			$slen = 3600;
			$t = $this->Lang('title_hours');
		} elseif ($slen < 604800) {
			$slen = 86400;
			$t = $this->Lang('title_days');
		} elseif ($slen < 2592000) {
			$slen = 604800;
			$t = $this->Lang('title_weeks');
		} else {
			$slen = 2592000;
			$t = $this->Lang('title_months');
		}

		$translates = array(
			'bkg'=>$this->Lang('count'),
			'grp'=>$this->Lang('bkgtype_grouped'),
			'item_id'=>$this->Lang('title_name'),
			'month'=>$this->Lang('title_month'),
			'rept'=>$this->Lang('bkgtype_repeated'),
			'singl'=>$this->Lang('bkgtype_single'),
			'slotlen'=>$t,
		);
		$months = array();
		foreach (explode(',',$this->Lang('longmonths')) as $k => $val) {
			$months['M'.$k] = $val;
		}
		$translates += $months;

		$row = reset($pivoted);
		//interpet titles, and log row-indices of *\'slotlen'
		$summers = array();
		foreach ($row as $t2 => $val) {
			$parts = explode('\\',$t2);
			foreach ($parts as $k => &$val) {
				switch ($val) {
				 case 'type':
					$parts = FALSE; //type field won't be displayed
					break 2;
				 case 'item_id':
					break;
				 case 'slotlen':
					$summers[] = $t2;
					break;
				}
				if ($val[0] == 'M') {
					unset($parts[$k]);
				} elseif (array_key_exists($val,$translates)) {
					$val = $translates[$val];
				}
			}
			unset($val);
			if ($parts) {
				$coltitles[] = implode('<br />',$parts);
			}
		}

		$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
			cms_utils::get_theme_object();
		$t2 = $this->Lang('tip_seetype',$this->Lang('item'));
		$icon_view = $theme->DisplayImage('icons/system/view.gif',$t2,'','','systemicon');
		$translates = $db->GetAssoc('SELECT item_id,name FROM '.$this->ItemTable.' ORDER BY item_id');
		//CHECKME fallback for any missing name ?

		$ic = count($pivoted);
		for ($i = 0; $i < $ic; $i++) {
			$row = $pivoted[$i];
			$dataline = ($row['type'] == Booker\PivotBase::TYPE_LINE);
			unset($row['type']);
			$iid = $row['item_id'];
			if ($dataline) {
				$current = $translates[$iid]; //item name
				$row['item_id'] = $current;
				$row['month'] = $months[$row['month']];;
			} elseif (strpos($iid,$subtotal) !== FALSE) {
				$row['item_id'] = str_replace('item_id',$current,$iid);
			}
			//interpret *\'slotlen'
			foreach ($summers as $t2) {
				if (isset($row[$t2])) {
					$a = round(($row[$t2]/$slen),1);
					$row[$t2] = $a;
				}
			}

			$oneset = new stdClass();
			$oneset->fields = array_values($row);
			$oneset->view = ($dataline) ? $this->CreateLink($id,$params['action'],'',$icon_view,
				array('filter'=>1,'item_id'=>$iid)) : NULL; //TODO $params[]
			$display[] = $oneset;
		}
	}
}
