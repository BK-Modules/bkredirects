<?php
/**
 * Panel: cómo van las URLs de la tienda y qué arreglar primero.
 *
 * Arriba, los totales del periodo y el gráfico diario de redirecciones servidas frente a 404 de
 * visitantes y de bots; debajo, las recomendaciones ordenadas por lo que se gana arreglándolas, cada
 * una con su acción en un clic. Lo que se busca es que ninguna URL que tenía visitas o
 * posicionamiento se pierda en un 404.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/BkRedirectsAdminController.php';

class AdminBkRedirectsDashboardController extends BkRedirectsAdminController
{
    public $screen = 'dashboard';

    /** Periodos que se pueden elegir en el panel, en días */
    const PERIODS = [7, 30, 90];

    /** Geometría del gráfico (unidades del viewBox del SVG) */
    const CHART_WIDTH = 960;
    const CHART_HEIGHT = 260;
    const CHART_LEFT = 48;
    const CHART_RIGHT = 12;
    const CHART_TOP = 14;
    const CHART_BOTTOM = 30;

    public function postProcess()
    {
        if (Tools::isSubmit('bkrDismiss')) {
            BkRedirectsRecommendations::dismiss((string) Tools::getValue('bkrDismiss'));
            $this->redirectWithConfirmation(4);
        }

        if (Tools::isSubmit('bkrApply')) {
            $this->processApply([(string) Tools::getValue('bkrApply')]);
        }

        if (Tools::isSubmit('submitBkrApplyBulk')) {
            $this->processApply((array) Tools::getValue('bkr_keys', []));
        }

        return parent::postProcess();
    }

    /**
     * Aplica recomendaciones. El destino sugerido y el lote son PRO: en Gratis no hay sugerencia
     * que aplicar (la da el complemento) y cada regla se crea desde el formulario con el destino elegido a mano.
     *
     * @param array $keys
     */
    private function processApply(array $keys)
    {
        $keys = array_filter(array_map('strval', $keys));
        // Desactivar una regla sin visitas es de todas las ediciones; lo demás aplica una sugerencia.
        $needsPro = count($keys) > 1 || strpos((string) reset($keys), 'stale_rule:') !== 0;
        if ($needsPro && !$this->isPro()) {
            $this->errors[] = $this->t('Applying suggested destinations is a PRO feature.');

            return;
        }

        $created = 0;
        foreach ($keys as $key) {
            try {
                BkRedirectsRecommendations::apply($key);
                ++$created;
            } catch (Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }

        if ($created && !$this->errors) {
            Tools::redirectAdmin(self::$currentIndex . '&token=' . $this->token . '&bkrApplied=' . $created . '&days=' . $this->days());
        }
        if ($created) {
            $this->confirmations[] = $this->t('%count% recommendation(s) fixed.', ['%count%' => $created]);
        }
    }

    public function initContent()
    {
        if ((int) Tools::getValue('bkrApplied')) {
            $this->confirmations[] = $this->t('%count% recommendation(s) fixed.', ['%count%' => (int) Tools::getValue('bkrApplied')]);
        }

        $days = $this->days();
        $summary = BkRedirectsStats::summary($this->shopId(), $days);
        $recommendations = BkRedirectsRecommendations::all($this->shopId(), $this->langId());

        $this->context->smarty->assign([
            'bkr_days' => $days,
            'bkr_periods' => self::PERIODS,
            'bkr_period_url' => self::$currentIndex . '&token=' . $this->token . '&days=',
            'bkr_totals' => $summary['totals'],
            'bkr_chart' => $this->chart($summary['series']),
            'bkr_recommendations' => $this->decorate($recommendations),
            'bkr_kinds' => $this->kinds(),
            'bkr_top_notfound' => BkRedirectsStats::topNotFound($this->shopId(), 10),
            'bkr_top_rules' => $this->decorateRules(BkRedirectsStats::topRules($this->shopId(), 10)),
            'bkr_action_url' => self::$currentIndex . '&token=' . $this->token . '&days=' . $days,
            'bkr_new_rule_url' => $this->context->link->getAdminLink('AdminBkRedirectsRules') . '&addbkredirects_rule',
        ]);

        $this->content .= $this->fetchTemplate('dashboard.tpl');

        parent::initContent();
    }

    /**
     * @return int
     */
    private function days()
    {
        $days = (int) Tools::getValue('days', 30);

        return in_array($days, self::PERIODS, true) ? $days : 30;
    }

    /**
     * @param int $count
     */
    private function redirectWithConfirmation($count)
    {
        Tools::redirectAdmin(self::$currentIndex . '&token=' . $this->token . '&conf=' . (int) $count . '&days=' . $this->days());
    }

    /**
     * Añade a cada recomendación sus enlaces de acción (llegan ya ordenadas por gravedad y visitas).
     *
     * @param array $recommendations
     *
     * @return array
     */
    private function decorate(array $recommendations)
    {
        $rulesUrl = $this->context->link->getAdminLink('AdminBkRedirectsRules');

        foreach ($recommendations as $i => $rec) {
            $rec['create_url'] = $rulesUrl . '&addbkredirects_rule&bkr_rec=' . urlencode($rec['key'])
                . '&source=' . urlencode($rec['url']);
            $rec['edit_url'] = !empty($rec['id_rule'])
                ? $rulesUrl . '&updatebkredirects_rule&id_rule=' . (int) $rec['id_rule']
                : '';
            $recommendations[$i] = $rec;
        }

        return $recommendations;
    }

    /**
     * @param array $rules
     *
     * @return array
     */
    private function decorateRules(array $rules)
    {
        $rulesUrl = $this->context->link->getAdminLink('AdminBkRedirectsRules');
        foreach ($rules as $i => $rule) {
            $rules[$i]['target_label'] = $this->targetLabel(
                $rule['target_type'],
                (int) $rule['target_id'],
                (string) $rule['target_url']
            );
            $rules[$i]['edit_url'] = $rulesUrl . '&updatebkredirects_rule&id_rule=' . (int) $rule['id_rule'];
        }

        return $rules;
    }

    /**
     * Qué es cada tipo de recomendación, dicho para el comerciante.
     *
     * @return array
     */
    private function kinds()
    {
        return [
            'notfound' => $this->t('Page not found that still gets visits'),
            'inactive_product_404' => $this->t('Disabled product that still gets visits'),
            'disabled_target' => $this->t('Rule pointing to a page that is gone'),
            'chain' => $this->t('Redirect chain'),
            'loop' => $this->t('Redirect loop'),
            'stale_rule' => $this->t('Rule without visits for 180 days'),
        ];
    }

    /**
     * Geometría del gráfico diario. Se calcula aquí para que la plantilla solo pinte: tres líneas
     * sobre el mismo eje (las tres son visitas por día) y una columna invisible por día que lleva
     * los valores para el detalle al pasar el ratón.
     *
     * @param array $series
     *
     * @return array
     */
    private function chart(array $series)
    {
        $keys = ['redirects', 'notfound', 'notfound_bots'];
        $max = 0;
        foreach ($series as $day) {
            foreach ($keys as $key) {
                $max = max($max, (int) $day[$key]);
            }
        }
        $top = $this->niceCeiling($max);

        $plotW = self::CHART_WIDTH - self::CHART_LEFT - self::CHART_RIGHT;
        $plotH = self::CHART_HEIGHT - self::CHART_TOP - self::CHART_BOTTOM;
        $count = count($series);
        $step = $count > 1 ? $plotW / ($count - 1) : 0;
        $baseline = self::CHART_TOP + $plotH;

        $lines = array_fill_keys($keys, []);
        $columns = [];
        $labels = [];
        $labelEvery = max(1, (int) ceil($count / 7));
        $dateFormat = $this->context->language->date_format_lite;

        foreach (array_values($series) as $i => $day) {
            $x = $count > 1 ? self::CHART_LEFT + $i * $step : self::CHART_LEFT + $plotW / 2;
            foreach ($keys as $key) {
                $y = $baseline - ((int) $day[$key] / $top) * $plotH;
                $lines[$key][] = round($x, 1) . ',' . round($y, 1);
            }
            $date = date($dateFormat, strtotime($day['day']));
            $columns[] = [
                'x' => round($x - max($step, 8) / 2, 1),
                'w' => round(max($step, 8), 1),
                'cx' => round($x, 1),
                'date' => $date,
                'redirects' => (int) $day['redirects'],
                'notfound' => (int) $day['notfound'],
                'notfound_bots' => (int) $day['notfound_bots'],
            ];
            if (($count - 1 - $i) % $labelEvery === 0) {
                $labels[] = [
                    'pct' => round($x / self::CHART_WIDTH * 100, 2),
                    'text' => date($this->shortDateFormat(), strtotime($day['day'])),
                ];
            }
        }

        $grid = [];
        for ($i = 0; $i <= 4; ++$i) {
            $value = $top * $i / 4;
            $y = $baseline - ($value / $top) * $plotH;
            $grid[] = [
                'y' => round($y, 1),
                'pct' => round($y / self::CHART_HEIGHT * 100, 2),
                'label' => (int) $value,
            ];
        }

        $polylines = [];
        foreach ($lines as $key => $points) {
            $polylines[$key] = implode(' ', $points);
        }

        return [
            'width' => self::CHART_WIDTH,
            'height' => self::CHART_HEIGHT,
            'left' => self::CHART_LEFT,
            'right' => self::CHART_WIDTH - self::CHART_RIGHT,
            'top' => self::CHART_TOP,
            'baseline' => $baseline,
            'lines' => $polylines,
            'area' => $count ? self::CHART_LEFT . ',' . $baseline . ' ' . $polylines['redirects'] . ' ' . round(self::CHART_LEFT + ($count - 1) * $step, 1) . ',' . $baseline : '',
            'columns' => $columns,
            'rows' => array_reverse($columns),
            'labels' => $labels,
            'grid' => $grid,
            'empty' => $max === 0,
        ];
    }

    /**
     * Día y mes sin año, en el orden del idioma del empleado.
     *
     * @return string
     */
    private function shortDateFormat()
    {
        $format = (string) $this->context->language->date_format_lite;

        return strpos($format, 'm') < strpos($format, 'd') ? 'm/d' : 'd/m';
    }

    /**
     * Techo del eje: cuatro tramos de 1, 2 o 5 por la potencia de diez que toque, para que cada
     * línea de la rejilla caiga en un número entero y redondo.
     *
     * @param int $max
     *
     * @return int
     */
    private function niceCeiling($max)
    {
        $raw = max(1, $max / 4);
        $power = pow(10, floor(log10($raw)));
        foreach ([1, 2, 5, 10] as $factor) {
            if ($factor * $power >= $raw) {
                return (int) ($factor * $power * 4);
            }
        }

        return (int) (40 * $power);
    }
}
