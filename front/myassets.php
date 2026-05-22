<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus - self-service page
 *
 * The logged-in user sees their own assets, and either:
 *   - a "Confirm and generate PDF" button if no confirmed export exists yet;
 *   - the read-only confirmation summary + PDF download otherwise.
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

$users_id = (int) Session::getLoginUserID();

if (!isset($_SESSION['plugins']['useditemsexportplus']['config'])) {
    PluginUseditemsexportplusConfig::loadInSession();
}
$cfg = $_SESSION['plugins']['useditemsexportplus']['config'] ?? null;
if (!$cfg || empty($cfg['is_active'])) {
    Html::displayErrorAndDie(__('Plugin is disabled', 'useditemsexportplus'));
}

// --- POST: confirm & generate PDF ---------------------------------------
// CSRF is already validated by inc/includes.php on POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_and_generate'])) {
    if (PluginUseditemsexportplusExport::hasConfirmedExport($users_id)) {
        Session::addMessageAfterRedirect(
            __('A confirmed export already exists. Please contact your administrator to reset it.', 'useditemsexportplus'),
            true,
            ERROR
        );
    } else {
        $assets = PluginUseditemsexportplusExport::getAllUsedItemsForUser($users_id);
        if (empty($assets)) {
            Session::addMessageAfterRedirect(
                __('No asset is assigned to you. Nothing to confirm.', 'useditemsexportplus'),
                true,
                ERROR
            );
        } elseif (PluginUseditemsexportplusExport::generateConfirmedPDF($users_id)) {
            Session::addMessageAfterRedirect(
                __('Confirmation registered and PDF successfully generated.', 'useditemsexportplus'),
                true
            );
        } else {
            Session::addMessageAfterRedirect(
                __('Could not generate the PDF.', 'useditemsexportplus'),
                true,
                ERROR
            );
        }
    }
    Html::back();
    exit;
}

// --- Render -------------------------------------------------------------

Html::helpHeader(PluginUseditemsexportplusExport::getTypeName());

echo "<div class='center' style='max-width:1100px;margin:auto;'>";
echo "<h2>" . __('My assets', 'useditemsexportplus') . "</h2>";

$exports = PluginUseditemsexportplusExport::getAllForUser($users_id);
$assets  = PluginUseditemsexportplusExport::getAllUsedItemsForUser($users_id);

if (empty($assets) && empty($exports)) {
    echo "<div class='alert alert-info'>"
        . __('No asset is assigned to you.', 'useditemsexportplus')
        . "</div>";
    echo "</div>";
    Html::helpFooter();
    return;
}

// Asset listing (read-only).
if (!empty($assets)) {
    echo "<p>" . __('Below is the list of assets assigned to you. By confirming, you acknowledge that you currently use them and that the data is correct.', 'useditemsexportplus') . "</p>";

    echo "<table class='tab_cadre_fixehov'>";
    echo "<thead><tr>";
    echo "<th>" . __('Type') . "</th>";
    echo "<th>" . __('Name') . "</th>";
    echo "<th>" . __('Serial number') . "</th>";
    echo "<th>" . __('Inventory number') . "</th>";
    echo "</tr></thead><tbody>";

    foreach ($assets as $itemtype => $rows) {
        $item = getItemForItemtype($itemtype);
        $type_label = $item ? $item->getTypeName(1) : $itemtype;
        foreach ($rows as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars((string) $type_label) . "</td>";
            echo "<td>" . htmlspecialchars((string) ($row['name'] ?? '')) . "</td>";
            echo "<td>" . htmlspecialchars((string) ($row['serial'] ?? '')) . "</td>";
            echo "<td>" . htmlspecialchars((string) ($row['otherserial'] ?? '')) . "</td>";
            echo "</tr>";
        }
    }
    echo "</tbody></table>";
}

// Confirmation block.
if (!empty($exports)) {
    $data = reset($exports);
    echo "<div class='alert alert-success' style='margin-top:1.5em;'>";
    echo "<strong>" . __('Already confirmed', 'useditemsexportplus') . "</strong><br />";
    echo sprintf(
        __('You confirmed your assets on %s.', 'useditemsexportplus'),
        Html::convDateTime($data['date_ack'])
    );
    echo "<br />";
    echo __('Reference', 'useditemsexportplus') . ': '
        . htmlspecialchars((string) $data['refnumber']);
    echo "</div>";

    $doc = new Document();
    if ($doc->getFromDB($data['documents_id'])) {
        echo "<div style='margin-top:1em;text-align:center;'>";
        echo $doc->getDownloadLink();
        echo "</div>";
    }

    echo "<p class='text-muted' style='margin-top:1em;'>"
        . __('A new export can be created only after an administrator resets the current confirmation.', 'useditemsexportplus')
        . "</p>";
} elseif (!empty($assets)) {
    $confirm_msg = __("By confirming you also generate the PDF. The action is final: you won't be able to create a new export until an administrator resets it. Continue?", 'useditemsexportplus');
    echo "<form method='post' action='' style='margin-top:1.5em;text-align:center;' "
        . "onsubmit=\"return confirm('" . htmlspecialchars($confirm_msg, ENT_QUOTES) . "');\">";
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo "<button type='submit' name='confirm_and_generate' value='1' class='btn btn-primary'>"
        . "<i class='ti ti-checks'></i> "
        . __('Confirm and generate PDF', 'useditemsexportplus')
        . "</button>";
    echo "</form>";
}

echo "</div>";

Html::helpFooter();
