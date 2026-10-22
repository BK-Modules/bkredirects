{**
 * Alta y edición de una regla. Tres bloques: desde dónde, hacia dónde y dónde aplica.
 * Lo PRO se ve con su candado y una línea; las opciones PRO de un desplegable salen desactivadas.
 * El destino cambia de forma con el tipo (views/js/admin.js): URL, entidad con buscador o nada
 * (410/451).
 *}
<div class="bkr">
  <form method="post" action="{$bkr_form_url|escape:'html':'UTF-8'}" class="form-horizontal bkr-rule-form" novalidate>
    <input type="hidden" name="id_rule" value="{$bkr_rule.id_rule|intval}">
    <input type="hidden" name="submitBkrRule" value="1">
    {if $bkr_rec}<input type="hidden" name="bkr_rec" value="{$bkr_rec|escape:'html':'UTF-8'}">{/if}

    <div class="panel">
      <div class="panel-heading">
        <i class="icon-random"></i>
        {if $bkr_rule.id_rule}{l s='Edit rule #%id%' sprintf=['%id%' => $bkr_rule.id_rule] d='Modules.Bkredirects.Admin'}{else}{l s='New rule' d='Modules.Bkredirects.Admin'}{/if}
      </div>

      {if $bkr_pro_only}
        <div class="alert alert-warning">
          {l s='This rule uses PRO options: it is kept, and it applies while BK SEO Redirects PRO is installed. From the list you can enable, disable or delete it. Saving changes needs PRO, unless you switch its PRO options to the free ones.' d='Modules.Bkredirects.Admin'}
        </div>
      {/if}

      {* ---------- Desde ---------- *}
      <h4 class="bkr-form-step">{l s='When a visitor opens' d='Modules.Bkredirects.Admin'}</h4>

      <div class="form-group{if isset($bkr_errors.source)} has-error{/if}">
        <label class="control-label col-lg-3 required" for="bkr-source">{l s='Old URL' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <div class="input-group">
            <span class="input-group-addon bkr-base">{$bkr_shop_base|escape:'html':'UTF-8'}</span>
            <input type="text" name="source" id="bkr-source" class="form-control bkr-mono" value="{$bkr_rule.source|escape:'html':'UTF-8'}"
                   placeholder="/12-old-product.html" required autocomplete="off" spellcheck="false"{if $bkr_rec} readonly{elseif !$bkr_rule.source} autofocus{/if}>
          </div>
          {if isset($bkr_errors.source)}<p class="help-block bkr-error">{$bkr_errors.source|escape:'html':'UTF-8'}</p>{/if}
          <p class="help-block">{l s='Paste the whole address or just the part after the domain. Copy it from Google Search Console, an old link or the 404 log.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      <div class="form-group{if isset($bkr_errors.match_type)} has-error{/if}">
        <label class="control-label col-lg-3" for="bkr-match">{l s='Which URLs' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <select name="match_type" id="bkr-match" class="bkr-chosen fixed-width-xxl">
            {foreach from=$bkr_match_types key=type item=match}
              <option value="{$type|escape:'html':'UTF-8'}"{if $bkr_rule.match_type == $type} selected{/if}{if $match.pro && !$bkr_is_pro && $bkr_rule.match_type != $type} disabled{/if}>
                {$match.label|escape:'html':'UTF-8'}{if $match.pro && !$bkr_is_pro} · PRO{/if}
              </option>
            {/foreach}
          </select>
          {if isset($bkr_errors.match_type)}<p class="help-block bkr-error">{$bkr_errors.match_type|escape:'html':'UTF-8'}</p>{/if}
          {if !$bkr_is_pro}
            {capture assign=bkr_pro_text}{l s='One rule for a whole folder, a pattern with * or a regular expression: /old-blog/* goes to /blog/ in one go.' d='Modules.Bkredirects.Admin'}{/capture}
            {include file="`$bkr_tpl`_pro.tpl" text=$bkr_pro_text compact=true}
          {else}
            <p class="help-block bkr-when bkr-when--prefix">{l s='Example: /old-blog/ catches /old-blog/any-post too.' d='Modules.Bkredirects.Admin'}</p>
            <p class="help-block bkr-when bkr-when--wildcard">{l s='Example: /shoes/*.html. Each * matches any text and can be reused in a destination URL as $1, $2…' d='Modules.Bkredirects.Admin'}</p>
            <p class="help-block bkr-when bkr-when--regex">{l s='Example: ^/p/([0-9]+)$. Use the groups in a destination URL as $1, $2…' d='Modules.Bkredirects.Admin'}</p>
          {/if}
        </div>
      </div>

      <div class="form-group{if isset($bkr_errors.query_mode)} has-error{/if}">
        <label class="control-label col-lg-3">{l s='Parameters after ?' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          {foreach from=$bkr_query_modes key=mode item=label}
            <div class="radio{if $mode != 'pass' && !$bkr_is_pro} disabled{/if}">
              <label>
                <input type="radio" name="query_mode" value="{$mode|escape:'html':'UTF-8'}"{if $bkr_rule.query_mode == $mode} checked{/if}{if $mode != 'pass' && !$bkr_is_pro && $bkr_rule.query_mode != $mode} disabled{/if}>
                {$label|escape:'html':'UTF-8'}
              </label>
            </div>
          {/foreach}
          {if isset($bkr_errors.query_mode)}<p class="help-block bkr-error">{$bkr_errors.query_mode|escape:'html':'UTF-8'}</p>{/if}
          {if !$bkr_is_pro}
            {capture assign=bkr_pro_text}{l s='Match the URL only when it has no parameters, or drop them on the way to the new page.' d='Modules.Bkredirects.Admin'}{/capture}
            {include file="`$bkr_tpl`_pro.tpl" text=$bkr_pro_text compact=true}
          {/if}
        </div>
      </div>

      {* ---------- Hacia ---------- *}
      <h4 class="bkr-form-step">{l s='Send them to' d='Modules.Bkredirects.Admin'}</h4>

      <div class="form-group{if isset($bkr_errors.http_code)} has-error{/if}">
        <label class="control-label col-lg-3" for="bkr-code">{l s='Type of redirect' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <select name="http_code" id="bkr-code" class="bkr-chosen fixed-width-xxl">
            {foreach from=$bkr_codes item=code}
              <option value="{$code.value|intval}"{if $bkr_rule.http_code == $code.value} selected{/if}{if $code.pro && !$bkr_is_pro && $bkr_rule.http_code != $code.value} disabled{/if}>
                {$code.label|escape:'html':'UTF-8'}{if $code.pro && !$bkr_is_pro} · PRO{/if}
              </option>
            {/foreach}
          </select>
          {if isset($bkr_errors.http_code)}<p class="help-block bkr-error">{$bkr_errors.http_code|escape:'html':'UTF-8'}</p>{/if}
          <p class="help-block">{l s='301 is the right one almost always: it tells Google the page has moved for good and passes its ranking on. Use 410 for something that is gone and has no replacement.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      <div class="bkr-target-block">
        <div class="form-group{if isset($bkr_errors.target_type)} has-error{/if}">
          <label class="control-label col-lg-3" for="bkr-target-type">{l s='Destination' d='Modules.Bkredirects.Admin'}</label>
          <div class="col-lg-9">
            <select name="target_type" id="bkr-target-type" class="bkr-chosen fixed-width-xxl">
              {foreach from=$bkr_target_types key=type item=label}
                <option value="{$type|escape:'html':'UTF-8'}"{if $bkr_rule.target_type == $type} selected{/if}>{$label|escape:'html':'UTF-8'}</option>
              {/foreach}
            </select>
            {if isset($bkr_errors.target_type)}<p class="help-block bkr-error">{$bkr_errors.target_type|escape:'html':'UTF-8'}</p>{/if}
            <p class="help-block">{l s='Pointing to a product, category or page keeps working when its URL changes later.' d='Modules.Bkredirects.Admin'}</p>
          </div>
        </div>

        <div class="form-group bkr-target-entity{if isset($bkr_errors.target_id)} has-error{/if}">
          <label class="control-label col-lg-3" for="bkr-finder">{l s='Which one' d='Modules.Bkredirects.Admin'}</label>
          <div class="col-lg-9">
            <input type="hidden" name="target_id" value="{$bkr_rule.target_id|intval}">
            <div class="bkr-finder{if $bkr_target_name} has-value{/if}">
              <div class="bkr-finder-chosen">
                <span class="bkr-finder-label">{$bkr_target_name|escape:'html':'UTF-8'}</span>
                <button type="button" class="btn btn-link btn-sm bkr-finder-change">{l s='Change' d='Modules.Bkredirects.Admin'}</button>
              </div>
              <div class="bkr-finder-search">
                <div class="input-group bkr-finder-box">
                  <span class="input-group-addon"><i class="icon-search"></i></span>
                  <input type="text" id="bkr-finder" class="form-control bkr-finder-input" autocomplete="off"
                         role="combobox" aria-expanded="false" aria-controls="bkr-finder-list" aria-autocomplete="list"
                         placeholder="{l s='Type a name, a reference or an ID' d='Modules.Bkredirects.Admin'}">
                </div>
                <ul class="bkr-finder-list" id="bkr-finder-list" role="listbox" hidden></ul>
              </div>
            </div>
            {if isset($bkr_errors.target_id)}<p class="help-block bkr-error">{$bkr_errors.target_id|escape:'html':'UTF-8'}</p>{/if}
          </div>
        </div>

        <div class="form-group bkr-target-url{if isset($bkr_errors.target_url)} has-error{/if}">
          <label class="control-label col-lg-3" for="bkr-target-url">{l s='URL' d='Modules.Bkredirects.Admin'}</label>
          <div class="col-lg-9">
            <input type="text" name="target_url" id="bkr-target-url" class="form-control bkr-mono" value="{$bkr_rule.target_url|escape:'html':'UTF-8'}"
                   placeholder="/new-page or https://…" autocomplete="off" spellcheck="false">
            {if isset($bkr_errors.target_url)}<p class="help-block bkr-error">{$bkr_errors.target_url|escape:'html':'UTF-8'}</p>{/if}
            <p class="help-block">{l s='A path of this shop (/new-page) or a full address of any site.' d='Modules.Bkredirects.Admin'}</p>
          </div>
        </div>
      </div>

      <div class="bkr-gone-note">
        <div class="col-lg-9 col-lg-offset-3">
          <p class="help-block"><i class="icon-info-circle"></i> {l s='No destination: visitors see the page-not-found page, and Google learns this URL is gone on purpose and drops it sooner.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      {* ---------- Dónde ---------- *}
      <h4 class="bkr-form-step">{l s='Applies to' d='Modules.Bkredirects.Admin'}</h4>

      {if $bkr_shops}
        <div class="form-group{if isset($bkr_errors.id_shop)} has-error{/if}">
          <label class="control-label col-lg-3" for="bkr-shop">{l s='Shop' d='Modules.Bkredirects.Admin'}</label>
          <div class="col-lg-9">
            <select name="id_shop" id="bkr-shop" class="bkr-chosen fixed-width-xxl">
              <option value="0"{if !$bkr_rule.id_shop} selected{/if}>{l s='All shops' d='Modules.Bkredirects.Admin'}</option>
              {foreach from=$bkr_shops item=shop}
                <option value="{$shop.id|intval}"{if $bkr_rule.id_shop == $shop.id} selected{/if}>{$shop.name|escape:'html':'UTF-8'}</option>
              {/foreach}
            </select>
            {if isset($bkr_errors.id_shop)}<p class="help-block bkr-error">{$bkr_errors.id_shop|escape:'html':'UTF-8'}</p>{/if}
          </div>
        </div>
      {else}
        <input type="hidden" name="id_shop" value="{$bkr_rule.id_shop|intval}">
      {/if}

      <div class="form-group{if isset($bkr_errors.id_lang)} has-error{/if}">
        <label class="control-label col-lg-3" for="bkr-lang">{l s='Language' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <select name="id_lang" id="bkr-lang" class="bkr-chosen fixed-width-xxl"{if !$bkr_is_pro && !$bkr_rule.id_lang} disabled{/if}>
            <option value="0"{if !$bkr_rule.id_lang} selected{/if}>{l s='All languages' d='Modules.Bkredirects.Admin'}</option>
            {foreach from=$bkr_languages item=lang}
              <option value="{$lang.id_lang|intval}"{if $bkr_rule.id_lang == $lang.id_lang} selected{/if}>{$lang.name|escape:'html':'UTF-8'}</option>
            {/foreach}
          </select>
          {if isset($bkr_errors.id_lang)}<p class="help-block bkr-error">{$bkr_errors.id_lang|escape:'html':'UTF-8'}</p>{/if}
          {if !$bkr_is_pro}
            {capture assign=bkr_pro_text}{l s='A different destination for each language of the same URL.' d='Modules.Bkredirects.Admin'}{/capture}
            {include file="`$bkr_tpl`_pro.tpl" text=$bkr_pro_text compact=true}
          {/if}
        </div>
      </div>

      <div class="form-group">
        <label class="control-label col-lg-3" for="bkr-note">{l s='Note' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <input type="text" name="note" id="bkr-note" class="form-control" maxlength="255" value="{$bkr_rule.note|escape:'html':'UTF-8'}"
                 placeholder="{l s='Why this rule exists, for whoever reads it later' d='Modules.Bkredirects.Admin'}">
        </div>
      </div>

      <div class="form-group">
        <label class="control-label col-lg-3">{l s='Active' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          {include file="`$bkr_tpl`_switch.tpl" name='active' value=$bkr_rule.active}
        </div>
      </div>

      <div class="panel-footer">
        <a href="{$bkr_back_url|escape:'html':'UTF-8'}" class="btn btn-default">
          <i class="process-icon-cancel"></i> {l s='Cancel' d='Modules.Bkredirects.Admin'}
        </a>
        <button type="submit" class="btn btn-default pull-right">
          <i class="process-icon-save"></i> {l s='Save' d='Modules.Bkredirects.Admin'}
        </button>
      </div>
    </div>
  </form>
</div>
