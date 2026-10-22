<?php
/**
 * Contrato de un complemento del módulo (BK SEO Redirects PRO es uno): lo que el módulo le pregunta y dónde
 * le deja pintar. El complemento se ofrece por el hook BkRedirectsExtension::HOOK.
 *
 * El módulo solo sabe de reglas exactas. Las de patrón (`match_type` distinto de `exact`) son del
 * complemento de principio a fin: cómo se normalizan, cómo se validan, a qué URLs capturan y cuál gana.
 * Sin complemento, una regla de patrón se guarda tal cual y no se aplica.
 *
 * Pantallas (`$screen`): dashboard, rules, automations, import, help (BkRedirectsAdminController::$screen).
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

interface BkRedirectsExtensionInterface
{
    /**
     * El módulo del complemento: su nombre y versión van al aviso de versión nueva de las pantallas.
     *
     * @return Module
     */
    public function module();

    /* ---------------------------- Motor ---------------------------- */

    /**
     * La regla de patrón que se aplica a una petición sin regla exacta.
     *
     * @return array|null ['rule' => BkRedirectsRule con `target_url` ya sustituido, 'groups' => string[]]
     */
    public function matchPattern(string $path, string $query, int $idShop, int $idLang);

    /**
     * Si una regla de patrón captura una ruta (el idioma ya lo ha comprobado BkRedirectsEngine::captures()).
     *
     * @return string[]|null Lo capturado ($1…), o null
     */
    public function captures(array $row, string $path, string $query);

    /**
     * Forma con la que se guarda el origen de una regla de patrón.
     */
    public function normalizeSource(string $source, string $matchType, int $idShop): string;

    /**
     * Error del origen de una regla de patrón, traducido, o null si vale.
     *
     * @return string|null
     */
    public function validateSource(string $source, string $matchType);

    /* ------------------------ Recomendaciones ------------------------ */

    /**
     * Destino sugerido para una recomendación de BkRedirectsRecommendations.
     *
     * @return array|null {target_type, target_id, target_url, label, score}
     */
    public function suggest(array $item, int $idLang);

    /* -------------------------- Back office -------------------------- */

    public function setMedia(string $screen, BkRedirectsAdminController $controller);

    /**
     * Procesa lo que el complemento envía en una pantalla (también sus peticiones ajax, que contesta y
     * terminan ahí). Se ejecuta antes del postProcess() del núcleo de PrestaShop.
     */
    public function postProcess(string $screen, BkRedirectsAdminController $controller);

    /**
     * Contenido del complemento en una pantalla. En `automations` e `import` ocupa el sitio del panel
     * bloqueado; en las demás, vacío.
     */
    public function render(string $screen, BkRedirectsAdminController $controller): string;
}
