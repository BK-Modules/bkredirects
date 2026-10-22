<?php
/**
 * Recomendaciones del panel: lo que conviene arreglar, de más a menos grave.
 *
 * Tipos (`kind`) y su clave (`kind:id`):
 *   - `notfound:<id_notfound>`: URL que da 404 a personas (las que solo ven bots no cuentan).
 *   - `inactive_product_404:<id_notfound>`: lo mismo, pero es la ficha de un producto desactivado.
 *   - `disabled_target:<id_rule>`: regla activa cuyo destino está desactivado o borrado (no redirige).
 *   - `chain:<id_rule>`: regla cuyo destino es el origen de otra regla (dos saltos o más).
 *   - `loop:<id_rule>`: regla cuyo destino acaba volviendo a ella.
 *   - `stale_rule:<id_rule>`: regla con más de STALE_DAYS días y sin visitas en ese tiempo.
 * Con el complemento cada recomendación trae destino sugerido (`suggestion`, de
 * BkRedirectsExtensionInterface::suggest); sin él es null y se aplica de una en una con el destino elegido a
 * mano. Descartar una 404 la marca como ignorada; descartar una regla guarda la clave en
 * BkRedirectsConfig::DISMISSED. Las reglas PRO solo se analizan con el complemento: sin él no se aplican.
 *
 * Cada recomendación lleva, además de lo que pinta el panel, lo que el complemento necesita para sugerir:
 * `id_shop` e `id_lang` (de la 404 o de la tienda analizada), `id_product` (inactive_product_404),
 * `match_type` (reglas) y `chain_end` (chain: destino de la última regla de la cadena).
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsRecommendations
{
    const STALE_DAYS = 180;
    /** Saltos que se siguen como máximo al recorrer una cadena */
    const MAX_HOPS = 10;
    /** Reglas que se analizan como mucho en cada cálculo (las más recientes) */
    const MAX_RULES = 5000;

    const SEVERITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    /**
     * @param int $idShop
     * @param int $idLang Idioma de las etiquetas y de las URLs de destino
     * @param int $limit
     *
     * @return array
     */
    public static function all(int $idShop, int $idLang, int $limit = 200): array
    {
        $dismissed = array_flip(BkRedirectsConfig::dismissed());
        $items = [];

        foreach (self::notFoundRows($idShop, $limit) as $row) {
            $items[] = self::fromNotFound($row);
        }
        foreach (self::ruleItems($idShop, $idLang) as $item) {
            if (!isset($dismissed[$item['key']])) {
                $items[] = $item;
            }
        }

        usort($items, function ($a, $b) {
            $bySeverity = self::SEVERITY_ORDER[$a['severity']] - self::SEVERITY_ORDER[$b['severity']];

            return $bySeverity ?: $b['hits'] - $a['hits'];
        });

        $items = array_slice($items, 0, max(1, $limit));
        $extension = BkRedirectsExtension::get();
        if ($extension) {
            foreach ($items as $i => $item) {
                $items[$i]['suggestion'] = $extension->suggest($item, $idLang);
            }
        }

        return $items;
    }

    /**
     * Aplica una recomendación: crea la regla de una 404, reapunta una regla o desactiva una sin uso.
     *
     * @param string $key
     * @param array  $override target_type, target_id, target_url, http_code elegidos a mano; sin destino se
     *                         usa el sugerido por el complemento
     *
     * @return BkRedirectsRule
     *
     * @throws PrestaShopException Si la clave no existe, falta el destino o la regla no valida
     */
    public static function apply(string $key, array $override = []): BkRedirectsRule
    {
        list($kind, $id) = self::parseKey($key);
        $context = Context::getContext();
        $idLang = (int) $context->language->id;
        $extension = BkRedirectsExtension::get();

        if ($kind === 'notfound' || $kind === 'inactive_product_404') {
            $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . BkRedirectsNotFound::TABLE . '` WHERE `id_notfound` = ' . (int) $id);
            if (!$row) {
                throw new PrestaShopException(Context::getContext()->getTranslator()->trans('Recommendation not found', [], 'Modules.Bkredirects.Admin'));
            }
            $item = self::fromNotFound($row);
            // Solo se reaprovecha la regla de esa URL que no está funcionando (desactivada o con el destino caído).
            // Una que redirige no se toca: la 404 viene de visitas con parámetros que no compara, y reescribirla
            // cambiaría el destino de las que sí redirige; validate() la señala como regla duplicada.
            $existing = BkRedirectsRule::findExact((int) $row['id_shop'], $row['url']);
            $rule = $existing && !self::isServing($existing, $idLang) ? $existing : new BkRedirectsRule();
            $rule->id_shop = (int) $row['id_shop'];
            $rule->source = $row['url'];
            $rule->match_type = 'exact';
            $rule->query_mode = 'pass';
            $rule->http_code = BkRedirectsConfig::defaultCode();
            $rule->origin = 'recommendation';
            $rule->active = true;
            self::setTarget($rule, $override, $extension ? $extension->suggest($item, $idLang) : null);
            self::save($rule);
            BkRedirectsNotFound::setStatus((int) $id, 'resolved', (int) $rule->id);

            return $rule;
        }

        $rule = new BkRedirectsRule((int) $id);
        if (!Validate::isLoadedObject($rule)) {
            throw new PrestaShopException(Context::getContext()->getTranslator()->trans('Recommendation not found', [], 'Modules.Bkredirects.Admin'));
        }
        if ($kind === 'stale_rule') {
            $rule->active = false;
            $rule->save();

            return $rule;
        }
        $suggestion = null;
        if ($extension) {
            foreach (self::ruleItems($rule->id_shop ?: (int) $context->shop->id, $idLang, (int) $rule->id) as $item) {
                if ($item['key'] === $key) {
                    $suggestion = $extension->suggest($item, $idLang);
                }
            }
        }
        self::setTarget($rule, $override, $suggestion);
        self::save($rule);

        return $rule;
    }

    /**
     * @param string $key
     */
    public static function dismiss(string $key)
    {
        list($kind, $id) = self::parseKey($key);
        if ($kind === 'notfound' || $kind === 'inactive_product_404') {
            BkRedirectsNotFound::setStatus((int) $id, 'ignored');

            return;
        }
        $keys = BkRedirectsConfig::dismissed();
        $keys[] = $kind . ':' . (int) $id;
        BkRedirectsConfig::setDismissed($keys);
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param string $key
     *
     * @return array [kind, id]
     *
     * @throws PrestaShopException
     */
    private static function parseKey($key)
    {
        $kinds = ['notfound', 'inactive_product_404', 'disabled_target', 'chain', 'loop', 'stale_rule'];
        if (!preg_match('/^([a-z_0-9]+):(\d+)$/', $key, $m) || !in_array($m[1], $kinds, true)) {
            throw new PrestaShopException(Context::getContext()->getTranslator()->trans('Invalid recommendation key', [], 'Modules.Bkredirects.Admin'));
        }

        return [$m[1], (int) $m[2]];
    }

    /**
     * @param int $idShop
     * @param int $limit
     *
     * @return array
     */
    private static function notFoundRows($idShop, $limit)
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . BkRedirectsNotFound::TABLE . '`
             WHERE `id_shop` = ' . (int) $idShop . ' AND `status` = \'open\' AND `hits` > 0
             ORDER BY `hits` DESC, `last_seen` DESC LIMIT ' . (int) $limit
        ) ?: [];
    }

    /**
     * @param array $row Fila de `bkredirects_notfound`
     *
     * @return array
     */
    private static function fromNotFound(array $row)
    {
        $t = Context::getContext()->getTranslator();
        $idProduct = (int) $row['id_product'];
        $inactive = $idProduct && (int) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'product_shop` WHERE `id_product` = ' . $idProduct . ' AND `id_shop` = ' . (int) $row['id_shop'] . ' AND `active` = 0'
        );
        $hits = (int) $row['hits'];

        return [
            'key' => ($inactive ? 'inactive_product_404' : 'notfound') . ':' . (int) $row['id_notfound'],
            'kind' => $inactive ? 'inactive_product_404' : 'notfound',
            'severity' => $hits >= 50 ? 'high' : ($hits >= 5 ? 'medium' : 'low'),
            'url' => $row['url'],
            'hits' => $hits,
            'bot_hits' => (int) $row['bot_hits'],
            'last_seen' => $row['last_seen'],
            'reason' => $inactive
                ? $t->trans('This product is disabled and its page returns a 404 to visitors.', [], 'Modules.Bkredirects.Admin')
                : $t->trans('This URL returns a 404 to visitors.', [], 'Modules.Bkredirects.Admin'),
            'suggestion' => null,
            'id_notfound' => (int) $row['id_notfound'],
            'id_rule' => null,
            'id_shop' => (int) $row['id_shop'],
            'id_lang' => (int) $row['id_lang'],
            'id_product' => $inactive ? $idProduct : 0,
        ];
    }

    /**
     * Recomendaciones sobre las reglas activas: destino caído, cadenas, bucles y reglas sin uso.
     *
     * @param int $idShop
     * @param int $idLang
     * @param int $onlyRule Analizar solo esta regla (0 = todas)
     *
     * @return array
     */
    private static function ruleItems($idShop, $idLang, $onlyRule = 0)
    {
        $isPro = BkRedirectsExtension::isPro();
        $t = Context::getContext()->getTranslator();
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . BkRedirectsRule::TABLE . '`
             WHERE `id_shop` IN (0, ' . (int) $idShop . ') AND `active` = 1' . ($onlyRule ? ' AND `id_rule` = ' . (int) $onlyRule : '') . '
             ORDER BY `id_rule` DESC LIMIT ' . self::MAX_RULES
        ) ?: [];
        $staleBefore = date('Y-m-d H:i:s', strtotime('-' . self::STALE_DAYS . ' days'));
        $items = [];

        foreach ($rows as $row) {
            if (!$isPro && BkRedirectsRule::rowIsProOnly($row)) {
                continue;
            }
            $base = [
                'url' => $row['source'],
                'hits' => (int) $row['hits'],
                'bot_hits' => 0,
                'last_seen' => $row['last_hit'],
                'suggestion' => null,
                'id_notfound' => null,
                'id_rule' => (int) $row['id_rule'],
                'id_shop' => (int) $idShop,
                'id_lang' => (int) $idLang,
                'match_type' => $row['match_type'],
            ];
            $gone = in_array((int) $row['http_code'], BkRedirectsConfig::CODES_GONE, true);

            if ($row['date_add'] < $staleBefore && (!$row['last_hit'] || $row['last_hit'] < $staleBefore)) {
                $items[] = $base + [
                    'key' => 'stale_rule:' . $row['id_rule'],
                    'kind' => 'stale_rule',
                    'severity' => 'low',
                    'reason' => $t->trans('No visits in the last %days% days: you can disable it.', ['%days%' => self::STALE_DAYS], 'Modules.Bkredirects.Admin'),
                ];
            }
            if ($gone) {
                continue;
            }

            $rule = self::ruleFromRow($row);
            $url = BkRedirectsResolver::targetUrl($rule, (int) $idLang, (int) $idShop, '');
            if ($url === null) {
                $recent = $row['last_hit'] && $row['last_hit'] > date('Y-m-d H:i:s', strtotime('-30 days'));
                $items[] = array_merge($base, [
                    'key' => 'disabled_target:' . $row['id_rule'],
                    'kind' => 'disabled_target',
                    'severity' => $recent ? 'high' : 'medium',
                    'reason' => $t->trans('The target of this rule is disabled or deleted, so it does not redirect.', [], 'Modules.Bkredirects.Admin'),
                ]);
                continue;
            }

            $chain = self::follow((int) $row['id_rule'], $url, (int) $idShop, (int) $idLang);
            if ($chain === null) {
                continue;
            }
            if ($chain['loop']) {
                $items[] = $base + [
                    'key' => 'loop:' . $row['id_rule'],
                    'kind' => 'loop',
                    'severity' => 'high',
                    'reason' => $t->trans('This rule ends up redirecting back to itself (%hops% hops).', ['%hops%' => $chain['hops']], 'Modules.Bkredirects.Admin'),
                ];
                continue;
            }
            $last = $chain['last'];
            $items[] = array_merge($base, [
                'key' => 'chain:' . $row['id_rule'],
                'kind' => 'chain',
                'severity' => 'medium',
                'reason' => $t->trans('This rule leads to another rule (%hops% hops): point it straight to the final target.', ['%hops%' => $chain['hops']], 'Modules.Bkredirects.Admin'),
                'chain_end' => ['target_type' => $last->target_type, 'target_id' => (int) $last->target_id, 'target_url' => (string) $last->target_url],
            ]);
        }

        return $items;
    }

    /**
     * Sigue los saltos de una regla dentro de la tienda.
     *
     * @param int    $idRule
     * @param string $url    Destino de la regla
     * @param int    $idShop
     * @param int    $idLang
     *
     * @return array|null null si el destino no es otra regla; si no, hops, loop y last (la última regla)
     */
    private static function follow($idRule, $url, $idShop, $idLang)
    {
        $visited = [$idRule => true];
        $hops = 1;
        $last = null;
        while ($hops <= self::MAX_HOPS) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host && strcasecmp($host, (string) Context::getContext()->shop->domain_ssl) !== 0
                && strcasecmp($host, (string) Context::getContext()->shop->domain) !== 0) {
                break;
            }
            $full = BkRedirectsRule::normalizeSource($url, 'exact', $idShop);
            $path = BkRedirectsRule::stripQuery($full);
            $match = BkRedirectsEngine::match($path, (string) substr($full, strlen($path) + 1), $idShop, $idLang);
            if ($match === null) {
                break;
            }
            $next = $match['rule'];
            if (isset($visited[(int) $next->id])) {
                return ['hops' => $hops, 'loop' => true, 'last' => $next];
            }
            $visited[(int) $next->id] = true;
            $last = $next;
            ++$hops;
            if (in_array((int) $next->http_code, BkRedirectsConfig::CODES_GONE, true)) {
                break;
            }
            $url = BkRedirectsResolver::targetUrl($next, $idLang, $idShop, '');
            if ($url === null) {
                break;
            }
        }

        return $last === null ? null : ['hops' => $hops - 1, 'loop' => false, 'last' => $last];
    }

    /**
     * @param array $row
     *
     * @return BkRedirectsRule
     */
    private static function ruleFromRow(array $row)
    {
        $rule = new BkRedirectsRule();
        $rule->hydrate($row);
        $rule->id = (int) $row['id_rule'];

        return $rule;
    }

    /**
     * La regla se aplica hoy: activa y con respuesta (410/451, o un destino que existe).
     *
     * @param BkRedirectsRule $rule
     * @param int             $idLang
     *
     * @return bool
     */
    private static function isServing(BkRedirectsRule $rule, $idLang)
    {
        if (!$rule->active) {
            return false;
        }
        if (in_array((int) $rule->http_code, BkRedirectsConfig::CODES_GONE, true)) {
            return true;
        }
        $idShop = (int) $rule->id_shop ?: (int) Context::getContext()->shop->id;

        return BkRedirectsResolver::entityUrl($rule->target_type, (int) $rule->target_id, (int) $idLang, $idShop, (string) $rule->target_url) !== null;
    }

    /**
     * Pone el destino elegido a mano o, si no lo hay, el sugerido por el complemento.
     *
     * @param BkRedirectsRule $rule
     * @param array           $override
     * @param array|null      $suggestion
     *
     * @throws PrestaShopException
     */
    private static function setTarget(BkRedirectsRule $rule, array $override, $suggestion)
    {
        if (isset($override['http_code'])) {
            $rule->http_code = (int) $override['http_code'];
        }
        if (isset($override['target_type'])) {
            $rule->target_type = (string) $override['target_type'];
            $rule->target_id = isset($override['target_id']) ? (int) $override['target_id'] : 0;
            $rule->target_url = isset($override['target_url']) ? (string) $override['target_url'] : '';

            return;
        }
        if (in_array((int) $rule->http_code, BkRedirectsConfig::CODES_GONE, true)) {
            return;
        }
        if ($suggestion === null) {
            throw new PrestaShopException(Context::getContext()->getTranslator()->trans('Choose where the rule redirects to.', [], 'Modules.Bkredirects.Admin'));
        }
        $rule->target_type = $suggestion['target_type'];
        $rule->target_id = (int) $suggestion['target_id'];
        $rule->target_url = (string) $suggestion['target_url'];
    }

    /**
     * @param BkRedirectsRule $rule
     *
     * @throws PrestaShopException
     */
    private static function save(BkRedirectsRule $rule)
    {
        $data = ['id_rule' => (int) $rule->id];
        foreach (['id_shop', 'id_lang', 'source', 'match_type', 'query_mode', 'http_code', 'target_type', 'target_id', 'target_url'] as $field) {
            $data[$field] = $rule->{$field};
        }
        $errors = BkRedirectsRule::validate($data);
        if ($errors) {
            throw new PrestaShopException(implode(' ', $errors));
        }
        if (!$rule->save()) {
            throw new PrestaShopException(Context::getContext()->getTranslator()->trans('The rule could not be saved', [], 'Modules.Bkredirects.Admin'));
        }
    }
}
