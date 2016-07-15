{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
{if !empty($message)}<h3>{$message}</h3>{/if}
<h4>{$title}</h4>
{$startform}
{$hidden}
<div id="selectors">
 <table style="border:0"><tbody>
{foreach from=$selects item=entry}
 <tr></tr><td>{$entry->title}</td><td>{$entry->input}</td><tr></tr>
{/foreach}
 </tbody></table>
 <div id="calendar" style="margin:5px auto 0 5em"></div>
</div>
<div id="results">
{if $count}
<table id="details"{if $count>1} class="table_sort"{/if} style="margin:0;border:0;border-collapse:collapse;">
 <thead><tr>
  <th>{$whattitle}</th>
  <th class="{ldelim}sss:'slotwhen'{rdelim}">{$whentitle}</th>
  <th>{$whotitle}</th>
  <th class="{ldelim}sss:false{rdelim}"></th>
 </tr></thead>
 <tbody>
 {foreach from=$finds item=entry}
 <tr class="{$entry->rowclass}" onmouseover="this.className='{$entry->rowclass}hover';" onmouseout="this.className='{$entry->rowclass}';">
 <td>{$entry->what}</td>
 <td>{$entry->when}</td>
 <td>{$entry->who}</td>
 <td><span class="identifier" style="display:none;">{$entry->hidden}</span>{$entry->cb}</td>
 </tr>
 {/foreach}
 </tbody>
</table>
{else}
{if !empty($nofinds)}<p>{$nofinds}</p>{/if}
{/if}
</div>
<br />
{$search} {$cancel} {$submit}
{$endform}
{if $jsincs}
{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
{if $jsfuncs}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
{/if}