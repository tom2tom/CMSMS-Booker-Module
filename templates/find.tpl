<div id="needjs">{$needjs}</div>
{if !empty($message)}<p class="pagemessage">{$message}</p><br />{/if}
<h4 class="bkgtitle">{$title}</h4>
{$startform}
{$hidden}
<div id="selectors">
 <table style="border:0"><tbody>
{foreach from=$selects item=entry}
 <tr></tr><td>{$entry->ttl}</td><td>{$entry->inp}</td><tr></tr>
{/foreach}
 </tbody></table>
</div>
{if $count}
{if $hasnav}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}&nbsp;({$pageof})&nbsp;&nbsp;{$rowchanger}</div>
{/if}
<div id="results" style="overflow:auto;">
<table id="details" class="{if $count>1}table_sort {/if}bkr_collapse">
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
 <td>{$entry->sel}</td>
 </tr>
 {/foreach}
 </tbody>
</table>
</div>
{if $hasnav}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}</div>{/if}
{else}
{if !empty($nofinds)}<p>{$nofinds}</p>{/if}
{/if}
<br />
{$search} {$cancel} {$submit}
{$endform}
