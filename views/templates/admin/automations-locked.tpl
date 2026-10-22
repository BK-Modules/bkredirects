{**
 * Automatizaciones sin el complemento PRO: qué harían, con el candado y el enlace a la ficha.
 *}
<div class="bkr">
  <div class="panel bkr-auto bkr-locked">
    <div class="panel-heading">
      <i class="icon-magic"></i> {l s='When you delete something or change its URL' d='Modules.Bkredirects.Admin'}
      <span class="bkr-pro-tag bkr-pro-tag--heading"><i class="icon-lock" aria-hidden="true"></i> PRO</span>
    </div>
    <p class="bkr-lead">
      {l s='PrestaShop forgets the old address the moment a product is deleted or its URL changes, and whoever arrives from Google or an old link finds a 404. With these on, the module creates the redirect at that same moment, so the visit and the ranking go to the right page.' d='Modules.Bkredirects.Admin'}
    </p>
    {capture assign=bkr_pro_text}{l s='creates these redirects on its own, the moment something is deleted or renamed.' d='Modules.Bkredirects.Admin'}{/capture}
    {include file="`$bkr_tpl`_pro.tpl" text=$bkr_pro_text}
  </div>
</div>
