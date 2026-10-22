{**
 * Edición de la tienda y cómo pasar a PRO. PRO es un complemento: el módulo «BK SEO Redirects PRO», que se
 * instala junto a este y añade lo suyo en estas mismas pantallas.
 *}
<div class="bkr">
  <div class="panel bkr-edition">
    <div class="panel-heading">
      <i class="icon-key"></i> {l s='Your edition' d='Modules.Bkredirects.Admin'}
    </div>

    <div class="bkr-edition-head">
      <div>
        <p class="bkr-edition-name">
          {if $bkr_is_pro}
            <span class="bkr-pro-tag bkr-pro-tag--on">PRO</span> {l s='BK SEO Redirects PRO' d='Modules.Bkredirects.Admin'}
          {else}
            {l s='BK SEO Redirects Free' d='Modules.Bkredirects.Admin'}
          {/if}
        </p>
        {if $bkr_is_pro}
          <p class="bkr-edition-meta">{l s='Add-on installed: version %version%.' sprintf=['%version%' => $bkr_pro_version] d='Modules.Bkredirects.Admin'}</p>
        {/if}
      </div>
    </div>

    {if $bkr_is_pro}
      <p class="bkr-lead">
        {l s='Everything is on: suggested destinations, fixing in bulk, automations, import and export, patterns, URL parameters and rules per language.' d='Modules.Bkredirects.Admin'}
      </p>
    {else}
      <h4 class="bkr-subtitle">{l s='How to switch to PRO' d='Modules.Bkredirects.Admin'}</h4>
      <p class="bkr-lead">{l s='PRO is an add-on module that installs next to this one and adds its features to these same screens. Your rules and log stay as they are.' d='Modules.Bkredirects.Admin'}</p>
      <ol class="bkr-steps">
        <li>
          <strong>{l s='Get the PRO licence' d='Modules.Bkredirects.Admin'}</strong>
          <span>{l s='On the module page at bkmodules.com.' d='Modules.Bkredirects.Admin'}
            <a href="{$bkr_pro_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='See PRO' d='Modules.Bkredirects.Admin'}</a></span>
        </li>
        <li>
          <strong>{l s='Download BK SEO Redirects PRO' d='Modules.Bkredirects.Admin'}</strong>
          <span>{l s='It is in My licences, next to this module.' d='Modules.Bkredirects.Admin'}
            <a href="{$bkr_licenses_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Open My licences' d='Modules.Bkredirects.Admin'}</a></span>
        </li>
        <li>
          <strong>{l s='Install it in Modules' d='Modules.Bkredirects.Admin'}</strong>
          <span>{l s='Upload the zip in Modules › Module Manager. PRO turns on as soon as it is installed.' d='Modules.Bkredirects.Admin'}</span>
        </li>
      </ol>
    {/if}
  </div>
</div>
