<?php
/**
 * BK SEO Redirects — redirecciones 301/302/307/410 sin override ni .htaccess, registro de 404 con los bots
 * aparte y recomendaciones.
 *
 * Todo entra por hooks:
 * - actionDispatcherBefore: el motor (BkRedirectsEngine::dispatch), antes de crear el controlador.
 * - actionOutputHTMLBefore: código 410/451 de una regla y registro de 404 (BkRedirectsEngine::onOutput).
 *
 * Lo PRO lo añade un complemento aparte (BK SEO Redirects PRO) por los puntos de extensión de
 * BkRedirectsExtension: este módulo no lleva código PRO ni comprueba licencias.
 *
 * @author    BK Modules
 * @copyright BK Modules
 * @license   MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class BkRedirects extends Module
{
    public function __construct()
    {
        $this->name = 'bkredirects';
        $this->tab = 'seo';
        $this->version = '1.0.0';
        $this->author = 'BK Modules';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.5.0', 'max' => '9.99.99'];

        parent::__construct();

        // PrestaShop 1.7.5 no carga los XLF de los módulos; donde el núcleo ya los carga no hace nada.
        \BkModules\I18n\V1\Translations::ensure($this->name);

        $this->displayName = $this->trans('BK SEO Redirects', [], 'Modules.Bkredirects.Admin');
        $this->description = $this->trans(
            'Redirect old URLs to products, categories or any page, log every 404 and keep your rankings when you delete or rename products.',
            [],
            'Modules.Bkredirects.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Your redirect rules and the 404 log will be deleted. Uninstall?',
            [],
            'Modules.Bkredirects.Admin'
        );
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * @return string[]
     */
    private function hookList()
    {
        return ['actionDispatcherBefore', 'actionOutputHTMLBefore'];
    }

    public function install()
    {
        if (!BkRedirectsRule::installTables() || !parent::install()) {
            return false;
        }
        $this->registerHooks();
        BkRedirectsConfig::installDefaults();
        if (!BkRedirectsTabsInstaller::install($this->name)) {
            BkRedirectsLogger::error('No se pudieron crear las pestañas del back office');
        }
        BkRedirectsLogger::confirmation('Módulo instalado, versión ' . $this->version);
        \BkModules\Registry\V2\InstallReporter::report($this->name, $this->version, 'install');

        return true;
    }

    public function uninstall()
    {
        // Un complemento vive sobre las tablas y las clases de este módulo: se va con él, esté activo o no.
        foreach (BkRedirectsExtension::installedModules() as $extension) {
            if (!$extension->uninstall()) {
                BkRedirectsLogger::error('No se pudo desinstalar el complemento ' . $extension->name);
                $this->_errors[] = $this->trans('Uninstall %module% first.', ['%module%' => $extension->displayName], 'Modules.Bkredirects.Admin');

                return false;
            }
        }

        \BkModules\Registry\V2\InstallReporter::report($this->name, $this->version, 'uninstall');

        BkRedirectsTabsInstaller::uninstall();
        $ok = parent::uninstall();
        BkRedirectsRule::uninstallTables();
        BkRedirectsConfig::uninstallKeys();
        // Caché del panel «Más de BK Modules»: se regenera sola, no sobrevive a la desinstalación.
        BkRedirectsRemoteImage::purge();
        foreach (glob(_PS_CACHE_DIR_ . 'bkredirects_catalog_*.json') ?: [] as $file) {
            @unlink($file);
        }
        BkRedirectsLogger::confirmation('Módulo desinstalado');

        return $ok;
    }

    /**
     * Idempotente: la instalación y cada upgrade que traiga un hook nuevo pasan por aquí.
     *
     * @return bool
     */
    public function registerHooks()
    {
        $ok = true;
        foreach ($this->hookList() as $hook) {
            if (!$this->registerHook($hook)) {
                BkRedirectsLogger::error('No se pudo registrar el hook ' . $hook);
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * La configuración del módulo es el Panel.
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(BkRedirectsTabsInstaller::DASHBOARD));
    }

    /* ------------------------------------------------------------------ *
     * Motor y registro de 404
     * ------------------------------------------------------------------ */

    public function hookActionDispatcherBefore(array $params)
    {
        BkRedirectsEngine::dispatch(isset($params['controller_type']) ? (int) $params['controller_type'] : 0);
    }

    public function hookActionOutputHTMLBefore(array $params)
    {
        BkRedirectsEngine::onOutput();
    }

    /* ------------------------------------------------------------------ *
     * Utilidades para los controladores
     * ------------------------------------------------------------------ */

    /**
     * Traducción accesible desde los controladores: `Module::trans()` es protected y `AdminController`
     * no tiene `trans()` propio hasta 1.7.6.
     *
     * @param string $id
     * @param array  $parameters
     * @param string $domain
     *
     * @return string
     */
    public function t($id, array $parameters = [], $domain = 'Modules.Bkredirects.Admin')
    {
        return $this->trans($id, $parameters, $domain);
    }

    /**
     * URL de un asset del módulo firmada con la fecha del fichero.
     *
     * @param string $relative
     *
     * @return string
     */
    public function assetUrl($relative)
    {
        $path = _PS_MODULE_DIR_ . $this->name . '/' . $relative;

        return $this->_path . $relative . '?v=' . (file_exists($path) ? filemtime($path) : $this->version);
    }
}
