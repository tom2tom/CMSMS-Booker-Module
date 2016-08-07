<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: sortlike - ajax processor to generate replacement table-body
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!function_exists('array_like')) {
	/*
	simple_diff:
	Paul's simple diff algorithm, similar to Ratcliff/Obershelp, v 0.1
	(C) Paul Butler 2007 <http://www.paulbutler.org/>
	@a: array
	@b: array
	Returns: array of the changes between @a and @b
	*/
	function simple_diff($a, $b)
	{
		$matrix = array();
		$maxlen = 0;
		foreach ($a as $oindex=>$ovalue) {
			$nkeys = array_keys($b,$ovalue);
			foreach ($nkeys as $nindex) {
				$matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
						$matrix[$oindex - 1][$nindex - 1] + 1 : 1;
				if ($matrix[$oindex][$nindex] > $maxlen) {
					$maxlen = $matrix[$oindex][$nindex];
					$omax = $oindex + 1 - $maxlen;
					$nmax = $nindex + 1 - $maxlen;
				}
			}
		}
		if ($maxlen == 0) { //arrays are completely different
			if ($a || $b)
				return array(array('d'=>$a,'i'=>$b));
			return array();
		}
		return array_merge(
			simple_diff(array_slice($a,0,$omax),array_slice($b,0,$nmax)),
			array_slice($b,$nmax,$maxlen),
			simple_diff(array_slice($a,$omax + $maxlen), array_slice($b,$nmax + $maxlen)));
	}

	/*
	array_like:
	For comparing keywords or split strings with re-ordered chars
	@a: array
	@b: array
	Returns: float 0.0 .. 1.0, higher = @a,@b more similar
	*/
	function array_like($a, $b)
	{
		if (!$a || !$b)
			return 0.0;
		$m = 0;
		$ins = array();
		$outs = array();
		$parts = simple_diff($a,$b);
		foreach ($parts as $i=>$one) {
			if (is_array($one)) { //difference
				if ($one['d']) { //something missing
					$f = array_search($one['d'],$ins);
					if ($f === FALSE)
						$outs[$i] = $one['d'];
					else {
						unset($ins[$f]);
						$m += 0.75 / abs($f-$i);
					}
				}
				if ($one['i']) { //something extra
					$f = array_search($one['i'],$outs);
					if ($f === FALSE) {
						$ins[$i] = $one['i'];
                    } else {
						unset($outs[$f]);
						$m += 0.75 / abs($f-$i);
					}
				}
			} else { //matching part
				$m++;
			}
		}
		$mc  = max(count($a),count($b));
		return $m / $mc;
	}

	function cmp_like($a, $b)
	{
		$d = $a['score'] - $b['score'];
		if ($d > 0) return -1;
		if ($d < 0) return 1;
		return strnatcasecmp($a['name'],$b['name']);
	}

}
/*supplied $params = array(
'item_id' => number
'sort' => 'groups' or 'members'
)
*/
$funcs = new Booker\Utils();
$item_id = (int)$params['item_id'];
$havegroups = array($item_id);
$type = $params['sort'];
if ($type == 'members')
	$members = $funcs->GetGroupItems($this,$item_id,TRUE);
else
	$members = $funcs->GetItemGroups($this,$item_id);
if ($members) {
	if (count($members) > 1) {
		$data = array();
		foreach ($members as $i) {
			$data[$i] = $funcs->GetItemProperty($this,$i,array('name','description','keywords'));
			if (empty($data[$i]['name']))
				$data[$i]['name'] = $funcs->GetItemNameForID($this,$i);
			if ($i >= Booker::MINGRPID)
				$havegroups[] = $i;
		}

		$first = $item_id; //$params['first_id'];
		$fname = !empty($data[$first]['name']) ? str_split($data[$first]['name']) : FALSE;
		$fdesc = !empty($data[$first]['description']) ? str_split($data[$first]['description']) : FALSE;
		$fkeys = !empty($data[$first]['keywords']) ? explode(',',$data[$first]['keywords']) : FALSE;
		$cmps = array();
		foreach ($data as $i=>$one) {
			if (!($i == $first || $i == $item_id)) {
				$cmps[$i] = array('score'=>0.0,'name'=>'');
				if ($fname && !empty($data[$i]['name'])) {
					$ref = str_split($data[$i]['name']);
					$cmps[$i]['score'] += 1.0 - array_like($fname,$ref);
					$cmps[$i]['name'] = $data[$i]['name'];
				}
				if ($fdesc && !empty($data[$i]['description'])) {
					$ref = str_split($data[$i]['description']);
					$cmps[$i]['score'] += 1.0 - array_like($fdesc,$ref);
				}
				if ($fkeys && !empty($data[$i]['keywords'])) {
					$ref = explode(',',$data[$i]['description']);
					$cmps[$i]['score'] += 1.0 - array_like($fkeys,$ref);
				}
			}
		}
		uasort($cmps,'cmp_like');
	} else {
		$i = key($members);
		if ($i != $first) {
			$cmps = array($i=>array('score'=>30.0,'name'=>''));
			if ($i >= Booker::MINGRPID)
				$havegroups[] = $i;
		}
	}
	$all = array($first=>array('score'=>50.0,'name'=>'')) + $cmps; //CHECKME why prepend?
	$chkname = ($type == 'members') ? $type : 'ingroups'; //kludge to conform

	$sorted = array();
	//NOTE must conform with action.openitem.php table-data creation, except -
	//cuz' js & ajax are available, the up/down links will be hidden/irrelevant
	foreach ($all as $i=>$one) {
		if (isset($data[$i])) {
			$oneset = new stdClass();
			$oneset->name = $data[$i]['name'];
			$oneset->uplink = '';
			$oneset->dnlink = '';
			$oneset->check = $this->CreateInputCheckbox($id,$chkname.'[]',$i,$i);
			$sorted[] = $oneset;
		}
	}
	//append extra groups, unselected
	$moregroups = $db->GetAssoc('SELECT item_id,name FROM '.$this->ItemTable.
	' WHERE item_id >= '.Booker::MINGRPID.' AND item_id NOT IN ('.
	implode(',',$havegroups).') ORDER BY name');
	foreach ($moregroups as $i=>$one) {
		$oneset = new stdClass();
		$oneset->name = $one; //too bad if name empty!
		$oneset->uplink = '';
		$oneset->dnlink = '';
		$oneset->check = $this->CreateInputCheckbox($id,$chkname.'[]',$i,-1);
		$sorted[] = $oneset;
	}
	$tplvars = array(
		'entries' => $sorted,
		'cellclass' => $type
	);
	echo Booker\Utils::ProcessTemplate($this,'membersbody.tpl',$tplvars);
} else
	echo 0;
