<div class="browsenav">{$pagenav}</div><br />
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
