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

    if (!TableExists(PluginProjectbridgeTicket::$table_name)) {
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS `" . PluginProjectbridgeTicket::$table_name . "`
            (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `ticket_id` INT(11) NOT NULL,
                `project_id` INT(11) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`ticket_id`)
            )
            COLLATE='utf8_unicode_ci'
            ENGINE=MyISAM
        ";
        $DB->query($create_table_query) or die($DB->error());
    }

    if (!TableExists(PluginProjectbridgeConfig::$table_name)) {
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS `" . PluginProjectbridgeConfig::$table_name . "`
            (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`user_id`)
            )
            COLLATE='utf8_unicode_ci'
            ENGINE=MyISAM
        ";
        $DB->query($create_table_query) or die($DB->error());
    }

    if (!TableExists(PluginProjectbridgeState::$table_name)) {
        $create_table_query = "
            CREATE TABLE IF NOT EXISTS `" . PluginProjectbridgeState::$table_name . "`
            (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `status` VARCHAR(250) NOT NULL,
                `projectstates_id` INT(11) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX (`status`)
            )
            COLLATE='utf8_unicode_ci'
            ENGINE=MyISAM
        ";
        $DB->query($create_table_query) or die($DB->error());
    }

    // cron for alerts
    CronTask::Register('PluginProjectbridgeContract', 'AlertContractsToRenew', DAY_TIMESTAMP);

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
        PluginProjectbridgeTicket::$table_name,
        PluginProjectbridgeConfig::$table_name,
        PluginProjectbridgeState::$table_name,
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

            case 'Project':
                PluginProjectbridgeContract::postShowProject($post_show_data['item']);
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
 * @param boolean $force (optional)
 * @return void|integer|boolean
 */
function plugin_projectbridge_pre_entity_update(Entity $entity, $force = false)
{
    if (
        (
            $force === true
            || $entity->canUpdate()
        )
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
            return $bridge_entity->add($post_data);
        } else if ($selected_contract_id != $contract_id) {
            $post_data['id'] = $bridge_entity->getId();
            return $bridge_entity->update($post_data);
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
        && isset($contract->input['update'])
        && isset($contract->input['projectbridge_project_id'])
    ) {
        if ($contract->input['update'] != 'Confirmer le renouvellement') {
            // update contract

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

            if ($selected_project_id > 0) {
                $bridge_contract = new PluginProjectbridgeContract($contract);
                $project_id = $bridge_contract->getProjectId();

                $post_data = array(
                    'contract_id' => $contract->getId(),
                    'project_id' => $selected_project_id,
                    'nb_hours' => $nb_hours,
                );

                if (empty($project_id)) {
                    $bridge_contract->add($post_data);
                } else {
                    $post_data['id'] = $bridge_contract->getId();
                    $bridge_contract->update($post_data);
                }
            }
        } else {
            // renew the task of the project linked to the contract

            if (
                empty($contract->input['_projecttask_begin_date'])
                || empty($contract->input['_projecttask_end_date'])
                || empty($contract->input['projectbridge_nb_hours_to_use'])
            ) {
                Session::addMessageAfterRedirect('Veuillez remplir tous les champs de renouvellement.', false, ERROR);
                return false;
            }

            $bridge_contract = new PluginProjectbridgeContract($contract);
            $bridge_contract->renewProjectTask();
        }
    }
}


/**
 * Hook called after the creation of a contract
 *
 * @param Contract $contract
 * @param boolean $force (optional)
 * @return boolean|void
 */
function plugin_projectbridge_contract_add(Contract $contract, $force = false)
{
    if (
        $force === true
        || (
            $contract->canUpdate()
            && isset($contract->input['projectbridge_create_project'])
            && $contract->input['projectbridge_create_project']
        )
    ) {
        $nb_hours = 0;

        if (
            !empty($contract->input['projectbridge_project_hours'])
            && $contract->input['projectbridge_project_hours'] > 0
        ) {
            $nb_hours = (int) $contract->input['projectbridge_project_hours'];
        }

        $date_creation = '';
        $begin_date = '';

        if (
            !empty($contract->fields['begin_date'])
            && $contract->fields['begin_date'] != 'NULL'
        ) {
            $begin_date = date('Y-m-d H:i:s', strtotime($contract->fields['begin_date']));
        }

        if (empty($begin_date)) {
            Session::addMessageAfterRedirect('Le contrat n\'a pas de date de début. Le projet n\'a pas pu être créé.', false, ERROR);
            return false;
        }

        if (
            !empty($contract->fields['date_creation'])
            && $contract->fields['date_creation'] != 'NULL'
        ) {
            $date_creation = $contract->fields['date_creation'];
        } else if (
            !empty($contract->fields['date'])
            && $contract->fields['date'] != 'NULL'
        ) {
            $date_creation = $contract->fields['date'];
        } else {
            $date_creation = $begin_date;
        }

        if (!empty($date_creation)) {
            $date_creation = date('Y-m-d H:i:s', strtotime($date_creation));
        }

        $project_data = array(
            // data from contract
            'name' => $contract->input['name'],
            'entities_id' => $contract->fields['entities_id'],
            'is_recursive' => $contract->fields['is_recursive'],
            'content' => addslashes($contract->fields['comment']),
            'date' => $date_creation,
            'date_mod' => $date_creation,
            'date_creation' => $date_creation,
            'plan_start_date' => $begin_date,

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

        $state_in_progress_value = PluginProjectbridgeState::getProjectStateIdByStatus('in_progress');

        if (empty($state_in_progress_value)) {
            Session::addMessageAfterRedirect('La correspondance pour le statut "En cours" n\'a pas été définie. Le projet n\'a pas pu être créé.', false, ERROR);
            return false;
        }

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
                'name' => date('Y-m'),
                'entities_id' => $contract->fields['entities_id'],
                'is_recursive' => $contract->fields['is_recursive'],
                'projects_id' => $project_id,
                'content' => addslashes($contract->fields['comment']),
                'plan_start_date' => $begin_date,
                'plan_end_date' => (
                    !empty($begin_date)
                    && !empty($contract->fields['duration'])
                        ? date('Y-m-d H:i:s', strtotime(
                            Infocom::getWarrantyExpir($begin_date, $contract->fields['duration'])
                          ))
                        : ''
                ),
                'planned_duration' => $nb_hours * 3600, // in seconds
                'projectstates_id' => $state_in_progress_value, // "in progress"

                // standard data to bootstrap task
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

            return true;
        }
    }
}

/**
 * Hook called before the update of a ticket
 * If possible, link the ticket to the project task of the entity's default contract
 * If requested link the ticket to a specific project's task and set the project as default
 *
 * @param  Ticket $ticket
 * @return void
 */
function plugin_projectbridge_ticket_update(Ticket $ticket)
{
    if (
        !empty($ticket->input['update'])
        && $ticket->input['update'] == 'Faire la liaison'
        && !empty($ticket->input['projectbridge_project_id'])
    ) {
        $is_project_link_update = true;
        $contract_id = null;
    } else {
        $is_project_link_update = false;

        $entity = new Entity();
        $entity->getFromDB($ticket->fields['entities_id']);

        $bridge_entity = new PluginProjectbridgeEntity($entity);
        $contract_id = $bridge_entity->getContractId();
    }

    if (
        $is_project_link_update
        || $contract_id
    ) {
        // default contract for the entity found or update

        if (!$is_project_link_update) {
            $contract = new Contract();
            $contract->getFromDB($contract_id);

            $contract_bridge = new PluginProjectbridgeContract($contract);
            $project_id = $contract_bridge->getProjectId();
        } else {
            $project_id = (int) $ticket->input['projectbridge_project_id'];
        }

        if (
            $project_id
            && PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists')
        ) {
            // project linked to contract found & task exists

            PluginProjectbridgeTicket::deleteProjectLinks($ticket->getId());

            $task_id = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'task_id');

            // link the task to the ticket
            $project_task_link_ticket = new ProjectTask_Ticket();
            $project_task_link_ticket->add(array(
                'projecttasks_id' => $task_id,
                'tickets_id'      => $ticket->getId(),
            ));

            if ($is_project_link_update) {
                $bridge_ticket = new PluginProjectbridgeTicket($ticket);

                if ($bridge_ticket->getProjectId() > 0) {
                    $bridge_ticket->update(array(
                        'id' => $bridge_ticket->getId(),
                        'project_id' => $project_id,
                    ));
                } else {
                    $bridge_ticket->add(array(
                        'ticket_id' => $ticket->getId(),
                        'project_id' => $project_id,
                    ));
                }
            }
        }
    }
}

/**
 * Hook called after showing a ticket tab
 *
 * @param  array $tab_data
 * @return void
 */
function plugin_projectbridge_post_show_tab(array $tab_data)
{
    if (
        !empty($tab_data['item'])
        && is_object($tab_data['item'])
        && !empty($tab_data['options']['itemtype'])
        && (
            $tab_data['options']['itemtype'] == 'Projecttask_Ticket'
            || $tab_data['options']['itemtype'] == 'ProjectTask_Ticket'
            // naming is not uniform: https://github.com/glpi-project/glpi/issues/4177
        )
    ) {
        if ($tab_data['item'] instanceof Ticket) {
            PluginProjectbridgeTicket::postShow($tab_data['item']);
        } else if ($tab_data['item'] instanceof ProjectTask) {
            PluginProjectbridgeTicket::postShowTask($tab_data['item']);
        }
    }
}

/**
 * Add new search options
 *
 * @param string $itemtype
 * @return array
 */
function plugin_projectbridge_getAddSearchOptionsNew($itemtype)
{
    $options = [];

    switch ($itemtype) {
       case 'Entity':
            $options[] = [
                'id'            => 4200,
                'table'         => PluginProjectbridgeEntity::$table_name,

                // trick GLPI search into thinking we want the contract id so the addSelect function is called
                'field'         => 'contract_id',
                'name'          => 'ProjectBridge - Contrat par défaut',
                'massiveaction' => false,
            ];
            break;

        case 'Ticket':
            $options[] = [
                'id'    => 4201,
                'name'  => 'ProjectBridge',
            ];

            $options[] = [
                'id'            => 4202,
                'table'         => PluginProjectbridgeTicket::$table_name,
                'field'         => 'project_id',
                'name'          => 'Projet',
                'massiveaction' => false,
            ];

            $options[] = [
                'id'            => 4203,
                'table'         => PluginProjectbridgeTicket::$table_name,
                'field'         => 'project_id',
                'name'          => 'Tâche de projet',
                'massiveaction' => false,
            ];

            $options[] = [
                'id'            => 4204,
                'table'         => PluginProjectbridgeTicket::$table_name,
                'field'         => 'project_id',
                'name'          => 'Statut de tâche',
                'massiveaction' => false,
            ];
            break;

       default:
           // nothing to do
    }

    return $options;
}


/**
 * Add a custom select part to search
 *
 * @param string $itemtype
 * @param string $key
 * @param integer $offset
 * @return string
 */
function plugin_projectbridge_addSelect($itemtype, $key, $offset)
{
    global $CFG_GLPI;
    $select = "";

    switch ($itemtype) {
        case 'Entity':
            $contract_link = rtrim($CFG_GLPI['root_doc'], '/') . '/front/contract.form.php?id=';

            $select = "
                (CASE
                    WHEN `" . PluginProjectbridgeEntity::$table_name . "`.`contract_id` IS NOT NULL
                        THEN CONCAT(
                            '<a href=\"" . $contract_link . "',
                            `" . PluginProjectbridgeEntity::$table_name . "`.`contract_id`,
                            '\">',
                            `glpi_contracts`.`name`,
                            '</a>'
                        )
                    ELSE
                        NULL
                END)
                AS `ITEM_" . $offset . "`,
            ";
            break;

        case 'Ticket':
            if ($key == 4202) {
                // project name

                $project_link = rtrim($CFG_GLPI['root_doc'], '/') . '/front/project.form.php?id=';

                $select = "
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            '<a href=\"" . $project_link . "',
                            `glpi_projects`.`id`,
                            '\">',
                            `glpi_projects`.`name`,
                            '</a>'
                        )
                        SEPARATOR '$$##$$'
                    )
                    AS `ITEM_" . $offset . "`,
                ";
            } else if ($key == 4203) {
                // project task

                $task_link = rtrim($CFG_GLPI['root_doc'], '/') . '/front/projecttask.form.php?id=';

                $select = "
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            '<a href=\"" . $task_link . "',
                            `glpi_projecttasks`.`id`,
                            '\">',
                            `glpi_projecttasks`.`name`,
                            '</a>'
                        )
                        SEPARATOR '$$##$$'
                    )
                    AS `ITEM_" . $offset . "`,
                ";
            } else if ($key == 4204) {
                // project task status

                $select = "
                    GROUP_CONCAT(DISTINCT `glpi_projectstates`.`name` SEPARATOR '$$##$$')
                    AS `ITEM_" . $offset . "`,
                ";
            }

            break;

        default:
           // nothing to do
    }

    return $select;
}

/**
 * Add a custom left join to search
 *
 * @param string $itemtype
 * @param string $ref_table Reference table (glpi_...)
 * @param integer $new_table Plugin table
 * @param integer $linkfield
 * @param array $already_link_tables
 * @return string
 */
function plugin_projectbridge_addLeftJoin($itemtype, $ref_table, $new_table, $linkfield, $already_link_tables)
{
    $left_join = "";

    switch ($new_table) {
        case PluginProjectbridgeEntity::$table_name:
            $left_join = "
                LEFT JOIN `" . $new_table . "`
                    ON (`" . $new_table . "`.`entity_id` = `" . $ref_table . "`.`id`)
                LEFT JOIN `glpi_contracts`
                    ON (`" . $new_table . "`.`contract_id` = `glpi_contracts`.`id`)
            ";
            break;

        case PluginProjectbridgeTicket::$table_name:
            $left_join = "
                LEFT JOIN `glpi_projecttasks_tickets`
                    ON (`glpi_projecttasks_tickets`.`tickets_id` = `" . $ref_table . "`.`id`)
                LEFT JOIN `glpi_projecttasks`
                    ON (`glpi_projecttasks`.`id` = `glpi_projecttasks_tickets`.`projecttasks_id`)
                LEFT JOIN `glpi_projects`
                    ON (`glpi_projecttasks`.`projects_id` = `glpi_projects`.`id`)
                LEFT JOIN `glpi_projectstates`
                    ON (`glpi_projectstates`.`id` = `glpi_projecttasks`.`projectstates_id`)
            ";
            break;

        default:
            // nothing to do
    }

    return $left_join;
}

/**
 * Add a custom where to search
 *
 * @param  string $link
 * @param  string $nott
 * @param  string $itemtype
 * @param  string $key
 * @param  string $val        Search argument
 * @param  string $searchtype Type of search (contains, equals, ...)
 * @return string
 */
function plugin_projectbridge_addWhere($link, $nott, $itemtype, $key, $val, $searchtype)
{
    $where = "";

    switch ($itemtype) {
        case 'Entity':
            if ($searchtype == 'contains') {
                $where = $link . "`glpi_contracts`.`name` " . Search::makeTextSearch($val);
            }

            break;

        case 'Ticket':
            if ($searchtype == 'contains') {
                if ($key == 4202) {
                    // project name
                    $where = $link . "`glpi_projects`.`name` " . Search::makeTextSearch($val);
                } else if ($key == 4203) {
                    // project task
                    $where = $link . "`glpi_projecttasks`.`name` " . Search::makeTextSearch($val);
                } else if ($key == 4204) {
                    // project task status
                    $where = $link . "`glpi_projectstates`.`name` " . Search::makeTextSearch($val);
                }
            }

            break;

        default:
            // nothing to do
    }

    return $where;
}

/**
 * Add massive action options
 *
 * @param  string $type
 * @return array
 */
function plugin_projectbridge_MassiveActions($type)
{
    $massive_actions = [];

    switch ($type) {
        case 'Ticket':
            $massive_actions['PluginProjectbridgeTicket'. MassiveAction::CLASS_ACTION_SEPARATOR . 'deleteProjectLink'] = 'Supprimer le lien avec toute tâche de projet';
            break;

        default:
            // nothing to do
    }

    return $massive_actions;
}
