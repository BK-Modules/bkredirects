<?php
/**
 * Pestañas del back office: un padre «SEO Redirects» dentro de Parámetros de la tienda (junto a Tráfico
 * y SEO, el mismo sitio de 1.7.5 a 9.x) y sus cinco pantallas. El primer hijo es el Panel, que es donde
 * lleva el menú al pulsar el padre.
 *
 * INVARIANTE: el padre cuelga de `ShopParameters`, la entrada de primer nivel «Parámetros de la tienda». Así es
 * una entrada del menú lateral como Tráfico y SEO y sus hijos salen como pestañas arriba de cada pantalla. Bajo
 * `AdminParentPreferences` (que es «Configuración», no la sección) quedaría a un nivel que el menú no pinta.
 *
 * El nombre de la clase lleva el del módulo porque es global: dos módulos con una clase `TabsInstaller`
 * definirían la misma y cruzarían la columna `module` de sus pestañas.
 *
 * @author BK Modules
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class BkRedirectsTabsInstaller
{
    const PARENT = 'AdminBkRedirects';
    const DASHBOARD = 'AdminBkRedirectsDashboard';
    const RULES = 'AdminBkRedirectsRules';
    const AUTOMATIONS = 'AdminBkRedirectsAutomations';
    const IMPORT = 'AdminBkRedirectsImport';
    const HELP = 'AdminBkRedirectsHelp';

    /**
     * @return array En orden: el padre primero, después los hijos en el orden del menú
     */
    private static function structure()
    {
        return [
            [
                'class_name' => self::PARENT,
                'parent' => 'ShopParameters',
                'wording' => 'SEO Redirects',
                'name' => ['en' => 'SEO Redirects', 'es' => 'Redirecciones SEO', 'fr' => 'Redirections SEO', 'it' => 'Reindirizzamenti SEO', 'de' => 'SEO-Weiterleitungen', 'pl' => 'Przekierowania SEO', 'cs' => 'SEO přesměrování', 'pt' => 'Redirecionamentos SEO'],
            ],
            [
                'class_name' => self::DASHBOARD,
                'parent' => self::PARENT,
                'wording' => 'Dashboard',
                'name' => ['en' => 'Dashboard', 'es' => 'Panel', 'fr' => 'Tableau de bord', 'it' => 'Pannello', 'de' => 'Übersicht', 'pl' => 'Panel', 'cs' => 'Přehled', 'pt' => 'Painel'],
            ],
            [
                'class_name' => self::RULES,
                'parent' => self::PARENT,
                'wording' => 'Rules',
                'name' => ['en' => 'Rules', 'es' => 'Reglas', 'fr' => 'Règles', 'it' => 'Regole', 'de' => 'Regeln', 'pl' => 'Reguły', 'cs' => 'Pravidla', 'pt' => 'Regras'],
            ],
            [
                'class_name' => self::AUTOMATIONS,
                'parent' => self::PARENT,
                'wording' => 'Automations',
                'name' => ['en' => 'Automations', 'es' => 'Automatizaciones', 'fr' => 'Automatisations', 'it' => 'Automazioni', 'de' => 'Automatisierungen', 'pl' => 'Automatyzacje', 'cs' => 'Automatizace', 'pt' => 'Automatizações'],
            ],
            [
                'class_name' => self::IMPORT,
                'parent' => self::PARENT,
                'wording' => 'Import and export',
                'name' => ['en' => 'Import and export', 'es' => 'Importar y exportar', 'fr' => 'Importer et exporter', 'it' => 'Importa ed esporta', 'de' => 'Import und Export', 'pl' => 'Import i eksport', 'cs' => 'Import a export', 'pt' => 'Importar e exportar'],
            ],
            [
                'class_name' => self::HELP,
                'parent' => self::PARENT,
                'wording' => 'License and help',
                'name' => ['en' => 'License and help', 'es' => 'Licencia y ayuda', 'fr' => 'Licence et aide', 'it' => 'Licenza e aiuto', 'de' => 'Lizenz und Hilfe', 'pl' => 'Licencja i pomoc', 'cs' => 'Licence a nápověda', 'pt' => 'Licença e ajuda'],
            ],
        ];
    }

    /**
     * Idempotente: una pestaña que ya existe (de una instalación anterior o ligada a otro módulo por
     * una base de datos importada) se reutiliza y se le corrige el módulo.
     *
     * @param string $moduleName
     *
     * @return bool
     */
    public static function install($moduleName)
    {
        $languages = Language::getLanguages(false);

        foreach (self::structure() as $data) {
            $existingId = (int) Tab::getIdFromClassName($data['class_name']);
            $tab = $existingId ? new Tab($existingId) : new Tab();
            $tab->active = 1;
            $tab->class_name = $data['class_name'];
            $tab->module = $moduleName;
            $tab->id_parent = (int) Tab::getIdFromClassName($data['parent']);
            if (property_exists($tab, 'wording')) {
                $tab->wording = $data['wording'];
                $tab->wording_domain = 'Modules.Bkredirects.Admin';
            }
            foreach ($languages as $language) {
                $iso = strtolower($language['iso_code']);
                $tab->name[(int) $language['id_lang']] = isset($data['name'][$iso]) ? $data['name'][$iso] : $data['name']['en'];
            }
            if (!($existingId ? $tab->update() : $tab->add())) {
                return false;
            }
        }

        return true;
    }

    public static function uninstall()
    {
        foreach (array_reverse(self::structure()) as $data) {
            $idTab = (int) Tab::getIdFromClassName($data['class_name']);
            if ($idTab) {
                (new Tab($idTab))->delete();
            }
        }

        return true;
    }
}
