{**
 * Importar y exportar sin el complemento PRO: qué haría, con el candado y el enlace a la ficha.
 *}
<div class="bkr">
  <div class="panel bkr-locked">
    <div class="panel-heading">
      <i class="icon-exchange"></i> {l s='Import and export' d='Modules.Bkredirects.Admin'}
      <span class="bkr-pro-tag bkr-pro-tag--heading"><i class="icon-lock" aria-hidden="true"></i> PRO</span>
    </div>
    {capture assign=bkr_pro_text}{l s='Importing is only available in PRO. Bring in the redirects you already have, from a spreadsheet or from another module, in one go, and export them whenever you like.' d='Modules.Bkredirects.Admin'}{/capture}
    {include file="`$bkr_tpl`_pro.tpl" text=$bkr_pro_text}
    <ul class="bkr-locked-list">
      <li><i class="icon-upload" aria-hidden="true"></i> {l s='Import from a CSV file' d='Modules.Bkredirects.Admin'}</li>
      <li><i class="icon-exchange" aria-hidden="true"></i> {l s='Import from other modules' d='Modules.Bkredirects.Admin'}</li>
      <li><i class="icon-download-alt" aria-hidden="true"></i> {l s='Export' d='Modules.Bkredirects.Admin'}</li>
    </ul>
  </div>
</div>
