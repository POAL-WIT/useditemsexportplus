<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus - admin POST handler
 *
 * Used from the User tab and the admin page to reset (purge) an export.
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

if (isset($_POST['reset_export'])) {
    if (!Session::haveRight('plugin_useditemsexportplus_export', PURGE)) {
        Html::displayRightError();
    }
    $export_id = (int) $_POST['reset_export'];
    if ($export_id > 0) {
        $export = new PluginUseditemsexportplusExport();
        if ($export->getFromDB($export_id)) {
            $export->delete(['id' => $export_id], true);
            Session::addMessageAfterRedirect(
                __('Confirmation reset. The user can now generate a new export.', 'useditemsexportplus')
            );
        }
    }
    Html::back();
    exit;
}

Html::back();
