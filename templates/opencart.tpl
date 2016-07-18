{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
{if !empty($message)}<h3>{$message}</h3><br />{/if}
<h3>{$title}</h3>
{if !empty($desc)}<p>{$desc}</p>{/if}
{$startform}
 <div id="itemstable" class="bkr_overflow">
  <table id="cart" class="bkr_collapse">
   <thead><tr>
    <th>{$whattitle}</th>
    <th>{$whentitle}</th>
    <th>{$feetitle}</th>
    <th>{$cmttitle}</th>
    <th class="pageicon {ldelim}sss:false{rdelim}"></th>
   </tr></thead>
   <tbody>
{if $count}
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
{/if}
   </tbody>
  </table>
 </div>
 <div id="emptymessage" style="display:none;"><p>{$noitems}</p></div>
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
