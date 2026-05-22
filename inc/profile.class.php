<?php

/**
 * -------------------------------------------------------------------------
 * UsedItemsExportPlus plugin for GLPI
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

class PluginUseditemsexportplusProfile extends CommonDBTM
{
    public static $rightname = "profile";

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('interface') != 'helpdesk') {
            return PluginUseditemsexportplusExport::getTypeName();
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            $ID   = $item->getID();
            $prof = new self();
            foreach (self::getAllRights() as $right) {
                self::addDefaultProfileInfos($ID, [$right['field'] => 0]);
            }
            $prof->showForm($ID);
        }
        return true;
    }

    public static function getAllRights()
    {
        return [
            [
                'itemtype' => 'PluginUseditemsexportplusExport',
                'label'    => PluginUseditemsexportplusExport::getTypeName(),
                'field'    => 'plugin_useditemsexportplus_export',
                'rights'   => [
                    CREATE => __('Create'),
                    READ   => __('Read'),
                    PURGE  => [
                        'short' => __('Purge'),
                        'long'  => _x('button', 'Delete permanently'),
                    ],
                ],
                'default' => 21,
            ],
        ];
    }

    public static function addDefaultProfileInfos($profiles_id, $rights)
    {
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if (
                !countElementsInTable(
                    'glpi_profilerights',
                    ['profiles_id' => $profiles_id, 'name' => $right]
                )
            ) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    public function showForm($ID, array $options = [])
    {
        echo "<div class='firstbloc'>";
        if ($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE])) {
            $profile = new Profile();
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }

        $profile = new Profile();
        $profile->getFromDB($ID);
        if ($profile->getField('interface') == 'central') {
            $rights = $this->getAllRights();
            $profile->displayRightsChoiceMatrix($rights, [
                'canedit'       => $canedit,
                'default_class' => 'tab_bg_2',
                'title'         => __('General'),
            ]);
        }

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $ID]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }
        echo "</div>";

        return true;
    }

    public static function install(Migration $migration)
    {
        foreach (self::getAllRights() as $right) {
            self::addDefaultProfileInfos(
                $_SESSION['glpiactiveprofile']['id'],
                [$right['field'] => $right['default']]
            );
        }

        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;

        foreach (self::getAllRights() as $right) {
            $DB->delete('glpi_profilerights', ['name' => $right['field']]);

            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }

        return true;
    }
}
