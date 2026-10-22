<?php
/**
 * Única fuente de configuración del módulo: toda clave nueva entra aquí, no en el controlador.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsConfig
{
    /** Interruptor maestro del motor de redirección */
    const ENABLED = 'BK_REDIRECTS_ENABLED';
    /** Código de las reglas que crean las recomendaciones (y las automatizaciones del complemento) */
    const DEFAULT_CODE = 'BK_REDIRECTS_DEFAULT_CODE';
    /** Registrar las URL que responden 404 */
    const LOG_404 = 'BK_REDIRECTS_LOG_404';
    /** Días que se guarda una URL del registro de 404 sin volver a dar 404 */
    const LOG_DAYS = 'BK_REDIRECTS_LOG_DAYS';
    /** Trozos de user-agent que marcan un bot, uno por línea, sin distinguir mayúsculas */
    const BOT_PATTERNS = 'BK_REDIRECTS_BOT_PATTERNS';
    /** Rutas que el módulo no toca (ni redirige ni registra), una por línea, como prefijo */
    const EXCLUDED_PATHS = 'BK_REDIRECTS_EXCLUDED_PATHS';
    /** Claves de recomendaciones descartadas que no viven en una tabla (JSON) */
    const DISMISSED = 'BK_REDIRECTS_DISMISSED';
    /** Día de la última purga del registro de 404 */
    const LAST_PURGE = 'BK_REDIRECTS_LAST_PURGE';
    /** Traza de depuración */
    const DEBUG = 'BK_REDIRECTS_DEBUG';

    /** Códigos de la edición gratuita y los que añade PRO */
    const CODES_FREE = [301, 302, 307, 410];
    const CODES_PRO = [303, 308, 451];
    /** Códigos que no redirigen: responden con la página 404 del tema y su propio código */
    const CODES_GONE = [410, 451];

    /** Extensiones de ficheros estáticos: ni se registran como 404 ni se redirigen */
    const STATIC_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'js', 'mjs', 'css', 'map', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp4', 'webm', 'mp3'];

    /**
     * @return array Claves con su valor por defecto
     */
    public static function getDefaults()
    {
        return [
            self::ENABLED => '1',
            self::DEFAULT_CODE => '301',
            self::LOG_404 => '1',
            self::LOG_DAYS => '90',
            self::BOT_PATTERNS => implode("\n", [
                'bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'mediapartners', 'bingpreview',
                'curl', 'wget', 'python', 'go-http-client', 'java/', 'okhttp', 'httpclient', 'axios',
                'headless', 'lighthouse', 'scrapy', 'semrush', 'ahrefs', 'mj12', 'petalbot', 'bytespider',
                'gptbot', 'claude', 'perplexity', 'yandex', 'baidu', 'seznam', 'sogou', 'dataforseo',
            ]),
            self::EXCLUDED_PATHS => '',
            self::DEBUG => '0',
        ];
    }

    /**
     * Da de alta, como valor global, las claves que falten: el global es el que heredan todas las
     * tiendas, y una actualización nunca pisa un ajuste del comerciante.
     */
    public static function installDefaults()
    {
        foreach (self::getDefaults() as $key => $value) {
            if (!Configuration::hasKey($key)) {
                Configuration::updateGlobalValue($key, $value);
            }
        }
    }

    public static function uninstallKeys()
    {
        $keys = array_merge(array_keys(self::getDefaults()), [self::DISMISSED, self::LAST_PURGE]);
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public static function isOn($key)
    {
        return (bool) Configuration::get($key);
    }

    /**
     * Código de las reglas que se crean solas. Se valida al leerlo: una redirección permanente o temporal de
     * la edición de la tienda (308 es PRO: sin el complemento vale 301).
     *
     * @return int
     */
    public static function defaultCode()
    {
        $code = (int) Configuration::get(self::DEFAULT_CODE);
        $codes = BkRedirectsExtension::isPro() ? [301, 302, 307, 308] : [301, 302, 307];

        return in_array($code, $codes, true) ? $code : 301;
    }

    /**
     * @return int
     */
    public static function logDays()
    {
        return max(1, (int) Configuration::get(self::LOG_DAYS) ?: 90);
    }

    /**
     * @return string[]
     */
    public static function botPatterns()
    {
        return self::lines(self::BOT_PATTERNS);
    }

    /**
     * @return string[] Prefijos de ruta relativos a la tienda, con barra inicial
     */
    public static function excludedPaths()
    {
        return self::lines(self::EXCLUDED_PATHS);
    }

    /**
     * @return string[]
     */
    public static function dismissed()
    {
        $list = json_decode((string) Configuration::getGlobalValue(self::DISMISSED), true);

        return is_array($list) ? $list : [];
    }

    /**
     * @param string[] $keys
     */
    public static function setDismissed(array $keys)
    {
        Configuration::updateGlobalValue(self::DISMISSED, json_encode(array_values(array_unique($keys))));
    }

    /**
     * @param string $key
     *
     * @return string[] Líneas no vacías del valor
     */
    private static function lines($key)
    {
        $lines = preg_split('/\R/', (string) Configuration::get($key)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), 'strlen'));
    }
}
