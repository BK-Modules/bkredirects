<?php
/**
 * Automatizaciones y ajustes generales del motor.
 *
 * Dos bloques: qué hace la tienda cuando se borra algo o cambia su URL, que es del complemento PRO (él lo
 * pinta y lo guarda; sin él se ve el panel bloqueado con lo que haría), y el motor con su registro de 404,
 * de todas las ediciones. Cada bloque se guarda por su lado.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/BkRedirectsAdminController.php';

class AdminBkRedirectsAutomationsController extends BkRedirectsAdminController
{
    public $screen = 'automations';

    public function postProcess()
    {
        if (Tools::isSubmit('submitBkrEngine')) {
            $this->processEngine();
        }

        return parent::postProcess();
    }

    private function processEngine()
    {
        $days = (int) Tools::getValue('log_days');
        if ($days < 1 || $days > 3650) {
            $this->errors[] = $this->t('Keep the log between 1 and 3650 days.');

            return;
        }

        Configuration::updateValue(BkRedirectsConfig::ENABLED, (int) Tools::getValue('enabled') ? '1' : '0');
        Configuration::updateValue(BkRedirectsConfig::LOG_404, (int) Tools::getValue('log_404') ? '1' : '0');
        Configuration::updateValue(BkRedirectsConfig::LOG_DAYS, (string) $days);
        Configuration::updateValue(BkRedirectsConfig::BOT_PATTERNS, $this->cleanLines(Tools::getValue('bot_patterns')));
        Configuration::updateValue(BkRedirectsConfig::EXCLUDED_PATHS, $this->cleanLines(Tools::getValue('excluded_paths')));

        $this->confirmations[] = $this->t('Settings saved.');
    }

    /**
     * Una entrada por línea, sin blancos ni repetidas.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function cleanLines($value)
    {
        $lines = array_filter(array_map('trim', preg_split('/\R/', (string) $value) ?: []), 'strlen');

        return implode("\n", array_values(array_unique($lines)));
    }

    public function initContent()
    {
        $extension = BkRedirectsExtension::get();

        $this->context->smarty->assign([
            'bkr_form_url' => $this->screenUrl(),
            'bkr_engine' => [
                'enabled' => BkRedirectsConfig::isOn(BkRedirectsConfig::ENABLED),
                'log_404' => BkRedirectsConfig::isOn(BkRedirectsConfig::LOG_404),
                'log_days' => BkRedirectsConfig::logDays(),
                'bot_patterns' => implode("\n", BkRedirectsConfig::botPatterns()),
                'excluded_paths' => implode("\n", BkRedirectsConfig::excludedPaths()),
            ],
        ]);

        $this->content .= $extension ? $extension->render($this->screen, $this) : $this->fetchTemplate('automations-locked.tpl');
        $this->content .= $this->fetchTemplate('automations.tpl');

        parent::initContent();
    }
}
