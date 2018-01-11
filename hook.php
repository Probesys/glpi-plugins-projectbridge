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
                INDEX (`entity_id`)
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
                `nb_hours` INT(11) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`contract_id`)
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
        $nb_hours = 0;

        if (empty($contract->input['projectbridge_project_id'])) {
            $selected_project_id = 0;
        } else {
            $selected_project_id = (int) $contract->input['projectbridge_project_id'];

            if (
                !empty($contract->input['projectbridge_project_hours'])
                && $contract->input['projectbridge_project_hours'] > 0
            ) {
                $nb_hours = (int) $contract->input['projectbridge_project_hours'];
            }
        }

        $bridge_contract = new PluginProjectbridgeContract($contract);
        $project_id = $bridge_contract->getProjectId();

        $post_data = array(
            'contract_id' => $contract->getId(),
            'project_id' => $selected_project_id,
            'nb_hours' => $nb_hours,
        );

        if ($project_id === null) {
            $bridge_contract->add($post_data);
        } else {
            $post_data['id'] = $bridge_contract->getId();
            $bridge_contract->update($post_data);
        }
    }
}


/**
 * Hook called after the creation of a contract
 *
 * @param Contract $contract
 * @return void
 */
function plugin_projectbridge_contract_add(Contract $contract)
{
    if (
        $contract->canUpdate()
        && isset($contract->input['projectbridge_create_project'])
        && $contract->input['projectbridge_create_project']
    ) {
        $nb_hours = 0;

        if (
            !empty($contract->input['projectbridge_project_hours'])
            && $contract->input['projectbridge_project_hours'] > 0
        ) {
            $nb_hours = (int) $contract->input['projectbridge_project_hours'];
        }

        $project_data = array(
            // data from contract
            'name' => $contract->input['name'],
            'entities_id' => $contract->fields['entities_id'],
            'is_recursive' => $contract->fields['is_recursive'],
            'content' => $contract->fields['comment'],
            'date' => $contract->fields['date_creation'],
            'date_mod' => $contract->fields['date_creation'],
            'date_creation' => $contract->fields['date_creation'],
            'plan_start_date' => (!empty($contract->fields['begin_date']) ? $contract->fields['begin_date'] : ''),

            // standard data to bootstrap project
            'comment' => '',
            'code' => '',
            'priority' => 3,
            'projectstates_id' => 0,
            'projecttypes_id' => 0,
            'users_id' => 0,
            'groups_id' => 0,
            'plan_end_date' => '',
            'real_start_date' => '',
            'real_end_date' => '',
            'percent_done' => 0,
            'show_on_global_gantt' => 0,
            'is_deleted' => 0,
            'projecttemplates_id' => 0,
            'is_template' => 0,
            'template_name' => '',
        );

        // create the project
        $project = new Project();
        $project_id = $project->add($project_data);

        if ($project_id) {
            $bridge_data = array(
                'contract_id' => $contract->getId(),
                'project_id' => $project_id,
                'nb_hours' => $nb_hours,
            );

            // link the project to the contract
            $bridge_contract = new PluginProjectbridgeContract($contract);
            $bridge_contract->add($bridge_data);

            $project_task_data = array(
                // data from contract
                'name' => 'Année ' . date('Y-m'),
                'entities_id' => $contract->fields['entities_id'],
                'is_recursive' => $contract->fields['is_recursive'],
                'projects_id' => $project_id,
                'content' => $contract->fields['comment'],
                'plan_start_date' => (!empty($contract->fields['begin_date']) ? $contract->fields['begin_date'] : ''),
                'plan_end_date' => (
                    !empty($contract->fields['begin_date'])
                    && !empty($contract->fields['duration'])
                        ? Infocom::getWarrantyExpir($contract->fields['begin_date'], $contract->fields['duration'])
                        : ''
                ),
                'planned_duration' => $nb_hours * 3600, // in seconds
                'projectstates_id' => 2, // "processing"

                // standard data to bootstrap project
                'projecttasktemplates_id' => 0,
                'projecttasks_id' => 0,
                'projecttasktypes_id' => 0,
                'percent_done' => 0,
                'is_milestone' => 0,
                'real_start_date' => '',
                'real_end_date' => '',
                'effective_duration' => 0,
                'comment' => '',
            );

            // create the project's task
            $project_task = new ProjectTask();
            $project_task->add($project_task_data);
        }
    }
}
