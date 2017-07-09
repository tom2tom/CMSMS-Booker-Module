<div id="needjs">{$needjs}</div>
{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
<h4 class="bkgtitle">{$title}: {$textwhat}</h4>
{if !empty($desc)}<p class="bkgdesc">{$desc}</p>{/if}
{if !empty($pictures)}<div class="bkgimg">
{foreach from=$pictures item=pic}
<img src="{$pic->url}"{if !empty($pic->ttl)} alt="{$pic->ttl}"{/if} />
{/foreach}
</div><br />{/if}
{if !empty($bulletin)}<p class="bkgnotice">{$bulletin}</p>{/if}
{if !empty($currentmsg)}<p>{$currentmsg}</p>{/if}
<p>{$mustmsg}</p>
{$startform}
{$hidden}
<div style="overflow:auto;">
<table class="shrink"><tbody>
{foreach from=$tablerows item=entry}
<tr{if $entry->class} class="{$entry->class}"{/if}><td>{if $entry->mst}* {/if}{$entry->ttl}</td><td>{$entry->inp}</td></tr>
{/foreach}
</tbody></table>
</div>
{if isset($cart)}
<div style="float:left;">{$carticon}</div>
<div class="helptoggle">{$carthelp}</div>
{/if}
<div style="margin-top:0.5em;">
{$submit}{if isset($cart)}&nbsp;{$cart}{/if}{if isset($delete)}&nbsp;{$delete}{/if}&nbsp;{$cancel}
<div style="float:right;">{$register}&nbsp;{$change}</div>
<div style="clear:both;"></div>
</div>
{$endform}
