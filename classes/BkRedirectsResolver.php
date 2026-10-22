<?php
/**
 * Del destino de una regla a la URL a la que se redirige, calculada en el momento con el `Link` de
 * PrestaShop en el idioma y la tienda de la petición: una regla a una entidad sigue valiendo aunque la
 * URL de la entidad cambie (y el `Link` lleva los overrides de la tienda, p. ej. el de ets_seo).
 *
 * Un destino desactivado, borrado o fuera de la tienda no tiene URL (null): el motor entonces no
 * redirige y la regla aparece en las recomendaciones como `disabled_target`.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsResolver
{
    /** @var Link|null */
    private static $link = null;

    /**
     * @param BkRedirectsRule $rule         Con `target_url` ya sustituido ($1…) si viene de un patrón
     * @param int             $idLang
     * @param int             $idShop
     * @param string          $requestQuery Query de la petición, sin `?`; se añade si la regla la pasa
     *
     * @return string|null URL absoluta, o null si el destino no existe o la regla no redirige
     */
    public static function targetUrl(BkRedirectsRule $rule, int $idLang, int $idShop, string $requestQuery): ?string
    {
        $url = self::entityUrl((string) $rule->target_type, (int) $rule->target_id, $idLang, $idShop, (string) $rule->target_url);
        if ($url === null || $url === '') {
            return null;
        }
        if ($rule->query_mode === 'pass' && $requestQuery !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . $requestQuery;
        }

        return $url;
    }

    /**
     * URL de un destino. Sirve igual para reglas que para sugerencias y recomendaciones.
     *
     * @param string $type
     * @param int    $id
     * @param int    $idLang
     * @param int    $idShop
     * @param string $targetUrl Solo para `url`: absoluta o relativa a la tienda
     *
     * @return string|null
     */
    public static function entityUrl($type, $id, $idLang, $idShop, $targetUrl = '')
    {
        $link = self::link();
        $id = (int) $id;
        switch ($type) {
            case 'url':
                if ($targetUrl === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $targetUrl)) {
                    return $targetUrl;
                }

                return rtrim($link->getBaseLink((int) $idShop), '/') . '/' . ltrim($targetUrl, '/');
            case 'home':
                return $link->getPageLink('index', true, (int) $idLang, null, false, (int) $idShop);
            case 'manufacturer':
            case 'supplier':
                if ($id === 0) {
                    return Configuration::get($type === 'manufacturer' ? 'PS_DISPLAY_MANUFACTURERS' : 'PS_DISPLAY_SUPPLIERS', null, null, (int) $idShop)
                        ? $link->getPageLink($type, true, (int) $idLang, null, false, (int) $idShop)
                        : null;
                }
                // no break
            case 'product':
            case 'category':
            case 'cms':
            case 'cms_category':
                if (!self::targetExists($type, $id, (int) $idShop)) {
                    return null;
                }

                return self::linkOf($type, $id, (int) $idLang, (int) $idShop);
        }

        return null;
    }

    /**
     * El destino existe, está activo y se ve en la tienda. La categoría de inicio cuenta como la portada.
     *
     * @param string $type
     * @param int    $id
     * @param int    $idShop 0 = en cualquier tienda
     *
     * @return bool
     */
    public static function targetExists($type, $id, $idShop = 0)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return in_array($type, ['home', 'url'], true);
        }
        $p = _DB_PREFIX_;
        $shop = (int) $idShop ? ' AND s.`id_shop` = ' . (int) $idShop : '';
        switch ($type) {
            case 'product':
                $sql = "SELECT 1 FROM `{$p}product_shop` s WHERE s.`id_product` = $id AND s.`active` = 1 AND s.`visibility` <> 'none'$shop";
                break;
            case 'category':
                if ($id === (int) Configuration::get('PS_ROOT_CATEGORY')) {
                    return false;
                }
                $sql = "SELECT 1 FROM `{$p}category` c INNER JOIN `{$p}category_shop` s ON s.`id_category` = c.`id_category`
                        WHERE c.`id_category` = $id AND c.`active` = 1 AND c.`is_root_category` = 0$shop";
                break;
            case 'cms':
                $sql = "SELECT 1 FROM `{$p}cms` c INNER JOIN `{$p}cms_shop` s ON s.`id_cms` = c.`id_cms` WHERE c.`id_cms` = $id AND c.`active` = 1$shop";
                break;
            case 'cms_category':
                $sql = "SELECT 1 FROM `{$p}cms_category` c INNER JOIN `{$p}cms_category_shop` s ON s.`id_cms_category` = c.`id_cms_category`
                        WHERE c.`id_cms_category` = $id AND c.`active` = 1$shop";
                break;
            case 'manufacturer':
            case 'supplier':
                $sql = "SELECT 1 FROM `{$p}{$type}` c INNER JOIN `{$p}{$type}_shop` s ON s.`id_{$type}` = c.`id_{$type}` WHERE c.`id_{$type}` = $id AND c.`active` = 1$shop";
                break;
            default:
                return false;
        }

        return (bool) Db::getInstance()->getValue($sql);
    }

    /**
     * Nombre legible de un destino, para la UI y las sugerencias.
     *
     * @param string $type
     * @param int    $id
     * @param int    $idLang
     * @param string $targetUrl
     *
     * @return string
     */
    public static function label($type, $id, $idLang, $targetUrl = '')
    {
        $id = (int) $id;
        $idLang = (int) $idLang;
        $p = _DB_PREFIX_;
        switch ($type) {
            case 'url':
                return (string) $targetUrl;
            case 'home':
                return Context::getContext()->getTranslator()->trans('Home page', [], 'Modules.Bkredirects.Admin');
            case 'product':
                return (string) Product::getProductName($id, null, $idLang);
            case 'category':
                return (string) Db::getInstance()->getValue("SELECT `name` FROM `{$p}category_lang` WHERE `id_category` = $id AND `id_lang` = $idLang");
            case 'cms':
                return (string) Db::getInstance()->getValue("SELECT `meta_title` FROM `{$p}cms_lang` WHERE `id_cms` = $id AND `id_lang` = $idLang");
            case 'cms_category':
                return (string) Db::getInstance()->getValue("SELECT `name` FROM `{$p}cms_category_lang` WHERE `id_cms_category` = $id AND `id_lang` = $idLang");
            case 'manufacturer':
            case 'supplier':
                if ($id === 0) {
                    return Context::getContext()->getTranslator()->trans($type === 'manufacturer' ? 'Brands page' : 'Suppliers page', [], 'Modules.Bkredirects.Admin');
                }

                return (string) Db::getInstance()->getValue("SELECT `name` FROM `{$p}{$type}` WHERE `id_{$type}` = $id");
        }

        return '';
    }

    /**
     * URL de una entidad aunque esté desactivada: las automatizaciones la necesitan para guardar la URL
     * que conocen los buscadores. Vacía solo si la entidad no existe: una sin `link_rewrite` en el idioma
     * (productos importados por SQL, p. ej.) tiene igualmente la URL que le da `Link` (`/12-.html`), que el
     * despachador sirve por el id.
     *
     * @param string $type
     * @param int    $id
     * @param int    $idLang
     * @param int    $idShop
     *
     * @return string
     */
    public static function linkOf($type, $id, $idLang, $idShop)
    {
        $link = self::link();
        switch ($type) {
            case 'product':
                $product = new Product((int) $id, false, (int) $idLang, (int) $idShop);

                return Validate::isLoadedObject($product) ? $link->getProductLink($product, null, null, null, (int) $idLang, (int) $idShop) : '';
            case 'category':
                $category = new Category((int) $id, (int) $idLang, (int) $idShop);
                if (!Validate::isLoadedObject($category)) {
                    return '';
                }
                if ((int) $id === (int) Configuration::get('PS_HOME_CATEGORY')) {
                    return $link->getPageLink('index', true, (int) $idLang, null, false, (int) $idShop);
                }

                return $link->getCategoryLink($category, null, (int) $idLang, null, (int) $idShop);
            case 'cms':
                $cms = new CMS((int) $id, (int) $idLang, (int) $idShop);

                return Validate::isLoadedObject($cms) ? $link->getCMSLink($cms, null, true, (int) $idLang, (int) $idShop) : '';
            case 'cms_category':
                $cmsCategory = new CMSCategory((int) $id, (int) $idLang, (int) $idShop);

                return Validate::isLoadedObject($cmsCategory) ? $link->getCMSCategoryLink($cmsCategory, null, (int) $idLang, (int) $idShop) : '';
            case 'manufacturer':
                $manufacturer = new Manufacturer((int) $id, (int) $idLang);

                return Validate::isLoadedObject($manufacturer) ? $link->getManufacturerLink($manufacturer, null, (int) $idLang, (int) $idShop) : '';
            case 'supplier':
                $supplier = new Supplier((int) $id, (int) $idLang);

                return Validate::isLoadedObject($supplier) ? $link->getSupplierLink($supplier, null, (int) $idLang, (int) $idShop) : '';
        }

        return '';
    }

    /**
     * @return Link
     */
    private static function link()
    {
        if (self::$link === null) {
            self::$link = new Link();
        }

        return self::$link;
    }
}
