<div class="browsenav">{$pagenav}</div><br />
{if !empty($message)}<p class="pagetext pagemessage">{$message}</p><br />{/if}
<h3 class="pagetext">{$title}</h3><br />
{if !empty($compulsory)}<p class="pagetext" style="font-weight:normal;">{$compulsory}</p><br />{/if}
{$startform}
<div class="pageinput pageoverflow">
{foreach from=$settings item=entry}
 <p class="pagetext" style="margin-left:0;">{$entry->ttl}:{if !empty($entry->mst)} *{/if}</p>
 <div>{$entry->inp}</div>
{if !empty($entry->hlp)}<p>{$entry->hlp}</p>{/if}
{/foreach}
</div>
<div class="pageinput" style="margin-top:1em;">
{if $mod}{$submit} {/if}{$cancel}{if $mod} {$apply}{/if}
</div>
{$endform}