<h3 style="margin:2em 0 1em 2em">{$title}</h3>
{if !empty($intro)}<p style="margin:0 0 1em 2em">{$intro}</p>{/if}
{$startform}
{$hidden}
<div style="margin-left:2em">
{if $count > 0}
<div style="overflow:auto;display:inline-block;">
 <table id="fees" class="pagetable{if $mod} table_drag{/if}" style="border-collapse:collapse">
  <thead><tr>
   <th>{$desctext}</th>
   <th>{$counttext}</th>
   <th>{$typetext}</th>
   <th>{$feetext}</th>
   <th>{$condtext}</th>
{if $mod}   <th class="updown">{$movetext}</th>
   <th class="pageicon">&nbsp;</th>
   <th class="checkbox" style="width:20px;">{if $count > 1}{$selectall}{/if}</th>
{/if}
  </tr></thead>
  <tbody>
 {foreach from=$grpitems item=entry} {cycle values='row1,row2' name='c2' assign='rowclass'}
  <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
   <td>{$entry->hidden}{$entry->input_desc}</td>
   <td>{$entry->input_type}</td>
   <td>{$entry->input_fee}</td>
   <td>{$entry->input_cond}</td>
{if $mod}   <td class="updown">{$entry->downlink}{$entry->uplink}</td>
   <td>{$entry->deletelink}</td>
   <td class="checkbox">{$entry->selected}</td>
{/if}
  </tr>
 {/foreach}
  </tbody>
 </table>
 </div>
{if $mod && $count > 1}<p class="dndhelp">{$dndhelp}</p>{/if}
{else}
 <p class="pageinput" style="margin:20px;">{$nofees}</p>
{/if}
<div class="pageoptions" style="margin:1em 0 0 0;">
{if $mod}{$addlink}{/if}
{if $count > 0}
<div class="pageinput" style="float:right;text-align:right">
{$cancel}{if $mod} {$submit}{if $count > 1} {$delete}{/if}{/if}
</div>
<div class="clearb"></div>
{else}
{$cancel}
{/if}
</div>
</div>
{$endform}
