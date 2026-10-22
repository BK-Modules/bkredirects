<?php
/**
 * Importar y exportar reglas. Es del complemento PRO de punta a punta (él procesa, contesta los lotes por
 * ajax y pinta la pantalla); sin él se ve qué haría, bloqueado.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/BkRedirectsAdminController.php';

class AdminBkRedirectsImportController extends BkRedirectsAdminController
{
    public $screen = 'import';

    public function initContent()
    {
        $extension = BkRedirectsExtension::get();
        $this->content .= $extension ? $extension->render($this->screen, $this) : $this->fetchTemplate('import-locked.tpl');

        parent::initContent();
    }
}
