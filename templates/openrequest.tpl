<div class="browsenav">{$pagenav}</div><br />
{if !empty($message)}<p class="pagetext pagemessage">{$message}</p><br />{/if}
<h3 class="pagetext">{$title}</h3>
{if !empty($desc)}<p class="pageinput">{$desc}</p>{/if}
{if !empty($compulsory)}<p class="pagetext" style="font-weight:normal;">{$compulsory}</p>{/if}
{$startform}
<div class="pageoverflow">
{foreach from=$data item=entry}
 <p class="pagetext">{$entry->ttl}:{if !empty($entry->mst)} *{/if}</p>
 <div class="pageinput">{$entry->inp}</div>
 {if !empty($entry->hlp)}<p class="pageinput">{$entry->hlp}</p>{/if}
{/foreach}
</div>
<div class="pageinput" style="margin-top:1em">
{if $mod}{$submit} {/if}{$cancel}{if $mod} {$apply}<br /><br />{$approve} {$reject}{/if}{if $pmsg} {$ask} {$notify}{/if} {$find} {$table} {$list}
</div>
{$endform}
