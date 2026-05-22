<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus plugin for GLPI
 * Forked from UsedItemsExport (Teclib) with confirmation feature.
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_USEDITEMSEXPORTPLUS_VERSION', '1.0.0');
define('PLUGIN_USEDITEMSEXPORTPLUS_MIN_GLPI', '10.0.1');
define('PLUGIN_USEDITEMSEXPORTPLUS_MAX_GLPI', '10.0.99');

function plugin_init_useditemsexportplus()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $plugin = new Plugin();

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['useditemsexportplus'] = true;

    if (!Session::getLoginUserID() || !$plugin->isActivated('useditemsexportplus')) {
        return;
    }

    PluginUseditemsexportplusConfig::loadInSession();

    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['useditemsexportplus'] = 'front/config.form.php';
    }

    if (Session::haveRight('profile', UPDATE)) {
        Plugin::registerClass(
            'PluginUseditemsexportplusProfile',
            ['addtabon' => 'Profile']
        );
    }

    $config = $_SESSION['plugins']['useditemsexportplus']['config'] ?? null;
    if (!$config || empty($config['is_active'])) {
        return;
    }

    // Self-service entry: any logged-in user can confirm & export their own assets.
    $PLUGIN_HOOKS['helpdesk_menu_entry']['useditemsexportplus']      = '/front/myassets.php';
    $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['useditemsexportplus'] = 'ti ti-file-export';

    if (Session::haveRightsOr('plugin_useditemsexportplus_export', [READ, CREATE, PURGE])) {
        Plugin::registerClass(
            'PluginUseditemsexportplusExport',
            ['addtabon' => 'User']
        );

        // Central (admin) menu entry for managing confirmed exports of all users.
        if (Session::haveRight('plugin_useditemsexportplus_export', READ)) {
            $PLUGIN_HOOKS['menu_toadd']['useditemsexportplus'] = ['admin' => 'PluginUseditemsexportplusExport'];
        }
    }
}

function plugin_version_useditemsexportplus()
{
    return [
        'name'         => __('Used items export plus', 'useditemsexportplus'),
        'version'      => PLUGIN_USEDITEMSEXPORTPLUS_VERSION,
        'oldname'      => 'useditemsexport',
        'license'      => 'GPLv3+',
        'author'       => 'Alessandro Paoli',
        'homepage'     => 'https://www.neteye-blog.com/',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_USEDITEMSEXPORTPLUS_MIN_GLPI,
                'max' => PLUGIN_USEDITEMSEXPORTPLUS_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_useditemsexportplus_check_prerequisites(): bool
{
    return true;
}

function plugin_useditemsexportplus_check_config(bool $verbose = false): bool
{
    return true;
}
