<?php
/**
 * El complemento instalado, si lo hay. Un módulo se ofrece como complemento registrándose en el hook
 * HOOK y devolviendo desde él un BkRedirectsExtensionInterface.
 *
 * Edición: con un complemento instalado y activo la tienda es PRO; sin él, Gratis. No hay más: ninguna
 * licencia se comprueba en ejecución.
 *
 * INVARIANTE: lo que es PRO (BkRedirectsRule::rowIsProOnly) se aplica, se crea y se edita solo con
 * complemento. Sin él las reglas PRO siguen guardadas, se ven como «PRO» y no se aplican; al volver a
 * instalarlo se aplican otra vez tal cual.
 *
 * Preguntar no cuesta consultas: PrestaShop ya tiene en memoria la lista de módulos por hook de la
 * petición. La respuesta se guarda para el resto de la petición.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsExtension
{
    const HOOK = 'actionBkRedirectsExtension';

    /** @var BkRedirectsExtensionInterface|false|null false = aún no se ha preguntado en esta petición */
    private static $extension = false;

    /**
     * @return BkRedirectsExtensionInterface|null
     */
    public static function get()
    {
        if (self::$extension === false) {
            self::$extension = null;
            // Sin excepciones por controlador ni permisos del empleado: la edición es de la tienda.
            foreach (Hook::exec(self::HOOK, [], null, true, false) ?: [] as $offer) {
                if ($offer instanceof BkRedirectsExtensionInterface) {
                    self::$extension = $offer;
                    break;
                }
            }
        }

        return self::$extension;
    }

    /**
     * Módulos que se han registrado como complemento, estén activos o no.
     *
     * @return Module[]
     */
    public static function installedModules()
    {
        $names = Db::getInstance()->executeS(
            'SELECT DISTINCT m.`name` FROM `' . _DB_PREFIX_ . 'hook_module` hm
             INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`
             INNER JOIN `' . _DB_PREFIX_ . 'module` m ON m.`id_module` = hm.`id_module`
             WHERE h.`name` = \'' . pSQL(self::HOOK) . '\''
        ) ?: [];
        $modules = [];
        foreach ($names as $row) {
            $module = Module::getInstanceByName($row['name']);
            if ($module) {
                $modules[] = $module;
            }
        }

        return $modules;
    }

    /**
     * @return bool
     */
    public static function isPro()
    {
        return self::get() !== null;
    }
}
