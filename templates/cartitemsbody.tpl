{foreach from=$items item=bkg}
 <tr>
  <td>{if $bkg->pic}{$bkg->pic}  {/if}{$bkg->name}</td>
  <td>{$bkg->when}</td>
  <td style="text-align:right">{$bkg->fee}</td>
  <td>{$bkg->comment}</td>
  <td>{$bkg->cb}<span style="display:none;">{$bkg->hidden}</span></td>
 </tr>
{/foreach}
{if $payable}
 <tr>
  <td>{$totaltitle}</td>
  <td></td>
  <td>{$payable}</td>
  <td></td>
  <td></td>
 </tr>
{/if}