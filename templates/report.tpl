<div class="browsenav">{$pagenav}</div><br />
{if !empty($message)}{$message}<br />{/if}
<h4 style="margin-left:5%;">{$title}</h4>
{$startform}
{if $dcount > 0}
 {if !empty($hasnav)}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}&nbsp;({$pageof})&nbsp;&nbsp;{$rowchanger}</div>
 {/if}
 {$startchooser} {$endchooser} {$range}
  <div class="pageinput" style="overflow:auto;">
   <table id="datatable" class="{if $dcount > 1}table_sort {/if}leftwards pagetable">
   <thead><tr>
{foreach from=$colnames key=fcol item=fname}
     <th class="{ldelim}sss:{if $colsorts[$fcol]}'text'{else}false{/if}{rdelim}">{$fname}</th>
{/foreach}
     <th class="pageicon {ldelim}sss:false{rdelim}"></th>
     <th class="pageicon {ldelim}sss:false{rdelim}"></th>
     <th class="checkbox {ldelim}sss:false{rdelim}">{if $dcount > 1}{$$header_checkbox}{/if}</th>
    </tr></thead>
    <tbody>
{foreach from=$data item=entry}{cycle values='row1,row2' assign='rowclass'}
     <tr class="{$rowclass}">
{foreach from=$entry->fields item=value}<td>{$value|escape}</td>{/foreach}
      <td>{$entry->view}</td>
      <td>{$entry->export}</td>
      <td>{$entry->sel}</td>
     </tr>
{/foreach}
    </tbody>
   </table>
  </div>
 {if !empty($hasnav)}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}</div>{/if}
{else}
  <br />
  <p class="pageinput">{$nodata}</p>
{/if}
<div class="pageoptions" style="margin-top:1em;">
{if $dcount > 0}{$export} {/if}{$close}
</div>
{$endform}