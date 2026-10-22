<?php
/**
 * Registro de 404: una fila por ruta rota y tienda (`bkredirects_notfound`), con las visitas de personas
 * (`hits`) y de bots (`bot_hits`) por separado. Se guarda la ruta sin la query: la misma página rota con
 * distintos parámetros de campaña es una sola URL que arreglar.
 *
 * Bot = user-agent vacío o que contiene alguno de los trozos de BkRedirectsConfig::BOT_PATTERNS.
 * Una ruta que vuelve a dar 404 después de resolverse se reabre; una ignorada sigue ignorada.
 * Una vez al día, con el primer 404, se purgan las filas que llevan más de LOG_DAYS sin repetirse.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsNotFound
{
    const TABLE = 'bkredirects_notfound';
    const STATUSES = ['open', 'ignored', 'resolved'];
    /** Días que se guarda el agregado diario de estadísticas */
    const STAT_DAYS = 730;

    /**
     * @param array $request   BkRedirectsEngine::request()
     * @param int   $idProduct Producto que respondió 404 (desactivado), o 0
     */
    public static function record(array $request, $idProduct = 0)
    {
        $bot = self::isBot(isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '');
        $human = $bot ? 0 : 1;
        $referer = $bot || empty($_SERVER['HTTP_REFERER']) ? '' : Tools::substr((string) $_SERVER['HTTP_REFERER'], 0, 512);
        $url = Tools::substr($request['path'], 0, 2048);
        $now = date('Y-m-d H:i:s');

        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '`
                (`id_shop`, `id_lang`, `url`, `url_hash`, `hits`, `bot_hits`, `last_referer`, `id_product`, `first_seen`, `last_seen`, `status`)
             VALUES (' . (int) $request['id_shop'] . ', ' . (int) $request['id_lang'] . ', \'' . pSQL($url) . '\', \'' . sha1($url) . '\',
                ' . $human . ', ' . (1 - $human) . ', \'' . pSQL($referer) . '\', ' . (int) $idProduct . ', \'' . $now . '\', \'' . $now . '\', \'open\')
             ON DUPLICATE KEY UPDATE `hits` = `hits` + ' . $human . ', `bot_hits` = `bot_hits` + ' . (1 - $human) . ',
                `last_seen` = VALUES(`last_seen`), `id_lang` = VALUES(`id_lang`), `id_product` = VALUES(`id_product`),
                `last_referer` = IF(VALUES(`last_referer`) <> \'\', VALUES(`last_referer`), `last_referer`),
                `status` = IF(`status` = \'resolved\', \'open\', `status`)'
        );
        BkRedirectsStats::add((int) $request['id_shop'], $bot ? 'notfound_bots' : 'notfound');
        self::purgeOncePerDay();
    }

    /**
     * @param string $userAgent
     *
     * @return bool
     */
    public static function isBot($userAgent)
    {
        if (trim($userAgent) === '') {
            return true;
        }
        foreach (BkRedirectsConfig::botPatterns() as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int    $idNotFound
     * @param string $status     open|ignored|resolved
     * @param int    $idRule     Regla que la resuelve
     *
     * @return bool
     */
    public static function setStatus($idNotFound, $status, $idRule = 0)
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        return Db::getInstance()->update(self::TABLE, ['status' => pSQL($status), 'id_rule' => (int) $idRule], '`id_notfound` = ' . (int) $idNotFound);
    }

    /**
     * Marca como resueltas las 404 abiertas que la regla captura de verdad (BkRedirectsEngine::captures, la
     * misma definición que usa el motor). Las 404 se guardan sin query, así que una regla exacta que compara
     * la query (`/x?ref=1`) no resuelve la 404 de `/x`.
     *
     * @param BkRedirectsRule $rule
     */
    public static function resolveByRule(BkRedirectsRule $rule)
    {
        if (!$rule->id) {
            return;
        }
        $row = [
            'id_lang' => (int) $rule->id_lang,
            'source' => (string) $rule->source,
            'match_type' => (string) $rule->match_type,
            'query_mode' => (string) $rule->query_mode,
        ];
        $where = '`status` = \'open\'' . ((int) $rule->id_shop ? ' AND `id_shop` = ' . (int) $rule->id_shop : '');
        if ($rule->match_type === 'exact') {
            $where .= ' AND `url_hash` = \'' . sha1(BkRedirectsRule::stripQuery($rule->source)) . '\'';
        }
        $ids = [];
        foreach (Db::getInstance()->executeS(
            'SELECT `id_notfound`, `url`, `id_lang` FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE ' . $where
        ) ?: [] as $notFound) {
            if (BkRedirectsEngine::captures($row, $notFound['url'], '', (int) $notFound['id_lang']) !== null) {
                $ids[] = (int) $notFound['id_notfound'];
            }
        }
        if ($ids) {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . self::TABLE . '` SET `status` = \'resolved\', `id_rule` = ' . (int) $rule->id . '
                 WHERE `id_notfound` IN (' . implode(',', $ids) . ')'
            );
        }
    }

    /**
     * Borra lo que lleva más de LOG_DAYS sin dar 404 y el agregado diario de hace más de STAT_DAYS.
     */
    public static function purge()
    {
        $db = Db::getInstance();
        $db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `last_seen` < \'' . date('Y-m-d H:i:s', strtotime('-' . BkRedirectsConfig::logDays() . ' days')) . '\''
        );
        $db->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'bkredirects_stat` WHERE `day` < \'' . date('Y-m-d', strtotime('-' . self::STAT_DAYS . ' days')) . '\''
        );
    }

    private static function purgeOncePerDay()
    {
        $today = date('Y-m-d');
        if (Configuration::getGlobalValue(BkRedirectsConfig::LAST_PURGE) === $today) {
            return;
        }
        Configuration::updateGlobalValue(BkRedirectsConfig::LAST_PURGE, $today);
        self::purge();
    }
}
