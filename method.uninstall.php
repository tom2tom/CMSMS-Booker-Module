<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: uninstall
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

//NB caller must be very careful that top-level dir is valid!
function delTree($dir)
{
	$files = array_diff(scandir($dir),array('.','..'));
	if ($files) {
		foreach ($files as $file) {
			$fp = cms_join_path($dir,$file);
			if (is_dir($fp)) {
			 	if (!delTree($fp))
					return FALSE;
			} else
				unlink($fp);
		}
		unset($files);
	}
	return rmdir($dir);
}

$pre = cms_db_prefix();
$dict = NewDataDictionary($db);
// remove table indices
//$sql = $dict->DropIndexSQL('idx_'.$this->AvailTable,$this->AvailTable);
//$dict->ExecuteSQLArray($sql);
$sql = $dict->DropIndexSQL('idx_'.$this->DataTable,$this->DataTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropIndexSQL('idx_'.$this->GroupTable,$this->GroupTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropIndexSQL('idx_'.$this->HistoryTable,$this->HistoryTable);
$dict->ExecuteSQLArray($sql);
// remove database tables
//$sql = $dict->DropTableSQL($this->AvailTable);
//$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($this->BookerTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($this->DataTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($this->FeeTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($this->GroupTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($this->HistoryTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($this->ItemTable);
$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($this->RepeatTable);
$dict->ExecuteSQLArray($sql);
//$sql = $dict->DropTableSQL($this->ItemTable.'pick');
//$dict->ExecuteSQLArray($sql);
$sql = $dict->DropTableSQL($pre.'module_bkr_cache');
$dict->ExecuteSQLArray($sql);
// remove sequences
$db->DropSequence($this->BookerTable.'_seq');
$db->DropSequence($this->DataTable.'_seq');
$db->DropSequence($this->FeeTable.'_seq');
$db->DropSequence($this->GroupTable.'_seq');
$db->DropSequence($this->HistoryTable.'_seq');
$db->DropSequence($this->ItemTable.'_seq');
$db->DropSequence($this->ItemTable.'_gseq');
//RepeatTable sequence same as for DataTable
$db->DropSequence($pre.'module_bkr_cache_seq');
// remove permissions
$this->RemovePermission($this->PermStructName);
$this->RemovePermission($this->PermAdminName);
$this->RemovePermission($this->PermEditName);
//$this->RemovePermission($this->PermSeeName);
$this->RemovePermission($this->PermAddName);
$this->RemovePermission($this->PermDelName);
$this->RemovePermission($this->PermModName);

$fp = $config['uploads_path'];
if ($fp && is_dir($fp)) {
	$ud = $this->GetPreference('uploadsdir','');
	if ($ud) {
		$fp = cms_join_path($fp,$ud);
		if ($fp && is_dir($fp))
			delTree($fp);
	} else {
		$files = $db->GetCol("SELECT DISTINCT stylesfile FROM $this->ItemTable WHERE stylesfile IS NOT NULL AND stylesfile<>''");
		if ($files) {
			foreach ($files as $fn) {
				$fn = cms_join_path($fp,$fn);
				if (is_file($fn))
					unlink($fn);
			}
		}
	}
}

// remove all preferences
$this->RemovePreference();
// remove FormBuilder-module custom processing
$ob = cms_utils::get_module('FormBuilder');
if (is_object($ob)) {
	$fp = cms_join_path($ob->GetModulePath,'classes');
	if ($fp && is_dir($fp)) {
		$fp = cms_join_path($fp,'DispositionBookingRequest.class.php');
		unlink($fn);
	}
	unset($ob);
}

// put mention into the admin log
$this->Audit(0, $this->Lang('fullname'), $this->Lang('audit_uninstalled'));
