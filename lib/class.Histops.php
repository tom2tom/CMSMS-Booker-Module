<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Histops - functions for processing history data
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Histops
{
	private $dbhandle;
	private $table;
	private $history; //history-data table

	public function __construct (&$mod, &$db)
	{
		$this->dbhandle = $db;
		$this->table = cms_db_prefix().'module_brk_bookers';
		$this->history = $mod->HistoryTable;
	}

	/**
	CountBookingsBy:
	@after: UTC timestamp, or FALSE
	@before: UTC timestamp, or FALSE
	@booker: a numeric booker_id, or array of them, or a callback for filtering, or NULL for all
	Returns: integer
	*/
	public function CountBookingsBy ($after=FALSE, $before=FALSE, $booker=NULL)
	{
		return 0;
	}

	/**
	CountBookingsOf:
	@after: UTC timestamp, or FALSE
	@before: UTC timestamp, or FALSE
	@item: a callback for filtering, or NULL for all, or a numeric item_id, or array of them
	Returns: integer
	*/
	public function CountBookingsOf ($after=FALSE, $before=FALSE, $item=NULL)
	{
		return 0;
	}

	/**
	CreditTotal:
	Determine how much credit has been accumulated by @booker_id
	@booker_id: numeric booker-identifier
	Returns: float amount
	*/
	public function CreditTotal ($booker_id)
	{
		return 0.0;
	}

	/**
	UseCredit:
	Reduce the the credit accumulated by @booker_id by @amount
	@booker_id: numeric booker-identifier
	@amount:
	Returns: remaining credit for @booker_id (maybe < 0), or FALSE upon error
	*/
	public function UseCredit ($booker_id, $amount)
	{
		return 0.0;
	}

	/**
	Flag as 'expired' all credit records older than @before and matching @booker
	@before: UTC timestamp
	@booker: a numeric booker_id, or array of them, or a callback for filtering, or NULL for all
	Returns: nothing
	*/
	public function ExpireCredit ($before, $booker=NULL)
	{
	}

	/**
	Flag as 'expired' all credit records matching @booker
	@before: UTC timestamp
	@booker: a numeric booker_id, or array of them, or a callback for filtering, or NULL for all
	Returns: nothing
	*/
	public function ExpireCreditFor ($booker=NULL)
	{
	}

	/**
	Delete all history records older than @before and matching @booker
	@before: UTC timestamp
	@booker: a numeric booker_id, or array of them, or a callback for filtering, or NULL for all
	Returns: nothing
	*/
	public function ClearHistory ($before, $booker=NULL)
	{
	}

	/**
	Delete all history records matching @booker
	@booker: a numeric booker_id, or array of them, or a callback for filtering, or NULL for all
	Returns: nothing
	*/
	public function ClearHistoryFor ($booker=NULL)
	{
	}
}
