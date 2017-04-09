<div id="needjs">{$needjs}</div>
{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
<h4 class="bkgtitle">{$title}</h4>
{if isset($desc)}<p class="bkgdesc">{$desc}</p><br />{/if}
{if isset($pictures)}<div class="bkgimg">
{foreach from=$pictures item=pic}
<img src="{$pic->url}"{if !empty($pic->ttl)} alt="{$pic->ttl}"{/if} />
{/foreach}
</div><br />{/if}
{if !empty($bulletin)}<p>{$bulletin}</p><br />{/if}
{$startform}
{foreach from=$hidden item=inc}{$inc}{/foreach}
{if isset($actions)}{foreach from=$actions key=k item=btn}{if $k>0}&nbsp;&nbsp;{/if}{$btn}{/foreach}<br /><br />{/if}
{if $columns}
<div style="margin:2px 2px 12px 2px;height:30em;width:100%;">
<table id="scroller" class="booker {$tableclass}">
 <thead><tr>
{for $c=0 to $colcount-1}
{if !empty($columns[$c])}<th {$columns[$c][0]->style} iso="{$columns[$c][0]->iso}">{$columns[$c][0]->data}{else}<th>{/if}</th>
{/for}
 </tr></thead>
 <tbody>
{for $r=1 to $rowcount-1}
 <tr>
{for $c=0 to $colcount-1}
{if !empty($columns[$c])}<td{if !empty($columns[$c][$r]->bkgid)} id="{$columns[$c][$r]->bkgid}"{/if}
{if !empty($columns[$c][$r]->style)} {$columns[$c][$r]->style}{/if}
{if !empty($columns[$c][$r]->tip)} title="{$columns[$c][$r]->tip}"{/if}
{if !empty($columns[$c][$r]->iso)} iso="{$columns[$c][$r]->iso}"{/if}>{$columns[$c][$r]->data}
{else}<td>{/if}</td>
{/for}
 </tr>
{/for}
 </tbody>
</table>
</div>
<p>{$focushelp}</p>
{else}
<p>{$nobookings}</p>
{/if}
<div>
<table id="bookactions" style="display:inline-block;border:0;overflow:auto;">
<tr>{foreach from=$actions1 item=inc}<td>{$inc}</td>{/foreach}</tr>
<tr>{foreach from=$actions2 item=inc}<td>{$inc}</td>{/foreach}</tr>
</table>
</div>
{$endform}