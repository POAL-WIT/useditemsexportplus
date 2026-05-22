<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus - configuration page
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::haveRight("config", UPDATE);

Html::header(
    PluginUseditemsexportplusConfig::getTypeName(1),
    $_SERVER['PHP_SELF'],
    "plugins",
    "useditemsexportplus",
    "config"
);

if (!isset($_GET["id"])) {
    $_GET["id"] = 1;
}

$config = new PluginUseditemsexportplusConfig();

if (isset($_POST["update"])) {
    $config->check($_POST["id"], UPDATE);
    $config->update($_POST);
    Html::back();
}

$config->showForm($_GET["id"]);

Html::footer();
