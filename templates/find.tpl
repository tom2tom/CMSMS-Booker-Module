{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
<h4 class="bkgtitle">{$title}</h4>
{$startform}
{$hidden}
<table class="plain"><tbody>
<tr><td>:</td><td>TODO</td></tr>
<tr><td>:</td><td>TODO</td></tr>
<tr><td>:</td><td>TODO</td></tr>
<tr><td>:</td><td>TODO</td></tr>
<tr><td>:</td><td>TODO</td></tr>
</tbody></table>
<br />
{$submit}&nbsp;{$cancel}
{$endform}
<div id="calendar"></div>
{$jsincs}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
