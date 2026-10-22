{**
 * Interruptor Sí/No del back office (el mismo marcado que pinta HelperForm).
 *}
<span class="switch prestashop-switch fixed-width-lg">
  <input type="radio" name="{$name|escape:'html':'UTF-8'}" id="{$name|escape:'html':'UTF-8'}_on" value="1"{if $value} checked="checked"{/if}{if isset($disabled) && $disabled} disabled{/if}>
  <label for="{$name|escape:'html':'UTF-8'}_on">{l s='Yes' d='Modules.Bkredirects.Admin'}</label>
  <input type="radio" name="{$name|escape:'html':'UTF-8'}" id="{$name|escape:'html':'UTF-8'}_off" value="0"{if !$value} checked="checked"{/if}{if isset($disabled) && $disabled} disabled{/if}>
  <label for="{$name|escape:'html':'UTF-8'}_off">{l s='No' d='Modules.Bkredirects.Admin'}</label>
  <a class="slide-button btn"></a>
</span>
