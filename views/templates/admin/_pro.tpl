{**
 * Reclamo PRO de una función bloqueada: candado, una línea de qué hace y el enlace a la ficha.
 * Nunca esconde la función ni interrumpe: se lee y se sigue.
 *}
<p class="bkr-pro-line{if isset($compact) && $compact} bkr-pro-line--inline{/if}">
  <span class="bkr-pro-tag"><i class="icon-lock" aria-hidden="true"></i> PRO</span>
  <span class="bkr-pro-text">{$text|escape:'html':'UTF-8'}</span>
  <a class="bkr-pro-link" href="{$bkr_pro_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='See PRO' d='Modules.Bkredirects.Admin'}</a>
</p>
