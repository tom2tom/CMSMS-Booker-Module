<div class="browsenav">{$pagenav}</div><br />
{if !empty($message)}{$message}<br />{/if}
<h3 style="margin-left:2%;">{$title}</h3>
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
    </tr></thead>
    <tbody>
{foreach from=$data item=entry}{cycle values='row1,row2' assign='rowclass'}
     <tr class="{$rowclass}">
{foreach from=$entry->fields key=k item=value}<td{if $k>1} style="text-align:right"{/if}>{$value}</td>{/foreach}
      <td>{$entry->view}</td>
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
<br />
<fieldset>
 <legend>{$rangeset}</legend>
 <p class="pagetext" style="margin:0">{$titlefrom}</p>
 <div class="pageinput" style="margin:0 0 1em 0">{$showfrom}<br />
 {$helpfrom}</div>
 <p class="pagetext" style="margin:0">{$titleto}</p>
 <div class="pageinput" style="margin:0 0 1em 0">{$showto}<br />
 {$helpto}</div>
 {$range}
</fieldset>
{$endform}