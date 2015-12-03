<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: upgrade
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (! $this->CheckAccess('admin')) return;

switch ($oldversion)
{
}
// put mention into the admin log
$this->Audit(0, $this->Lang('fullname'), $this->Lang('audit_upgraded',$newversion));

?>
