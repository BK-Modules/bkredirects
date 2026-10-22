{**
 * Ajustes del motor y de su registro de 404 (todas las ediciones). Las automatizaciones van encima: las
 * pinta el complemento PRO o, sin él, automations-locked.tpl.
 *}
<div class="bkr">
  <form method="post" action="{$bkr_form_url|escape:'html':'UTF-8'}" class="form-horizontal">
    <div class="panel">
      <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='Redirects and 404 log' d='Modules.Bkredirects.Admin'}
      </div>

      <div class="form-group">
        <label class="control-label col-lg-3">{l s='Apply redirect rules' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          {include file="`$bkr_tpl`_switch.tpl" name='enabled' value=$bkr_engine.enabled}
          <p class="help-block">{l s='Turn it off to stop every rule at once without deleting any.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      <div class="form-group">
        <label class="control-label col-lg-3">{l s='Record pages not found' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          {include file="`$bkr_tpl`_switch.tpl" name='log_404' value=$bkr_engine.log_404}
          <p class="help-block">{l s='Each URL that returns 404 is noted once, with how many visitors and bots reach it. The recommendations come from here.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      <div class="form-group">
        <label class="control-label col-lg-3" for="bkr-log-days">{l s='Forget a URL after' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <div class="input-group fixed-width-md">
            <input type="number" min="1" max="3650" name="log_days" id="bkr-log-days" class="form-control" value="{$bkr_engine.log_days|intval}">
            <span class="input-group-addon">{l s='days' d='Modules.Bkredirects.Admin'}</span>
          </div>
          <p class="help-block">{l s='Counted from the last time it returned 404.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      <div class="form-group">
        <label class="control-label col-lg-3" for="bkr-bots">{l s='Recognise as a bot' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <textarea name="bot_patterns" id="bkr-bots" rows="6" class="form-control bkr-mono">{$bkr_engine.bot_patterns|escape:'html':'UTF-8'}</textarea>
          <p class="help-block">{l s='One word per line. A visit whose browser identification contains any of them is counted as a bot, apart from real visitors.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      <div class="form-group">
        <label class="control-label col-lg-3" for="bkr-excluded">{l s='Leave these paths alone' d='Modules.Bkredirects.Admin'}</label>
        <div class="col-lg-9">
          <textarea name="excluded_paths" id="bkr-excluded" rows="4" class="form-control bkr-mono" placeholder="/api/&#10;/module/mymodule/">{$bkr_engine.excluded_paths|escape:'html':'UTF-8'}</textarea>
          <p class="help-block">{l s='One path per line, starting with a slash. Every URL that starts like this is neither redirected nor recorded.' d='Modules.Bkredirects.Admin'}</p>
        </div>
      </div>

      <div class="panel-footer">
        <button type="submit" name="submitBkrEngine" class="btn btn-default pull-right">
          <i class="process-icon-save"></i> {l s='Save' d='Modules.Bkredirects.Admin'}
        </button>
      </div>
    </div>
  </form>
</div>
