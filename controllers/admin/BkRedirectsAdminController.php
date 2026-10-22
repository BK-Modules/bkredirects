<?php
/**
 * Base de las cinco pantallas del módulo en el back office.
 *
 * Reúne lo que comparten todas: los recursos del BO firmados con la fecha del fichero, la edición
 * (Gratis o PRO) y sus enlaces, el aviso de versión nueva (del módulo y del complemento), que va arriba de
 * cualquier pantalla del módulo y fuera de sus pestañas, y los puntos donde el complemento procesa y pinta
 * lo suyo (BkRedirectsExtensionInterface: setMedia, postProcess y render con la pantalla `$screen`). Los
 * métodos públicos de esta clase son los que el complemento usa para pintar con el mismo aspecto.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

abstract class BkRedirectsAdminController extends ModuleAdminController
{
    /** @var string Pantalla para el complemento: dashboard, rules, automations, import, help */
    public $screen = '';

    /** @var array Catálogo, enlaces y última versión de bkmodules.com por módulo: una petición por pantalla */
    private $remote = [];

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->assetUrl('views/css/admin.css'), 'all', null, false);
        $this->addJqueryPlugin('chosen');
        $this->addJS($this->module->assetUrl('views/js/admin.js'), false);
        if ($extension = BkRedirectsExtension::get()) {
            $extension->setMedia($this->screen, $this);
        }
    }

    public function postProcess()
    {
        if ($extension = BkRedirectsExtension::get()) {
            $extension->postProcess($this->screen, $this);
        }

        return parent::postProcess();
    }

    public function initContent()
    {
        $notices = $this->renderUpdateNotice($this->module);
        if ($extension = BkRedirectsExtension::get()) {
            $notices .= $this->renderUpdateNotice($extension->module());
        }
        $this->content = $notices . $this->content;

        parent::initContent();
    }

    /**
     * Traducción en el dominio del módulo. Pasa por el módulo y no por `$this->trans()`: el
     * controlador del BO no tiene traductor propio hasta 1.7.6.
     *
     * @param string $id
     * @param array $parameters
     *
     * @return string
     */
    public function t($id, array $parameters = [])
    {
        return $this->module->t($id, $parameters);
    }

    /**
     * Pinta una plantilla de `views/templates/admin/` del módulo (o del complemento que se pase) con las
     * variables comunes ya asignadas.
     *
     * @param string $template
     * @param Module|null $module
     *
     * @return string
     */
    public function fetchTemplate($template, $module = null)
    {
        if ($this->context->smarty->getTemplateVars('bkr_tpl') === null) {
            $this->context->smarty->assign($this->commonVars());
        }

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . ($module ? $module->name : $this->module->name) . '/views/templates/admin/' . $template
        );
    }

    /**
     * Edición de la tienda: PRO con el complemento instalado y activo.
     *
     * @return bool
     */
    public function isPro()
    {
        return BkRedirectsExtension::isPro();
    }

    /**
     * @return int
     */
    public function shopId()
    {
        return (int) $this->context->shop->id;
    }

    /**
     * @return int
     */
    public function langId()
    {
        return (int) $this->context->language->id;
    }

    /**
     * URL de esta pantalla con su token.
     *
     * @return string
     */
    public function screenUrl()
    {
        return self::$currentIndex . '&token=' . $this->token;
    }

    /**
     * Variables que leen todas las plantillas: edición y enlaces del reclamo PRO.
     *
     * @return array
     */
    protected function commonVars()
    {
        $remote = $this->remote();

        return [
            'bkr_tpl' => _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/',
            'bkr_is_pro' => $this->isPro(),
            'bkr_pro_url' => $this->proUrl(),
            'bkr_licenses_url' => $remote['links']['licenses'],
            'bkr_links' => [
                'dashboard' => $this->context->link->getAdminLink('AdminBkRedirectsDashboard'),
                'rules' => $this->context->link->getAdminLink('AdminBkRedirectsRules'),
                'automations' => $this->context->link->getAdminLink('AdminBkRedirectsAutomations'),
                'import' => $this->context->link->getAdminLink('AdminBkRedirectsImport'),
                'help' => $this->context->link->getAdminLink('AdminBkRedirectsHelp'),
            ],
        ];
    }

    /**
     * Ficha del módulo en bkmodules.com, en el idioma del empleado: es donde se compara Gratis con
     * PRO. La da el registro con la última versión; sin red, el área de licencias.
     *
     * @return string
     */
    public function proUrl()
    {
        $remote = $this->remote();

        return !empty($remote['latest']['url']) ? (string) $remote['latest']['url'] : $remote['links']['licenses'];
    }

    /**
     * Módulos, artículos, enlaces y última versión publicada de un módulo (este o el complemento), con
     * caché de un día.
     *
     * @param Module|null $module
     *
     * @return array
     */
    protected function remote($module = null)
    {
        $name = $module ? $module->name : $this->module->name;
        if (isset($this->remote[$name])) {
            return $this->remote[$name];
        }

        // Una caché por módulo e idioma: el catálogo llega ya traducido al idioma pedido.
        $iso = $this->context->language->iso_code;
        $remote = \BkModules\Registry\V2\Catalog::fetch(
            _PS_CACHE_DIR_ . $name . '_catalog_' . $iso . '.json',
            $iso,
            $name
        );

        // Las imágenes se sirven desde la propia tienda: enlazadas a bkmodules.com son, para el
        // navegador, peticiones a otro sitio que un bloqueador retiene sin cerrarlas.
        $remote = BkRedirectsRemoteImage::localize($remote);

        // Las URLs del área de cliente las construye bkmodules.com; aquí solo se pintan.
        $remote['links'] = array_merge(
            [
                'licenses' => 'https://bkmodules.com',
                'contact' => 'https://bkmodules.com',
                'blog' => 'https://bkmodules.com',
            ],
            array_filter(isset($remote['links']) ? (array) $remote['links'] : [])
        );
        $remote['catalog'] = isset($remote['catalog']) ? (array) $remote['catalog'] : [];
        $remote['posts'] = isset($remote['posts']) ? (array) $remote['posts'] : [];

        $this->remote[$name] = $remote;

        return $remote;
    }

    /**
     * Versión instalada de un módulo (este o el complemento) frente a la última publicada, en tres estados:
     * al día, desactualizado y sin comprobar (sin red o sin release publicada no se afirma ninguna de las
     * otras dos).
     *
     * @param Module|null $module
     *
     * @return array
     */
    protected function versionState($module = null)
    {
        $module = $module ?: $this->module;
        $remote = $this->remote($module);
        $latest = isset($remote['latest']['version']) ? (string) $remote['latest']['version'] : '';

        return [
            'name' => $module->displayName,
            'installed' => $module->version,
            'latest' => $latest,
            'known' => $latest !== '',
            'outdated' => $latest !== '' && version_compare($module->version, $latest, '<'),
            'url' => !empty($remote['latest']['url']) ? (string) $remote['latest']['url'] : $remote['links']['licenses'],
        ];
    }

    /**
     * @param Module $module
     *
     * @return string
     */
    private function renderUpdateNotice($module)
    {
        $version = $this->versionState($module);
        if (!$version['outdated']) {
            return '';
        }

        $this->context->smarty->assign('bkr_version', $version);

        return $this->fetchTemplate('update-notice.tpl');
    }

    /**
     * Nombre legible del destino de una regla o de una sugerencia: «Producto · Camiseta azul».
     *
     * @param string $type
     * @param int $id
     * @param string $url
     *
     * @return string
     */
    public function targetLabel($type, $id, $url = '')
    {
        $name = $this->entityName($type, (int) $id, (string) $url);
        if (in_array($type, ['url', 'home', 'none'], true) || (!(int) $id && in_array($type, ['manufacturer', 'supplier'], true))) {
            return $type === 'none' ? $this->targetTypes()['none'] : $name;
        }
        $types = $this->targetTypes();
        $prefix = isset($types[$type]) ? $types[$type] : $type;

        return $name === null
            ? $prefix . ' #' . (int) $id . ' · ' . $this->t('no longer exists')
            : $prefix . ' · ' . $name;
    }

    /**
     * Código HTTP con lo que significa para el comerciante.
     *
     * @param int $code
     *
     * @return string
     */
    public function codeLabel($code)
    {
        $labels = [
            301 => $this->t('301 · Moved permanently'),
            302 => $this->t('302 · Moved temporarily'),
            303 => $this->t('303 · See other'),
            307 => $this->t('307 · Temporary, keeps the method'),
            308 => $this->t('308 · Permanent, keeps the method'),
            410 => $this->t('410 · Gone for good'),
            451 => $this->t('451 · Unavailable for legal reasons'),
        ];

        return isset($labels[$code]) ? $labels[$code] : (string) $code;
    }

    /**
     * Tipos de destino de una regla con su nombre para el comerciante.
     *
     * @return array
     */
    public function targetTypes()
    {
        return [
            'url' => $this->t('A URL'),
            'product' => $this->t('Product'),
            'category' => $this->t('Category'),
            'cms' => $this->t('CMS page'),
            'cms_category' => $this->t('CMS category'),
            'manufacturer' => $this->t('Brand'),
            'supplier' => $this->t('Supplier'),
            'home' => $this->t('Home page'),
            'none' => $this->t('No destination'),
        ];
    }

    /**
     * Nombre de una entidad en el idioma del empleado; null si ya no existe.
     *
     * @param string $type
     * @param int $id
     * @param string $url
     *
     * @return string|null
     */
    public function entityName($type, $id, $url = '')
    {
        $name = BkRedirectsResolver::label($type, (int) $id, $this->langId(), (string) $url);

        return $name === '' ? null : $name;
    }

    /**
     * Buscador de destinos: por nombre, por referencia (productos) o por id.
     *
     * @param string $type
     * @param string $query
     *
     * @return array lista de {id, name, hint}
     */
    protected function searchEntities($type, $query)
    {
        $sources = [
            'product' => ['product', 'id_product', 'name', 'p.reference', true],
            'category' => ['category', 'id_category', 'name', null, true],
            'cms' => ['cms', 'id_cms', 'meta_title', null, true],
            'cms_category' => ['cms_category', 'id_cms_category', 'name', null, true],
            'manufacturer' => ['manufacturer', 'id_manufacturer', 'name', null, false],
            'supplier' => ['supplier', 'id_supplier', 'name', null, false],
        ];
        if (!isset($sources[$type])) {
            return [];
        }

        list($table, $key, $nameField, $hintField, $multilang) = $sources[$type];
        $nameColumn = $multilang ? 'l.`' . $nameField . '`' : 'p.`' . $nameField . '`';

        $sql = new DbQuery();
        $sql->select('p.`' . $key . '` AS id, ' . $nameColumn . ' AS name' . ($hintField ? ', ' . $hintField . ' AS hint' : ', "" AS hint'));
        $sql->from($table, 'p');
        if ($multilang) {
            $sql->innerJoin(
                $table . '_lang',
                'l',
                'l.`' . $key . '` = p.`' . $key . '` AND l.`id_lang` = ' . $this->langId()
                . ' AND l.`id_shop` = ' . $this->shopId()
            );
        }

        $like = '\'%' . pSQL(str_replace(['%', '_'], ['\\%', '\\_'], $query)) . '%\'';
        $where = $nameColumn . ' LIKE ' . $like;
        if ($hintField) {
            $where .= ' OR ' . $hintField . ' LIKE ' . $like;
        }
        if (ctype_digit($query)) {
            $where .= ' OR p.`' . $key . '` = ' . (int) $query;
        }
        $sql->where($where);
        $sql->orderBy($nameColumn . ' ASC');
        $sql->limit(20);

        $rows = Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array $payload
     */
    public function respondJson(array $payload)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}
