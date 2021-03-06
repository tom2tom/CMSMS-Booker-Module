<div class="browsenav">{$pagenav}</div><br />
{if !empty($message)}<h3>{$message}</h3><br />{/if}
  <h2 class="pageinput">{$item_title}</h2>
{if !empty($desc)}<p class="pageinput">{$desc}</p>{/if}
{if !empty($help_group)}<p class="pageinput">{$help_group}</p><br />{/if}
  {$startform}
{if $ocount > 0}
{if $hasnav}
  <div class="browsenav">{$first}&nbsp;|&nbsp;{$prev}&nbsp;&lt;&gt;&nbsp;{$next}&nbsp;|&nbsp;{$last}&nbsp;({$pageof})&nbsp;&nbsp;{$rowchanger}</div>
{/if}
  <div style="overflow:auto;">
   <table id="bookings" class="{if $ocount > 1}table_sort {/if}leftwards pagetable">
    <thead><tr>
{foreach from=$colnames key=fcol item=fname}
     <th class="{ldelim}sss:{if $colsorts[$fcol]}'text'{else}false{/if}{rdelim}">{$fname}</th>
{/foreach}
     <th class="pageicon {ldelim}sss:false{rdelim}"></th>
     <th class="pageicon {ldelim}sss:false{rdelim}"></th>
{if $tell}  <th class="pageicon {ldelim}sss:false{rdelim}"></th>{/if}
{if $pmod}  <th class="pageicon {ldelim}sss:false{rdelim}"></th>{/if}
     <th class="checkbox {ldelim}sss:false{rdelim}">{if $ocount > 1}{$header_checkbox}{/if}</th>
    </tr></thead>
    <tbody>
{foreach from=$oncerows item=bkg}{cycle values='row1,row2' assign='rowclass'}
     <tr class="{$rowclass}">
      <td>{$bkg->time}</td>
      <td>{$bkg->name}</td>
      <td>{$bkg->paid}</td>
      <td>{$bkg->open}</td>
      <td>{$bkg->export}</td>
{if $tell} <td class="bkrtell">{$bkg->tell}</td>{/if}
{if $pmod}  <td class="bkrdel">{$bkg->delete}</td>{/if}
      <td>{$bkg->selected}</td>
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
{if $pmod}{$iconlinkadd}&nbsp;{$textlinkadd}<span style="margin-left:5em;">{$importbbtn} {/if}
{if $ocount > 0}{$export} {if $tell}{$notify}{/if}{if $pmod} {$delete}{/if}</span>{/if}
  </div>
  {$endform}

<p class="pagetext">{$item_title2}</p>
{$startform2}
{if $rcount > 0}
   <div style="overflow:auto;">
    <table id="repeats" class="{if $rcount > 1}table_sort {/if}leftwards pagetable">
     <thead><tr>
{foreach from=$colnames2 key=fcol item=fname}
      <th class="{ldelim}sss:{if $colsorts2[$fcol]}'text'{else}false{/if}{rdelim}">{$fname}</th>
{/foreach}
      <th class="pageicon {ldelim}sss:false{rdelim}"></th>
{if $tell}    <th class="pageicon {ldelim}sss:false{rdelim}"></th>{/if}
{if $pmod}    <th class="pageicon {ldelim}sss:false{rdelim}"></th>
      <th class="pageicon {ldelim}sss:false{rdelim}"></th>{/if}
      <th class="checkbox {ldelim}sss:false{rdelim}">{if $rcount > 1}{$header_checkbox2}{/if}</th>
     </tr></thead>
     <tbody>
{foreach from=$reptrows item=bkg}{cycle values='row1,row2' assign='rowclass'}
      <tr class="{$rowclass}">
       <td>{$bkg->desc}</td>
       <td>{$bkg->name}</td>
{if isset($bkg->count)}    <td>{$bkg->count}</td>{/if}
       <td>{$bkg->paid}</td>
       <td>{$bkg->open}</td>
{if $tell}    <td class="bkrtell">{$bkg->tell}</td>{/if}
{if $pmod}    <td class="bkrfresh">{$bkg->refresh}</td>
       <td class="bkrdel">{$bkg->delete}</td>{/if}
       <td>{$bkg->selected}</td>
      </tr>
{/foreach}
     </tbody>
    </table>
   </div>
{else}
  <br />
  <p class="pageinput">{$norecords}</p>
{/if}
   <div class="pageoptions" style="margin-top:1em;">
{if $pmod}{$iconlinkadd2}&nbsp;{$textlinkadd2}{/if}
{if $rcount > 0}<span style="margin-left:3em">{if $tell}{$notify2}{/if}{if $pmod} {$refresh2} {$delete2}{/if}</span>{/if}
   </div>
{$endform}
