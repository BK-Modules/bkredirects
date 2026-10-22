{**
 * Módulos y artículos de bkmodules.com en el panel de configuración.
 *
 * El contenido llega del endpoint del hub con caché de un día; los enlaces del área de cliente
 * vienen ya construidos desde allí y aquí solo se pintan.
 *
 * Las imágenes las sirve la propia tienda (el módulo se trae una copia); si alguna
 * copia no se pudo hacer queda su URL de bkmodules.com, y por eso viajan los atributos de carga:
 * prioridad baja y decodificación asíncrona para no competir con la pantalla, y sin referer para
 * que ningún CDN las trate como enlazadas desde otro sitio. Sin `loading=lazy` a propósito: en una
 * pestaña que no está en primer plano una imagen diferida no se pide nunca.
 *}
<div class="panel bkr-info">
  <div class="panel-heading">
    <i class="icon-info-circle"></i> {l s='More from BK Modules' d='Modules.Bkredirects.Admin'}
  </div>

  {* Estado de la versión: lo primero que se mira al abrir la ayuda *}
  <div class="bkr-status {if !$bkr_version.known}bkr-status-unknown{elseif $bkr_version.outdated}bkr-status-outdated{else}bkr-status-current{/if}">
    <span class="bkr-status-icon" aria-hidden="true"><i class="{if !$bkr_version.known}icon-question{elseif $bkr_version.outdated}icon-refresh{else}icon-check{/if}"></i></span>
    <span class="bkr-status-text">
      <strong>{$bkr_display_name|escape:'html':'UTF-8'} {$bkr_version.installed|escape:'html':'UTF-8'}</strong>
      {if !$bkr_version.known}
        {l s='The latest published version could not be checked right now.' d='Modules.Bkredirects.Admin'}
      {elseif $bkr_version.outdated}
        {l s='You have version %installed%; the latest published version is %latest%.' sprintf=['%installed%' => $bkr_version.installed, '%latest%' => $bkr_version.latest] d='Modules.Bkredirects.Admin'}
      {else}
        {l s='Your module is up to date.' d='Modules.Bkredirects.Admin'}
      {/if}
    </span>
    {if $bkr_version.outdated}
      <a class="btn btn-primary" href="{$bkr_version.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
        <i class="icon-download"></i> {l s='Get the update' d='Modules.Bkredirects.Admin'}
      </a>
    {/if}
  </div>

  <div class="bkr-info-links">
    <a class="btn btn-primary" href="{$bk_remote.links.contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
      <i class="icon-envelope"></i> {l s='Contact support' d='Modules.Bkredirects.Admin'}
    </a>
    <a class="btn btn-default" href="{$bk_remote.links.licenses|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
      <i class="icon-download"></i> {l s='My licenses and downloads' d='Modules.Bkredirects.Admin'}
    </a>
    <a class="btn btn-default" href="{$bk_remote.links.blog|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
      <i class="icon-book"></i> {l s='Guides and news' d='Modules.Bkredirects.Admin'}
    </a>
  </div>

  {if $bk_remote.catalog}
    <h4 class="bkr-info-title">
      <i class="icon-puzzle-piece"></i> {l s='Other BK Modules modules' d='Modules.Bkredirects.Admin'}
    </h4>
    <div class="bk-catalog">
      {foreach from=$bk_remote.catalog item=item}
        <a class="bk-catalog-card" href="{$item.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {if $item.image}
            {* Si la imagen no llega, se retira el hueco en vez de dejar el icono de rota *}
            <span class="bk-catalog-media"><img src="{$item.image|escape:'html':'UTF-8'}" alt=""
                     decoding="async" fetchpriority="low" referrerpolicy="no-referrer"
                     onerror="this.parentNode.style.display='none';"></span>
          {/if}
          <span class="bk-catalog-body">
            <span class="bk-catalog-name">{$item.name|escape:'html':'UTF-8'}</span>
            <span class="bk-catalog-desc">{$item.description|escape:'html':'UTF-8'|truncate:120}</span>
            <span class="bk-catalog-more">{l s='View the module' d='Modules.Bkredirects.Admin'} →</span>
          </span>
        </a>
      {/foreach}
    </div>
  {/if}

  {if $bk_remote.posts}
    <h4 class="bkr-info-title">
      <i class="icon-rss"></i> {l s='Latest blog posts' d='Modules.Bkredirects.Admin'}
    </h4>
    <div class="bk-posts">
      {foreach from=$bk_remote.posts item=post}
        <a class="bk-post-card" href="{$post.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
          {if $post.image}
            <span class="bk-post-thumb"><img src="{$post.image|escape:'html':'UTF-8'}" alt=""
                     decoding="async" fetchpriority="low" referrerpolicy="no-referrer"
                     onerror="this.parentNode.style.display='none';"></span>
          {/if}
          <span class="bk-post-body">
            <span class="bk-post-title">{$post.title|escape:'html':'UTF-8'}</span>
            <span class="bk-post-summary">{$post.summary|escape:'html':'UTF-8'}</span>
            <span class="bk-post-date">{$post.date|date_format:'%d/%m/%Y'}</span>
          </span>
        </a>
      {/foreach}
    </div>
  {/if}
</div>
