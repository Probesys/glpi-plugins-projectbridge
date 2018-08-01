<?php

class PluginProjectbridgeTask extends CommonDBTM
{
    /**
     * @var ProjectTask
     */
    private $_task;

    /**
     * Constructor
     *
     * @param int|null $task_id
     */
    public function __construct($task_id = null)
    {
        if (!empty($task_id)) {
            $task = new ProjectTask();

            if ($task->getFromDB($task_id)) {
                $this->_task = $task;
            }
        }
    }

    /**
     * Type name for cron
     *
     * @param  integer $nb
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return 'ProjectBridge';
    }

    /**
     * Give cron information
     *
     * @param $name string Cron name
     * @return array of information
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'ProcessTasks':
                return [
                    'description' => 'Traitement des tâches de projet',
                ];

                break;
        }

        return [];
    }

    /**
     * Cron action to process tasks (close if expired or quota reached)
     *
     * @param CronTask|null $cron_task for log, if NULL display (default NULL)
     * @return integer 1 if an action was done, 0 if not
     */
    public static function cronProcessTasks($cron_task = null)
    {
        if (class_exists('PluginProjectbridgeConfig')) {
            $plugin = new Plugin();

            if (!$plugin->isActivated(PluginProjectbridgeConfig::NAMESPACE)) {
                echo 'Plugin n\'est pas actif' . "<br />\n";
                return 0;
            }
        } else {
            echo 'Plugin n\'est pas installé' . "<br />\n";
            return 0;
        }

        $nb_successes = 0;

        $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');

        if (empty($state_closed_value)) {
            echo 'Veuillez définir la correspondance du statut "Clos".' . "<br />\n";
            return 0;
        }

        $ticket_request_type = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

        if (empty($ticket_request_type)) {
            echo 'Veuillez définir la correspondance du statut de ticket "Renouvellement".' . "<br />\n";
            return 0;
        }

        $task = new ProjectTask();
        $tasks = $task->find("
            TRUE
            AND projectstates_id != " . $state_closed_value . "
        ");

        foreach ($tasks as $task_data) {
            $expired = false;
            $timediff = 0;
            $action_time = null;

            if (
                !empty($task_data['plan_end_date'])
                && time() >= strtotime($task_data['plan_end_date'])
            ) {
                $expired = true;
            }

            if (!empty($task_data['planned_duration'])) {
                $action_time = ProjectTask_Ticket::getTicketsTotalActionTime($task_data['id']);
                $timediff = $action_time - $task_data['planned_duration'];
            }

            if (
                $expired
                || (
                    $timediff >= 0
                    && $action_time !== null
                )
            ) {
                $brige_task = new PluginProjectbridgeTask($task_data['id']);
                $nb_successes += $brige_task->closeTask($expired, ($action_time !== null) ? $timediff : 0);

                if ($timediff > 0) {
                    $brige_task->createExcessTicket($timediff, $task_data['entities_id']);
                }

                continue;
            }
        }

        $cron_task->addVolume($nb_successes);

        echo 'Fini' . "<br />\n";

        return ($nb_successes > 0) ? 1 : 0;
    }

    /**
     * Close a task
     * Recreate all not closed or solved tickets linked to the task
     *
     * @param boolean $expired
     * @param integer $action_time
     * @return int
     */
    public function closeTask($expired = false, $action_time = 0)
    {
        $nb_successes = 0;
        echo 'Fermeture de la tâche ' . $this->_task->getId() . "<br />\n";

        // close task
        $closed = $this->_task->update([
            'id' => $this->_task->getId(),
            'projectstates_id' => PluginProjectbridgeState::getProjectStateIdByStatus('closed'),
        ]);

        if ($closed) {
            $ticket_link = new ProjectTask_Ticket();
            $ticket_links = $ticket_link->find("
                TRUE
                AND projecttasks_id = " . $this->_task->getId() . "
            ");

            if (!empty($ticket_links)) {
                $ticket_states_to_ignore = [
                    Ticket::SOLVED,
                    Ticket::CLOSED,
                ];

                $ticket_fields_to_ignore = [
                    'id' => null,
                    'closedate' => null,
                    'solvedate' => null,
                    'users_id_lastupdater' => null,
                    'close_delay_stat' => null,
                    'solve_delay_stat' => null,
                ];

                $ticket_request_type = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

                foreach ($ticket_links as $ticket_link) {
                    $ticket = new Ticket();

                    if (
                        $ticket->getFromDB($ticket_link['tickets_id'])
                        && !in_array($ticket->fields['status'], $ticket_states_to_ignore)
                        && $ticket->fields['is_deleted'] == 0
                    ) {
                        // use only not deleted not resolved not closed tickets

                        // close the ticket
                        $ticket_fields = $ticket->fields;
                        $closed = $ticket->update([
                            'id' => $ticket->getId(),
                            'status' => Ticket::CLOSED,
                        ]);

                        if ($closed) {

                            // clone the old ticket into a new one WITHOUT the link to the project task

                            $old_ticket_id = $ticket->getId();
                            $ticket_fields = array_diff_key($ticket_fields, $ticket_fields_to_ignore);
                            $ticket_fields['name'] = str_replace("'", "\'", $ticket_fields['name']);
                            $ticket_fields['name'] = str_replace('"', '\"', $ticket_fields['name']);
                            $additional_content = "(Ce ticket est issu d'une copie automatique du ticket " . $old_ticket_id . " suite au dépassement d'heures ou l'expiration du contrat de maintenance)";
                            $ticket_fields['content'] = $additional_content . $ticket_fields['content'];
                            $ticket_fields['content'] = str_replace("'", "\'", $ticket_fields['content']);
                            $ticket_fields['content'] = str_replace('"', '\"', $ticket_fields['content']);
                            $ticket_fields['actiontime'] = 0;
                            $ticket_fields['requesttypes_id'] = $ticket_request_type;

                            $ticket = new Ticket();

                            if ($ticket->add($ticket_fields)) {
                                // force ticket update
                                $ticket->update([
                                    'id' => $ticket->getId(),
                                    'users_id_recipient' => $ticket_fields['users_id_recipient'],
                                ]);

                                // link groups (requesters, observers, technicians)
                                $group_ticket = new Group_Ticket();

                                $ticket_groups = $group_ticket->find("
                                    TRUE
                                    AND tickets_id = " . $old_ticket_id . "
                                ");

                                foreach ($ticket_groups as $ticket_group_data) {
                                    $group = new Group();

                                    if ($group->getFromDB($ticket_group_data['groups_id'])) {
                                        $group_ticket = new Group_Ticket();
                                        $group_ticket->add([
                                            'tickets_id' => $ticket->getId(),
                                            'groups_id' => $ticket_group_data['groups_id'],
                                            'type' => $ticket_group_data['type'],
                                        ]);
                                    }
                                }

                                // link users (requesters, observers, technicians)
                                $ticket_user = new Ticket_User();
                                $ticket_users = $ticket_user->find("
                                    TRUE
                                    AND tickets_id = " . $old_ticket_id . "
                                ");

                                foreach ($ticket_users as $ticket_user_data) {
                                    $ticket_user = new Ticket_User();
                                    $ticket_user->add([
                                        'tickets_id' => $ticket->getId(),
                                        'users_id' => $ticket_user_data['users_id'],
                                        'type' => $ticket_user_data['type'],
                                        'use_notification' => $ticket_user_data['use_notification'],
                                        'alternative_email' => $ticket_user_data['alternative_email'],
                                    ]);
                                }

                                // reproduce links to other tickets
                                $ticket_link = new Ticket_Ticket();
                                $ticket_links = $ticket_link->find("
                                    TRUE
                                    AND tickets_id_1 = " . $old_ticket_id . "
                                ");

                                foreach ($ticket_links as $ticket_link_data) {
                                    $ticket_link = new Ticket_Ticket();
                                    $ticket_link->add([
                                        'tickets_id_1' => $ticket->getId(),
                                        'tickets_id_2' => $ticket_link_data['tickets_id_2'],
                                        'link' => $ticket_link_data['link'],
                                    ]);
                                }

                                // link the clone to the old ticket
                                $ticket_link = new Ticket_Ticket();
                                $ticket_link->add([
                                    'tickets_id_1' => $ticket->getId(),
                                    'tickets_id_2' => $old_ticket_id,
                                    'link' => Ticket_Ticket::LINK_TO,
                                ]);

                                // add followups
                                $ticket_followup = new TicketFollowup();
                                $ticket_followups = $ticket_followup->find("
                                    TRUE
                                    AND tickets_id = " . $old_ticket_id . "
                                ");

                                foreach ($ticket_followups as $ticket_followup_data) {
                                    $ticket_new_followup_data = array_diff_key($ticket_followup_data, ['id' => null]);
                                    $ticket_new_followup_data['tickets_id'] = $ticket->getId();
                                    $ticket_new_followup_data['content'] = str_replace("'", "\'", $ticket_new_followup_data['content']);
                                    $ticket_new_followup_data['content'] = str_replace('"', '\"', $ticket_new_followup_data['content']);

                                    $ticket_followup = new TicketFollowup();
                                    $ticket_followup_id = $ticket_followup->add($ticket_new_followup_data);

                                    if ($ticket_followup_id) {
                                        $ticket_followup->update([
                                            'id' => $ticket_followup_id,
                                            'date' => $ticket_followup_data['date'],
                                            'date_mod' => $ticket_followup_data['date_mod'],
                                            'date_creation' => $ticket_followup_data['date_creation'],
                                        ]);
                                    }
                                }

                                // add documents
                                $document_item = new Document_Item();
                                $ticket_document_items = $document_item->find("
                                    TRUE
                                    AND items_id = " . $old_ticket_id . "
                                    AND itemtype = 'Ticket'
                                ");

                                foreach ($ticket_document_items as $ticket_document_item_data) {
                                    $ticket_new_document_item_data = array_diff_key($ticket_document_item_data, ['id' => null, 'date_mod' => null]);
                                    $ticket_new_document_item_data['items_id'] = $ticket->getId();

                                    $document_item = new Document_Item();
                                    $document_item_id = $document_item->add($ticket_new_document_item_data);

                                    if ($document_item_id) {
                                        $glpi_time_before = $_SESSION['glpi_currenttime'];
                                        $_SESSION['glpi_currenttime'] = $ticket_document_item_data['date_mod'];

                                        $document_item->update([
                                            'id' => $document_item_id,
                                            'date_mod' => $ticket_document_item_data['date_mod'],
                                        ]);

                                        $_SESSION['glpi_currenttime'] = $glpi_time_before;
                                    }
                                }

                                // add tasks
                                $ticket_task = new TicketTask();
                                $ticket_tasks = $ticket_task->find("
                                    TRUE
                                    AND tickets_id = " . $old_ticket_id . "
                                ");

                                foreach ($ticket_tasks as $ticket_task_data) {
                                    $ticket_new_task_data = array_diff_key($ticket_task_data, ['id' => null, 'actiontime' => null, 'begin' => null, 'end' => null]);
                                    $ticket_new_task_data['tickets_id'] = $ticket->getId();
                                    $ticket_new_task_data['content'] = str_replace("'", "\'", $ticket_new_task_data['content']);
                                    $ticket_new_task_data['content'] = str_replace('"', '\"', $ticket_new_task_data['content']);

                                    $ticket_task = new TicketTask();
                                    $ticket_task->add($ticket_new_task_data);
                                }

                                // add solution
                                $log = new Log();
                                $solutions = $log->find("
                                    TRUE
                                    AND items_id = " . $old_ticket_id . "
                                    AND id_search_option = 24
                                    AND itemtype = 'Ticket'
                                ", "id DESC", 1);

                                if (!empty($solutions)) {
                                    $ticket_new_solution_data = array_diff_key(current($solutions), ['id' => null, ]);
                                    $ticket_new_solution_data['items_id'] = $ticket->getId();
                                    $ticket_new_solution_data['user_name'] = str_replace("'", "\'", $ticket_new_solution_data['user_name']);
                                    $ticket_new_solution_data['user_name'] = str_replace('"', '\"', $ticket_new_solution_data['user_name']);
                                    $ticket_new_solution_data['new_value'] = str_replace("'", "\'", $ticket_new_solution_data['new_value']);
                                    $ticket_new_solution_data['new_value'] = str_replace('"', '\"', $ticket_new_solution_data['new_value']);

                                    $log_id = $log->add($ticket_new_solution_data);

                                    if ($log_id) {
                                        $glpi_time_before = $_SESSION['glpi_currenttime'];
                                        $_SESSION['glpi_currenttime'] = $ticket_new_solution_data['date_mod'];

                                        $log->update([
                                            'id' => $log_id,
                                            'date_mod' => $ticket_new_solution_data['date_mod'],
                                        ]);

                                        $_SESSION['glpi_currenttime'] = $glpi_time_before;
                                    }
                                }

                                $nb_successes++;
                            }
                        }
                    }
                }
            }
        }

        if ($nb_successes > 0) {
            $recipients = PluginProjectbridgeConfig::getRecipients();
            echo 'Trouvé ' . count($recipients) . ' personne(s) à alerter' . "<br />\n";

            if (count($recipients)) {
                global $CFG_GLPI;

                $project = new Project();
                $project->getFromDB($this->_task->fields['projects_id']);

                $subject = 'Tâche de projet "' . $project->fields['name'] . '" fermée';

                $html_parts = [];
                $html_parts[] = '<p>' . "\n";
                $html_parts[] = 'Bonjour.';
                $html_parts[] = '<br />';
                $html_parts[] = 'La tâche ouverte du projet <a href="' . rtrim($CFG_GLPI['root_doc'], '/') . '/front/project.form.php?id=' . $this->_task->getId() . '">' . $project->fields['name'] . '</a> vient d\'être fermée.';
                $html_parts[] = '<br />';
                $html_parts[] = '<br />';
                $html_parts[] = 'Motif(s) :';
                $html_parts[] = '<br />';
                $html_parts[] = 'Expirée : ' . ($expired ? 'oui' : 'non');
                $html_parts[] = '<br />';
                $html_parts[] = 'Dépassement : ' . ($action_time > 0 ? 'oui' : 'non');

                $html_parts[] = '</p>' . "\n";

                foreach ($recipients as $recipient) {
                    PluginProjectbridgeConfig::notify(implode('', $html_parts), $recipient['email'], $recipient['name'], $subject);
                }
            }
        }

        return $nb_successes;
    }

    /**
     * Create excess ticket
     *
     * @param int $timediff
     * @param int $entities_id
     * @return void
     */
    public function createExcessTicket($timediff, $entities_id)
    {
        $ticket_request_type = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

        $ticket_fields = [
            'entities_id' => $entities_id,
            'name' => 'Ticket de réajustement',
            'content' => 'Ticket de réajustement de temps',
            'actiontime' => 0,
            'requesttypes_id' => $ticket_request_type,
            'status' => Ticket::CLOSED,
        ];

        $ticket = new Ticket();

        if ($ticket->add($ticket_fields)) {
            $ticket_task_data = [
                'actiontime' => $timediff,
                'tickets_id' => $ticket->getId(),
                'content' => 'Tâche de réajustement',
            ];

            $ticket_task = new TicketTask();
            $ticket_task->add($ticket_task_data);

            PluginProjectbridgeTicket::deleteProjectLinks($ticket->getId());
        }
    }

    /**
     * Update the progress percentage of tasks linked to a ticket
     *
     * @param  integer $ticket_id
     * @param  integer $timediff
     * @return void
     */
    public static function updateProgressPercent($ticket_id, $timediff = 0)
    {
        $task_link = new ProjectTask_Ticket();
        $task_links = $task_link->find("
            TRUE
            AND tickets_id = " . $ticket_id . "
        ");

        if (!empty($task_links)) {
            foreach ($task_links as $task_link) {
                $task = new ProjectTask();

                if ($task->getFromDB($task_link['projecttasks_id'])) {
                    $total_actiontime = ProjectTask_Ticket::getTicketsTotalActionTime($task->getId());

                    $target = $total_actiontime + $timediff;
                    $target_percent = round(($target / $task->fields['planned_duration']) * 100);

                    if ($target_percent > 100) {
                        $target_percent = 100;
                    } else if ($target_percent < 0) {
                        $target_percent = 0;
                    }

                    $task->update([
                        'id' => $task->getId(),
                        'percent_done' => $target_percent,
                    ]);
                }
            }
        }
    }
}
