<?php
/**
 * El motor: decide en `actionDispatcherBefore`, antes de que el despachador cree el controlador, si la
 * petición se redirige.
 *
 * Coste por petición: una consulta indexada por hash para las reglas exactas. Las reglas de patrón son
 * del complemento (BkRedirectsExtensionInterface::matchPattern), que las lee de su caché en fichero: una
 * petición sin regla no hace más consultas que esa.
 *
 * Nunca redirige: el back office, las peticiones ajax, lo que no sea GET/HEAD, las rutas excluidas ni
 * una petición hacia sí misma. Las reglas 410/451 no redirigen: responden con la página 404 del tema
 * y su propio código.
 *
 * El idioma de la petición es el del contexto: el despachador ya ha leído el prefijo de idioma de la
 * URL (`isolang`) y ha hecho `Tools::switchLanguage()` en su constructor, antes de este hook, en
 * todas las versiones de 1.7.5 a 9.x.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsEngine
{
    /** Código 410/451 de la petición en curso: lo aplica onOutput() al pintar la página 404 */
    private static $goneCode = 0;

    /** @var array|null Petición en curso normalizada (request()) */
    private static $request = null;

    /**
     * actionDispatcherBefore.
     *
     * @param int $controllerType Dispatcher::FC_FRONT, FC_MODULE o FC_ADMIN
     */
    public static function dispatch($controllerType)
    {
        if ((int) $controllerType === Dispatcher::FC_ADMIN || defined('_PS_ADMIN_DIR_')
            || !BkRedirectsConfig::isOn(BkRedirectsConfig::ENABLED)) {
            return;
        }
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        if (!in_array($method, ['GET', 'HEAD'], true) || self::isAjax()) {
            return;
        }
        $request = self::request();
        if ($request === null || self::isExcluded($request['path'])) {
            return;
        }

        $match = self::match($request['path'], $request['query'], $request['id_shop'], $request['id_lang']);
        if ($match === null) {
            return;
        }
        $rule = $match['rule'];
        $code = (int) $rule->http_code;

        if (in_array($code, BkRedirectsConfig::CODES_GONE, true)) {
            self::countHit($rule, $request['id_shop']);
            self::renderGone($code);

            return;
        }

        $url = BkRedirectsResolver::targetUrl($rule, $request['id_lang'], $request['id_shop'], $request['raw_query']);
        if ($url === null) {
            BkRedirectsLogger::debug('Regla #' . (int) $rule->id . ' sin destino válido para ' . $request['full']);

            return;
        }
        if (self::pointsToRequest($url, $request)) {
            BkRedirectsLogger::warning('Regla #' . (int) $rule->id . ' redirige a la misma URL (' . $request['full'] . '): no se aplica');

            return;
        }

        self::countHit($rule, $request['id_shop']);
        header('X-Redirect-By: BK SEO Redirects');
        header('Location: ' . $url, true, $code);
        exit;
    }

    /**
     * actionOutputHTMLBefore: el último punto antes de enviar el HTML de una página del front, con el
     * código de respuesta ya decidido por el controlador. Aplica el 410/451 de una regla y registra los
     * 404, vengan de la página 404 o de una ficha (producto, categoría…) que responde 404.
     */
    public static function onOutput()
    {
        if (self::$goneCode) {
            $text = self::$goneCode === 451 ? 'Unavailable For Legal Reasons' : 'Gone';
            header('HTTP/1.1 ' . self::$goneCode . ' ' . $text, true, self::$goneCode);
            header('Status: ' . self::$goneCode . ' ' . $text);

            return;
        }
        if (http_response_code() !== 404 || !BkRedirectsConfig::isOn(BkRedirectsConfig::LOG_404)) {
            return;
        }
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        $request = self::request();
        if ($method !== 'GET' || $request === null || self::isExcluded($request['path']) || self::isStatic($request['path'])
            || self::isCoreNotFoundPage($request)) {
            return;
        }
        $controller = Context::getContext()->controller;
        $idProduct = isset($controller->php_self) && $controller->php_self === 'product' ? (int) Tools::getValue('id_product') : 0;
        BkRedirectsNotFound::record($request, $idProduct);
    }

    /**
     * La regla que se aplica a una ruta: primero las exactas (una consulta) y, si ninguna captura la ruta, los
     * patrones del complemento. Entre exactas gana la que compara la query, después la de idioma concreto,
     * después la de tienda concreta.
     *
     * Las reglas PRO (BkRedirectsRule::rowIsProOnly) solo se aplican con el complemento instalado.
     *
     * @param string $path   Ruta normalizada, sin query
     * @param string $query  Query descodificada, sin `?`
     * @param int    $idShop
     * @param int    $idLang
     *
     * @return array|null ['rule' => BkRedirectsRule (target_url ya sustituido), 'groups' => string[]]
     */
    public static function match($path, $query, $idShop, $idLang)
    {
        $full = $path . ($query !== '' ? '?' . $query : '');
        $hashes = array_unique([sha1($full), sha1($path)]);
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . BkRedirectsRule::TABLE . '`
             WHERE `id_shop` IN (0, ' . (int) $idShop . ') AND `source_hash` IN (\'' . implode('\',\'', $hashes) . '\')
               AND `match_type` = \'exact\' AND `active` = 1'
        ) ?: [];
        $extension = BkRedirectsExtension::get();

        $best = null;
        $bestScore = -1;
        foreach ($rows as $row) {
            if ((!$extension && BkRedirectsRule::rowIsProOnly($row)) || self::captures($row, $path, $query, $idLang) === null) {
                continue;
            }
            $score = ($row['query_mode'] === 'exact' && $query !== '' ? 4 : 0)
                + ((int) $row['id_lang'] ? 2 : 0) + ((int) $row['id_shop'] ? 1 : 0);
            if ($score > $bestScore) {
                $best = $row;
                $bestScore = $score;
            }
        }
        if ($best !== null) {
            return ['rule' => self::hydrate($best), 'groups' => []];
        }

        return $extension ? $extension->matchPattern((string) $path, (string) $query, (int) $idShop, (int) $idLang) : null;
    }

    /**
     * Si una regla captura una ruta: la única definición de «esta regla se aplica a esta URL», que usan el
     * motor y el registro de 404 (BkRedirectsNotFound::resolveByRule). No mira ni `active` ni la tienda.
     *
     * - exacta con `query_mode = exact`: el origen es la ruta con su query (sin query solo casa sin query);
     * - exacta con `ignore`/`pass`: el origen es la ruta, la query da igual;
     * - patrón: lo decide el complemento; sin él, ninguna.
     *
     * @param array  $row    Fila de la regla
     * @param string $path
     * @param string $query  Descodificada, sin `?`
     * @param int    $idLang 0 = cualquier idioma
     *
     * @return string[]|null Lo capturado ($1…), o null si no la captura
     */
    public static function captures(array $row, $path, $query, $idLang = 0)
    {
        if ((int) $row['id_lang'] && (int) $idLang && (int) $row['id_lang'] !== (int) $idLang) {
            return null;
        }
        if ($row['match_type'] !== 'exact') {
            $extension = BkRedirectsExtension::get();

            return $extension ? $extension->captures($row, (string) $path, (string) $query) : null;
        }
        if ($row['query_mode'] === 'exact') {
            return $row['source'] === $path . ($query !== '' ? '?' . $query : '') ? [] : null;
        }

        return $row['source'] === $path ? [] : null;
    }

    /**
     * La petición en curso, relativa a la tienda y normalizada como los orígenes de las reglas.
     *
     * @return array|null path, query (descodificada), raw_query (tal cual llegó), full, id_shop, id_lang
     */
    public static function request()
    {
        if (self::$request !== null) {
            return self::$request ?: null;
        }
        $context = Context::getContext();
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($uri === '' || !isset($context->shop->id, $context->language->id)) {
            self::$request = false;

            return null;
        }
        $mark = strpos($uri, '?');
        $rawQuery = $mark === false ? '' : (string) substr($uri, $mark + 1);
        $full = BkRedirectsRule::normalizeSource($uri, 'exact', 0);
        $path = BkRedirectsRule::stripQuery($full);

        return self::$request = [
            'path' => $path,
            'query' => (string) substr($full, strlen($path) + 1),
            'raw_query' => $rawQuery,
            'full' => $full,
            'id_shop' => (int) $context->shop->id,
            'id_lang' => (int) $context->language->id,
        ];
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isStatic($path)
    {
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' && in_array($ext, BkRedirectsConfig::STATIC_EXTENSIONS, true);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isExcluded($path)
    {
        foreach (BkRedirectsConfig::excludedPaths() as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * La página 404 a la que el propio núcleo manda con un 302 (p. ej. una página CMS desactivada) no es
     * una URL rota: la rota es la de antes del salto, y esa no llega aquí.
     *
     * @param array $request
     *
     * @return bool
     */
    private static function isCoreNotFoundPage(array $request)
    {
        return $request['path'] === '/404'
            || ($request['path'] === '/index.php' && preg_match('/(^|&)controller=(404|pagenotfound)(&|$)/i', $request['query']));
    }

    /**
     * @return bool
     */
    private static function isAjax()
    {
        return Tools::getValue('ajax')
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * El destino es la propia petición (misma tienda, misma ruta y query): redirigir sería un bucle.
     *
     * @param string $url
     * @param array  $request
     *
     * @return bool
     */
    private static function pointsToRequest($url, array $request)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && isset($_SERVER['HTTP_HOST']) && strcasecmp($host, preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'])) !== 0) {
            return false;
        }

        return BkRedirectsRule::normalizeSource($url, 'exact', 0) === $request['full'];
    }

    /**
     * Suma la visita a la regla y al día. SQL directo: un contador no es un guardado de la regla y no
     * debe vaciar la caché de patrones.
     *
     * @param BkRedirectsRule $rule
     * @param int             $idShop
     */
    private static function countHit(BkRedirectsRule $rule, $idShop)
    {
        $db = Db::getInstance();
        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . BkRedirectsRule::TABLE . '` SET `hits` = `hits` + 1, `last_hit` = \'' . pSQL(date('Y-m-d H:i:s')) . '\'
             WHERE `id_rule` = ' . (int) $rule->id
        );
        BkRedirectsStats::add((int) $idShop, 'redirects');
    }

    /**
     * Pinta la página 404 del tema con el código de la regla. El controlador es el mismo que usaría el
     * despachador; onOutput() cambia el 404 que fija por el de la regla justo antes de enviar.
     *
     * @param int $code
     */
    private static function renderGone($code)
    {
        self::$goneCode = (int) $code;
        Controller::getController('PageNotFoundController')->run();
        exit;
    }

    /**
     * Regla a partir de su fila, sin consultar (la usa también el complemento con sus patrones).
     *
     * @param array $row
     *
     * @return BkRedirectsRule
     */
    public static function hydrate(array $row)
    {
        $rule = new BkRedirectsRule();
        $rule->id = (int) $row['id_rule'];
        foreach (['id_shop', 'id_lang', 'http_code', 'target_id'] as $key) {
            $rule->{$key} = (int) $row[$key];
        }
        foreach (['source', 'match_type', 'query_mode', 'target_type', 'target_url'] as $key) {
            $rule->{$key} = (string) $row[$key];
        }

        return $rule;
    }
}
