<table {if $identifier}id="{$identifier}" {/if}class="{if $drag}table_drag {/if}{if $sort} table_sort {/if}pagetable" style="margin-left:0;width:auto;border-collapse:collapse;">
{if $sort && $rc > 1}
 <thead><tr style="height:0.5em;"><th style="height:0.5em;"></th><th class="updown nosort" style="height:0.5em;"></th><th class="checkbox" style="height:0.5em;width:20px;">{$selectall}</th></tr></thead>
{/if}
 <tbody>
{foreach from=$entries item=row}
{cycle values='row1,row2' assign='rowclass'}
  <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
  <td>{$row->name}</td><td class="updown">{$row->dnlink}{$row->uplink}</td><td class="checkbox{if $identifier} {$identifier}{/if}">{$row->check}</td>
  </tr>
{/foreach}
 </tbody>
</table>
