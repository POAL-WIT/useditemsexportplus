<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus plugin for GLPI
 *
 * Forked from UsedItemsExport (Teclib) with the following additions:
 *   - Self-service entry: a user can confirm + generate the PDF of their
 *     own assets in a single action.
 *   - Confirmation is global on the export and immutable once stored.
 *   - The PDF includes the confirmation note (user + datetime).
 *   - As long as a confirmed export exists for the user, no new export can
 *     be created. An admin can "reset" (delete) it to unlock a new run.
 *   - Admin overview page lists every confirmed export across all users.
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginUseditemsexportplusExport extends CommonDBTM
{
    public static $rightname = 'plugin_useditemsexportplus_export';

    public static function getTypeName($nb = 0)
    {
        return __('Used items export plus', 'useditemsexportplus');
    }

    public static function getIcon()
    {
        return 'ti ti-file-export';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof User) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(), self::countForItem($item));
            }
            return self::getTypeName();
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        if ($item instanceof User) {
            if (Session::haveRightsOr('plugin_useditemsexportplus_export', [READ, CREATE, PURGE])) {
                $self = new self();
                $self->showForUser($item);
            } else {
                echo "<div align='center'><br><br><img src=\"" . $CFG_GLPI["root_doc"]
                    . "/pics/warning.png\" alt=\"warning\"><br><br>";
                echo "<b>" . __("Access denied") . "</b></div>";
            }
        }

        return true;
    }

    public static function countForItem(CommonDBTM $item)
    {
        return countElementsInTable(
            getTableForItemType(__CLASS__),
            ['users_id' => $item->getID()]
        );
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getMenuContent()
    {
        if (!Session::haveRight('plugin_useditemsexportplus_export', READ)) {
            return false;
        }

        $menu          = [];
        $menu['title'] = self::getMenuName();
        $menu['page']  = '/' . Plugin::getWebDir('useditemsexportplus', false) . '/front/admin.php';
        $menu['icon']  = self::getIcon();

        return $menu;
    }

    /**
     * @return array<int,array<string,mixed>> exports indexed by export id
     */
    public static function getAllForUser($users_id)
    {
        /** @var DBmysql $DB */
        global $DB;

        $exports = [];
        $it = $DB->request([
            'FROM'  => getTableForItemType(__CLASS__),
            'WHERE' => ['users_id' => $users_id],
        ]);
        foreach ($it as $data) {
            $exports[$data['id']] = $data;
        }

        return $exports;
    }

    /**
     * True if the user already has a (confirmed) export.
     * Used to block creation of a new export until an admin resets it.
     */
    public static function hasConfirmedExport($users_id): bool
    {
        return countElementsInTable(
            getTableForItemType(__CLASS__),
            ['users_id' => (int) $users_id]
        ) > 0;
    }

    /**
     * Render the user's tab on User profile (admin/central).
     * Shows current confirmed export if any, with reset button.
     */
    public function showForUser($item, $options = [])
    {
        $users_id = (int) $item->getField('id');

        $exports   = self::getAllForUser($users_id);
        $canpurge  = self::canPurge();
        $cancreate = self::canCreate();
        $rand      = mt_rand();

        if ($cancreate && count($exports) === 0) {
            echo "<form method='post' name='useditemsexportplus_form{$rand}' "
                . "id='useditemsexportplus_form{$rand}' action=\""
                . Plugin::getWebDir('useditemsexportplus') . "/front/export.form.php\">";
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
            echo "<table class='tab_cadre_fixehov'>";
            echo "<tr class='tab_bg_2'><th>"
                . __('No confirmed export for this user. The user can create one from the self-service.', 'useditemsexportplus')
                . "</th></tr>";
            echo "</table>";
            Html::closeForm();
        }

        echo "<table class='tab_cadre_fixehov'>";
        $colspan = $canpurge ? 6 : 5;
        echo "<tr><th colspan='{$colspan}'>"
            . __('Confirmed export', 'useditemsexportplus') . "</th></tr>";

        if (count($exports) === 0) {
            echo "<tr class='tab_bg_1'><td class='center' colspan='{$colspan}'>"
                . __('No item to display') . "</td></tr>";
        } else {
            echo "<tr>";
            echo "<th>" . __('Reference number of export', 'useditemsexportplus') . "</th>";
            echo "<th>" . __('Date of export', 'useditemsexportplus') . "</th>";
            echo "<th>" . __('Confirmation date', 'useditemsexportplus') . "</th>";
            echo "<th>" . __('Author of export', 'useditemsexportplus') . "</th>";
            echo "<th>" . __('Export document', 'useditemsexportplus') . "</th>";
            if ($canpurge) {
                echo "<th>" . __('Actions') . "</th>";
            }
            echo "</tr>";

            foreach ($exports as $data) {
                echo "<tr class='tab_bg_1'>";
                echo "<td class='center'>" . htmlspecialchars((string) $data["refnumber"]) . "</td>";
                echo "<td class='center'>" . Html::convDateTime($data["date_mod"]) . "</td>";
                echo "<td class='center'>" . Html::convDateTime($data["date_ack"]) . "</td>";

                $author = new User();
                if ($author->getFromDB($data['authors_id'])) {
                    echo "<td class='center'>" . $author->getLink() . "</td>";
                } else {
                    echo "<td class='center'>&mdash;</td>";
                }

                $doc = new Document();
                if ($doc->getFromDB($data['documents_id'])) {
                    echo "<td class='center'>" . $doc->getDownloadLink() . "</td>";
                } else {
                    echo "<td class='center'>&mdash;</td>";
                }

                if ($canpurge) {
                    echo "<td class='center'>";
                    self::renderResetButton((int) $data['id']);
                    echo "</td>";
                }
                echo "</tr>";
            }
        }
        echo "</table>";
    }

    /**
     * Render an inline form with a reset (delete) button for a given export.
     */
    public static function renderResetButton(int $export_id): void
    {
        $confirm = __("Reset this confirmation? The user will be allowed to generate a new export.", 'useditemsexportplus');
        $action  = Plugin::getWebDir('useditemsexportplus') . '/front/export.form.php';

        echo "<form method='post' action='" . htmlspecialchars($action) . "' style='display:inline;' "
            . "onsubmit=\"return confirm('" . htmlspecialchars($confirm, ENT_QUOTES) . "');\">";
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo Html::hidden('reset_export', ['value' => $export_id]);
        echo "<button type='submit' class='btn btn-sm btn-outline-danger'>"
            . "<i class='ti ti-rotate-clockwise'></i> " . __('Reset', 'useditemsexportplus')
            . "</button>";
        echo "</form>";
    }

    /**
     * Generate the confirmed PDF for the user and persist it.
     *
     * @return int|false export id on success, false otherwise
     */
    public static function generateConfirmedPDF($users_id)
    {
        $users_id = (int) $users_id;

        if (self::hasConfirmedExport($users_id)) {
            return false;
        }

        $num       = self::getNextNum();
        $refnumber = self::getNextRefnumber();
        $now       = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        if (!isset($_SESSION['plugins']['useditemsexportplus']['config'])) {
            PluginUseditemsexportplusConfig::loadInSession();
        }
        $cfg = $_SESSION['plugins']['useditemsexportplus']['config'];

        $entity = new Entity();
        $entity->getFromDB($_SESSION['glpiactive_entity']);
        $entity_address  = '<h3>' . htmlspecialchars((string) $entity->fields["name"]) . '</h3><br />';
        $entity_address .= htmlspecialchars((string) $entity->fields["address"]) . '<br />';
        $entity_address .= htmlspecialchars((string) $entity->fields["postcode"]) . ' - '
                         . htmlspecialchars((string) $entity->fields['town']) . '<br />';
        $entity_address .= htmlspecialchars((string) $entity->fields["country"]) . '<br />';
        if (!empty($entity->fields["email"])) {
            $entity_address .= __('Email') . ' : '
                . htmlspecialchars((string) $entity->fields["email"]) . '<br />';
        }
        if (!empty($entity->fields["phonenumber"])) {
            $entity_address .= __('Phone') . ' : '
                . htmlspecialchars((string) $entity->fields["phonenumber"]) . '<br />';
        }

        $User = new User();
        $User->getFromDB($users_id);

        $Author = new User();
        $Author->getFromDB(Session::getLoginUserID());

        $logo_path = GLPI_PLUGIN_DOC_DIR . '/useditemsexportplus/logo.png';
        $logo_base64 = file_exists($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';

        $confirmation_label = sprintf(
            __('Confirmed by %1$s on %2$s', 'useditemsexportplus'),
            $User->getFriendlyName(),
            Html::convDateTime($now)
        );

        ob_start();
        ?>
        <style type="text/css">
            table { border: 1px solid #000000; width: 100%; font-size: 10pt; font-family: helvetica, arial, sans-serif; }
            .ack-box {
                border: 2px solid #198754;
                background-color: #e9f7ef;
                padding: 6px;
                font-weight: bold;
                color: #0f5132;
                text-align: center;
            }
        </style>
        <page backtop="70mm" backleft="10mm" backright="10mm" backbottom="30mm">
            <page_header>
                <table>
                    <tr>
                        <td style="height: 60mm; width: 40%; text-align: center">
                            <?php if ($logo_base64 !== ''): ?>
                                <img src="data:image/png;base64,<?php echo $logo_base64; ?>" />
                            <?php endif; ?>
                        </td>
                        <td style="width: 60%; text-align: center;">
                            <?php echo $entity_address; ?>
                        </td>
                    </tr>
                </table>
            </page_header>

            <table>
                <tr>
                    <td style="border: 1px solid #000000; text-align: center; width: 100%; font-size: 15pt; height: 8mm;">
                        <?php echo __('Asset export ref : ', 'useditemsexportplus') . htmlspecialchars($refnumber); ?>
                    </td>
                </tr>
            </table>

            <br />
            <table>
                <tr>
                    <td class="ack-box">
                        <?php echo htmlspecialchars($confirmation_label); ?>
                    </td>
                </tr>
            </table>

            <br /><br />
            <table>
                <tr>
                    <th style="width: 25%;"><?php echo __('Serial number'); ?></th>
                    <th style="width: 25%;"><?php echo __('Inventory number'); ?></th>
                    <th style="width: 25%;"><?php echo __('Name'); ?></th>
                    <th style="width: 25%;"><?php echo __('Type'); ?></th>
                </tr>
                <?php
                $allUsedItemsForUser = self::getAllUsedItemsForUser($users_id);
                foreach ($allUsedItemsForUser as $itemtype => $used_items) {
                    $item = getItemForItemtype($itemtype);
                    foreach ($used_items as $item_datas) {
                        ?>
                        <tr>
                            <td style="width: 25%;">
                                <?php echo htmlspecialchars((string) ($item_datas['serial'] ?? '')); ?>
                            </td>
                            <td style="width: 25%;">
                                <?php echo htmlspecialchars((string) ($item_datas['otherserial'] ?? '')); ?>
                            </td>
                            <td style="width: 25%;">
                                <?php echo htmlspecialchars((string) $item_datas['name']); ?>
                            </td>
                            <td style="width: 25%;">
                                <?php echo htmlspecialchars((string) $item->getTypeName(1)); ?>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </table>

            <br /><br /><br />
            <table style="border-collapse: collapse;">
                <tr>
                    <td style="width: 100%; border: 1px solid #000000; vertical-align: top">
                        <strong><?php echo htmlspecialchars(__('Acknowledgement', 'useditemsexportplus')); ?> :</strong>
                        <br /><br />
                        <?php echo htmlspecialchars($confirmation_label); ?>
                        <br /><br />
                    </td>
                </tr>
            </table>

            <page_footer>
                <div style="width: 100%; text-align: center; font-size: 8pt">
                    - <?php echo htmlspecialchars((string) $cfg['footer_text']); ?> -
                </div>
            </page_footer>
        </page>
        <?php
        $content = ob_get_clean();

        $pdf = new GLPIPDF([
            'orientation' => $cfg['orientation'],
            'format'      => $cfg['format'],
        ]);
        $pdf->WriteHTML($content);
        $contentPDF = $pdf->Output('', 'S');

        file_put_contents(GLPI_UPLOAD_DIR . '/' . $refnumber . '.pdf', $contentPDF);
        $documents_id = self::createDocument($refnumber);

        $export = new self();
        $input = [
            'users_id'     => $users_id,
            'date_mod'     => $now,
            'num'          => $num,
            'refnumber'    => $refnumber,
            'authors_id'   => Session::getLoginUserID(),
            'documents_id' => $documents_id,
            'date_ack'     => $now,
        ];

        return $export->add($input) ?: false;
    }

    public static function createDocument($refnumber)
    {
        // No explicit check(-1, CREATE, ...) here: the server-side flow that
        // calls this method already enforces who can trigger it (the user
        // confirming their own assets, or an admin), and self-service users
        // don't necessarily have generic Document creation rights.
        $doc = new Document();

        $input = [
            'entities_id'           => $_SESSION['glpiactive_entity'],
            'name'                  => __('Used-Items-Export', 'useditemsexportplus') . '-' . $refnumber,
            'upload_file'           => $refnumber . '.pdf',
            'documentcategories_id' => 0,
            'mime'                  => 'application/pdf',
            'date_mod'              => date('Y-m-d H:i:s'),
            'users_id'              => Session::getLoginUserID(),
        ];

        return $doc->add($input);
    }

    public static function getNextNum()
    {
        /** @var DBmysql $DB */
        global $DB;

        $result = $DB->request([
            'SELECT' => [new QueryExpression('MAX(' . $DB::quoteName('num') . ') AS ' . $DB::quoteName('num'))],
            'FROM'   => self::getTable(),
        ]);
        $nextNum = count($result) ? $result->current()['num'] : false;
        if (!$nextNum) {
            return 1;
        }
        return $nextNum + 1;
    }

    public static function getNextRefnumber()
    {
        if ($nextNum = self::getNextNum()) {
            $nextRefnumber = str_pad((string) $nextNum, 4, "0", STR_PAD_LEFT);
            $date = new DateTime();
            return $nextRefnumber . '-' . $date->format('Y');
        }
        return '';
    }

    /**
     * Returns assets used by the given user, grouped by itemtype.
     */
    public static function getAllUsedItemsForUser($ID)
    {
        /**
         * @var DBmysql $DB
         * @var array $CFG_GLPI
         */
        global $DB, $CFG_GLPI;

        $items = [];

        foreach ($CFG_GLPI['linkuser_types'] as $itemtype) {
            if (!($item = getItemForItemtype($itemtype))) {
                continue;
            }
            // No canView() guard: self-service users don't hold a generic
            // READ right on Computer/Monitor/etc., but they may see their
            // own assets here (the query is filtered by users_id = $ID).

            $itemtable = getTableForItemType($itemtype);
            if (!$DB->tableExists($itemtable)) {
                continue;
            }
            $criteria = [
                'FROM'  => $itemtable,
                'WHERE' => ['users_id' => $ID],
            ];
            if ($item->maybeTemplate()) {
                $criteria['WHERE']['is_template'] = '0';
            }
            if ($item->maybeDeleted()) {
                $criteria['WHERE']['is_deleted'] = '0';
            }
            $result = $DB->request($criteria);

            if (count($result) > 0) {
                foreach ($result as $data) {
                    $items[$itemtype][] = $data;
                }
            }
        }

        // Consumables
        $consumables = $DB->request([
            'SELECT' => ['name', 'otherserial'],
            'FROM'   => ConsumableItem::getTable(),
            'WHERE'  => [
                'id' => new QuerySubQuery([
                    'SELECT' => 'consumableitems_id',
                    'FROM'   => Consumable::getTable(),
                    'WHERE'  => [
                        'itemtype' => User::class,
                        'items_id' => $ID,
                    ],
                ]),
            ],
        ]);
        foreach ($consumables as $data) {
            $items['ConsumableItem'][] = $data;
        }

        return $items;
    }

    public function cleanDBonPurge()
    {
        if (!empty($this->fields['documents_id'])) {
            $doc = new Document();
            if ($doc->getFromDB($this->fields['documents_id'])) {
                $doc->delete(['id' => $this->fields['documents_id']], true);
            }
        }
    }

    public static function install(Migration $migration)
    {
        /** @var DBmysql $DB */
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        $table = getTableForItemType(__CLASS__);

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                  `users_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                  `date_mod` TIMESTAMP NULL DEFAULT NULL,
                  `num` SMALLINT NOT NULL DEFAULT 0,
                  `refnumber` VARCHAR(9) NOT NULL DEFAULT '0000-0000',
                  `authors_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                  `documents_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                  `date_ack` DATETIME NULL DEFAULT NULL,
               PRIMARY KEY  (`id`),
               KEY `users_id` (`users_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());
        } else {
            // Make sure date_ack exists on upgrade.
            if (!$DB->fieldExists($table, 'date_ack')) {
                $migration->addField($table, 'date_ack', 'datetime');
                $migration->migrationOneTable($table);
            }
        }

        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $table = getTableForItemType(__CLASS__);
        $DB->query("DROP TABLE IF EXISTS `" . $table . "`") or die($DB->error());

        return true;
    }
}
