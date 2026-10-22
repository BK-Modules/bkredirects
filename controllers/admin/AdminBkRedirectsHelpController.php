<?php
/**
 * Licencia y ayuda: la edición de la tienda, cómo pasar a PRO y el panel «Más de BK Modules»
 * (revisión 2 del estándar: estado de versión, soporte, catálogo y blog).
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/BkRedirectsAdminController.php';

class AdminBkRedirectsHelpController extends BkRedirectsAdminController
{
    public $screen = 'help';

    public function initContent()
    {
        $extension = BkRedirectsExtension::get();

        $this->context->smarty->assign([
            'bkr_pro_version' => $extension ? $extension->module()->version : '',
            'bkr_version' => $this->versionState(),
            'bkr_display_name' => $this->module->displayName,
            'bk_remote' => $this->remote(),
        ]);

        $this->content .= $this->fetchTemplate('help.tpl');
        $this->content .= $this->fetchTemplate('info-panel.tpl');

        parent::initContent();
    }
}
