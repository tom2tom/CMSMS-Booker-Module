{if !empty($back_nav)}<div class="bkr_browsenav">{$back_nav}</div><br />{/if}
{if !empty($message)}<p class="pagetext pagemessage">{$message}</p><br />{/if}
<h3 class="pagetext">{$title}</h3><br />
<p class="pageinput">{$compulsory}</p><br />
{$startform}
<div class="pageinput pageoverflow">
{foreach from=$settings item=entry}
 <p class="pagetext" style="margin-left:0;">{$entry.ttl}:{if isset($entry.mst)} *{/if}</p>
 <div>{$entry.inp}</div>
{if $entry.hlp}<p>{$entry.hlp}</p>{/if}
{/foreach}
</div>
<div class="pageinput" style="margin-top:1em;">
{if $mod}{$submit} {/if}{$cancel}{if $mod} {$apply}{/if}
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
