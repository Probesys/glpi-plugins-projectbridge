<?php

/**
 * Install the plugin
 *
 * @return boolean
 */
function plugin_projectbridge_install()
{
    global $DB;

    if (!TableExists(PluginProjectbridgeEntity::$table_name)) {
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS `" . PluginProjectbridgeEntity::$table_name . "`
            (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `entity_id` INT(11) NOT NULL,
                `contract_id` INT(11) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`entity_id`, `contract_id`)
            )
            COLLATE='utf8_unicode_ci'
            ENGINE=MyISAM
        ";
        $DB->query($create_table_query) or die($DB->error());
    }

    if (!TableExists(PluginProjectbridgeContract::$table_name)) {
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS `" . PluginProjectbridgeContract::$table_name . "`
            (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `contract_id` INT(11) NOT NULL,
                `project_id` INT(11) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`contract_id`, `project_id`)
            )
            COLLATE='utf8_unicode_ci'
            ENGINE=MyISAM
        ";
        $DB->query($create_table_query) or die($DB->error());
    }

    return true;
}

/**
 * Uninstall the plugin
 *
 * @return boolean
 */
function plugin_projectbridge_uninstall()
{
    global $DB;

    $tables_to_drop = array(
        PluginProjectbridgeEntity::$table_name,
        PluginProjectbridgeContract::$table_name,
    );

    $drop_table_query = "DROP TABLE IF EXISTS `" . implode('`, `', $tables_to_drop) . "`";

    return $DB->query($drop_table_query) or die($DB->error());
}

/**
 * Hook called after showing an item
 *
 * @param array $post_show_data
 * @return void
 */
function plugin_projectbridge_post_show_item(array $post_show_data)
{
    if (
        !empty($post_show_data['item'])
        && is_object($post_show_data['item'])
    ) {
        switch (get_class($post_show_data['item'])) {
            case 'Entity':
                PluginProjectbridgeEntity::postShow($post_show_data['item']);
                break;

            case 'Contract':
                PluginProjectbridgeContract::postShow($post_show_data['item']);
                break;

            default:
                // nothing to do
        }
    }
}

/**
 * Hook called before the update of an entity
 *
 * @param Entity $entity
 * @return void
 */
function plugin_projectbridge_pre_entity_update(Entity $entity)
{
    if (
        $entity->canUpdate()
        && isset($entity->input['projectbridge_contract_id'])
    ) {
        if (empty($entity->input['projectbridge_contract_id'])) {
            $selected_contract_id = 0;
        } else {
            $selected_contract_id = (int) $entity->input['projectbridge_contract_id'];
        }

        $bridge_entity = new PluginProjectbridgeEntity($entity);
        $contract_id = $bridge_entity->getContractId();

        $post_data = array(
            'entity_id' => $entity->getId(),
            'contract_id' => $selected_contract_id,
        );

        if ($contract_id === null) {
            $bridge_entity->add($post_data);
        } else if ($selected_contract_id != $contract_id) {
            $post_data['id'] = $bridge_entity->getId();
            $bridge_entity->update($post_data);
        }
    }
}

/**
 * Hook called before the update of a contract
 *
 * @param Contract $contract
 * @return void
 */
function plugin_projectbridge_pre_contract_update(Contract $contract)
{
    if (
        $contract->canUpdate()
        && isset($contract->input['projectbridge_project_id'])
    ) {
        $selected_project_id = (int) $contract->input['projectbridge_project_id'];
        $bridge_contract = new PluginProjectbridgeContract($contract);
        $project_id = (int) $bridge_contract->getProjectId();

        if ($selected_project_id != $project_id) {
            $update_data = array(
                'id' => $bridge_contract->getId(),
                'contract_id' => $contract->getId(),
                'project_id' => $selected_project_id,
            );

            $bridge_contract->update($update_data);
        }
    }
}


/**
 * Hook called on creation of a contract
 *
 * @param Contract $contract
 * @return void
 * @todo remove update
 */
function plugin_projectbridge_contract_create(Contract $contract)
{
    if (
        $contract->canUpdate()
        && isset($contract->input['projectbridge_project_id'])
    ) {
        if (empty($contract->input['projectbridge_project_id'])) {
            $selected_project_id = 0;
        } else {
            $selected_project_id = (int) $contract->input['projectbridge_project_id'];
        }

        $bridge_contract = new PluginProjectbridgeContract($contract);
        $project_id = $bridge_contract->getProjectId();

        $post_data = array(
            'contract_id' => $contract->getId(),
            'project_id' => $selected_project_id,
        );

        if ($project_id === null) {
            $bridge_contract->add($post_data);
        } else if ($selected_project_id != $project_id) {
            $post_data['id'] = $bridge_contract->getId();
            $bridge_contract->update($post_data);
        }
    }
}
