{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
<h4 class="bkgtitle">{$title}</h4>
{if isset($desc)}<p class="bkgdesc">{$desc}</p><br />{/if}
{if isset($pictures)}<div class="bkgimg">
{foreach from=$pictures item=pic}
<img src="{$pic->url}"{if !empty($pic->title)} alt="{$pic->title}"{/if} />
{/foreach}
</div><br />{/if}
{$startform}
{foreach from=$hidden item=inc}{$inc}
{/foreach}
{if isset($actions)}{foreach from=$actions key=k item=btn}{if $k>0}&nbsp;&nbsp;{/if}{$btn}{/foreach}<br /><br />{/if}
{if $columns}
<div style="margin:2px 2px 12px 2px;height:30em;width:100%;">
<table id="scroller" class="booker {$tableclass}">
 <thead><tr>
{section name=c loop=$columns}
{if !empty($columns[c])}<th {$columns[c][0]->style} iso="{$columns[c][0]->iso}">{$columns[c][0]->data}{else}<th>{/if}</th>{/section}
 </tr></thead>
 <tbody>
{section name=rows start=1 loop=$rowcount}{*smarty3 for $r=1 to $rowcount*}
{assign var='r' value=$smarty.section.rows.index}
 <tr>
{section name=c loop=$columns}
{if !empty($columns[c])}<td{if !empty($columns[c][$r]->bkgid)} id="{$columns[c][$r]->bkgid}"{/if}
{if !empty($columns[c][$r]->style)} {$columns[c][$r]->style}{/if}
{if !empty($columns[c][$r]->tip)} title="{$columns[c][$r]->tip}"{/if}
{if !empty($columns[c][$r]->iso)} iso="{$columns[c][$r]->iso}"{/if}>{$columns[c][$r]->data}
{else}<td>{/if}</td>
{/section}
 </tr>
{/section}{*smarty3 /for*}
 </tbody>
</table>
</div>
<p>{$focushelp}</p>
{else}
<p>{$nobookings}</p>
{/if}
<div>
<table id="bookactions" style="display:inline-block;border:0;overflow:auto;">
<tr>{section name=c loop=$actions1}<td>{$actions1[c]}</td>{/section}</tr>
<tr>{section name=c loop=$actions2}<td>{$actions2[c]}</td>{/section}</tr>
</table>
</div>
<div id="calendar"></div>
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
