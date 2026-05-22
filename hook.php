<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus plugin for GLPI
 * -------------------------------------------------------------------------
 */

function plugin_useditemsexportplus_install()
{
    $migration = new Migration(PLUGIN_USEDITEMSEXPORTPLUS_VERSION);

    foreach (glob(dirname(__FILE__) . '/inc/*.class.php') as $filepath) {
        if (preg_match("/inc\/(.+)\.class\.php$/", $filepath, $matches)) {
            $classname = 'PluginUseditemsexportplus' . ucfirst($matches[1]);
            include_once($filepath);
            if (method_exists($classname, 'install')) {
                $classname::install($migration);
            }
        }
    }
    return true;
}

function plugin_useditemsexportplus_uninstall()
{
    foreach (glob(dirname(__FILE__) . '/inc/*.class.php') as $filepath) {
        if (preg_match("/inc\/(.+)\.class\.php$/", $filepath, $matches)) {
            $classname = 'PluginUseditemsexportplus' . ucfirst($matches[1]);
            include_once($filepath);
            if (method_exists($classname, 'uninstall')) {
                $classname::uninstall();
            }
        }
    }
    return true;
}
