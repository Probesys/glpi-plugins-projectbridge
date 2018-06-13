<?php

class PluginProjectbridgeTicket extends CommonDBTM
{
    private $_ticket;
    private $_project_id;

    public static $table_name = 'glpi_plugin_projectbridge_tickets';

    /**
     * Constructor
     *
     * @param Ticket $ticket
     */
    public function __construct(Ticket $ticket = null)
    {
        $this->_ticket = $ticket;
    }

    /**
     * Get the id of the contract linked to the ticket
     *
     * @param void
     * @return integer|null
     */
    public function getProjectId()
    {
        if ($this->_project_id === null) {
            $result = $this->getFromDBByQuery("WHERE ticket_id = " . $this->_ticket->getId());

            if ($result) {
                $this->_project_id = (int) $this->fields['project_id'];
            }
        }

        return $this->_project_id;
    }

    /**
     * Show HTML after ticket's link with project tasks has been shown
     *
     * @param  Ticket $ticket
     * @return void
     */
    public static function postShow(Ticket $ticket)
    {
        $html_parts = array();
        $html_parts[] = '<table>' . "\n";
        $html_parts[] = '<tr id="projectbridge_config">' . "\n";

        $html_parts[] = '<th>';
        $html_parts[] = 'Projet lié';
        $html_parts[] = '</th>' . "\n";

        if (true) {
            global $CFG_GLPI;

            $search_filters = array(
                'TRUE',
                '`is_deleted` = 0',
            );

            if (!empty($_SESSION['glpiactiveentities'])) {
                $search_filters[] = "`entities_id` IN (" . implode(', ', $_SESSION['glpiactiveentities']) . ")";
            }

            $project = new Project();
            $project_results = $project->find(implode(" AND ", $search_filters));
            $project_list = array(
                null => Dropdown::EMPTY_VALUE,
            );

            foreach ($project_results as $project_data) {
                if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_data['id'], 'exists')) {
                    $project_list[$project_data['id']] = $project_data['name'] . ' (' . $project_data['id'] . ')';
                }
            }

            $bridge_ticket = new PluginProjectbridgeTicket($ticket);
            $project_id = $bridge_ticket->getProjectId();

            if (!$project_id) {
                // so link between ticket and project in DB, get the contract for the current entity

                $entity = new Entity();
                $entity->getFromDB($_SESSION['glpiactive_entity']);
                $bridge_entity = new PluginProjectbridgeEntity($entity);
                $contract_id = $bridge_entity->getContractId();

                if ($contract_id) {
                    // default contract found, let's find the linked project

                    $contract = new Contract();
                    $contract->getFromDB($contract_id);
                    $bridge_contract = new PluginProjectbridgeContract($contract);
                    $project_id = $bridge_contract->getProjectId();

                    if (!isset($project_list[$project_id])) {
                        // project does not exist anymore
                        $project_id = null;
                    }
                } else {
                    $project_id = null;
                }
            }

            if (!PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists')) {
                $project_id = null;
            }

            $project_config = array(
                'value' => $project_id,
                'values' => $project_list,
                'display' => false,
            );

            $html_parts[] = '<th colspan="5">' . "\n";
            $html_parts[] = '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $ticket->getId() . '">' . "\n";
            $html_parts[] = Dropdown::showFromArray('projectbridge_project_id', $project_list, $project_config);

            if (!empty($project_id)) {
                $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/project.form.php?id=' . $project_id . '" style="margin-left: 10px" target="_blank">';
                $html_parts[] = 'Accéder au projet';
                $html_parts[] = '</a>' . "\n";
            }

            $html_parts[] = '<input type="submit" name="update" value="Faire la liaison" class="submit" style="margin-left: 10px" />' . "\n";
            $html_parts[] = '<input type="hidden" name="id" value="' . $ticket->getId() . '" />' . "\n";

            $html_parts[] = Html::closeForm(false);
            $html_parts[] = '</th>' . "\n";
        }

        $html_parts[] = '<th colspan="4">&nbsp;</th>' . "\n";

        $html_parts[] = '</tr>' . "\n";
        $html_parts[] = '</table>' . "\n";

        $html_parts[] = Html::scriptBlock('$(document).ready(function() {
            var projectbridge_config = $("#projectbridge_config");
            $("#ui-tabs-8 .tab_cadre_fixehov tr:last").after(projectbridge_config.clone());
            projectbridge_config.remove();

            $("#projectbridge_config .select2-container").remove();
            $("#projectbridge_config select").select2({
                dropdownAutoWidth: true
            });
            $("#projectbridge_config .select2-container").show();
            $("#projectbridge_config").before("<tr><td colspan=\'10\'>&nbsp;</td></tr>");
        });');

        echo implode('', $html_parts);
    }
}
