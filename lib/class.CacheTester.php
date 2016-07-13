<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: CacheTester
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

namespace Booker;

class CacheTester
{
	protected $mod; //reference to current module-object

	public function __construct(&$mod)
	{
		$this->mod = $mod; //cache current module object
	}

// XCACHE INIT FAILS - NO CLIENT OBJECT
	public function Run()
	{
//		$drivers = array('yac','apc','apcu','wincache',/*'xcache',*/'redis','predis','file','database');
		$drivers = array('xcache');
		$ob1 = new \stdClass();
		$ob2 = new Itemops();
		$vals = array(
		'REALNULL'=>NULL,
		'REALFALSE'=>FALSE,
		'INTZERO'=>0,
		'FLOATZERO'=>0.0,
		'REALEMPTY'=>'',
		'ZEROSTRING'=>'0',
		'STRING'=>'shortstring',
		'INT'=>12,
		'FLOAT1'=>12.0,
		'FLOAT2'=>22.6222,
		'ARRAY'=>array(),
		'ARRAYINT1'=>array(12),
		'ARRAYINT4'=>array(12,13,14,15),
		'ARRAYINT2'=>array('key1'=>12,'key2'=>13),
		'STDCLASS'=>$ob1,
		'ITEMOBS'=>$ob2,
		'ARRAYCLASS2'=>array($ob1,$ob2),
		'ARRAYCLASS3'=>array('key1'=>$ob1,'key2'=>$ob2)
		); 

		$funcs = new Cache();
		$results = array();

		foreach ($drivers as $type) {
			$cache = $funcs->GetCache($this->mod,$type);
			if ($cache) {
				foreach ($vals as $key=>$value) {
					$cache->upsert($key,$value);
				}
				$match = TRUE;
				$results[$type] = array('result'=>FALSE);
				foreach ($vals as $key=>$value) {
					$stored = $cache->get($key);
					$results[$type][$key] = $stored;
					if ($stored !== $value) {
						$match = FALSE;
					}
				}
				$results[$type]['result'] = ($match) ? 'match' : 'OOPS!';
				$cache->clean();
			}
			else
				$results[$type] = 'Driver N/A';
		}

		$this->Crash();
	} 
}
