{**
 * Panel: totales del periodo, gráfico diario y recomendaciones para recuperar URLs perdidas.
 *}
<div class="bkr">
  <div class="panel bkr-overview">
    <div class="panel-heading">
      <i class="icon-bar-chart"></i> {l s='Your URLs over the last %days% days' sprintf=['%days%' => $bkr_days] d='Modules.Bkredirects.Admin'}
      <span class="bkr-periods" role="group" aria-label="{l s='Period' d='Modules.Bkredirects.Admin'}">
        {foreach from=$bkr_periods item=period}
          <a class="btn btn-default btn-sm{if $period == $bkr_days} active{/if}" href="{$bkr_period_url|escape:'html':'UTF-8'}{$period|intval}"{if $period == $bkr_days} aria-current="true"{/if}>
            {l s='%days% days' sprintf=['%days%' => $period] d='Modules.Bkredirects.Admin'}
          </a>
        {/foreach}
      </span>
    </div>

    {* Las tres primeras cifras son también la leyenda del gráfico *}
    <dl class="bkr-figures">
      <div class="bkr-figure">
        <dt><span class="bkr-swatch bkr-swatch--redirects" aria-hidden="true"></span>{l s='Visits redirected' d='Modules.Bkredirects.Admin'}</dt>
        <dd>{$bkr_totals.redirects|intval}</dd>
      </div>
      <div class="bkr-figure">
        <dt><span class="bkr-swatch bkr-swatch--notfound" aria-hidden="true"></span>{l s='Visitors who hit a 404' d='Modules.Bkredirects.Admin'}</dt>
        <dd>{$bkr_totals.notfound|intval}</dd>
      </div>
      <div class="bkr-figure">
        <dt><span class="bkr-swatch bkr-swatch--bots" aria-hidden="true"></span>{l s='Bots that hit a 404' d='Modules.Bkredirects.Admin'}</dt>
        <dd>{$bkr_totals.notfound_bots|intval}</dd>
      </div>
      <div class="bkr-figure bkr-figure--quiet">
        <dt>{l s='Broken URLs to review' d='Modules.Bkredirects.Admin'}</dt>
        <dd>{$bkr_totals.open_404|intval}</dd>
      </div>
      <div class="bkr-figure bkr-figure--quiet">
        <dt>{l s='Active rules' d='Modules.Bkredirects.Admin'}</dt>
        <dd>
          <a href="{$bkr_links.rules|escape:'html':'UTF-8'}">{$bkr_totals.rules_active|intval}</a>
          {if $bkr_totals.rules_inactive}<small>{l s='+ %count% disabled' sprintf=['%count%' => $bkr_totals.rules_inactive] d='Modules.Bkredirects.Admin'}</small>{/if}
        </dd>
      </div>
    </dl>

    <figure class="bkr-chart">
      {if $bkr_chart.empty}
        <p class="bkr-chart-empty">
          {l s='No visits recorded in this period yet. The chart fills in as visitors reach redirected or missing URLs.' d='Modules.Bkredirects.Admin'}
        </p>
      {/if}
      <svg class="bkr-chart-svg" viewBox="0 0 {$bkr_chart.width|intval} {$bkr_chart.height|intval}" preserveAspectRatio="none"
           role="img" aria-label="{l s='Visits per day: redirected, visitors who hit a 404 and bots that hit a 404' d='Modules.Bkredirects.Admin'}">
        <g class="bkr-chart-grid">
          {foreach from=$bkr_chart.grid item=line}
            <line x1="{$bkr_chart.left}" x2="{$bkr_chart.right}" y1="{$line.y}" y2="{$line.y}"></line>
          {/foreach}
        </g>
        <polygon class="bkr-chart-area" points="{$bkr_chart.area}"></polygon>
        <polyline class="bkr-chart-line bkr-chart-line--bots" points="{$bkr_chart.lines.notfound_bots}"></polyline>
        <polyline class="bkr-chart-line bkr-chart-line--notfound" points="{$bkr_chart.lines.notfound}"></polyline>
        <polyline class="bkr-chart-line bkr-chart-line--redirects" points="{$bkr_chart.lines.redirects}"></polyline>
        <line class="bkr-chart-cursor" x1="0" x2="0" y1="{$bkr_chart.top}" y2="{$bkr_chart.baseline}"></line>
        <g class="bkr-chart-hits">
          {foreach from=$bkr_chart.columns item=col}
            <rect x="{$col.x}" y="0" width="{$col.w}" height="{$bkr_chart.baseline}"
                  data-cx="{$col.cx}" data-date="{$col.date|escape:'html':'UTF-8'}"
                  data-redirects="{$col.redirects|intval}" data-notfound="{$col.notfound|intval}" data-bots="{$col.notfound_bots|intval}"></rect>
          {/foreach}
        </g>
      </svg>
      {* Las etiquetas van en HTML y no en el SVG: el SVG se estira al ancho y deformaría el texto *}
      <div class="bkr-chart-yaxis" aria-hidden="true">
        {foreach from=$bkr_chart.grid item=line}
          <span style="top: {$line.pct}%">{$line.label|intval}</span>
        {/foreach}
      </div>
      <div class="bkr-chart-xaxis" aria-hidden="true">
        {foreach from=$bkr_chart.labels item=label}
          <span style="left: {$label.pct}%">{$label.text|escape:'html':'UTF-8'}</span>
        {/foreach}
      </div>
      <div class="bkr-chart-tip" role="status" hidden>
        <strong class="bkr-chart-tip-date"></strong>
        <span><i class="bkr-swatch bkr-swatch--redirects"></i>{l s='Redirected' d='Modules.Bkredirects.Admin'} <b data-key="redirects"></b></span>
        <span><i class="bkr-swatch bkr-swatch--notfound"></i>{l s='Visitors on a 404' d='Modules.Bkredirects.Admin'} <b data-key="notfound"></b></span>
        <span><i class="bkr-swatch bkr-swatch--bots"></i>{l s='Bots on a 404' d='Modules.Bkredirects.Admin'} <b data-key="bots"></b></span>
      </div>
    </figure>

    <details class="bkr-chart-table">
      <summary>{l s='See the figures day by day' d='Modules.Bkredirects.Admin'}</summary>
      <table class="table">
        <thead>
          <tr>
            <th>{l s='Day' d='Modules.Bkredirects.Admin'}</th>
            <th class="text-right">{l s='Redirected' d='Modules.Bkredirects.Admin'}</th>
            <th class="text-right">{l s='Visitors on a 404' d='Modules.Bkredirects.Admin'}</th>
            <th class="text-right">{l s='Bots on a 404' d='Modules.Bkredirects.Admin'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$bkr_chart.rows item=col}
            <tr>
              <td>{$col.date|escape:'html':'UTF-8'}</td>
              <td class="text-right">{$col.redirects|intval}</td>
              <td class="text-right">{$col.notfound|intval}</td>
              <td class="text-right">{$col.notfound_bots|intval}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </details>
  </div>

  <div class="panel bkr-recs" id="bkr-recommendations">
    <div class="panel-heading">
      <i class="icon-lightbulb"></i> {l s='Recommendations' d='Modules.Bkredirects.Admin'}
      <span class="badge">{$bkr_recommendations|@count}</span>
    </div>

    <p class="bkr-lead">
      {l s='Every URL here once had visitors or a place in Google. Send it to the right page and you keep both: the visitor lands somewhere useful and the ranking that URL earned passes to its new address.' d='Modules.Bkredirects.Admin'}
    </p>

    {if !$bkr_is_pro && $bkr_recommendations}
      {capture assign=bkr_pro_text}{l s='suggests the right destination for each URL and fixes them all in one click.' d='Modules.Bkredirects.Admin'}{/capture}
      {include file="`$bkr_tpl`_pro.tpl" text=$bkr_pro_text}
    {/if}

    {if !$bkr_recommendations}
      <div class="bkr-empty">
        <i class="icon-check" aria-hidden="true"></i>
        <p><strong>{l s='Nothing to fix right now.' d='Modules.Bkredirects.Admin'}</strong>
          {l s='When a URL starts returning 404 to real visitors, or a rule stops working, it will show up here with what to do.' d='Modules.Bkredirects.Admin'}</p>
      </div>
    {else}
      <form method="post" action="{$bkr_action_url|escape:'html':'UTF-8'}" class="bkr-recs-form">
        <div class="table-responsive">
          <table class="table bkr-recs-table">
            <thead>
              <tr>
                {if $bkr_is_pro}<th class="bkr-col-check"><input type="checkbox" class="bkr-check-all" aria-label="{l s='Select all' d='Modules.Bkredirects.Admin'}"></th>{/if}
                <th>{l s='What happens' d='Modules.Bkredirects.Admin'}</th>
                <th>{l s='URL' d='Modules.Bkredirects.Admin'}</th>
                <th class="text-right">{l s='Visits' d='Modules.Bkredirects.Admin'}</th>
                <th>{l s='Last visit' d='Modules.Bkredirects.Admin'}</th>
                {if $bkr_is_pro}<th>{l s='Suggested destination' d='Modules.Bkredirects.Admin'}</th>{/if}
                <th class="text-right">{l s='Action' d='Modules.Bkredirects.Admin'}</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$bkr_recommendations item=rec}
                <tr>
                  {if $bkr_is_pro}
                    <td class="bkr-col-check">
                      {if $rec.suggestion}<input type="checkbox" name="bkr_keys[]" value="{$rec.key|escape:'html':'UTF-8'}" aria-label="{l s='Select' d='Modules.Bkredirects.Admin'}">{/if}
                    </td>
                  {/if}
                  <td class="bkr-rec-what">
                    <span class="bkr-sev bkr-sev--{$rec.severity|escape:'html':'UTF-8'}">
                      {if $rec.severity == 'high'}{l s='High' d='Modules.Bkredirects.Admin'}{elseif $rec.severity == 'medium'}{l s='Medium' d='Modules.Bkredirects.Admin'}{else}{l s='Low' d='Modules.Bkredirects.Admin'}{/if}
                    </span>
                    <strong>{if isset($bkr_kinds[$rec.kind])}{$bkr_kinds[$rec.kind]|escape:'html':'UTF-8'}{else}{$rec.kind|escape:'html':'UTF-8'}{/if}</strong>
                    {if $rec.reason}<span class="bkr-rec-reason">{$rec.reason|escape:'html':'UTF-8'}</span>{/if}
                  </td>
                  <td class="bkr-url"><span title="{$rec.url|escape:'html':'UTF-8'}">{$rec.url|escape:'html':'UTF-8'}</span></td>
                  <td class="text-right bkr-num">
                    {$rec.hits|intval}
                    {if $rec.bot_hits}<small class="bkr-bots" title="{l s='Bot visits, not counted' d='Modules.Bkredirects.Admin'}">+{$rec.bot_hits|intval} {l s='bots' d='Modules.Bkredirects.Admin'}</small>{/if}
                  </td>
                  <td class="bkr-date">{if $rec.last_seen}{dateFormat date=$rec.last_seen full=0}{else}&mdash;{/if}</td>
                  {if $bkr_is_pro}
                    <td class="bkr-suggestion">
                      {if $rec.suggestion}
                        <span>{$rec.suggestion.label|escape:'html':'UTF-8'}</span>
                        <small class="bkr-score" title="{l s='How sure the module is about this destination' d='Modules.Bkredirects.Admin'}">{$rec.suggestion.score|intval}%</small>
                      {else}
                        <span class="text-muted">{l s='No clear match' d='Modules.Bkredirects.Admin'}</span>
                      {/if}
                    </td>
                  {/if}
                  <td class="text-right bkr-actions"><div class="bkr-actions-row">
                    {if $rec.kind == 'stale_rule'}
                      <button type="submit" name="bkrApply" value="{$rec.key|escape:'html':'UTF-8'}" class="btn btn-default btn-sm">
                        <i class="icon-power-off"></i> {l s='Disable rule' d='Modules.Bkredirects.Admin'}
                      </button>
                    {elseif $bkr_is_pro && $rec.suggestion}
                      <button type="submit" name="bkrApply" value="{$rec.key|escape:'html':'UTF-8'}" class="btn btn-primary btn-sm">
                        {l s='Apply' d='Modules.Bkredirects.Admin'}
                      </button>
                    {/if}
                    {if $rec.kind == 'stale_rule'}
                    {elseif $rec.edit_url}
                      <a class="btn btn-default btn-sm" href="{$rec.edit_url|escape:'html':'UTF-8'}">
                        <i class="icon-pencil"></i> {l s='Edit rule' d='Modules.Bkredirects.Admin'}
                      </a>
                    {else}
                      <a class="btn {if $bkr_is_pro && $rec.suggestion}btn-default{else}btn-primary{/if} btn-sm" href="{$rec.create_url|escape:'html':'UTF-8'}">
                        <i class="icon-plus"></i> {l s='Create rule' d='Modules.Bkredirects.Admin'}
                      </a>
                    {/if}
                    <button type="submit" name="bkrDismiss" value="{$rec.key|escape:'html':'UTF-8'}" class="btn btn-link btn-sm bkr-dismiss">
                      {l s='Ignore' d='Modules.Bkredirects.Admin'}
                    </button>
                  </div></td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
        {if $bkr_is_pro}
          <div class="bkr-bulk">
            <button type="submit" name="submitBkrApplyBulk" class="btn btn-default" disabled>
              <i class="icon-magic"></i> {l s='Apply the suggested destination to the selected URLs' d='Modules.Bkredirects.Admin'}
            </button>
          </div>
        {/if}
      </form>
    {/if}
  </div>

  <div class="row">
    <div class="col-lg-6">
      <div class="panel">
        <div class="panel-heading"><i class="icon-warning-sign"></i> {l s='URLs that return 404 most' d='Modules.Bkredirects.Admin'}</div>
        {if !$bkr_top_notfound}
          <p class="text-muted">{l s='No 404 recorded yet.' d='Modules.Bkredirects.Admin'}</p>
        {else}
          <table class="table bkr-compact">
            <thead><tr>
              <th>{l s='URL' d='Modules.Bkredirects.Admin'}</th>
              <th class="text-right">{l s='Visitors' d='Modules.Bkredirects.Admin'}</th>
              <th class="text-right">{l s='Bots' d='Modules.Bkredirects.Admin'}</th>
              <th></th>
            </tr></thead>
            <tbody>
              {foreach from=$bkr_top_notfound item=row}
                <tr>
                  <td class="bkr-url"><span title="{$row.url|escape:'html':'UTF-8'}">{$row.url|escape:'html':'UTF-8'}</span></td>
                  <td class="text-right bkr-num">{$row.hits|intval}</td>
                  <td class="text-right bkr-num text-muted">{$row.bot_hits|intval}</td>
                  <td class="text-right">
                    {if $row.status == 'open'}
                      <a class="btn btn-default btn-xs" href="{$bkr_new_rule_url|escape:'html':'UTF-8'}&amp;source={$row.url|escape:'url'}">{l s='Create rule' d='Modules.Bkredirects.Admin'}</a>
                    {elseif $row.status == 'resolved'}
                      <span class="text-success"><i class="icon-check"></i> {l s='Redirected' d='Modules.Bkredirects.Admin'}</span>
                    {else}
                      <span class="text-muted">{l s='Ignored' d='Modules.Bkredirects.Admin'}</span>
                    {/if}
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        {/if}
      </div>
    </div>
    <div class="col-lg-6">
      <div class="panel">
        <div class="panel-heading"><i class="icon-random"></i> {l s='Most used rules' d='Modules.Bkredirects.Admin'}</div>
        {if !$bkr_top_rules}
          <p class="text-muted">{l s='No rule has redirected anyone yet.' d='Modules.Bkredirects.Admin'}</p>
        {else}
          <table class="table bkr-compact">
            <thead><tr>
              <th>{l s='From' d='Modules.Bkredirects.Admin'}</th>
              <th>{l s='To' d='Modules.Bkredirects.Admin'}</th>
              <th class="text-right">{l s='Visits' d='Modules.Bkredirects.Admin'}</th>
            </tr></thead>
            <tbody>
              {foreach from=$bkr_top_rules item=rule}
                <tr>
                  <td class="bkr-url"><a href="{$rule.edit_url|escape:'html':'UTF-8'}" title="{$rule.source|escape:'html':'UTF-8'}">{$rule.source|escape:'html':'UTF-8'}</a></td>
                  <td class="bkr-target"><span title="{$rule.target_label|escape:'html':'UTF-8'}">{$rule.target_label|escape:'html':'UTF-8'}</span></td>
                  <td class="text-right bkr-num">{$rule.hits|intval}</td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        {/if}
      </div>
    </div>
  </div>
</div>
