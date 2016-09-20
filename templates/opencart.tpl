{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
<div id="needjs">{$needjs}</div>
{if !empty($message)}<p class="pagemessage">{$message}</p><br />{/if}
<h4 class="bkgtitle">{$title}</h4>
{if !empty($desc)}<p class="bkgdesc">{$desc}</p><br /><br />{/if}
{$startform}
{if $count}
<div style="overflow:auto;">
 <table id="cart" class="bkr_collapse">
  <thead><tr>
   <th>{$whattitle}</th>
   <th>{$whentitle}</th>
   <th>{$feetitle}</th>
   <th>{$cmttitle}</th>
   <th class="pageicon {ldelim}sss:false{rdelim}"></th>
  </tr></thead>
  <tbody>
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
   <td colspan="2">{$totaltitle}</td>
   <td style="text-align:right">{$payable}</td>
   <td></td>
   <td></td>
  </tr>
{/if}
  </tbody>
 </table>
</div>
{else}
 <p>{$noitems}</p>
{/if}
 <div style="margin-top:1em;">
  {if $count && $submit}{$submit}{/if}{if $cancel} {$cancel}{/if}{if ($count && $delete)} {$delete}{/if}
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
