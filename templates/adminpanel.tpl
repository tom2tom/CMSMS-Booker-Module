{if !empty($message)}{$message}<br />{/if}
{$tab_headers}

{$start_data_tab}
{$startform1}
{if $dcount > 0}
<h4 style="margin-left:5%;">{$title_pending}</h4>
<div style="overflow:auto;">
  <table id="data" class="table_sort leftwards pagetable">
    <thead><tr>
      <th>{$title_lodger}</th>
      <th>{$title_contact}</th>
      <th>{$title_lodged}</th>
      <th>{$title_status}</th>
      <th>{$title_paid}</th>
      <th>{$title_name}</th>
      <th>{$title_start}</th>
      <th>{$title_comment}</th>
      <th class="pageicon nosort">&nbsp;</th>
{if $mod}  <th class="pageicon nosort">&nbsp;</th>{/if}
{if $bmod} <th class="pageicon nosort">&nbsp;</th>
      <th class="pageicon nosort">&nbsp;</th>{/if}
{if $tell}  <th class="pageicon nosort">&nbsp;</th>{/if}
{if $del}   <th class="pageicon nosort">&nbsp;</th>{/if}
      <th class="checkbox nosort" style="width:20px;">{if $dcount > 1}{$selectall_req}{/if}</th>
    </tr></thead>
    <tbody>
 {foreach from=$pending item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
    <td>{$entry->sender}</td>
    <td>{$entry->contact}</td>
    <td>{$entry->lodged}</td>
    <td>{$entry->status}</td>
    <td>{$entry->paid}</td>
    <td>{$entry->name}</td>
    <td>{$entry->start}</td>
    <td>{$entry->comment}</td>
    <td>{$entry->see}</td>
{if $mod}  <td>{$entry->edit}</td>{/if}
{if $bmod}  <td class="bkrapp">{$entry->approve}</td>
    <td class="bkrrej">{$entry->reject}</td>{/if}
{if $tell}  <td class="bkrtell">{$entry->notice}</td>{/if}
{if $del}  <td class="bkrdel">{$entry->delete}</td>{/if}
    <td class="checkbox">{$entry->sel}</td>
    </tr>
 {/foreach}
    </tbody>
  </table>
</div>
{else}
 <p class="pageinput">{$nodata}</p>
{/if}
<div id="dataacts" class="pageoptions" style="margin-top:1em;">
{if $bmod}{$importbtn1} {/if}{$findbtn}
{if $dcount > 0}
{if $bmod} {$approvbtn} {$rejectbtn} {/if}{if $tell}{$notifybtn}{/if}{if $del} {$deletebtn1}{/if}
{/if}
</div>
{$endform}
{$end_tab}

{$start_people_tab}
{$startform2}
{if $pcount > 0}
<div class="pageoverflow">
  <table id="people" class="table_sort leftwards pagetable">
    <thead><tr>
      <th>{$title_person}</th>
      <th>{$title_reg}</th>
      <th>{$title_added}</th>
      <th>{$title_total}</th>
      <th>{$title_pending}</th>
      <th>{$title_first}</th>
      <th>{$title_last}</th>
      <th>{$title_pending}</th>
{if $mod} <th class="pageicon nosort">&nbsp;</th>{/if}
{if $del} <th class="pageicon nosort">&nbsp;</th>{/if}
      <th class="checkbox nosort" style="width:20px;">{if $pcount > 1}{$selectall_bookers}{/if}</th>
    </tr></thead>
    <tbody>
{foreach from=$bookers item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
      <td>{$entry->name}</td>
      <td>{$entry->reg}</td>
      <td>{$entry->added}</td>
      <td>{$entry->total}</td>
      <td>{$entry->pending}</td>
      <td>{$entry->first}</td>
      <td>{$entry->last}</td>
{if $mod} <td>{$entry->edit}</td>{/if}
{if $del} <td class="bkrdel">{$entry->delete}</td>{/if}
      <td class="checkbox">{$entry->sel}</td>
    </tr>
{/foreach}
    </tbody>
  </table>
</div>
{else}
 <p class="pageinput">{$nobookers}</p>
{/if}
<div id="peopleacts" class="pageoptions" style="margin-top:1em;">
{if $add}{$addbooker}{/if}
{if $pcount > 0}
<span style="margin-left:5em;">
{if $add}{$importbtn2} {/if}{$exportbtn2}{if $mod} {$ablebtn2}{/if}{if $del} {$deletebtn2}{/if}
</span>
{else}
{if $add}<span style="margin-left:3em">{$importbtn2}</span>{/if}
{/if}
</div>
{$endform}
{$end_tab}

{$start_items_tab}
{$startform3}
{if $icount > 0}
<div class="pageoverflow">
  <table id="items" class="table_sort leftwards pagetable">
    <thead><tr>
      <th>{$inametext}</th>
      <th>{$title_grp}</th>
{if $own} <th>{$title_owner}</th>{/if}
      <th class="pageicon">{$title_active}</th>
{if $dev} <th>{$title_tag}</th>{/if}
      <th class="pageicon nosort">&nbsp;</th>
{if $bmod}<th class="pageicon nosort">&nbsp;</th>{/if}
      <th class="pageicon nosort">&nbsp;</th>
      <th class="pageicon nosort">&nbsp;</th>
{if $mod} <th class="pageicon nosort">&nbsp;</th>{/if}
{if $add} <th class="pageicon nosort">&nbsp;</th>{/if}
{if $del} <th class="pageicon nosort">&nbsp;</th>{/if}
      <th class="checkbox nosort" style="width:20px;">{if $icount > 1}{$selectall_items}{/if}</th>
    </tr></thead>
    <tbody>
 {foreach from=$items item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
      <td>{$entry->name}</td>
      <td>{$entry->group}</td>
{if $own} <td>{$entry->ownername}</td>{/if}
      <td>{$entry->active}</td>
{if $dev} <td>{ldelim}Booker item={$entry->tag}{rdelim}</td>{/if}
      <td>{$entry->bsee}</td>
{if $bmod}<td>{$entry->bedit}</td>{/if}
      <td>{$entry->export}</td>
      <td>{$entry->see}</td>
{if $mod} <td>{$entry->edit}</td>{/if}
{if $add} <td>{$entry->copy}</td>{/if}
{if $del} <td class="bkrdel">{$entry->delete}</td>{/if}
      <td class="checkbox">{$entry->sel}</td>
    </tr>
 {/foreach}
    </tbody>
  </table>
</div>
{else}
 <p class="pagetext" style="font-weight:normal;">{$noitems}</p>
{/if}
<div id="itemacts" class="pageoptions">
{if $add}{$additem}{/if}
{if $icount > 0}
<span style="margin-left:5em;">
{if $add}{$importbtn3} {/if}{$exportbtn3} {$feebtn3}{if $mod}{if $icount > 1} {$sortbtn3}{/if} {$ablebtn3}{/if}{if $del} {$deletebtn3}{/if}
</span>
{else}
{if $add}<span style="margin-left:3em">{$importbtn3}</span>{/if}
{/if}
</div>
{$endform}
{$end_tab}

{$start_grps_tab}
{$startform4}
{if $gcount > 0}
<div class="pageoverflow">
  <table id="groups" class="table_sort leftwards pagetable">
    <thead><tr>
      <th>{$title_gname}</th>
      <th>{$title_gcount}</th>
      <th>{$title_grp}</th>
{if $own} <th>{$title_owner}</th>{/if}
      <th class="pageicon">{$title_active}</th>
{if $dev} <th>{$title_tag}</th>{/if}
      <th class="pageicon nosort">&nbsp;</th>
{if $mod} <th class="pageicon nosort">&nbsp;</th>{/if}
      <th class="pageicon nosort">&nbsp;</th>
      <th class="pageicon nosort">&nbsp;</th>
{if $mod} <th class="pageicon nosort">&nbsp;</th>{/if}
{if $add} <th class="pageicon nosort">&nbsp;</th>{/if}
{if $del} <th class="pageicon nosort">&nbsp;</th>{/if}
      <th class="checkbox nosort" style="width:20px;">{if $gcount > 1}{$selectall_grps}{/if}</th>
    </tr></thead>
    <tbody>
 {foreach from=$groups item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
      <td>{$entry->name}</td>
      <td>{$entry->count}</td>
      <td>{$entry->group}</td>
{if $own}  <td>{$entry->ownername}</td>{/if}
      <td>{$entry->active}</td>
{if $dev}  <td>{ldelim}Booker item={$entry->tag}{rdelim}</td>{/if}
      <td>{$entry->bsee}</td>
{if $mod}  <td>{$entry->bedit}</td>{/if}
      <td>{$entry->export}</td>
      <td>{$entry->see}</td>
{if $mod} <td>{$entry->edit}</td>{/if}
{if $add} <td>{$entry->copy}</td>{/if}
{if $del} <td class="bkrdel">{$entry->delete}</td>{/if}
      <td class="checkbox">{$entry->sel}</td>
    </tr>
 {/foreach}
    </tbody>
  </table>
</div>
{else}
  <p class="pagetext" style="font-weight:normal;">{$nogroups}</p>
{/if}
<div id="groupacts" class="pageoptions">
{if $add}{$addgrp}{/if}
{if $gcount > 0}
<span style="margin-left:5em;">
{if $add}{$importbtn4} {/if}{$exportbtn4} {$feebtn4}{if $mod}{if $gcount > 1} {$sortbtn4}{/if} {$ablebtn4}{/if}{if $del} {$deletebtn4}{/if}
</span>
{else}
{if $add}<span style="margin-left:3em">{$importbtn4}</span>{/if}
{/if}
</div>
{$endform}
{$end_tab}

{$start_settings_tab}
{if $set}
{$startform5}
<div style="margin:0 20px;overflow:auto;">
<p class="pagetext" style="font-weight:normal;">{$compulsory}</p>
{foreach from=$settings item=entry name=opts}
 <p class="pagetext">{$entry->title}:{if !empty($entry->must)} *{/if}</p>
 <div class="pageinput">{$entry->input}</div>
 {if !empty($entry->help)}<p class="pageinput">{$entry->help}</p>{/if}
{if !$smarty.foreach.opts.last}<br />{/if}
{/foreach}
</div>
<div class="pageinput" style="margin-top:1em;">{$submitbtn4} {$cancel}</div>
{$endform}
{else}
<p class="pagetext" style="font-weight:normal;">{$nopermission}</p>
{/if}
{$end_tab}

{$tab_footers}

<div id="confirm" class="modal-overlay"></div>
<div id="confgeneral" class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit btn_conf" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit btn_deny" type="submit" value="{$no}" /></p>
</div>
{if ($tell && isset($modaltitle))}
<div id="confmessage" class="confirm-container">
<p style="text-align:center;font-weight:bold;">{$modaltitle}
<br /><br /><span id="common"></span>
<br /><br />{$prompttitle} {$customentry}</p>
<p style="text-align:center;"><input id="mc_conf2" class="cms_submit btn_conf" type="submit" value="{$proceed}" />
&nbsp;&nbsp;<input id="mc_deny2" class="cms_submit btn_deny" type="submit" value="{$abort}" /></p>
</div>
{/if}
{if !empty($jsincs)}{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
{if !empty($jsfuncs)}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
{/if}
