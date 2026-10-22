<?php
/**
 * Una regla de redirección: de una ruta de la tienda (o un patrón) a una entidad, a una URL o a una
 * respuesta 410/451.
 *
 * `source` es la ruta relativa a la tienda —sin dominio ni carpeta de la tienda, con barra inicial,
 * descodificada y con la query si la lleva—, tal como la devuelve normalizeSource(). Es la misma
 * normalización que aplica el motor a la petición, así que una regla exacta se encuentra por
 * `source_hash` (sha1 de `source`) con una sola consulta indexada.
 *
 * INVARIANTE: toda escritura de reglas pasa por add()/update()/delete() de esta clase: recalculan el hash y
 * disparan los hooks de ObjectModel (`actionObjectBkRedirectsRule…After`), que son los que avisan al
 * complemento de que su caché de patrones caduca. Los contadores de visitas se escriben con SQL directo y no
 * son un cambio de regla.
 *
 * Los patrones (`match_type` distinto de `exact`) son del complemento: sin él, su origen se guarda tal cual.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsRule extends ObjectModel
{
    const TABLE = 'bkredirects_rule';

    const MATCH_TYPES = ['exact', 'prefix', 'wildcard', 'regex'];
    const QUERY_MODES = ['exact', 'ignore', 'pass'];
    const TARGET_TYPES = ['url', 'product', 'category', 'cms', 'cms_category', 'manufacturer', 'supplier', 'home', 'none'];
    /** Destinos que son una entidad con id (fabricante y proveedor admiten 0: su página de listado) */
    const ENTITY_TARGETS = ['product', 'category', 'cms', 'cms_category', 'manufacturer', 'supplier'];

    /** @var int */
    public $id_shop = 0;
    /** @var int */
    public $id_lang = 0;
    /** @var string */
    public $source;
    /** @var string */
    public $source_hash;
    /** @var string */
    public $match_type = 'exact';
    /** @var string */
    public $query_mode = 'pass';
    /** @var int */
    public $http_code = 301;
    /** @var string */
    public $target_type = 'url';
    /** @var int */
    public $target_id = 0;
    /** @var string */
    public $target_url = '';
    /** @var string */
    public $origin = 'manual';
    /** @var string */
    public $note = '';
    /** @var bool */
    public $active = true;
    /** @var int */
    public $hits = 0;
    /** @var string|null */
    public $last_hit;
    /** @var string */
    public $date_add;
    /** @var string */
    public $date_upd;

    public static $definition = [
        'table' => self::TABLE,
        'primary' => 'id_rule',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_lang' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'source' => ['type' => self::TYPE_STRING, 'required' => true, 'size' => 2048],
            'source_hash' => ['type' => self::TYPE_STRING, 'size' => 40],
            'match_type' => ['type' => self::TYPE_STRING, 'size' => 10],
            'query_mode' => ['type' => self::TYPE_STRING, 'size' => 10],
            'http_code' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'target_type' => ['type' => self::TYPE_STRING, 'size' => 16],
            'target_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'target_url' => ['type' => self::TYPE_STRING, 'size' => 2048],
            'origin' => ['type' => self::TYPE_STRING, 'size' => 20],
            'note' => ['type' => self::TYPE_STRING, 'size' => 255],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'hits' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'last_hit' => ['type' => self::TYPE_DATE, 'allow_null' => true],
            'date_add' => ['type' => self::TYPE_DATE],
            'date_upd' => ['type' => self::TYPE_DATE],
        ],
    ];

    /* ------------------------------------------------------------------ *
     * Tablas del módulo (las tres nacen y mueren juntas)
     * ------------------------------------------------------------------ */

    /**
     * @return bool
     */
    public static function installTables()
    {
        $p = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;
        $queries = [
            "CREATE TABLE IF NOT EXISTS `{$p}bkredirects_rule` (
                `id_rule` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
                `source` VARCHAR(2048) NOT NULL,
                `source_hash` CHAR(40) NOT NULL,
                `match_type` VARCHAR(10) NOT NULL DEFAULT 'exact',
                `query_mode` VARCHAR(10) NOT NULL DEFAULT 'pass',
                `http_code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
                `target_type` VARCHAR(16) NOT NULL DEFAULT 'url',
                `target_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `target_url` VARCHAR(2048) NOT NULL DEFAULT '',
                `origin` VARCHAR(20) NOT NULL DEFAULT 'manual',
                `note` VARCHAR(255) NOT NULL DEFAULT '',
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_hit` DATETIME NULL DEFAULT NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_rule`),
                KEY `shop_hash` (`id_shop`, `source_hash`),
                KEY `target` (`target_type`, `target_id`),
                KEY `match_active` (`match_type`, `active`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}bkredirects_notfound` (
                `id_notfound` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
                `url` VARCHAR(2048) NOT NULL,
                `url_hash` CHAR(40) NOT NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `bot_hits` INT UNSIGNED NOT NULL DEFAULT 0,
                `last_referer` VARCHAR(512) NOT NULL DEFAULT '',
                `id_product` INT UNSIGNED NOT NULL DEFAULT 0,
                `first_seen` DATETIME NOT NULL,
                `last_seen` DATETIME NOT NULL,
                `status` VARCHAR(10) NOT NULL DEFAULT 'open',
                `id_rule` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_notfound`),
                UNIQUE KEY `shop_hash` (`id_shop`, `url_hash`),
                KEY `status_hits` (`status`, `hits`),
                KEY `last_seen` (`last_seen`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS `{$p}bkredirects_stat` (
                `day` DATE NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `redirects` INT UNSIGNED NOT NULL DEFAULT 0,
                `notfound` INT UNSIGNED NOT NULL DEFAULT 0,
                `notfound_bots` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`day`, `id_shop`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                BkRedirectsLogger::error('No se pudo crear una tabla: ' . Db::getInstance()->getMsgError());

                return false;
            }
        }

        return true;
    }

    public static function uninstallTables()
    {
        foreach (['bkredirects_rule', 'bkredirects_notfound', 'bkredirects_stat'] as $table) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
        }
    }

    /* ------------------------------------------------------------------ *
     * Escritura: hash y caché de patrones
     * ------------------------------------------------------------------ */

    public function add($autoDate = true, $nullValues = false)
    {
        $this->prepare();
        $ok = parent::add($autoDate, $nullValues);
        if ($ok && $this->active) {
            BkRedirectsNotFound::resolveByRule($this);
        }

        return $ok;
    }

    public function update($nullValues = false)
    {
        $this->prepare();
        $ok = parent::update($nullValues);
        if ($ok && $this->active) {
            BkRedirectsNotFound::resolveByRule($this);
        }

        return $ok;
    }

    /**
     * Deja la regla en su forma canónica antes de guardarla: origen normalizado (sin la query si la
     * regla no la compara), destino vacío en 410/451 y hash del origen.
     */
    private function prepare()
    {
        $this->query_mode = self::effectiveQueryMode((string) $this->match_type, (string) $this->source, (string) $this->query_mode);
        $this->source = self::normalizeSource((string) $this->source, (string) $this->match_type, (int) $this->id_shop);
        if ($this->match_type === 'exact' && $this->query_mode !== 'exact') {
            $this->source = self::stripQuery($this->source);
        }
        if (in_array((int) $this->http_code, BkRedirectsConfig::CODES_GONE, true)) {
            $this->target_type = 'none';
            $this->target_id = 0;
            $this->target_url = '';
        }
        $this->source_hash = sha1($this->source);
        if ($this->last_hit === '' || $this->last_hit === '0000-00-00 00:00:00') {
            $this->last_hit = null;
        }
    }

    /* ------------------------------------------------------------------ *
     * Normalización (compartida con el motor y los importadores)
     * ------------------------------------------------------------------ */

    /**
     * Forma canónica de un origen: ruta relativa a la tienda con barra inicial, descodificada, sin
     * fragmento y con la query tal cual (descodificada). Acepta una URL entera o una ruta; si empieza
     * por la carpeta de la tienda (`/tienda/es/x`), se le quita. El origen de un patrón lo normaliza el
     * complemento; sin él se deja como está.
     *
     * @param string $input
     * @param string $matchType
     * @param int    $idShop    0 = la tienda del contexto
     *
     * @return string
     */
    public static function normalizeSource($input, $matchType = 'exact', $idShop = 0)
    {
        $input = trim((string) $input);
        if ($matchType !== 'exact') {
            $extension = BkRedirectsExtension::get();

            return $extension ? $extension->normalizeSource($input, (string) $matchType, (int) $idShop) : $input;
        }
        if (preg_match('#^(https?:)?//#i', $input)) {
            $parts = parse_url($input);
            $input = (isset($parts['path']) ? $parts['path'] : '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }
        $hash = strpos($input, '#');
        if ($hash !== false) {
            $input = substr($input, 0, $hash);
        }
        $query = '';
        $mark = strpos($input, '?');
        if ($mark !== false) {
            $query = (string) substr($input, $mark + 1);
            $input = substr($input, 0, $mark);
        }
        $path = rawurldecode($input);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }
        $base = self::shopBaseUri($idShop);
        if ($base !== '/' && stripos($path, $base) === 0) {
            $path = '/' . substr($path, strlen($base));
        }

        return $path . ($query !== '' ? '?' . rawurldecode($query) : '');
    }

    /**
     * @param string $source
     *
     * @return string
     */
    public static function stripQuery($source)
    {
        $mark = strpos($source, '?');

        return $mark === false ? $source : substr($source, 0, $mark);
    }

    /**
     * Carpeta de la tienda en la URL (`/`, `/tienda/`): physical_uri + virtual_uri.
     *
     * @param int $idShop 0 = la del contexto
     *
     * @return string
     */
    public static function shopBaseUri($idShop = 0)
    {
        $shop = (int) $idShop ? new Shop((int) $idShop) : Context::getContext()->shop;
        if (!Validate::isLoadedObject($shop)) {
            return '/';
        }

        return $shop->getBaseURI() ?: '/';
    }

    /**
     * Regla exacta por origen, en la tienda y el idioma exactos (0 = todos).
     *
     * @param int    $idShop
     * @param string $source Ya normalizado
     * @param int    $idLang
     *
     * @return BkRedirectsRule|null
     */
    public static function findExact($idShop, $source, $idLang = 0)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_rule` FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `id_shop` = ' . (int) $idShop . ' AND `source_hash` = \'' . pSQL(sha1($source)) . '\'
               AND `id_lang` = ' . (int) $idLang . ' AND `match_type` = \'exact\'
             ORDER BY `active` DESC, `id_rule` DESC'
        );

        return $id ? new self($id) : null;
    }

    /**
     * Modo de query con el que se guarda y se aplica una regla. Una exacta cuyo origen lleva `?` compara
     * siempre esa query (`exact`); en las demás manda el modo elegido, y el de por defecto es `pass`: la regla
     * recoge la ruta con cualquier query y la pasa al destino, así las utm llegan.
     *
     * @param string $matchType
     * @param string $source    Tal como se escribe (URL entera o ruta)
     * @param string $queryMode
     *
     * @return string
     */
    public static function effectiveQueryMode($matchType, $source, $queryMode)
    {
        return $matchType === 'exact' && strpos((string) $source, '?') !== false ? 'exact' : (string) $queryMode;
    }

    /**
     * El modo de query es PRO: exigir la query concreta en una regla sin `?` (solo sin parámetros) o
     * descartarla. Pasarla (`pass`) y comparar la query escrita en el origen son de todas las ediciones.
     *
     * @param string $matchType
     * @param string $source
     * @param string $queryMode
     *
     * @return bool
     */
    public static function queryModeIsPro($matchType, $source, $queryMode)
    {
        $mode = self::effectiveQueryMode($matchType, $source, $queryMode);

        return $mode !== 'pass' && !($matchType === 'exact' && strpos((string) $source, '?') !== false);
    }

    /**
     * Usa algo que solo tiene la edición PRO: un patrón, un modo de query PRO (queryModeIsPro), un idioma
     * concreto o 303/308/451. Sin el complemento la regla sigue guardada, no se aplica y se puede activar,
     * desactivar o borrar, pero no editar: validate() rechaza lo PRO.
     *
     * @return bool
     */
    public function isProOnly()
    {
        return self::rowIsProOnly([
            'match_type' => $this->match_type,
            'query_mode' => $this->query_mode,
            'source' => $this->source,
            'id_lang' => $this->id_lang,
            'http_code' => $this->http_code,
        ]);
    }

    /**
     * @param array $row match_type, query_mode, source, id_lang, http_code
     *
     * @return bool
     */
    public static function rowIsProOnly(array $row)
    {
        return $row['match_type'] !== 'exact' || self::queryModeIsPro($row['match_type'], $row['source'], $row['query_mode']) || (int) $row['id_lang'] !== 0
            || in_array((int) $row['http_code'], BkRedirectsConfig::CODES_PRO, true);
    }

    /* ------------------------------------------------------------------ *
     * Validación (la usan la UI, los importadores y las recomendaciones)
     * ------------------------------------------------------------------ */

    /**
     * Errores por campo de una regla que se va a guardar. Vacío si vale.
     *
     * @param array $data Campos de la regla; `id_rule` si es una existente
     *
     * @return array campo => mensaje traducido
     */
    public static function validate(array $data): array
    {
        $isPro = BkRedirectsExtension::isPro();
        $errors = [];
        $get = function ($key, $default = '') use ($data) {
            return isset($data[$key]) ? $data[$key] : $default;
        };
        $matchType = (string) $get('match_type', 'exact');
        $queryMode = self::effectiveQueryMode((string) $get('match_type', 'exact'), (string) $get('source'), (string) $get('query_mode', 'pass'));
        $code = (int) $get('http_code', 301);
        $targetType = (string) $get('target_type', 'url');
        $targetId = (int) $get('target_id', 0);
        $targetUrl = trim((string) $get('target_url'));
        $idShop = (int) $get('id_shop', 0);
        $idLang = (int) $get('id_lang', 0);
        $rawSource = trim((string) $get('source'));

        if (!in_array($matchType, self::MATCH_TYPES, true)) {
            $errors['match_type'] = self::t('Invalid match type.');
        } elseif ($matchType !== 'exact' && !$isPro) {
            $errors['match_type'] = self::t('Patterns are a PRO feature.');
        }
        if (!in_array($queryMode, self::QUERY_MODES, true)) {
            $errors['query_mode'] = self::t('Invalid query string mode.');
        } elseif (!$isPro && self::queryModeIsPro($matchType, (string) $get('source'), $queryMode)) {
            $errors['query_mode'] = self::t('Matching only without parameters, or dropping them, is a PRO feature.');
        }
        if (!in_array($code, array_merge(BkRedirectsConfig::CODES_FREE, BkRedirectsConfig::CODES_PRO), true)) {
            $errors['http_code'] = self::t('Invalid HTTP code.');
        } elseif (in_array($code, BkRedirectsConfig::CODES_PRO, true) && !$isPro) {
            $errors['http_code'] = self::t('HTTP codes 303, 308 and 451 are a PRO feature.');
        }
        if ($idLang !== 0) {
            if (!$isPro) {
                $errors['id_lang'] = self::t('Rules for one language are a PRO feature.');
            } elseif (!Language::getLanguage($idLang)) {
                $errors['id_lang'] = self::t('This language does not exist.');
            }
        }
        if ($idShop !== 0 && !Validate::isLoadedObject(new Shop($idShop))) {
            $errors['id_shop'] = self::t('This shop does not exist.');
        }

        $source = '';
        if ($rawSource === '') {
            $errors['source'] = self::t('The source URL is required.');
        } elseif (Tools::strlen($rawSource) > 2048) {
            $errors['source'] = self::t('The source URL is too long.');
        } elseif ($matchType !== 'exact') {
            $extension = BkRedirectsExtension::get();
            $error = $extension ? $extension->validateSource($rawSource, $matchType) : null;
            if ($error !== null) {
                $errors['source'] = $error;
            }
        } else {
            $source = self::normalizeSource($rawSource, 'exact', $idShop);
            if ($queryMode !== 'exact') {
                $source = self::stripQuery($source);
            }
            if ($source === '/') {
                $errors['source'] = self::t('The home page cannot be redirected.');
            }
        }

        $gone = in_array($code, BkRedirectsConfig::CODES_GONE, true);
        if (!$gone) {
            if (!in_array($targetType, self::TARGET_TYPES, true) || $targetType === 'none') {
                $errors['target_type'] = self::t('Choose where the rule redirects to.');
            } elseif ($targetType === 'url') {
                if ($targetUrl === '' || !preg_match('#^(https?://[^\s]+|/[^\s]*)$#i', $targetUrl)) {
                    $errors['target_url'] = self::t('The target must be an absolute URL or a path starting with /.');
                } elseif ($source !== '' && $matchType === 'exact' && $targetUrl[0] === '/'
                    && self::normalizeSource($targetUrl, 'exact', $idShop) === $source) {
                    $errors['target_url'] = self::t('The rule would redirect to itself.');
                }
            } elseif (in_array($targetType, self::ENTITY_TARGETS, true)) {
                $listPage = $targetId === 0 && in_array($targetType, ['manufacturer', 'supplier'], true);
                if (!$listPage && !BkRedirectsResolver::targetExists($targetType, $targetId)) {
                    $errors['target_id'] = self::t('The target does not exist.');
                }
            }
        }

        if (!isset($errors['source']) && $matchType === 'exact' && $source !== '') {
            $duplicate = (int) Db::getInstance()->getValue(
                'SELECT `id_rule` FROM `' . _DB_PREFIX_ . self::TABLE . '`
                 WHERE `id_shop` = ' . $idShop . ' AND `id_lang` = ' . $idLang . ' AND `match_type` = \'exact\'
                   AND `source_hash` = \'' . pSQL(sha1($source)) . '\' AND `id_rule` <> ' . (int) $get('id_rule', 0)
            );
            if ($duplicate) {
                $errors['source'] = self::t('There is already a rule for this URL (#%id%).', ['%id%' => $duplicate]);
            }
        }

        return $errors;
    }

    /**
     * @param string $message
     * @param array  $params
     *
     * @return string
     */
    private static function t($message, array $params = [])
    {
        return Context::getContext()->getTranslator()->trans($message, $params, 'Modules.Bkredirects.Admin');
    }
}
