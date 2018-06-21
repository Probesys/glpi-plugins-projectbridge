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
     * @param CronTask|null $task for log, if NULL display (default NULL)
     * @return integer 1 if an action was done, 0 if not
     */
    public static function cronProcessTasks($task = null)
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
            if (
                !empty($task_data['plan_end_date'])
                && time() >= strtotime($task_data['plan_end_date'])
            ) {
                $brige_task = new PluginProjectbridgeTask($task_data['id']);
                $nb_successes += $brige_task->closeTask();
                continue;
            }

            if (!empty($task_data['planned_duration'])) {
                $action_time = ProjectTask_Ticket::getTicketsTotalActionTime($task_data['id']);

                if ($action_time >= $task_data['planned_duration']) {
                    $brige_task = new PluginProjectbridgeTask($task_data['id']);
                    $nb_successes += $brige_task->closeTask();
                    continue;
                }
            }
        }

        echo 'Fini' . "<br />\n";

        return ($nb_successes > 0) ? 1 : 0;
    }

    /**
     * Close a task
     * Recreate all not closed or solved tickets linked to the task
     *
     * @return int
     */
    public function closeTask()
    {
        $nb_successes = 0;
        echo 'Fermeture de la tâche ' . $this->_task->getId() . "<br />\n";

        // $closed = $this->_task->update([
        //     'id' => $this->_task->getId(),
        //     'projectstates_id' => PluginProjectbridgeState::getProjectStateIdByStatus('closed'),
        // ]);
        $closed = true;

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
                    'solutiontypes_id' => null,
                    'solution' => null,
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
                        $ticket_fields = $ticket->fields;
                        // $closed = $ticket->update([
                        //     'id' => $ticket->getId(),
                        //     'status' => Ticket::CLOSED,
                        // ]);
                        $closed = true;

                        if ($closed) {
                            $old_ticket_id = $ticket->getId();
                            $ticket_fields = array_diff_key($ticket_fields, $ticket_fields_to_ignore);
                            $ticket_fields['content'] = str_replace("'", "\'", $ticket_fields['content']);
                            $ticket_fields['requesttypes_id'] = $ticket_request_type;

                            $ticket = new Ticket();

// liens ticket
                            if ($ticket->add($ticket_fields)) {
                                $ticket->update([
                                    'id' => $ticket->getId(),
                                    'users_id_recipient' => $ticket_fields['users_id_recipient'],
                                ]);

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
echo 'yeah ' . $ticket->getId();
                                $nb_successes++;
                            }
echo ' done';
die;

                        }
                    }
                }
            }
        }

        return $nb_successes;
    }
}
