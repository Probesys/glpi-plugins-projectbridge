<?php

class PluginProjectbridgeContract extends CommonDBTM
{
    private $_contract;
    private $_project_id;
    private $_nb_hours;

    public static $table_name = 'glpi_plugin_projectbridge_contracts';

    /**
     * Constructor
     *
     * @param Contract $contract
     */
    public function __construct(Contract $contract)
    {
        $this->_contract = $contract;
    }

    /**
     * Get the id of the project linked to the contract
     *
     * @param void
     * @return integer|null
     */
    public function getProjectId()
    {
        if ($this->_project_id === null) {
            $this->_project_id = 0;
            $result = $this->getFromDBByQuery("WHERE contract_id = " . $this->_contract->getId());

            if ($result) {
                $this->_project_id = (int) $this->fields['project_id'];
            }
        }

        return $this->_project_id;
    }

    /**
     * Get number of hours for this contract
     *
     * @return integer|null
     */
    public function getNbHours()
    {
        if ($this->_nb_hours === null) {
            $result = $this->getFromDBByQuery("WHERE contract_id = " . $this->_contract->getId());

            if ($result) {
                $this->_nb_hours = (int) $this->fields['nb_hours'];
            }
        }

        return $this->_nb_hours;
    }

    /**
     * Display HTML after contract has been shown
     *
     * @param  Contract $contract
     * @return void
     */
    public static function postShow(Contract $contract)
    {
        $contract_id = $contract->getId();

        $html_parts = array();
        $html_parts[] = '<div style="display: none;">' . "\n";
        $html_parts[] = '<table>' . "\n";
        $html_parts[] = '<tr id="projectbridge_config">' . "\n";

        $html_parts[] = '<td>';
        $html_parts[] = 'Projet lié';
        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '<td colspan="2">' . "\n";

        if (empty($contract_id)) {
            // create
            $html_parts[] = PluginProjectbridgeContract::_getPostShowCreateHtml($contract);
        } else {
            // update
            $html_parts[] = PluginProjectbridgeContract::_getPostShowUpdateHtml($contract);
        }

        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '<td>';
        $html_parts[] = '&nbsp;';
        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '</tr>' . "\n";
        $html_parts[] = '</table>' . "\n";
        $html_parts[] = '</div>' . "\n";

        echo implode('', $html_parts);
        echo Html::scriptBlock('$(document).ready(function() {
            var projectbridge_config = $("#projectbridge_config");
            var target = $("#mainformtable tr.footerRow").next();

            if (!target.length) {
                target = $("#mainformtable tr:last");
            }

            target.before(projectbridge_config.clone());
            projectbridge_config.remove();

            $("#projectbridge_config .select2-container").remove();
            $("#projectbridge_config select").select2({
                dropdownAutoWidth: true
            });
            $("#projectbridge_config .select2-container").show();
        });');
    }

    /**
     * Get HTML to create a contract
     *
     * @param  Contract $contract
     * @return string HTML
     */
    private static function _getPostShowCreateHtml(Contract $contract)
    {
        $html_parts = array();

        $html_parts[] = 'Créer le projet :';
        $html_parts[] = '&nbsp;';
        $html_parts[] = Dropdown::showYesNo('projectbridge_create_project', 1, -1, array('display' => false));

        $html_parts[] = '<br />';
        $html_parts[] = '<br />';

        $html_parts[] = 'Nombre d\'heures :';
        $html_parts[] = '&nbsp;';
        $html_parts[] = '<input type="number" min="0" max="99999" step="6" name="projectbridge_project_hours" value="0" style="width: 50px" />';

        return implode('', $html_parts);
    }

    /**
     * Get HTML to update a contract
     *
     * @param  Contract $contract
     * @return string HTML
     */
    private static function _getPostShowUpdateHtml(Contract $contract)
    {
        $search_filters = array();

        if (!empty($_SESSION['glpiactiveentities'])) {
            $search_filters[] = "`entities_id` IN (" . implode(', ', $_SESSION['glpiactiveentities']) . ")";
        }

        $bridge_contract = new PluginProjectbridgeContract($contract);
        $project_id = $bridge_contract->getProjectId();

        $project = new Project();
        $project_results = $project->find(implode(' ', $search_filters));
        $project_list = array(
            null => Dropdown::EMPTY_VALUE,
        );

        foreach ($project_results as $project_data) {
            $project_list[$project_data['id']] = $project_data['name'];
        }

        $project_config = array(
            'value' => $project_id,
            'display' => false,
            'values' => $project_list,
        );

        $html_parts = array();
        $html_parts[] = Dropdown::showFromArray('projectbridge_project_id', $project_list, $project_config);

        global $CFG_GLPI;

        if (
            !empty($project_id)
            && isset($project_list[$project_id])
        ) {
            $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/project.form.php?id=' . $project_id . '" style="margin-left: 5px;" target="_blank">';
            $html_parts[] = 'Accéder au projet lié';
            $html_parts[] = '</a>' . "\n";

            $html_parts[] = PluginProjectbridgeContract::_getPostShowHoursHtml($bridge_contract->getNbHours());

            $html_parts[] = '<br />';
            $html_parts[] = '<br />';

            if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists')) {
                $consumption_ratio = 0;
                $nb_hours = $bridge_contract->getNbHours();

                if ($nb_hours) {
                    $consumption = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'consumption');
                    $consumption_ratio = $consumption / $nb_hours;

                    $html_parts[] = 'Consommation : ';
                    $html_parts[] = round($consumption, 2) . '/' . $nb_hours . ' heures';
                    $html_parts[] = '&nbsp;';
                    $html_parts[] = '(' . round($consumption_ratio * 100) . '%)';
                }

                $plan_end_date = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'plan_end_date');
                $end_date_reached = false;

                if (!empty($plan_end_date)) {
                    $datediff = strtotime($plan_end_date) - time();
                    $date_delta = $datediff / (60 * 60 * 24);
                    $end_date_delta = floor($date_delta);

                    if ($nb_hours) {
                        $html_parts[] = '&nbsp;';
                        $html_parts[] = '-';
                        $html_parts[] = '&nbsp;';
                    }

                    if ($end_date_delta == 0) {
                        $end_date_reached = true;
                        $html_parts[] = 'Expire dans moins de 24h';
                    } else if ($end_date_delta > 0) {
                        $html_parts[] = 'Expire dans ' . $end_date_delta . ' jours';
                    } else {
                        $end_date_reached = true;

                        if ($date_delta > -1) {
                            $html_parts[] = 'Expire aujourd\'hui';
                        } else {
                            $html_parts[] = 'Expiré il y a ' . (abs($end_date_delta)) . ' jours';
                        }
                    }
                }

                if (
                    $consumption_ratio >= 1
                    || $end_date_reached
                ) {
                    $html_parts[] = '&nbsp;';
                    $html_parts[] = '-';
                    $html_parts[] = '&nbsp;';

                    $html_parts[] = '<input type="submit" name="update" value="Renouveller le contrat" class="submit" />';
                }
            } else {
                $html_parts[] = 'Le projet lié n\'a pas de tâche ouverte';
            }
        } else {
            $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/setup.templates.php?itemtype=Project&add=1" style="margin-left: 5px;" target="_blank">';
            $html_parts[] = 'Créer un projet ?';
            $html_parts[] = '</a>' . "\n";

            $html_parts[] = '<small>';
            $html_parts[] = 'Pensez à rafraîchir cette page après avoir créé le projet';
            $html_parts[] = '</small>' . "\n";

            $html_parts[] = PluginProjectbridgeContract::_getPostShowHoursHtml($bridge_contract->getNbHours());
        }

        return implode('', $html_parts);
    }

    /**
     * Get HTML to manage hours
     *
     * @param  integer $nb_hours
     * @return string HTML
     */
    private static function _getPostShowHoursHtml($nb_hours)
    {
        $html_parts = array();

        $html_parts[] = '<br />';
        $html_parts[] = '<br />';

        $html_parts[] = 'Nombre d\'heures :';
        $html_parts[] = '&nbsp;';
        $html_parts[] = '<input type="number" min="0" max="99999" step="6" name="projectbridge_project_hours" value="' . $nb_hours . '" style="width: 50px" />';

        return implode('', $html_parts);
    }

    /**
     * Get data from a project's task
     * @param  integer $project_id The project to get the data from
     * @param  string $data_field The data to get
     * @return mixed
     */
    public static function getProjectTaskDataByProjectId($project_id, $data_field)
    {
        static $project_tasks;

        if ($project_tasks === null) {
            $project_tasks = array();
        }

        if (!isset($project_tasks[$project_id])) {
            $project_tasks[$project_id] = new ProjectTask();
            $project_tasks[$project_id]->getFromDBByQuery("
                WHERE TRUE
                    AND projects_id = " . $project_id . "
                    AND projectstates_id != 3
                ORDER BY
                    id ASC
                LIMIT 1
            ");
        }

        $return = null;

        switch ($data_field) {
            case 'exists':
                if ($project_tasks[$project_id]->getId() > 0) {
                    $return = true;
                } else {
                    $return = false;
                }

                break;

            case 'task_id':
                $return = $project_tasks[$project_id]->getId();
                break;

            case 'task':
                if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists')) {
                    $return = $project_tasks[$project_id];
                }

                break;

            case 'consumption':
                $return = 0;

                if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists')) {
                    $action_time = ProjectTask_Ticket::getTicketsTotalActionTime($project_tasks[$project_id]->getId());

                    if ($action_time > 0) {
                        $return = $action_time / 3600;
                    }
                }

                break;

            case 'plan_end_date':
                if (
                    PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists')
                    && !empty($project_tasks[$project_id]->fields['plan_end_date'])
                ) {
                    $return = $project_tasks[$project_id]->fields['plan_end_date'];
                }

                break;

            default:
                // nothing to do
        }

        return $return;
    }

    /**
     * Renew the task of the project linked to this contract
     *
     * @param void
     * @return void
     */
    public function renewProjectTask()
    {
        $project_id = $this->getProjectId();

        if (
            $project_id <= 0
            || !PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists')
        ) {
            return;
        }

        $nb_hours = $this->getNbHours();
        $nb_hours_to_use = $nb_hours;
        $delta_hours_to_use = 0;
        $consumption = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'consumption');

        if ($consumption > $nb_hours) {
            $delta_hours_to_use = $consumption - $nb_hours;
            $nb_hours_to_use -= $delta_hours_to_use;
        }

        $project_task = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'task');

        // close current task
        $closed = $project_task->update(array(
            'id' => $project_task->getId(),
            'projectstates_id' => 3, // "closed"
        ));

        if ($closed) {
            $task_start_date = date('Y-m-d');
            $delta_hours_to_use_str = '';

            if ($delta_hours_to_use != 0) {
                $delta_hours_to_use_str .= 'Dépassement de la tâche précédente : ';
                $delta_hours_to_use_str .= floor($delta_hours_to_use) . ' heures';

                if (floor($delta_hours_to_use) != $delta_hours_to_use) {
                    $delta_hours_to_use_str .= ' ';
                    $delta_hours_to_use_str .= (($delta_hours_to_use - floor($delta_hours_to_use)) * 60);
                    $delta_hours_to_use_str .= ' minutes';
                }
            }

            $project_task_data = array(
                // data from contract
                'name' => date('Y-m'),
                'entities_id' => $this->_contract->fields['entities_id'],
                'is_recursive' => $this->_contract->fields['is_recursive'],
                'projects_id' => $project_id,
                'content' => $this->_contract->fields['comment'],
                'comment' => $delta_hours_to_use_str,
                'plan_start_date' => $task_start_date,
                'plan_end_date' => (
                    !empty($this->_contract->fields['duration'])
                        ? Infocom::getWarrantyExpir($task_start_date, $this->_contract->fields['duration'])
                        : ''
                ),
                'planned_duration' => $nb_hours_to_use * 3600, // in seconds
                'projectstates_id' => 2, // "processing"

                // standard data to bootstrap task
                'projecttasktemplates_id' => 0,
                'projecttasks_id' => 0,
                'projecttasktypes_id' => 0,
                'percent_done' => 0,
                'is_milestone' => 0,
                'real_start_date' => '',
                'real_end_date' => '',
                'effective_duration' => 0,
            );

            // create the new project's task
            $project_task = new ProjectTask();
            $project_task->add($project_task_data);
        }
    }
}
