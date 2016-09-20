{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
<div class="bkr_browsenav">{$pagenav}</div><br />
{if !empty($message)}<p class="pagetext pagemessage">{$message}</p><br />{/if}
<h3 class="pagetext">{$title}</h3>
{if !empty($desc)}<p class="pageinput">{$desc}</p>{/if}
{if !empty($compulsory)}<p class="pagetext" style="font-weight:normal;">{$compulsory}</p>{/if}
{$startform}
<div class="pageoverflow">
{foreach from=$data item=entry}
 <p class="pagetext">{$entry->title}:{if !empty($entry->must)} *{/if}</p>
 <div class="pageinput">{$entry->input}</div>
 {if !empty($entry->help)}<p class="pageinput">{$entry->help}</p>{/if}
{/foreach}
</div>
<div class="pageinput" style="margin-top:1em">
{if $mod}{$submit} {/if}{$cancel}{if $mod} {$apply} {$find}{/if}
</div>
{$endform}
{if isset($yes)}
<div id="confirm" class="modal-overlay">
<div id="confgeneral" class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit btn_conf" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit btn_deny" type="submit" value="{$no}" /></p>
</div>
</div>
{/if}
{if !empty($jsincs)}{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
{if !empty($jsfuncs)}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
{/if}
