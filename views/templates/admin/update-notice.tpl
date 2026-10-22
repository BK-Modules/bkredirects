{**
 * Aviso de que hay una versión más reciente publicada. Va arriba de todas las pantallas del
 * módulo, fuera de sus pestañas: una actualización pendiente tiene que verse al abrir.
 *}
<div class="alert alert-warning bkr-update">
  <p class="bkr-update-title">
    <i class="icon-refresh"></i> {l s='There is a newer version of %module%' sprintf=['%module%' => $bkr_version.name] d='Modules.Bkredirects.Admin'}
  </p>
  <p>
    {l s='You have version %installed%; the latest published version is %latest%.' sprintf=['%installed%' => $bkr_version.installed, '%latest%' => $bkr_version.latest] d='Modules.Bkredirects.Admin'}
  </p>
  <a class="btn btn-primary" href="{$bkr_version.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">
    <i class="icon-download"></i> {l s='Get the update' d='Modules.Bkredirects.Admin'}
  </a>
</div>
