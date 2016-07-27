{if $jsstyler}<script type="text/javascript">
//<![CDATA[
{$jsstyler}
//]]>
</script>{/if}
{if !empty($message)}<p class="pagemessage">{$message}</p>{/if}
<h4 class="bkgtitle">{$title}: {$textwhat}</h4>
{if isset($desc)}<p class="bkgdesc">{$desc}</p><br /><br />{/if}
{if isset($pictures)}<div class="bkgimg">
{foreach from=$pictures item=pic}
<img src="{$pic->url}"{if !empty($pic->title)} alt="{$pic->title}"{/if} />
{/foreach}
</div><br /><br />{/if}
{if isset($membermsg)}<p>{$membermsg}</p>{/if}
{if isset($currentmsg)}<p>{$currentmsg}</p>{/if}
<p>{$mustmsg}</p>
{$startform}
{$hidden}
<table class="plain"><tbody>
<tr><td>{$title_what}</td><td>{$inputwhat}</td></tr>
{if isset($membermsg) && empty($past)}
<tr><td>* {$title_count}</td><td>{$inputcount}</td></tr>
{/if}
<tr><td>{if empty($past)}* {/if}{$title_when}:</td><td>{$inputwhen}</td></tr>
{if isset($inputuntil)}
<tr><td>{if empty($past)}* {/if}{$title_until}:</td><td>{$inputuntil}</td></tr>
{/if}
<tr><td>* {$title_sender}:</td><td>{$inputsender}</td></tr>
<tr><td>* {$title_contact}:</td><td>{$inputcontact}</td></tr>
<tr><td>{$title_comment}:</td><td>{$inputcomment}</td></tr>
{if isset($captcha)}<tr><td>* {$title_captcha}:</td><td>{$inputcaptcha} {$captcha}</td></tr>{/if}
</tbody></table>
<br />
{$submit}{if isset($cart)} {$cart}{/if} {$cancel} {if isset($choose)} {$choose}{/if}
{$endform}
<div id="calendar"></div>
{if !empty($jsincs)}{foreach from=$jsincs item=inc}{$inc}
{/foreach}{/if}
<script type="text/javascript">
//<![CDATA[
{foreach from=$jsfuncs item=func}{$func}{/foreach}
//]]>
</script>
