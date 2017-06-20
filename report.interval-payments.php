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

$t = '';
$t2 = $this->Lang('title_payments');
/*TODO supplement with interval-description if relevant, stamps 'showfrom','showto'
S to E, S and after, E and before
c.f. $this->GetPreference('dateformat') or $idata['dateformat']
*/
$tplvars['title'] = $this->Lang('report_title',$t,$t2);

//TODO support start/end time-limit(s) per $params['startchooser','endchooser'] if relevant
//TODO support RepeatsTable too
$sql =<<<EOS
SELECT D.slotstart,O.fee,O.feepaid,1 AS bkg
FROM $this->DispTable D
JOIN $this->OnceTable O ON D.bkg_id=O.bkg_id
WHERE D.displayed>0
ORDER BY D.slotstart
EOS;
//$args = array();
$data = $db->GetArray($sql);

if ($data) {
	$ic = count($data);
	for ($i = 0; $i < $ic; $i++) {
		$dt->setTimestamp($data[$i]['slotstart']);
		$data[$i]['year'] = $dt->format('Y');
		$data[$i]['month'] = 'M'.(string)($dt->format('n')-1);
	}

	$pivoton = array('year','month');
	$group = null;
	$groupvalue = array('bkg','fee','feepaid');
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
		$translates = array(
			'bkg'=>$this->Lang('count'),
			'fee'=>$this->Lang('title_fees'),
			'feepaid'=>$this->Lang('title_payments'),
			'month'=>$this->Lang('title_month'),
			'year'=>$this->Lang('title_year')
		);
		$months = array();
		foreach (explode(',',$this->Lang('longmonths')) as $k => $val) {
			$months['M'.$k] = $val;
		}
		$translates += $months;

		$row = reset($pivoted);
		//interpet titles, and log row-indices of 'fee*'
		$works = array();
		foreach ($row as $t2 => $val) {
			$parts = explode('\\',$t2);
			foreach ($parts as $k => &$val) {
				switch ($val) {
				 case 'type':
					$parts = FALSE; //type field won't be displayed
					break 2;
				 case 'fee':
				 case 'feepaid':
					$works[] = $t2;
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
			$yid = $row['year'];
			if ($dataline) {
				$current = $yid;
				$row['month'] = $months[$row['month']];
			} elseif (strpos($yid,$subtotal) !== FALSE) {
				$row['year'] = str_replace('year',$current,$row['year']);
			}
			//interpret 'fee*'
			foreach ($works as $t2) {
				if (isset($row[$t2])) {
					$row[$t2] = number_format($row[$t2],2); //TODO generalize
				}
			}

			$oneset = new stdClass();
			$oneset->fields = array_values($row);
			if ($display) {
				$oneset->view = ($dataline) ? $this->CreateLink($id,$params['action'],'',$icon_view,
					array('filter'=>1)) : NULL; //TODO $params[]
			}
			$output[] = $oneset;
		}
	}
}
