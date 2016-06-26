<table><tbody>
{foreach from=$entries item=row}
{cycle values='row1,row2' assign='rowclass'}
 <tr class="{$rowclass}" style="height:1.5em;" onmouseover="this.className='{$rowclass}hover';" onmouseout="this.className='{$rowclass}';">
  <td style="padding:0 5px;">{$row->desc}</td><td style="text-align:right;">{$row->fee}</td><td>::</td><td style="text-align:left;">{$row->cond}</td>
 </tr>
{/foreach}
</tbody></table>
