{if !empty($message)}{$message}<br />{/if}
{$tab_headers}

{$start_data_tab}
{$startform1}
{if $dcount > 0}
<h4 style="margin-left:0;">{$title_pending}</h4>
{if !empty($hasnav1)}<div class="browsenav">{$first1}&nbsp;|&nbsp;{$prev1}&nbsp;&lt;&gt;&nbsp;{$next1}&nbsp;|&nbsp;{$last1}&nbsp;({$pageof1})&nbsp;&nbsp;{$rowchanger1}</div>
{/if}
<div style="overflow:auto;">
  <table id="datatable" class="{if $dcount > 1}table_sort {/if}leftwards pagetable">
    <thead><tr>
      <th>{$title_lodger}</th>
      <th>{$title_contact}</th>
      <th>{$title_lodged}</th>
      <th>{$title_status}</th>
      <th>{$title_paid}</th>
      <th>{$title_name}</th>
      <th>{$title_start}</th>
      <th>{$title_comment}</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $mod}  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $bmod} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $tell} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $del}  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if $dcount > 1}{$selectall_req}{/if}</th>
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
{if !empty($hasnav1)}<div class="browsenav">{$first1}&nbsp;|&nbsp;{$prev1}&nbsp;&lt;&gt;&nbsp;{$next1}&nbsp;|&nbsp;{$last1}</div>{/if}
{else}
 <p class="pageinput">{$nodata}</p>
{/if}
<div id="dataacts" class="pageoptions" style="margin-top:1em;">
{$findbtn}
{if $dcount > 0}
{if $bmod} {$approvbtn} {$rejectbtn}{/if}{if $tell} {$askbtn}{/if}{if $del} {$deletebtn1}{/if}
{/if}
{if $bmod} {$importbtn1} {/if} {$bexportbtn1}
</div>
{$endform}
{$end_tab}

{$start_people_tab}
{$startform2}
{if $pcount > 0}
{if !empty($hasnav2)}<div class="browsenav">{$first2}&nbsp;|&nbsp;{$prev2}&nbsp;&lt;&gt;&nbsp;{$next2}&nbsp;|&nbsp;{$last2}&nbsp;({$pageof2})&nbsp;&nbsp;{$rowchanger2}</div>
{/if}
<div style="overflow:auto;">
  <table id="peopletable" class="{if $pcount > 1}table_sort {/if}leftwards pagetable">
    <thead><tr>
      <th>{$title_person}</th>
      <th class="{ldelim}sss:'icon'{rdelim}">{$title_reg}</th>
      <th class="{ldelim}sss:'icon'{rdelim}">{$title_active}</th>
      <th>{$title_added}</th>
      <th>{$title_total}</th>
      <th>{$title_first}</th>
      <th>{$title_last}</th>
      <th>{$title_future}</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $per} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $per} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if $pcount > 1}{$selectall_bookers}{/if}</th>
    </tr></thead>
    <tbody>
{foreach from=$bookers item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
      <td>{$entry->name}</td>
      <td>{$entry->reg}</td>
      <td>{$entry->act}</td>
      <td>{$entry->added}</td>
      <td>{$entry->total}</td>
      <td>{$entry->first}</td>
      <td>{$entry->last}</td>
      <td>{$entry->future}</td>
      <td>{$entry->bsee}</td>
{if $per} <td>{$entry->bedit}</td>{/if}
      <td>{$entry->export}</td>
      <td>{$entry->see}</td>
{if $per} <td>{$entry->edit}</td>
      <td class="bkrdel">{$entry->delete}</td>{/if}
      <td class="checkbox">{$entry->sel}</td>
    </tr>
{/foreach}
    </tbody>
  </table>
</div>
{if !empty($hasnav2)}<div class="browsenav">{$first2}&nbsp;|&nbsp;{$prev2}&nbsp;&lt;&gt;&nbsp;{$next2}&nbsp;|&nbsp;{$last2}</div>{/if}
{else}
 <p class="pageinput">{$nobookers}</p>
{/if}
<div id="peopleacts" class="pageoptions" style="margin-top:1em;">
{if $per}{$addbooker}{/if}
{if $pcount > 0}
<span style="margin-left:5em;">{if $per} {$ablebtn2} {$deletebtn2}{/if}</span>
 {$exportbtn2}{if $per} {$importbtn2}{/if} {$bexportbtn2}
{else}
{if $per}<span style="margin-left:3em">{$importbtn2}</span>{/if}
{/if}
</div>
{$endform}
{$end_tab}

{$start_items_tab}
{$startform3}
{if $icount > 0}
{if !empty($hasnav3)}<div class="browsenav">{$first3}&nbsp;|&nbsp;{$prev3}&nbsp;&lt;&gt;&nbsp;{$next3}&nbsp;|&nbsp;{$last3}&nbsp;({$pageof3})&nbsp;&nbsp;{$rowchanger3}</div>
{/if}
<div style="overflow:auto;">
  <table id="itemstable" class="{if $icount > 1}table_sort {/if}leftwards pagetable">
    <thead><tr>
      <th>{$inametext}</th>
      <th>{$title_grp}</th>
{if $own} <th>{$title_owner}</th>{/if}
      <th class="pageicon {ldelim}sss:'icon'{rdelim}">{$title_active}</th>
      <th>{$title_total}</th>
      <th>{$title_first}</th>
      <th>{$title_last}</th>
      <th>{$title_future}</th>
{if $dev} <th>{$title_tag}</th>{/if}
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $bmod}<th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $mod} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $add} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $del} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if $icount > 1}{$selectall_items}{/if}</th>
    </tr></thead>
    <tbody>
 {foreach from=$items item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
      <td>{$entry->name}</td>
      <td>{$entry->group}</td>
{if $own} <td>{$entry->ownername}</td>{/if}
      <td>{$entry->active}</td>
      <td>{$entry->total}</td>
      <td>{$entry->first}</td>
      <td>{$entry->last}</td>
      <td>{$entry->future}</td>
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
{if !empty($hasnav3)}<div class="browsenav">{$first3}&nbsp;|&nbsp;{$prev3}&nbsp;&lt;&gt;&nbsp;{$next3}&nbsp;|&nbsp;{$last3}</div>{/if}
{else}
 <p class="pageinput">{$noitems}</p>
{/if}
<div id="itemacts" class="pageoptions" style="margin-top:1em;">
{if $add}{$additem}{/if}
{if $icount > 0}
<span style="margin-left:5em;">
{$feebtn3}{if $mod}{if $icount > 1} {$sortbtn3}{/if} {$ablebtn3}{/if}{if $del} {$deletebtn3}{/if}
</span>
{if $add}<br /><span style="margin-left:12em;">{$importbtn3} {$fimportbtn3}</span>{/if} {$exportbtn3} {$bexportbtn3}
{else}
{if $add}<span style="margin-left:3em">{$importbtn3}</span>{/if}
{/if}
</div>
{$endform}
{$end_tab}

{$start_grps_tab}
{$startform4}
{if $gcount > 0}
{if !empty($hasnav4)}<div class="browsenav">{$first4}&nbsp;|&nbsp;{$prev4}&nbsp;&lt;&gt;&nbsp;{$next4}&nbsp;|&nbsp;{$last4}&nbsp;({$pageof4})&nbsp;&nbsp;{$rowchanger4}</div>
{/if}
<div style="overflow:auto;">
  <table id="groupstable" class="{if $gcount > 1}table_sort {/if}leftwards pagetable">
    <thead><tr>
      <th>{$title_gname}</th>
      <th>{$title_gcount}</th>
      <th>{$title_grp}</th>
{if $own} <th>{$title_owner}</th>{/if}
      <th class="pageicon {ldelim}sss:'icon'{rdelim}">{$title_active}</th>
      <th>{$title_total}</th>
      <th>{$title_first}</th>
      <th>{$title_last}</th>
      <th>{$title_future}</th>
{if $dev} <th>{$title_tag}</th>{/if}
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $mod} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $mod} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $add} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $del} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if $gcount > 1}{$selectall_grps}{/if}</th>
    </tr></thead>
    <tbody>
 {foreach from=$groups item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
      <td>{$entry->name}</td>
      <td>{$entry->count}</td>
      <td>{$entry->group}</td>
{if $own}  <td>{$entry->ownername}</td>{/if}
      <td>{$entry->active}</td>
      <td>{$entry->total}</td>
      <td>{$entry->first}</td>
      <td>{$entry->last}</td>
      <td>{$entry->future}</td>
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
{if !empty($hasnav4)}<div class="browsenav">{$first4}&nbsp;|&nbsp;{$prev4}&nbsp;&lt;&gt;&nbsp;{$next4}&nbsp;|&nbsp;{$last4}</div>{/if}
{else}
  <p class="pageinput">{$nogroups}</p>
{/if}
<div id="groupacts" class="pageoptions" style="margin-top:1em;">
{if $add}{$addgrp}{/if}
{if $gcount > 0}
<span style="margin-left:5em;">
{$feebtn4}{if $mod}{if $gcount > 1} {$sortbtn4}{/if} {$ablebtn4}{/if}{if $del} {$deletebtn4}{/if}
{if $add}<br /><span style="margin-left:12em;">{$importbtn4} {$fimportbtn4}</span>{/if} {$exportbtn4} {$bexportbtn4}
</span>
{else}
{if $add}<span style="margin-left:3em">{$importbtn4}</span>{/if}
{/if}
</div>
{$endform}
{$end_tab}

{$start_reports_tab}
{$startform5}
<h5>{$report_type}</h5>
<br />
{$reportchoose}
<br /><br />
<h5>{$report_range}</h5>
<br />
<p class="pagetext" style="margin:0">{$titlefrom}</p>
<div class="pageinput" style="margin:0 0 1em 0">{$showfrom}<br />
{$helpfrom}</div>
<p class="pagetext" style="margin:0">{$titleto}</p>
<div class="pageinput" style="margin:0 0 1em 0">{$showto}<br />
{$helpto}</div>
<div id="reportacts" class="pageoptions" style="margin-top:1em;">
{$displaybtn} {$exportbtn5}
</div>
{$endform}
{$end_tab}

{$start_settings_tab}
{if $set}
{$startform6}
<div style="margin:0 20px;overflow:auto;">
<p class="pagetext" style="font-weight:normal;">{$compulsory}</p>
{foreach from=$settings item=entry name=opts}
 <p class="pagetext">{$entry->ttl}:{if !empty($entry->mst)} *{/if}</p>
 <div class="pageinput">{$entry->inp}</div>
 {if !empty($entry->hlp)}<p class="pageinput">{$entry->hlp}</p>{/if}
{if !$smarty.foreach.opts.last}<br />{/if}
{/foreach}
</div>
<div class="pageinput" style="margin-top:1em;">{$submitbtn4} {$cancel}</div>
{$endform}
{else}
<p class="pageinput">{$nopermission}</p>
{/if}
{$end_tab}

{$tab_footers}
