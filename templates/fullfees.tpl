{if !empty($message)}{$message}<br />{/if}
<h3 style="margin:2em 0 1em 2em">{$title}</h3>
{if $count > 0}
{if !empty($intro)}<p style="margin:0 0 1em 2em">{$intro}</p>{/if}
{/if}
{$startform}
{$hidden}
<div style="margin-left:2em">
{if $count > 0}
<div style="overflow:auto;display:inline-block;">
 <table id="fees" class="pagetable{if $mod} table_drag{/if}" style="border-collapse:collapse">
  <thead><tr>
   <th>{$desctext}</th>
   <th colspan="2" style="text-align:center";>{$periodtext}</th>
   <th>{$feetext}</th>
   <th>{$condtext}</th>
   <th>{$activetext}</th>
{if $mod}   <th class="updown">{$movetext}</th>
   <th class="pageicon">&nbsp;</th>
   <th class="checkbox" style="width:20px;">{if $count > 1}{$selectall}{/if}</th>
{/if}
  </tr></thead>
  <tbody>
 {foreach from=$items item=entry} {cycle values='row1,row2' name='c2' assign='rowclass'}
  <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
   <td>{$entry->hidden}{$entry->desc}</td>
   <td>{$entry->count}</td>
   <td>{$entry->type}</td>
   <td>{$entry->fee}</td>
   <td>{$entry->cond}</td>
   <td>{$entry->active}</td>
{if $mod}   <td class="updown">{$entry->downlink}{$entry->uplink}</td>
   <td>{$entry->deletelink}</td>
   <td class="checkbox">{$entry->selected}</td>
{/if}
  </tr>
 {/foreach}
  </tbody>
 </table>
{if $mod && $count > 1}<p class="dndhelp" style="display:none;">{$dndhelp}</p>{/if}
 </div>
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
&nbsp;&nbsp;{$cancel}
{/if}
</div>
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

