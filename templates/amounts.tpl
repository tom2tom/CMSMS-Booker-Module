<div class="browsenav">{$pagenav}</div><br />
{if !empty($message)}<h3>{$message}</h3><br />{/if}
<h2 class="pageinput">{$title}</h2>
{$startform}
{if $rc > 0}
 {if $hasnav}
 <div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}&nbsp;({$pageof})&nbsp;&nbsp;{$rowchanger}</div>
 {/if}
 <div style="overflow:auto;">
  <table id="amounts" class="{if $rc > 1}table_sort {/if}leftwards pagetable">
   <thead><tr>
    <th{if $rc > 1} class="{ldelim}sss:'text'{rdelim}"{/if}>{$title_name}</th>
    <th{if $rc > 1} class="{ldelim}sss:'text'{rdelim}"{/if}>{$title_desc}</th>
    <th{if $rc > 1} class="{ldelim}sss:'text'{rdelim}"{/if}>{$title_fee}</th>
    <th{if $rc > 1} class="{ldelim}sss:'text'{rdelim}"{/if}>{$title_paid}</th>
{if $pmod}
    <th{if $rc > 1} class="{ldelim}sss:'textinput'{rdelim}"{/if}>{$title_change}</th>
    <th class="pageicon{if $rc > 1} {ldelim}sss:false{rdelim}{/if}"></th>
    <th class="pageicon{if $rc > 1} {ldelim}sss:false{rdelim}{/if}"></th>
    <th class="pageicon{if $rc > 1} {ldelim}sss:false{rdelim}{/if}"></th>
    <th class="checkbox{if $rc > 1} {ldelim}sss:false{rdelim}{/if}">{if $rc > 1}{$header_checkbox}{/if}</th>
{/if}
   </tr></thead>
   <tbody>
{foreach from=$data item=entry}{cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}">
     <td>{$entry->name}</td>
     <td>{$entry->desc}</td>
     <td>{$entry->fee}</td>
     <td>{$entry->paid}</td>
{if $pmod}
     <td>{$entry->inp}</td>
     <td>{$entry->chg}</td>
     <td>{$entry->set}</td>
     <td>{$entry->ref}</td>
     <td>{$entry->sel}</td>
{/if}
    </tr>
{/foreach}
   </tbody>
  </table>
 </div>
{if $hasnav}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}</div>{/if}
{else}
 <br />
 <p class="pageinput">{$norecords}</p>
{/if}
 <div class="pageoptions" style="margin-top:1em;">
{if ($rc > 0 && $pmod)}{$set} {$change} {/if}{$cancel}
</div>
{if isset($title_range)}
<br />
<h5 class="pageinput">{$title_range}</h5>
<br />
<p class="pagetext" style="margin:0">{$titlefrom}</p>
<div class="pageinput" style="margin:0 0 1em 0">{$showfrom}<br />
{$helpfrom}</div>
<p class="pagetext" style="margin:0">{$titleto}</p>
<div class="pageinput" style="margin:0 0 1em 0">{$showto}<br />
{$helpto}</div>
{$range}
{/if}
{if isset($title_credit)}
<br />
<h5 class="pageinput">{$title_credit}</h5>
<br />
<p class="pagetext" style="margin-left:0">{$current_credit}</p>
{if $pmod}
<div class="pageinput">{$input2}<br />
{$help_credit}</div>
<div class="pageoptions" style="margin:0 0 1em 0">
{$set2} {$change2}
</div>
{/if}
{/if}
{$endform}
