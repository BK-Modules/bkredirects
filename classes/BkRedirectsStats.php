<?php
/**
 * Estadísticas del panel: un agregado diario por tienda (`bkredirects_stat`) que escriben el motor
 * (redirecciones) y el registro de 404 (personas y bots por separado), más los contadores de cada regla
 * y de cada URL rota.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsStats
{
    const COLUMNS = ['redirects', 'notfound', 'notfound_bots'];

    /**
     * Suma uno al contador del día. El día es el de la zona horaria de la tienda (PHP), no el de MySQL.
     *
     * @param int    $idShop
     * @param string $column redirects|notfound|notfound_bots
     */
    public static function add($idShop, $column)
    {
        if (!in_array($column, self::COLUMNS, true)) {
            return;
        }
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'bkredirects_stat` (`day`, `id_shop`, `' . $column . '`)
             VALUES (\'' . pSQL(date('Y-m-d')) . '\', ' . (int) $idShop . ', 1)
             ON DUPLICATE KEY UPDATE `' . $column . '` = `' . $column . '` + 1'
        );
    }

    /**
     * @param int $idShop
     * @param int $days   Días hacia atrás, hoy incluido
     *
     * @return array totals (redirects, notfound, notfound_bots, rules_active, rules_inactive, open_404) y
     *               series (un elemento por día, también los días sin datos)
     */
    public static function summary(int $idShop, int $days): array
    {
        $days = max(1, $days);
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $p = _DB_PREFIX_;
        $db = Db::getInstance();

        $byDay = [];
        foreach ($db->executeS(
            "SELECT `day`, `redirects`, `notfound`, `notfound_bots` FROM `{$p}bkredirects_stat`
             WHERE `id_shop` = " . (int) $idShop . " AND `day` >= '" . pSQL($from) . "'"
        ) ?: [] as $row) {
            $byDay[$row['day']] = $row;
        }

        $totals = ['redirects' => 0, 'notfound' => 0, 'notfound_bots' => 0];
        $series = [];
        for ($i = 0; $i < $days; ++$i) {
            $day = date('Y-m-d', strtotime($from . ' +' . $i . ' days'));
            $point = ['day' => $day];
            foreach (self::COLUMNS as $column) {
                $point[$column] = isset($byDay[$day]) ? (int) $byDay[$day][$column] : 0;
                $totals[$column] += $point[$column];
            }
            $series[] = $point;
        }

        $rules = $db->getRow(
            "SELECT SUM(`active` = 1) AS active, SUM(`active` = 0) AS inactive FROM `{$p}bkredirects_rule`
             WHERE `id_shop` IN (0, " . (int) $idShop . ')'
        );
        $totals['rules_active'] = (int) $rules['active'];
        $totals['rules_inactive'] = (int) $rules['inactive'];
        $totals['open_404'] = (int) $db->getValue(
            "SELECT COUNT(*) FROM `{$p}bkredirects_notfound` WHERE `id_shop` = " . (int) $idShop . " AND `status` = 'open'"
        );

        return ['totals' => $totals, 'series' => $series];
    }

    /**
     * URLs rotas abiertas con más visitas de personas.
     *
     * @param int $idShop
     * @param int $limit
     *
     * @return array Filas de `bkredirects_notfound`
     */
    public static function topNotFound(int $idShop, int $limit): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'bkredirects_notfound`
             WHERE `id_shop` = ' . (int) $idShop . ' AND `status` = \'open\'
             ORDER BY `hits` DESC, `bot_hits` DESC, `last_seen` DESC LIMIT ' . max(1, $limit)
        ) ?: [];
    }

    /**
     * Reglas con más redirecciones.
     *
     * @param int $idShop
     * @param int $limit
     *
     * @return array Filas de `bkredirects_rule`
     */
    public static function topRules(int $idShop, int $limit): array
    {
        return Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . BkRedirectsRule::TABLE . '`
             WHERE `id_shop` IN (0, ' . (int) $idShop . ') AND `hits` > 0
             ORDER BY `hits` DESC, `last_hit` DESC LIMIT ' . max(1, $limit)
        ) ?: [];
    }
}
