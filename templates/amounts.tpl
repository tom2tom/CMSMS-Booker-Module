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
{if $mod}
    <th{if $rc > 1} class="{ldelim}sss:'textinput'{rdelim}"{/if}>{$title_change}</th>
    <th class="pageicon{if $rc > 1} nosort{/if}"></th>
    <th class="pageicon{if $rc > 1} nosort{/if}"></th>
    <th class="pageicon{if $rc > 1} nosort{/if}"></th>
    <th class="checkbox{if $rc > 1} nosort{/if}">{if $rc > 1}{$header_checkbox}{/if}</th>
{/if}
   </tr></thead>
   <tbody>
{foreach from=$data item=entry}{cycle values='row1,row2' assign='rowclass'}
    <tr class="{$rowclass}">
     <td>{$entry->name}</td>
     <td>{$entry->desc}</td>
     <td>{$entry->fee}</td>
     <td>{$entry->paid}</td>
{if $mod}
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
{if !empty($hasnav)}<div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}</div>{/if}
{else}
 <br />
 <p class="pageinput">{$norecords}</p>
{/if}
 <div class="pageoptions" style="margin-top:1em;">
{if ($rc > 0 && $mod)}{$set} {$change} {/if}{if !(isset($title_credit) || isset($title_range))}{$cancel}{/if}
 </div>
{if isset($title_range)}
<br /><br />
<h5 class="pageinput">{$title_range}</h5>
<p class="pagetext" style="margin:0">{$titlefrom}</p>
<div class="pageinput" style="margin:0 0 1em 0">{$showfrom}<br />
{$helpfrom}</div>
<p class="pagetext" style="margin:0">{$titleto}</p>
<div class="pageinput" style="margin:0 0 1em 0">{$showto}<br />
{$helpto}</div>
{$range}{if !isset($title_credit)} {$cancel}{/if}
{/if}
{if isset($title_credit)}
<br /><br />
<h5 class="pageinput">{$title_credit}</h5>
<p class="pagetext" style="margin-left:0">{$current_credit}</p>
{if $mod}
<div class="pageinput" style="margin-left:0">{$input2}<br />
{$help_credit}</div>
<div class="pageoptions" style="margin-top:1em">
{$set2} {$change2} {$cancel}
</div>
{/if}
{/if}
{$endform}
