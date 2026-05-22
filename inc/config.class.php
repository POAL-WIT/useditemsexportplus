<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginUseditemsexportplusConfig extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __('General setup of useditemsexportplus', 'useditemsexportplus');
    }

    public function showForm($ID, $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Active') . "</td>";
        echo "<td>";
        Dropdown::showYesNo("is_active", $this->fields["is_active"]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Footer text', 'useditemsexportplus') . "</td>";
        echo "<td><input type='text' name='footer_text' size='60' value='"
              . htmlspecialchars((string) $this->fields["footer_text"], ENT_QUOTES) . "'></td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Orientation', 'useditemsexportplus') . "</td>";
        echo "<td>";
        self::dropdownOrientation($this->fields["orientation"]);
        echo "</td>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Format', 'useditemsexportplus') . "</td>";
        echo "<td>";
        self::dropdownFormat($this->fields["format"]);
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);

        return true;
    }

    public function dropdownOrientation($value)
    {
        Dropdown::showFromArray(
            "orientation",
            [
                'L' => __('Landscape', 'useditemsexportplus'),
                'P' => __('Portrait', 'useditemsexportplus'),
            ],
            ['value' => $value]
        );
    }

    public function dropdownFormat($value)
    {
        Dropdown::showFromArray(
            "format",
            [
                'A3' => __('A3'),
                'A4' => __('A4'),
                'A5' => __('A5'),
            ],
            ['value' => $value]
        );
    }

    public static function loadInSession()
    {
        $config = new self();
        if (!$config->getFromDB(1)) {
            return;
        }
        unset($config->fields['id']);
        $_SESSION['plugins']['useditemsexportplus']['config'] = $config->fields;
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
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `footer_text` VARCHAR(255) DEFAULT '',
                     `is_active` TINYINT NOT NULL DEFAULT 1,
                     `orientation` VARCHAR(1) NOT NULL DEFAULT 'P',
                     `format` VARCHAR(2) NOT NULL DEFAULT 'A4',
               PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());

            $DB->insertOrDie($table, ['id' => 1]);
        }

        $migration->displayMessage("Create useditemsexportplus dir");
        if (!is_dir(GLPI_PLUGIN_DOC_DIR . '/useditemsexportplus')) {
            mkdir(GLPI_PLUGIN_DOC_DIR . '/useditemsexportplus');
        }

        $migration->displayMessage("Copy default logo from GLPI core");
        if (!file_exists(GLPI_PLUGIN_DOC_DIR . '/useditemsexportplus/logo.png')) {
            copy(
                GLPI_ROOT . '/pics/logos/logo-GLPI-250-black.png',
                GLPI_PLUGIN_DOC_DIR . '/useditemsexportplus/logo.png'
            );
        }

        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        $table = getTableForItemType(__CLASS__);

        $query = "DROP TABLE IF EXISTS  `" . $table . "`";
        $DB->query($query) or die($DB->error());

        if (is_dir(GLPI_PLUGIN_DOC_DIR . '/useditemsexportplus')) {
            Toolbox::deleteDir(GLPI_PLUGIN_DOC_DIR . '/useditemsexportplus');
        }

        return true;
    }
}
