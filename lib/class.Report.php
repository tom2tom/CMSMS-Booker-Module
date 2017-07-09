<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Report - base class for generating a report
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Report
{
	protected $mod; //reference to Booker-module object
	protected $utils; //reference to Booker\Utils-class object

	/**
	SlotParameters:
	@$item_id: item identifier whose slotlength is to be used
	Returns: 2-member array,
	 [0] = title for slotlen columns
	 [1] = divisor for slotlen values (seconds)
	*/
	protected function SlotParameters($item_id)
	{
		$slen = $this->utils->GetInterval($this->mod, $item_id, 'slot');
		if ($slen <= 86400) {
			$slen = 3600;
			$k = 'title_hours';
		} elseif ($slen < 604800) {
			$slen = 86400;
			$k = 'title_days';
		} elseif ($slen < 2592000) {
			$slen = 604800;
			$k = 'title_weeks';
		} else {
			$slen = 2592000;
			$k = 'title_months';
		}
		return [$this->mod->Lang($k),$slen];
	}

	/**
	Subclass this
	Get internal-use title/key, and un-ranged displayable title, for this report
	Returns: 2-member array of strings,
	 [0] = internal
	 [1] = public
	*/
	public function Titles()
	{
		return ['internal','public'];
	}

	/**
	Subclass this
	Get displayable title for this report
	@after: optional timestamp in first month of report-interval, default FALSE
	@before: optional timestamp in last month of report-interval, default FALSE
	Returns: string
	*/
	public function PublicTitle($after = FALSE, $before = FALSE)
	{
		return 'public';
	}

	/**
	Subclass this
	Get relevant bookings-data & related
	Returns: associative array
	*/
	public function GetReportData()
	{
		return $data;
	}

	/**
	Subclass this
	Get pivoted data
	@data: non-empty array returned from GetReportData()
	Returns: associative array or FALSE
	*/
	public function PivotReportData($data)
	{
		return $pivoted;
	}

	/**
	Subclass this
	Get output data
	@pivoted: non-empty array returned from PivotReportData()
	@id: module id for link-construction (when @display == TRUE)
	@linkaction: for link-construction (when @display == TRUE)
	@display: optional boolean whether output is for screen-display
	 (as opposed to export), default TRUE
	Returns: 2-member array,
	 [0] = array of strings for table-column titles
	 [1] = if @display == TRUE,
	   array of stdClass objects, each with member ->fields and xhtml-link-element ->view
	   otherwise, array of values
	*/
	public function PostProcessData($pivoted, $id, $linkaction, $display = TRUE)
	{
		return [$coltitles,$output];
	}
}
