<?php
/**
 * Reglas: listado estándar del back office (filtros, orden, paginación, activar por fila y en
 * lote) y formulario propio de alta y edición.
 *
 * El formulario es una plantilla propia y no HelperForm porque cada campo PRO lleva su candado y
 * su línea, y el destino cambia de forma según el tipo (URL, entidad con buscador o ninguno). La
 * validación es la del modelo, `BkRedirectsRule::validate()`, la misma que usan los importadores.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/BkRedirectsAdminController.php';

class AdminBkRedirectsRulesController extends BkRedirectsAdminController
{
    public $screen = 'rules';

    /** @var array Errores de validación por campo del último envío del formulario */
    private $fieldErrors = [];

    /** @var array|null Valores enviados, para volver a pintar el formulario tal cual */
    private $submitted = null;

    public function __construct()
    {
        $this->table = BkRedirectsRule::TABLE;
        $this->identifier = 'id_rule';
        $this->className = 'BkRedirectsRule';
        $this->lang = false;
        $this->_defaultOrderBy = 'hits';
        $this->_defaultOrderWay = 'DESC';
        $this->list_no_link = false;

        parent::__construct();

        // Las reglas de todas las tiendas (0) y las de la tienda del contexto.
        if (Shop::isFeatureActive() && Shop::getContext() === Shop::CONTEXT_SHOP) {
            $this->_where = ' AND a.`id_shop` IN (0, ' . (int) $this->context->shop->id . ')';
        }

        $this->addRowAction('edit');
        $this->addRowAction('delete');

        $this->fields_list = [
            'id_rule' => [
                'title' => $this->t('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            // Origen y destino se reparten el ancho que dejan las demás columnas y recortan con
            // puntos suspensivos (la URL entera va en el title): la tabla nunca es más ancha que la card.
            'source' => [
                'title' => $this->t('From'),
                'class' => 'bkr-col-url',
                'callback' => 'renderSource',
                'filter_key' => 'a!source',
            ],
            'target_type' => [
                'title' => $this->t('To'),
                'class' => 'bkr-col-url',
                'type' => 'select',
                'list' => $this->targetTypes(),
                'filter_key' => 'a!target_type',
                'callback' => 'renderTarget',
                'orderby' => false,
            ],
            'http_code' => [
                'title' => $this->t('Code'),
                'type' => 'select',
                'list' => array_combine($this->allCodes(), $this->allCodes()),
                'filter_key' => 'a!http_code',
                'align' => 'center',
                'class' => 'fixed-width-sm',
            ],
            'origin' => [
                'title' => $this->t('Created by'),
                'type' => 'select',
                'list' => $this->origins(),
                'filter_key' => 'a!origin',
                'callback' => 'renderOrigin',
                'class' => 'bkr-col-origin',
            ],
            'hits' => [
                'title' => $this->t('Visits'),
                'type' => 'int',
                'align' => 'text-right',
                'class' => 'fixed-width-sm',
                'search' => false,
            ],
            'last_hit' => [
                'title' => $this->t('Last visit'),
                'type' => 'date',
                'class' => 'bkr-col-date',
                'search' => false,
            ],
            'active' => [
                'title' => $this->t('Active'),
                'active' => 'status',
                'type' => 'bool',
                'ajax' => true,
                'align' => 'center',
                'class' => 'fixed-width-sm',
                'orderby' => false,
            ],
        ];

        $this->bulk_actions = [
            'enableSelection' => [
                'text' => $this->t('Enable selection'),
                'icon' => 'icon-power-off text-success',
            ],
            'disableSelection' => [
                'text' => $this->t('Disable selection'),
                'icon' => 'icon-power-off text-danger',
            ],
            'divider' => ['text' => 'divider'],
            'delete' => [
                'text' => $this->t('Delete selected'),
                'confirm' => $this->t('Delete the selected rules?'),
                'icon' => 'icon-trash',
            ],
        ];
    }

    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['new_rule'] = [
                'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
                'desc' => $this->t('Add a rule'),
                'icon' => 'process-icon-new',
            ];
        }

        parent::initPageHeaderToolbar();
    }

    /**
     * Interruptor de la fila sin recargar la página (lo llama el listado con `ajax=1`).
     */
    public function ajaxProcessStatusBkredirectsRule()
    {
        $rule = new BkRedirectsRule((int) Tools::getValue('id_rule'));
        if (!Validate::isLoadedObject($rule)) {
            $this->respondJson(['success' => false, 'text' => $this->t('This rule no longer exists.')]);
        }

        $rule->active = !$rule->active;
        $saved = $rule->update();

        $this->respondJson([
            'success' => (bool) $saved,
            'text' => $saved
                ? ($rule->active ? $this->t('Rule enabled.') : $this->t('Rule disabled.'))
                : $this->t('The rule could not be saved.'),
        ]);
    }

    /**
     * Buscador del destino: productos, categorías, páginas CMS, marcas o proveedores por nombre.
     */
    public function ajaxProcessSearchTargets()
    {
        $type = (string) Tools::getValue('type');
        $query = trim((string) Tools::getValue('q'));
        $results = [];

        // Marca y proveedor admiten «ninguno en concreto»: la página que los lista todos.
        if (in_array($type, ['manufacturer', 'supplier'], true)) {
            $results[] = ['id' => 0, 'name' => $this->entityName($type, 0), 'hint' => ''];
        }
        if (Tools::strlen($query) >= 2 || ctype_digit($query)) {
            foreach ($this->searchEntities($type, $query) as $row) {
                $results[] = ['id' => (int) $row['id'], 'name' => $row['name'], 'hint' => (string) $row['hint']];
            }
        }

        $this->respondJson(['results' => $results]);
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitBkrRule')) {
            $this->processRule();

            return true;
        }

        return parent::postProcess();
    }

    /**
     * Guarda la regla del formulario. Si llega desde una recomendación, la crea la propia
     * recomendación con el destino elegido, para que la URL quede marcada como resuelta.
     */
    private function processRule()
    {
        $data = $this->formData();
        $this->submitted = $data;
        $this->fieldErrors = BkRedirectsRule::validate($data);

        if ($this->fieldErrors) {
            $this->display = $data['id_rule'] ? 'edit' : 'add';
            $this->errors[] = $this->t('The rule was not saved. Check the fields marked below.');

            return;
        }

        $recommendation = (string) Tools::getValue('bkr_rec');
        try {
            if (!$data['id_rule'] && $recommendation !== '') {
                // La recomendación crea la regla con su origen y deja la URL resuelta; la nota y el
                // interruptor del formulario se le ponen después.
                $rule = BkRedirectsRecommendations::apply($recommendation, $data);
                if ($rule->note !== $data['note'] || (int) $rule->active !== $data['active']) {
                    $rule->note = $data['note'];
                    $rule->active = $data['active'];
                    $rule->update();
                }
            } else {
                $rule = new BkRedirectsRule($data['id_rule'] ?: null);
                foreach ($data as $field => $value) {
                    if ($field !== 'id_rule') {
                        $rule->{$field} = $value;
                    }
                }
                if (!$rule->id) {
                    $rule->origin = 'manual';
                }
                if (!$rule->save()) {
                    throw new PrestaShopException($this->t('The rule could not be saved.'));
                }
            }
        } catch (Exception $e) {
            $this->display = $data['id_rule'] ? 'edit' : 'add';
            $this->errors[] = $e->getMessage();

            return;
        }

        $conf = '&conf=' . ($data['id_rule'] ? 4 : 3);
        Tools::redirectAdmin($recommendation !== ''
            ? $this->context->link->getAdminLink('AdminBkRedirectsDashboard') . $conf . '#bkr-recommendations'
            : self::$currentIndex . '&token=' . $this->token . $conf);
    }

    /**
     * Campos de la regla tal como llegan del formulario.
     *
     * @return array
     */
    private function formData()
    {
        return [
            'id_rule' => (int) Tools::getValue('id_rule'),
            'source' => trim((string) Tools::getValue('source')),
            'match_type' => (string) Tools::getValue('match_type', 'exact'),
            'query_mode' => (string) Tools::getValue('query_mode', 'pass'),
            'http_code' => (int) Tools::getValue('http_code', 301),
            'target_type' => (string) Tools::getValue('target_type', 'url'),
            'target_id' => (int) Tools::getValue('target_id'),
            'target_url' => trim((string) Tools::getValue('target_url')),
            'id_shop' => (int) Tools::getValue('id_shop'),
            'id_lang' => (int) Tools::getValue('id_lang'),
            'note' => Tools::substr(trim((string) Tools::getValue('note')), 0, 255),
            'active' => (int) Tools::getValue('active', 1) ? 1 : 0,
        ];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        Media::addJsDef([
            'bkrRules' => [
                'searchUrl' => self::$currentIndex . '&token=' . $this->token . '&ajax=1&action=searchTargets',
                'gone' => BkRedirectsConfig::CODES_GONE,
                'i18n' => [
                    'noResults' => $this->t('Nothing found. Try another word or the ID.'),
                    'change' => $this->t('Change'),
                    'searching' => $this->t('Searching…'),
                ],
            ],
        ]);
    }

    public function renderForm()
    {
        $rule = $this->loadObject(true);
        if (!$rule) {
            return '';
        }

        $isPro = $this->isPro();
        $values = $this->submitted !== null ? $this->submitted : [
            'id_rule' => (int) $rule->id,
            'source' => $rule->id ? $rule->source : (string) Tools::getValue('source'),
            'match_type' => $rule->match_type,
            'query_mode' => $rule->query_mode,
            'http_code' => (int) ($rule->id ? $rule->http_code : BkRedirectsConfig::defaultCode()),
            'target_type' => $rule->id ? $rule->target_type : 'product',
            'target_id' => (int) $rule->target_id,
            'target_url' => $rule->target_url,
            'id_shop' => (int) ($rule->id ? $rule->id_shop : (Shop::isFeatureActive() ? $this->shopId() : 0)),
            'id_lang' => (int) $rule->id_lang,
            'note' => $rule->note,
            'active' => $rule->id ? (int) $rule->active : 1,
        ];

        $targetName = '';
        if (in_array($values['target_type'], BkRedirectsRule::ENTITY_TARGETS, true)
            && ($values['target_id'] || $rule->id)) {
            $targetName = $this->entityLabel($values['target_type'], (int) $values['target_id']);
        }

        $codes = [];
        foreach ($this->allCodes() as $code) {
            $codes[] = [
                'value' => $code,
                'label' => $this->codeLabel($code),
                'pro' => in_array($code, BkRedirectsConfig::CODES_PRO, true),
            ];
        }

        $shops = [];
        if (Shop::isFeatureActive()) {
            foreach (Shop::getShops(false) as $shop) {
                $shops[] = ['id' => (int) $shop['id_shop'], 'name' => $shop['name']];
            }
        }

        $this->context->smarty->assign([
            'bkr_rule' => $values,
            'bkr_errors' => $this->fieldErrors,
            'bkr_target_name' => $targetName,
            'bkr_codes' => $codes,
            'bkr_target_types' => array_diff_key($this->targetTypes(), ['none' => true]),
            'bkr_match_types' => [
                'exact' => ['label' => $this->t('Exactly this URL'), 'pro' => false],
                'prefix' => ['label' => $this->t('Every URL that starts like this'), 'pro' => true],
                'wildcard' => ['label' => $this->t('URLs that fit a pattern with *'), 'pro' => true],
                'regex' => ['label' => $this->t('Regular expression'), 'pro' => true],
            ],
            // `pass` primero: es el modo por defecto y el de todas las ediciones.
            'bkr_query_modes' => [
                'pass' => $this->t('With any parameters, and pass them on to the destination'),
                'exact' => $this->t('Only with exactly these parameters'),
                'ignore' => $this->t('With any parameters (they are dropped)'),
            ],
            'bkr_shops' => $shops,
            'bkr_languages' => Language::getLanguages(false),
            'bkr_shop_base' => rtrim($this->context->shop->getBaseURL(true), '/'),
            'bkr_form_url' => self::$currentIndex . '&token=' . $this->token,
            'bkr_back_url' => Tools::getValue('bkr_rec') ? $this->context->link->getAdminLink('AdminBkRedirectsDashboard') . '#bkr-recommendations' : self::$currentIndex . '&token=' . $this->token,
            'bkr_rec' => (string) Tools::getValue('bkr_rec'),
            'bkr_pro_only' => !$isPro && $rule->id && $rule->isProOnly(),
        ]);

        return $this->fetchTemplate('rule-form.tpl');
    }

    /**
     * Nombre de la entidad elegida, para enseñarla en el buscador al editar.
     *
     * @param string $type
     * @param int $id
     *
     * @return string
     */
    private function entityLabel($type, $id)
    {
        if ($id === 0 && in_array($type, ['manufacturer', 'supplier'], true)) {
            return $this->entityName($type, 0);
        }
        $name = $this->entityName($type, $id);

        return $name === null ? '#' . $id . ' · ' . $this->t('no longer exists') : $name . ' #' . $id;
    }

    /* ------------------------------------------------------------------ *
     * Celdas del listado (HelperList pinta lo que devuelven sin escapar)
     * ------------------------------------------------------------------ */

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     */
    public function renderSource($value, $row)
    {
        $html = '<span class="bkr-url-cell" title="' . $this->e($value) . '">' . $this->e($value) . '</span>';
        $tags = [];
        if ($row['match_type'] !== 'exact') {
            $labels = ['prefix' => $this->t('starts with'), 'wildcard' => $this->t('pattern'), 'regex' => 'regex'];
            $tags[] = isset($labels[$row['match_type']]) ? $labels[$row['match_type']] : $row['match_type'];
        }
        if ((int) $row['id_lang']) {
            $tags[] = Language::getIsoById((int) $row['id_lang']);
        }
        foreach ($tags as $tag) {
            $html .= ' <span class="bkr-tag">' . $this->e($tag) . '</span>';
        }
        // Sin el complemento una regla PRO se guarda y no se aplica: se puede activar, desactivar o borrar.
        if (!$this->isPro() && BkRedirectsRule::rowIsProOnly($row)) {
            $html .= ' <span class="bkr-tag bkr-tag--pro" title="' . $this->e($this->t('Uses PRO options: it is kept, and it applies while BK SEO Redirects PRO is installed. You can enable, disable or delete it; editing it needs PRO.')) . '">'
                . '<i class="icon-lock"></i> PRO</span>';
        }

        return $html;
    }

    /**
     * @param string $value
     * @param array $row
     *
     * @return string
     */
    public function renderTarget($value, $row)
    {
        if (in_array((int) $row['http_code'], BkRedirectsConfig::CODES_GONE, true)) {
            return '<span class="text-muted">' . $this->e($this->t('Gone, no destination')) . '</span>';
        }
        $label = $this->targetLabel($value, (int) $row['target_id'], (string) $row['target_url']);

        return '<span class="bkr-url-cell" title="' . $this->e($label) . '">' . $this->e($label) . '</span>';
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function renderOrigin($value)
    {
        $origins = $this->origins();

        return $this->e(isset($origins[$value]) ? $origins[$value] : $value);
    }

    /**
     * @return array
     */
    private function origins()
    {
        return [
            'manual' => $this->t('You'),
            'recommendation' => $this->t('Recommendation'),
            'auto_delete' => $this->t('Automatic, on delete'),
            'auto_url' => $this->t('Automatic, URL change'),
            'import' => $this->t('Imported'),
            'ets_seo' => $this->t('Imported from ets_seo'),
        ];
    }

    /**
     * @return int[]
     */
    private function allCodes()
    {
        $codes = array_merge(BkRedirectsConfig::CODES_FREE, BkRedirectsConfig::CODES_PRO);
        sort($codes);

        return $codes;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
