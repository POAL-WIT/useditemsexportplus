<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus - admin overview
 *
 * Lists every confirmed export across users with download + reset buttons.
 * Reset deletes the export row + its document, allowing the user to
 * generate a new one from the self-service page.
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

if (!Session::haveRight('plugin_useditemsexportplus_export', READ)) {
    Html::displayRightError();
}

global $DB, $CFG_GLPI;

$can_purge = Session::haveRight('plugin_useditemsexportplus_export', PURGE);

// --- POST: reset (purge) one export -------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_export'])) {
    if (!$can_purge) {
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

Html::header(
    PluginUseditemsexportplusExport::getTypeName(),
    $_SERVER['PHP_SELF'],
    'admin',
    'PluginUseditemsexportplusExport'
);

$filter_user_id = isset($_GET['users_id']) ? (int) $_GET['users_id'] : 0;

echo "<div class='center' style='max-width:1200px;margin:auto;'>";
echo "<h2>" . PluginUseditemsexportplusExport::getTypeName() . "</h2>";

// --- Filter form --------------------------------------------------------
echo "<form method='get' action='' class='no-shadow' style='margin-bottom:1em;'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_1'>";
echo "<th>" . __('Filters') . "</th>";
echo "<td>" . User::getTypeName(1) . "&nbsp;";
User::dropdown([
    'name'                => 'users_id',
    'value'               => $filter_user_id,
    'right'               => 'all',
    'display_emptychoice' => true,
]);
echo "</td>";
echo "<td><button type='submit' class='btn btn-primary'>"
    . __('Filter', 'useditemsexportplus') . "</button></td>";
echo "</tr></table>";
echo "</form>";

$table = getTableForItemType('PluginUseditemsexportplusExport');
$where = [];
if ($filter_user_id > 0) {
    $where["{$table}.users_id"] = $filter_user_id;
}

$iter = $DB->request([
    'SELECT' => [
        "{$table}.id",
        "{$table}.users_id",
        "{$table}.refnumber",
        "{$table}.date_mod",
        "{$table}.date_ack",
        "{$table}.documents_id",
        'glpi_users.name AS user_name',
        'glpi_users.realname AS user_realname',
        'glpi_users.firstname AS user_firstname',
    ],
    'FROM'      => $table,
    'LEFT JOIN' => [
        'glpi_users' => [
            'ON' => [
                'glpi_users' => 'id',
                $table       => 'users_id',
            ],
        ],
    ],
    'WHERE' => $where,
    'ORDER' => ["{$table}.date_ack DESC"],
]);

echo "<table class='tab_cadre_fixehov'>";
echo "<thead><tr>";
echo "<th>" . User::getTypeName(1) . "</th>";
echo "<th>" . __('Reference number of export', 'useditemsexportplus') . "</th>";
echo "<th>" . __('Date of export', 'useditemsexportplus') . "</th>";
echo "<th>" . __('Confirmation date', 'useditemsexportplus') . "</th>";
echo "<th>" . __('Export document', 'useditemsexportplus') . "</th>";
if ($can_purge) {
    echo "<th>" . __('Actions') . "</th>";
}
echo "</tr></thead><tbody>";

$count = 0;
foreach ($iter as $row) {
    $count++;
    $user_label = formatUserName(
        (int) $row['users_id'],
        (string) ($row['user_name'] ?? ''),
        (string) ($row['user_realname'] ?? ''),
        (string) ($row['user_firstname'] ?? '')
    );
    $user_url = $CFG_GLPI['root_doc'] . '/front/user.form.php?id=' . (int) $row['users_id'];

    echo "<tr>";
    echo "<td><a href='" . htmlspecialchars($user_url) . "'>"
        . htmlspecialchars($user_label) . "</a></td>";
    echo "<td>" . htmlspecialchars((string) $row['refnumber']) . "</td>";
    echo "<td>" . Html::convDateTime((string) $row['date_mod']) . "</td>";
    echo "<td>" . Html::convDateTime((string) $row['date_ack']) . "</td>";

    $doc = new Document();
    if ($doc->getFromDB((int) $row['documents_id'])) {
        echo "<td>" . $doc->getDownloadLink() . "</td>";
    } else {
        echo "<td>&mdash;</td>";
    }

    if ($can_purge) {
        echo "<td>";
        PluginUseditemsexportplusExport::renderResetButton((int) $row['id']);
        echo "</td>";
    }
    echo "</tr>";
}

if ($count === 0) {
    $colspan = $can_purge ? 6 : 5;
    echo "<tr><td colspan='{$colspan}' class='center'><i>"
        . __('No confirmed export found.', 'useditemsexportplus')
        . "</i></td></tr>";
}

echo "</tbody></table>";
echo "</div>";

Html::footer();
