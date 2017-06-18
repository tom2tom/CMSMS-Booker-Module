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
	$dt = new DateTime('@0',NULL);
	$ic = count($data);
	for ($i = 0; $i < $ic; $i++) {
		$dt->setTimestamp($data[$i]['slotstart']);
		$data[$i]['month'] = 'M'.(string)($dt->format('n')-1);
		$data[$i]['weekday'] = 'D'.$dt->format('w');
	}

	$pivoton = 'item_id';
	//$group = array('month','weekday');
	$group = 'month';
	//TODO total slotlen as 'slotsum'
	$groupvalue = array(array('singl','count'),array('grp','count'),array('rept','count'),'bkg','slotlen');

	$funcs = new Booker\Pivot1($data, $pivoton, $group, $groupvalue,
		TRUE, //include a column showing relevant Pivot::TYPE_* const
		FALSE, //exclude totals per line
		TRUE, //include totals per pivoted column
		TRUE, //include total for all table
		$this->Lang('total') //title
//		'type' //column-title - use default
	);
	$pivoted = $funcs->fetch();
/* gets array with rows like:
[0]	array[12]
[item_id]	integer	1
[type]	integer	0
[M4\bkg_id]	integer	2
[M4\grp]	integer	0
[M4\rept]	integer	2
[M4\singl]	integer	0
[M4\slotlen]	integer	28798
[M5\bkg_id]	integer	16
[M5\grp]	integer	0
[M5\rept]	integer	16
[M5\singl]	integer	0
[M5\slotlen]	integer	244784
*/
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
			'rept'=>$this->Lang('bkgtype_repeated'),
			'singl'=>$this->Lang('bkgtype_single'),
			'slotlen'=>$t,
		);
/*		$names = explode(',',$this->Lang('shortdays'));
		foreach ($names as $k => $val) {
			$translates['D'.$k] = $val;
		}
*/
		$names = explode(',',$this->Lang('longmonths'));
		foreach ($names as $k => $val) {
			$translates['M'.$k] = $val;
		}

		$row = reset($pivoted);
		//interpet titles, and log row-indices of 'type','item_id',*\'slotlen','slotsum'
		$k = 0;
		$is = array();
		foreach ($row as $t2 => $val) {
			$parts = explode('\\',$t2);
			foreach ($parts as &$val) {
				switch ($val) {
				 case 'type':
					$it = $k;
					$parts = FALSE; //type field won't be displayed
					break 2;
				 case 'item_id':
					$ii = $k;
					break;
				 case 'slotlen':
					$is[] = $k;
					break;
				}
				if (array_key_exists($val,$translates)) {
					$val = $translates[$val];
				}
			}
			unset($val);
			if ($parts) {
				$coltitles[] = implode('<br />',$parts);
			}
			$k++;
		}
		$coltitles[] = $this->Lang('total').'<br />'.$t; //for locally-calculated item-total booked time

		$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
			cms_utils::get_theme_object();
		$icon_view = $theme->DisplayImage('icons/system/view.gif', $this->Lang('view'), '', '', 'systemicon');
		$translates = $db->GetAssoc('SELECT item_id,name FROM '.$this->ItemTable.' ORDER BY item_id');
		//CHECKME fallback for any missing name ?
		$ic = count($pivoted);
		$ic--; //DEBUG = skip the dodgy totals row
		for ($i = 0; $i < $ic; $i++) {
			$oneset = new stdClass();
			$row = array_values($pivoted[$i]);
			$more = ($row[$it] == Booker\PivotBase::TYPE_LINE);
			unset($row[$it]);
			$iid = $row[$ii];
			$row[$ii] = $translates[$iid]; //item name
			//interpret *\'slotlen'
			$sum = 0.0;
			foreach ($is as $k) {
				$row[$k] = round(($row[$k]/$slen),1);
				$sum += $row[$k];
			}
			$row[] = $sum;
			$oneset->fields = $row;
			$oneset->view = ($more) ? $this->CreateLink($id,'filter','',$icon_view,
				array('item_id'=>$iid)) : NULL; //TODO $params[]
			$display[] = $oneset;
		}
	}
}
