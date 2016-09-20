{if !empty($message)}{$message}<br />{/if}
<h4 style="margin-left:5%;">{$title}</h4>
{if $count > 0}
{$startform}
STARTDATECHOOSER ENDDATECHOOSER
{if !empty($hasnav)}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}&nbsp;({$pageof})&nbsp;&nbsp;{$rowchanger}</div>
{/if}
<div style="overflow:auto;">
  <table id="datatable" class="{if $count > 1}table_sort {/if}leftwards pagetable">
    <thead><tr>
      <th>{$title_lodged}</th>
      <th>{$title_approved}</th>
      <th>{$title_name}</th>
      <th>{$title_count}</th>
      <th>{$title_start}</th>
      <th>{$title_end}</th>
      <th>{$title_fee}</th>
      <th>{$title_comment}</th>
      <th>{$title_status}</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
{if $mod} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>
      <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $tell} <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
{if $mod}  <th class="pageicon {ldelim}sss:false{rdelim}">&nbsp;</th>{/if}
      <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if $count > 1}{$selectall_req}{/if}</th>
    </tr></thead>
    <tbody>
 {foreach from=$data item=entry} {cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
    <td>{$entry->lodged}</td>
    <td>{$entry->approved}</td>
    <td>{$entry->name}</td>
    <td>{$entry->count}</td>
    <td>{$entry->start}</td>
    <td>{$entry->end}</td>
    <td>{$entry->fee}</td>
    <td>{$entry->comment}</td>
    <td>{$entry->status}</td>
    <td>{$entry->see}</td>
{if $mod} <td>{$entry->edit}</td>
     <td class="bkrapp">{$entry->approve}</td>
     <td class="bkrrej">{$entry->reject}</td>{/if}
{if $tell} <td class="bkrtell">{$entry->notice}</td>{/if}
{if $mod}  <td class="bkrdel">{$entry->delete}</td>{/if}
    <td class="checkbox">{$entry->sel}</td>
    </tr>
 {/foreach}
    </tbody>
  </table>
</div>
{if !empty($hasnav)}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}</div>{/if}
<div class="pageoptions" style="margin-top:1em;">
{if $mod} {$approvbtn} {$rejectbtn} {/if}{if $tell}{$notifybtn}{/if}{if $mod} {$deletebtn}{/if}
</div>
{$endform}
{if !empty($jsincs)}{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
{if !empty($jsfuncs)}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
{/if}
{else}
 <p class="pageinput">{$nodata}</p>
{/if}
