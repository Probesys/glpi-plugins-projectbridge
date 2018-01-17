<?php

if (!class_exists('PluginProjectbridgeConfig')) {
    require_once(GLPI_ROOT . '/plugins/projectbridge/inc/config.class.php');
}

/**
 * Plugin description
 *
 * @return boolean
 */
function plugin_version_projectbridge()
{
    return array(
        'name' => 'ProjectBridge',
        'version' => '1.0',
        'author' => 'Pierre de Vésian - <a href="http://www.probesys.com">Probesys</a>',
        'license' => 'GPLv2+',
        'minGlpiVersion' => PluginProjectbridgeConfig::MIN_GLPI_VERSION,
    );
}

/**
 * Initialize plugin
 *
 * @return boolean
 */
function plugin_init_projectbridge()
{
    if (Session::getLoginUserID()) {
        global $PLUGIN_HOOKS;

        $PLUGIN_HOOKS['csrf_compliant'][PluginProjectbridgeConfig::NAMESPACE] = true;
        $PLUGIN_HOOKS['config_page'][PluginProjectbridgeConfig::NAMESPACE] = 'front/config.form.php';
        $PLUGIN_HOOKS['post_show_item'][PluginProjectbridgeConfig::NAMESPACE] = 'plugin_projectbridge_post_show_item';
        $PLUGIN_HOOKS['pre_item_update'][PluginProjectbridgeConfig::NAMESPACE] = array(
            'Entity' => 'plugin_projectbridge_pre_entity_update',
            'Contract' => 'plugin_projectbridge_pre_contract_update',
            'Ticket' => 'plugin_projectbridge_ticket_update',
        );

        $PLUGIN_HOOKS['item_add'][PluginProjectbridgeConfig::NAMESPACE] = array(
            'Contract' => 'plugin_projectbridge_contract_add',
        );
    }
}

/**
 * Check if plugin prerequisites are met
 *
 * @return boolean
 */
function plugin_projectbridge_check_prerequisites()
{
    $prerequisites_check_ok = false;

    try {
        if (version_compare(GLPI_VERSION, PluginProjectbridgeConfig::MIN_GLPI_VERSION, '<')) {
            throw new Exception('This plugin requires GLPI >= ' . PluginProjectbridgeConfig::MIN_GLPI_VERSION);
        }

        $prerequisites_check_ok = true;
    } catch (Exception $e) {
        echo $e->getMessage();
    }

    return $prerequisites_check_ok;
}

/**
 * Check if config is compatible with plugin
 *
 * @return boolean
 */
function plugin_projectbridge_check_config()
{
    // nothing to do
    return true;
}
