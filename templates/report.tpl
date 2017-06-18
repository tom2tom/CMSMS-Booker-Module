<div class="browsenav">{$pagenav}</div><br />
{if !empty($message)}{$message}<br />{/if}
<h4 style="margin-left:5%;">{$title}</h4>
{$startform}
{if $dcount > 0}
 {if !empty($hasnav)}<div>{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}&nbsp;({$pageof})&nbsp;&nbsp;{$rowchanger}</div>
 {/if}
  <div style="overflow:auto;">
   <table id="datatable" class="{if $dcount > 1}table_sort {/if}leftwards pagetable">
   <thead><tr>
{foreach from=$colnames key=fcol item=fname}
     <th class="{ldelim}sss:false{rdelim}">{$fname}</th>
{/foreach}
     <th class="pageicon {ldelim}sss:false{rdelim}"></th>
     <th class="checkbox {ldelim}sss:false{rdelim}">{if $dcount > 1}{$header_checkbox}{/if}</th>
    </tr></thead>
    <tbody>
{foreach from=$data item=entry}{cycle values='row1,row2' assign='rowclass'}
     <tr class="{$rowclass}">
{foreach from=$entry->fields key=k item=value}<td{if $k>1} style="text-align:right"{/if}>{$value}</td>{/foreach}
      <td>{$entry->view}</td>
      <td class="checkbox">{$entry->sel}</td>
     </tr>
{/foreach}
    </tbody>
   </table>
  </div>
 {if !empty($hasnav)}<div>{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}</div>{/if}
{else}
  <br />
  <p class="pageinput">{$nodata}</p>
{/if}
<div class="pageoptions" style="margin-top:1em;">
{if $dcount > 0}{$export} {/if}{$close}
</div>
{if $dcount > 0}<fieldset>
 <legend>Change Report Interval</legend>
 Start Title<br />
 {$startchooser}<br />
 start tip<br />
 End Title<br />
 {$endchooser}<br />
 end tip<br />
 {$range}<br />
</fieldset>{/if}
{$endform}