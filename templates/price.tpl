<h3 style="margin:2em 0 1em 2em">{$title}</h3>
{if !empty($intro)}<p style="margin:0 0 1em 2em">{$intro}</p>{/if}
{$startform}
{$hidden}
<div style="margin-left:2em">
 <p>{$pricetext1}</p>
 <div>{$priceinput1}</div>
 <br />
 <p>{$pricetext2}</p>
 <div>{$priceinput2}</div>
</div>
<div class="pageinput" style="margin-top:1em;">
{if $mod}{$submit} {/if}{$cancel}
</div>
{$endform}
