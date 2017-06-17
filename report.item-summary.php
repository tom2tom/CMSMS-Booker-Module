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
SELECT item_id,bkg_id,(bulk=0) AS singl,(bulk=1) AS grp,(bulk>=20) AS rept,slotstart,slotlen
FROM $this->DispTable
WHERE displayed>0
ORDER BY item_id,slotstart
EOS;
//$args = array();
$data = $db->GetArray($sql);

if ($data) {
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
	$groupvalue = array('slotlen',array('singl','count'),array('grp','count'),array('rept','count'),array('bkg_id','count'));

	$funcs = new Booker\Pivot1($data, $pivoton, $group, $groupvalue,
		TRUE, //include a column showing relevant Pivot::TYPE_* const
		FALSE, //exclude totals per line
		TRUE, //include totals per pivoted column
		TRUE, //include total for all table
		$this->Lang('total') //title
//		'type' //column-title - use default
	);
	$pivoted = $funcs->fetch();

	if ($pivoted) {
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
		$translates = array(
			'item_id'=>$this->Lang('title_name'),
			'bkg_id'=>'Count', //$this->Lang('total'), //'title_bookings'
			'singl'=>'Single', //$this->Lang('bkgtype_single'),
			'grp'=>$this->Lang('bkgtype_grouped'),
			'rept'=>$this->Lang('bkgtype_repeated'),
			'slotlen'=>$this->Lang('duration'),
			'slotsum'=>$this->Lang('durationsum')
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
		$translates += $db->GetAssoc('SELECT item_id,name FROM '.$this->ItemTable.' ORDER BY item_id');

		$row = reset($pivoted);
		//interpet titles, and log row-indices of 'type','item_id',*\'slotlen','slotsum'
		$k = 0;
		$is = array();
		foreach ($row as $t => $val) {
			$parts = explode('\\',$t);
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
				 case 'slotsum':
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

		$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
			cms_utils::get_theme_object();
		$icon_view = $theme->DisplayImage('icons/system/view.gif', $this->Lang('view'), '', '', 'systemicon');

		$ic = count($pivoted);
		$ic--; //DEBUG = skip the dodgy totals row
		for ($i = 0; $i < $ic; $i++) {
			$oneset = new stdClass();
			$row = array_values($pivoted[$i]);
			$more = ($row[$it] == Booker\PivotBase::TYPE_LINE);
			unset($row[$it]);
			$iid = $row[$ii];
			$row[$ii] = $translates[$iid]; //item name
			//interpret $row[*\'slotlen','slotsum']
			foreach ($is as $k) {
				$row[$k] = round(($row[$k]/3600),1); //TODO better format related to slotlen
			}
			$oneset->fields = $row;
			if ($more) {
				$oneset->view = $this->CreateLink($id,'action','',$icon_view,
					array($iid)); //TODO
				$oneset->sel = $this->CreateInputCheckbox($id,'sel[]',$iid,-1);
			} else {
				$oneset->view = NULL;
				$oneset->sel = NULL;
			}
			$display[] = $oneset;
		}
		$k = 0;
	}
}
