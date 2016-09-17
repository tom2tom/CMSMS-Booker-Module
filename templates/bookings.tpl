<div class="bkr_browsenav">{$pagenav}</div><br />
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
     <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if !empty($header_checkbox)}{$header_checkbox}{/if}</th>
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
{if $pmod}{$iconlinkadd}&nbsp;{$textlinkadd}<span style="margin-left:5em;">{$importbbtn}&nbsp;{/if}
{if $ocount > 0}{$export}&nbsp;{if $tell}{$notify}{/if}{if $pmod}&nbsp;{$delete}{/if}</span>{/if}
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
{if $pmod}    <th class="pageicon {ldelim}sss:false{rdelim}"></th>{/if}
      <th class="checkbox {ldelim}sss:false{rdelim}" style="width:20px;">{if !empty($header_checkbox2)}{$header_checkbox2}{/if}</th>
     </tr></thead>
     <tbody>
{foreach from=$reptrows item=bkg}{cycle values='row1,row2' assign='rowclass'}
      <tr class="{$rowclass}">
       <td>{$bkg->desc}</td>
{if isset($bkg->count)}    <td>{$bkg->count}</td>{/if}
       <td>{$bkg->name}</td>
       <td>{$bkg->paid}</td>
       <td>{$bkg->open}</td>
{if $tell}    <td class="bkrtell">{$bkg->tell}</td>{/if}
{if $pmod}    <td class="bkrdel">{$bkg->delete}</td>{/if}
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
{if $rcount > 0}<span style="margin-left:5em">{if $tell}{$notify2}{/if}{if $pmod}&nbsp;{$delete2}{/if}</span>{/if}
   </div>
{$endform}

<div id="confirm" class="modal-overlay"></div>
<div id="confgeneral" class="confirm-container">
<p style="text-align:center;font-weight:bold;"></p>
<br />
<p style="text-align:center;"><input id="mc_conf" class="cms_submit btn_conf" type="submit" value="{$yes}" />
&nbsp;&nbsp;<input id="mc_deny" class="cms_submit btn_deny" type="submit" value="{$no}" /></p>
</div>
{if ($tell && isset($modaltitle))}
<div id="confmessage" class="confirm-container">
<p style="text-align:center;font-weight:bold;">{$modaltitle}
<br /><br /><span id="common"></span>
<br /><br />{$prompttitle} {$customentry}</p>
<p style="text-align:center;"><input id="mc_conf2" class="cms_submit btn_conf" type="submit" value="{$proceed}" />
&nbsp;&nbsp;<input id="mc_deny2" class="cms_submit btn_deny" type="submit" value="{$abort}" /></p>
</div>
{/if}
{if !empty($jsincs)}{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
