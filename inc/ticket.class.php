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
            var
                projectbridge_config = $("#projectbridge_config"),
                table = $("#ui-tabs-8 .tab_cadre_fixehov tr:last")
            ;

            if (table.length == 0) {
                table = $("#ui-tabs-8 form[id^=projecttaskticket_form]");
            }

            table.after(projectbridge_config.clone());
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

    /**
     * Show HTML after tickets linked to a task have been shown
     *
     * @param  ProjectTask $project_task
     * @return void
     */
    public static function postShowTask(ProjectTask $project_task)
    {
        global $CFG_GLPI;

        $get_tickets_actiontime_url = rtrim($CFG_GLPI['root_doc'], '/') . '/plugins/projectbridge/ajax/get_tickets_actiontime.php';
        $js_block = '
            var
                current_table_cell,
                table_parent,
                ticket_id,
                ticket_ids = []
            ;

            $(".tab_cadre_fixehov tr", "form[id^=massProjectTask_Ticket]").each(function() {
                current_table_cell = $("td.center:nth-child(2)", this);

                if (current_table_cell.length) {
                    if (table_parent === undefined) {
                        table_parent = current_table_cell.parents("table");
                    }

                    ticket_id = getTicketIdFromCell(current_table_cell);

                    if (ticket_id) {
                        ticket_ids.push(ticket_id);
                    }
                }
            });

            if (ticket_ids.length) {
                $.ajax("' . $get_tickets_actiontime_url . '", {
                    method: "POST",
                    cache: false,
                    data: {
                        ticket_ids: ticket_ids
                    }
                }).done(function(response, status) {
                    if (
                        status == "success"
                        && response.length
                    ) {
                        try {
                            var
                                tickets_actiontime = $.parseJSON(response),
                                current_row,
                                current_table_cell,
                                current_ticket_id,
                                current_action_time
                            ;

                            $("tr", table_parent).each(function(idx, elm) {
                                current_row = $(elm);

                                if (idx > 1) {
                                    current_table_cell = $("td.center:nth-child(2)", current_row);
                                    current_ticket_id = getTicketIdFromCell(current_table_cell);
                                    current_action_time = 0;

                                    if (tickets_actiontime[current_ticket_id] !== undefined) {
                                        current_action_time = tickets_actiontime[current_ticket_id];
                                    }

                                    current_row.append("<td>" + current_action_time + " heure(s)</td>");
                                } else if (idx == 0) {
                                    current_table_cell = $("th", current_row);
                                    current_table_cell.attr("colspan", parseInt(current_table_cell.attr("colspan")) + 1);
                                } else if (idx == 1) {
                                    current_row.append("<th>Durée</th>");
                                }
                            });
                        } catch (e) {
                        }
                    }
                });
            }

            /**
             * Get the ticket ID contained in the table table_cell
             *
             * @param jQueryObject table_cell
             * @return void
             */
            function getTicketIdFromCell(table_cell)
            {
                return parseInt($.trim(table_cell.text()).replace("ID : ", ""));
            };
        ';

        echo Html::scriptBlock($js_block);
    }

    /**
     * Delete project links from ticket
     *
     * @param  int $ticket_id
     * @return void
     */
    public static function deleteProjectLinks($ticket_id)
    {
        global $DB;

        // use a query as ProjectTask_Ticket can only get one item and does not return the number
        $get_nb_links_query = "
            SELECT
                COUNT(1) AS nb_links
            FROM
                glpi_projecttasks_tickets
            WHERE TRUE
                AND tickets_id = " . $ticket_id . "
        ";

        $result = $DB->query($get_nb_links_query);

        if (
            $result
            && $DB->numrows($result)
        ) {
            $results = $DB->fetch_assoc($result);
            $nb_links = (int) $results['nb_links'];
        } else {
            $nb_links = 0;
        }

        if ($nb_links != 0) {
            // todo: use a ProjectTask_Ticket method
            $delete_links_query = "
                DELETE FROM
                    glpi_projecttasks_tickets
                WHERE TRUE
                    AND tickets_id = " . $ticket_id . "
            ";

            $DB->query($delete_links_query);
            Log::history($ticket_id, 'Ticket', [0, '', 'Lien(s) avec tâche(s) de projet supprimé(s'], 0, Log::HISTORY_LOG_SIMPLE_MESSAGE);
        }

        // todo: use a native method
        $delete_bridge_links_query = "
            DELETE FROM
                " . PluginProjectbridgeTicket::$table_name . "
            WHERE TRUE
                AND ticket_id = " . $ticket_id . "
        ";

        $DB->query($delete_bridge_links_query);
    }

    /**
     * Show form for given massive action
     *
     * @param  MassiveAction $ma
     * @return boolean
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'deleteProjectLink' :
                echo "&nbsp;";
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;
                break;

            default:
                // nothing to do
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * Process a massive action
     *
     * @param  MassiveAction $ma
     * @param  CommonDBTM    $item
     * @param  array         $ids Item ids
     * @return void
     */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case 'deleteProjectLink':
                if ($item->getType() == 'Ticket') {
                    foreach ($ids as $ticket_id) {
                        $ticket = new Ticket();

                        if ($ticket->getFromDB($ticket_id)) {
                            PluginProjectbridgeTicket::deleteProjectLinks($ticket_id);
                            $ma->itemDone($item->getType(), $ticket_id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $ticket_id, MassiveAction::ACTION_KO);
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), $ids, MassiveAction::ACTION_KO);
                }

                return;
                break;

            default:
                // nothing to do
        }

        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }
}
