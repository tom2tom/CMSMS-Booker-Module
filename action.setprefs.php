<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: setprefs
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (isset($params['cancel']))
	$this->Redirect($id,'defaultadmin');

//maybe-missing checkboxes
if(!isset($params['pref_cleargroup']))
	$params['pref_cleargroup'] = 0;
//$params['pref_exportfile']
//$params['pref_striponexport]

if(isset($params['stylesdelete']))
{
	$fn = $params['pref_stylesfile'];
	if($fn)
	{
		$fp = $config['uploads_path'];
		if($fp && is_dir($fp))
		{
			$ud = $this->GetPreference('pref_uploadsdir','');
			if($ud)
				$fp = cms_join_path($fp,$ud,$fn);
			else
				$fp = cms_join_path($fp,$fn);
			if(is_file($fp))
				unlink($fp);
		}
		$params['pref_stylesfile'] = '';
	}
}

$updates = preg_grep('/^pref_.*/',array_keys($params));
foreach($updates as $k)
{
	$val = $params[$k];
	switch($k)
	{
	 case 'pref_cleargroup':
		$this->SetPreference($k,(int)$val);
		break;
	 case 'pref_timezone':
		if($val == FALSE)
			$val = 'UTC';
		$this->SetPreference($k,trim($val));
		break;
	 case 'pref_dateformat':
		if ($val == FALSE)
			$val = 'j M Y';
		$this->SetPreference($k,trim($val));
		break;
	 case 'pref_timeformat':
		if ($val == FALSE)
			$val = 'G:i';
		$this->SetPreference($k,trim($val));
		break;
	 case 'pref_smspattern':
		if ($val == FALSE)
			$val = '^\d{6,15}$';
		$this->SetPreference($k,trim($val));
		break;
/*	 case 'pref_smsprefix':
		if ($val == FALSE)
			$val = ; TODO func(timezone)
		$this->SetPreference($k,trim($val));
		break;
*/
	 default:
		$this->SetPreference($k,trim($val));
	}
}

$this->Redirect($id,'defaultadmin','',array('active_tab'=>'settings'));

?>
