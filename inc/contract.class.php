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

            $html_parts[] = '&nbsp;';
            $html_parts[] = '-';
            $html_parts[] = '&nbsp;';

            $nb_hours = $bridge_contract->getNbHours();
            $consumption = PluginProjectbridgeContract::_getProjectTaskDataByProjectId($project_id, 'consumption');
            $consumption_ratio = $consumption / $nb_hours;

            $html_parts[] = 'Consommation : ';
            $html_parts[] = $consumption . '/' . $nb_hours . ' heures';
            $html_parts[] = '&nbsp;';
            $html_parts[] = '(' . round($consumption_ratio * 100) . '%)';

            $plan_end_date = PluginProjectbridgeContract::_getProjectTaskDataByProjectId($project_id, 'plan_end_date');
            $end_date_reached = false;

            if (!empty($plan_end_date)) {
                $datediff = strtotime($plan_end_date) - time($plan_end_date);
                $end_date_delta = floor($datediff / (60 * 60 * 24));

                $html_parts[] = '&nbsp;';
                $html_parts[] = '-';
                $html_parts[] = '&nbsp;';

                if ($end_date_delta == 0) {
                    $end_date_reached = true;
                    $html_parts[] = 'Expire dans moins de 24h';
                } else {
                    $html_parts[] = 'Expire dans ' . $end_date_delta . ' jours';
                }
            }

            if (
                $consumption_ratio >= 1
                || $end_date_reached
            ) {
                $html_parts[] = '&nbsp;';
                $html_parts[] = '-';
                $html_parts[] = '&nbsp;';

                $html_parts[] = '<input type="submit" name="projectbridge_renew_contract" value="Renouveller le contrat" class="submit" />';
            }
        } else {
            $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/setup.templates.php?itemtype=Project&add=1" style="margin-left: 5px;" target="_blank">';
            $html_parts[] = 'Créer un projet ?';
            $html_parts[] = '</a>' . "\n";

            $html_parts[] = '<small>';
            $html_parts[] = 'Pensez à rafraîchir cette page après avoir créé le projet';
            $html_parts[] = '</small>' . "\n";
        }

        $html_parts[] = '<br />';
        $html_parts[] = '<br />';

        $html_parts[] = 'Nombre d\'heures :';
        $html_parts[] = '&nbsp;';
        $html_parts[] = '<input type="number" min="0" max="99999" step="6" name="projectbridge_project_hours" value="' . $bridge_contract->getNbHours() . '" style="width: 50px" />';

        return implode('', $html_parts);
    }

    /**
     * Get data from a project's task
     * @param  integer $project_id The project to get the data from
     * @param  string $data_field The data to get
     * @return mixed
     */
    private static function _getProjectTaskDataByProjectId($project_id, $data_field)
    {
        static $project_task;

        if ($project_task === null) {
            $project_task = new ProjectTask();
            $project_task->getFromDBByQuery("
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
            case 'consumption':
                $return = 0;

                if ($project_task->getId() > 0) {
                    $action_time = ProjectTask_Ticket::getTicketsTotalActionTime($project_task->getId());

                    if ($action_time > 0) {
                        $return = $action_time / 3600;
                    }
                }

                break;

            case 'plan_end_date':
                if (!empty($project_task->fields['plan_end_date'])) {
                    $return = $project_task->fields['plan_end_date'];
                }

                break;

            default:
                // nothing to do
        }

        return $return;
    }
}
