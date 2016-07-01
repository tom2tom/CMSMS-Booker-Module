{foreach from=$entries item=row}
{cycle values='row1,row2' assign='rowclass'}
  <tr class="{$rowclass}" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
  <td>{$row->name}</td><td class="updown">{$row->dnlink}{$row->uplink}</td><td class="checkbox{if $cellclass} {$cellclass}{/if}">{$row->check}</td>
  </tr>
{/foreach}
