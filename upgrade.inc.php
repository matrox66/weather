<?php
/**
*   Upgrade the plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2018 Lee Garner <lee@leegarner.com>
*   @package    weather
*   @version    1.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

global $_CONF, $_CONF_WEATHER, $_DB_dbms;

/** Include default values for new config items */
require_once __DIR__ . '/install_defaults.php';
global $_DB_dbms;
 require_once __DIR__ . '/sql/'.$_DB_dbms.'_install.php';
global $_SQL_UPGRADE;

/**
*   Sequentially perform version upgrades.
*
*   @return boolean     True on success, False on failure
*/
function weather_do_upgrade()
{
    global $_CONF_WEATHER, $_PLUGIN_INFO, $_WEA_DEFAULT;

    if (isset($_PLUGIN_INFO[$_CONF_WEATHER['pi_name']])) {
        if (is_array($_PLUGIN_INFO[$_CONF_WEATHER['pi_name']])) {
            // glFusion > 1.6.5
            $current_ver = $_PLUGIN_INFO[$_CONF_WEATHER['pi_name']]['pi_version'];
        } else {
            // legacy
            $current_ver = $_PLUGIN_INFO[$_CONF_WEATHER['pi_name']];
        }
    } else {
        return false;
    }
    $installed_ver = plugin_chkVersion_weather();
    $c = config::get_instance();
    if (!$c->group_exists($_CONF_WEATHER['pi_name'])) return false;

    if (!COM_checkVersion($current_ver, '1.0.0')) {
        $current_ver = '1.0.0';
        $c->add('provider',$_WEA_DEFAULT['provider'], 'select',
                0, 0, 16, 75, true, 'weather');
        // Provider - World Weather Online
        $c->add('fs_provider_wwo', NULL, 'fieldset', 0, 10, NULL, 0,
                true, 'weather');
        // Provider - Weather Underground
        $c->add('fs_provider_wu', NULL, 'fieldset', 0, 20, NULL, 0,
                true, 'weather');
        $c->add('api_key_wu', '', 'text', 0, 20, 0, 200, true, 'weather');
        $c->add('ref_key_wu', '', 'text', 0, 20, 0, 210, true, 'weather');
        if (!weather_do_upgrade_sql($current_ver)) return false;
        if (!weather_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.0.3')) {
        $current_ver = '1.0.3';
        // Add new configuration items
        $c->add('api_key',$_WEA_DEFAULT['api_key'], 'text',
                0, 0, NULL, 70, true, 'weather');
        $c->add('k_m',$_WEA_DEFAULT['k_m'], 'select',
                0, 0, 14, 80, true, 'weather');
        $c->add('f_c',$_WEA_DEFAULT['f_c'], 'select',
                    0, 0, 15, 90, true, 'weather');
        if (!weather_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.1.0')) {
        $current_ver = '1.0.4';
        $c->add('fs_provider_apixu', NULL, 'fieldset',
                0, 30, NULL, 0, true, $_CONF_WEATHER['pi_name']);
        $c->add('api_key_apixu', '', 'text',
                0, 30, 0, 100, true, $_CONF_WEATHER['pi_name']);
        if (!weather_do_set_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '1.1.2')) {
        $current_ver = '1.1.2';
        if (!weather_do_upgrade_sql($current_ver)) return false;
        if (!weather_do_set_version($current_ver)) return false;
    }

    // Final version update to catch updates that don't go through
    // any of the update functions, e.g. code-only updates
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!weather_do_set_version($installed_ver)) {
            COM_errorLog($_CONF_WEATHER['pi_display_name'] .
                    " Error performing final update $current_ver to $installed_ver");
            return false;
        }
    }
    COM_errorLog("Successfully updated the {$_CONF_WEATHER['pi_display_name']} Plugin", 1);
    Weather\apiBase::clearCache();
    return true;
}


/**
*   Execute the SQL statement to perform a version upgrade.
*   An empty SQL parameter will return success.
*
*   @param string   $version  Version being upgraded to
*   @param array    $sql      SQL statement to execute
*   @return integer Zero on success, One on failure.
*/
function weather_do_upgrade_sql($version)
{
    global $_TABLES, $_CONF_WEATHER, $_SQL_UPGRADE;

    // If no sql statements passed in, return success
    if (!is_array($_SQL_UPGRADE[$version]))
        return true;

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating Weather Plugin to version $version");
    foreach ($_SQL_UPGRADE[$version] as $sql) {
        COM_errorLot("Weather Plugin $version update: Executing SQL => $sql");
        DB_query($sql, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during Weather plugin update",1);
            return false;
            break;
        }
    }
    return true;
}


/**
*   Update the plugin version number in the database.
*   Called at each version upgrade to keep up to date with
*   successful upgrades.
*
*   @param  string  $ver    New version to set
*   @return boolean         True on success, False on failure
*/
function weather_do_set_version($ver)
{
    global $_TABLES, $_CONF_WEATHER;

    // now update the current version number.
    $sql = "UPDATE {$_TABLES['plugins']} SET
            pi_version = '{$_CONF_WEATHER['pi_version']}',
            pi_gl_version = '{$_CONF_WEATHER['gl_version']}',
            pi_homepage = '{$_CONF_WEATHER['pi_url']}'
        WHERE pi_name = '{$_CONF_WEATHER['pi_name']}'";

    $res = DB_query($sql, 1);
    if (DB_error()) {
        COM_errorLog("Error updating the {$_CONF_WEATHER['pi_display_name']} Plugin version",1);
        return false;
    } else {
        return true;
    }
}


/**
*   Upgrade to version 0.1.3
*   Implements WorldWeatherOnline provider, adds api key and
*   English/Metric selections to plugin configuration.
*
*   @return integer 0, no sql to upgrade here
*/
function weather_upgrade_0_1_3()
{
    global $_TABLES, $_CONF_WEATHER, $_WEA_DEFAULT;

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_WEATHER['pi_name'])) {
        $c->add('api_key',$_WEA_DEFAULT['api_key'], 'text',
                0, 0, NULL, 70, true, 'weather');
        $c->add('k_m',$_WEA_DEFAULT['k_m'], 'select',
                0, 0, 14, 80, true, 'weather');
        $c->add('f_c',$_WEA_DEFAULT['f_c'], 'select',
                0, 0, 15, 90, true, 'weather');
    }
    return 0;
}

/**
*   Upgrade to version 1.0.0
*   Implements Weather Underground provider, adds provider selection
*   to plugin configuration.
*
*   @return integer 0, no sql to upgrade here
*/
function weather_upgrade_1_0_0()
{
    global $_TABLES, $_CONF_WEATHER, $_WEA_DEFAULT;

    $status =  weather_do_upgrade_sql('1.0.0');
    if ($status > 0) return $status;

    // Add new configuration items
    $c = config::get_instance();
    if ($c->group_exists($_CONF_WEATHER['pi_name'])) {
        $c->add('provider',$_WEA_DEFAULT['provider'], 'select',
                0, 0, 16, 75, true, 'weather');
        // Provider - World Weather Online
        $c->add('fs_provider_wwo', NULL, 'fieldset', 0, 10, NULL, 0,
                true, 'weather');
        // Provider - Weather Underground
        $c->add('fs_provider_wu', NULL, 'fieldset', 0, 20, NULL, 0,
                true, 'weather');
        $c->add('api_key_wu', '', 'text', 0, 20, 0, 200, true, 'weather');
        $c->add('ref_key_wu', '', 'text', 0, 20, 0, 210, true, 'weather');
    }

    return 0;
}

?>
