<?php
/**
 * Traza del módulo en fichero propio. Es la única vía de logging: nada de error_log ni de
 * PrestaShopLogger, que mezclarían estas líneas con las del núcleo.
 *
 * Un complemento la hereda cambiando MODULE y FILE: escribe en su propia carpeta `log/`.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsLogger
{
    /** Módulo en cuya carpeta `log/` se escribe */
    const MODULE = 'bkredirects';
    const FILE = 'bkredirects.log';
    /** Tamaño a partir del cual el fichero se rota */
    const MAX_BYTES = 1048576;

    public static function error($message)
    {
        static::write('error', $message);
    }

    public static function warning($message)
    {
        static::write('warning', $message);
    }

    public static function confirmation($message)
    {
        static::write('confirmation', $message);
    }

    /**
     * Solo escribe con la traza encendida: en marcha normal el fichero no crece.
     */
    public static function debug($message)
    {
        if (BkRedirectsConfig::isOn(BkRedirectsConfig::DEBUG)) {
            static::write('debug', $message);
        }
    }

    protected static function write($level, $message)
    {
        $dir = _PS_MODULE_DIR_ . static::MODULE . '/log/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $file = $dir . static::FILE;
        if (is_file($file) && filesize($file) > static::MAX_BYTES) {
            @rename($file, $file . '.1');
        }

        $line = sprintf('[%s] %s: %s%s', date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
        @file_put_contents($file, $line, FILE_APPEND);
    }
}
