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

    public static function getMenuName()
    {
        //return __('ProjectBridge project tasks', 'projectbridge');
        return 'ProjectBridge';
    }

    /**
     * Add menu content
     *
     * @return array
     */
    public static function getMenuContent()
    {
        $menu = parent::getMenuContent();

        $menu = [
            'title' => self::getMenuName(),
            'page' => Plugin::getPhpDir('projectbridge', false) . '/front/projecttask.php',
            'icon' => self::getIcon(),
        ];

        return $menu;
    }

    public static function getIcon()
    {
        return "fa fa-tasks";
    }

    /**
     * Give cron information
     *
     * @param $name string Cron name
     * @return array of information
     */
    public static function cronInfo($name)
    {
        $return = [];
        switch ($name) {
            case 'ProcessTasks':
                $return = [
                    'description' => __('Project task treatment', 'projectbridge'),
                ];
                break;

            case 'UpdateProgressPercent':
                $return = [
                    'description' => __('Update percentage counters performed in project tasks', 'projectbridge'),
                ];
                break;
            case 'AlertContractsToRenew':
                $return = [
                    'description' => __('Contract Alert to renew', 'projectbridge'),
                ];
                break;
            case 'AlertContractsOverQuota':
                $return = [
                    'description' => __('Send email alert containing contract with consummation over quota', 'projectbridge'),
                ];
                break;
        }

        return $return;
    }

    /**
     * Cron action to process tasks (close if expired or quota reached)
     *
     * @param CronTask|null $cron_task for log, if NULL display (default NULL)
     * @return integer 1 if an action was done, 0 if not
     */
    public static function cronProcessTasks($cron_task = null)
    {
        global $DB;
        if (class_exists('PluginProjectbridgeConfig')) {
            $plugin = new Plugin();

            if (!$plugin->isActivated(PluginProjectbridgeConfig::NAMESPACE)) {
                echo __('Disabled plugin') . "<br />\n";
                return 0;
            }
        } else {
            echo __('Plugin is not installed', 'projectbridge') . "<br />\n";
            return 0;
        }

        $nb_successes = 0;

        $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');

        if (empty($state_closed_value)) {
            echo __('Please define the correspondence of the "Closed" status.', 'projectbridge') . "<br />\n";
            return 0;
        }

        $ticket_request_type = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

        if (empty($ticket_request_type)) {
            echo __('Please define the correspondence of the "Renewal" status.', 'projectbridge') . "<br />\n";
            return 0;
        }

        $task = new ProjectTask();


        global $DB;
        $bridgeContract = new PluginProjectbridgeContract();
        $tasks = [];
        foreach ($DB->request([
            'SELECT' => 'pt.*',
            'DISTINCT' => true,
            'FROM' => $bridgeContract::getTable() . ' AS c',
            'INNER JOIN' => [
                $task->getTable() . ' AS pt' => [
                    'FKEY' => [
                        'c' => 'project_id',
                        'pt' => 'projects_id'
                    ]
                ]
            ],
            'WHERE' => ['projectstates_id' => ['!=', $state_closed_value]]
        ]) as $data) {
            $tasks[] = $data;
        }

        $nb_successes += count(PluginProjectbridgeTask::closeTaskAndCreateExcessTicket($tasks));

        $cron_task->addVolume($nb_successes);

        echo __('Finish') . "<br />\n";

        return ($nb_successes > 0) ? 1 : 0;
    }

    /**
     * close all open tasks and create excess ticket
     * @param array $tasks
     * @param boolean $fromCronTask
     * @return type
     */
    public function closeTaskAndCreateExcessTicket($tasks, $fromCronTask = true)
    {
        $newTicketIds = [];
        foreach ($tasks as $task_data) {
            $expired = false;
            $timediff = 0;
            $action_time = null;

            if (!empty($task_data['plan_end_date']) && time() >= strtotime($task_data['plan_end_date'])) {
                $expired = true;
            }

            if (!empty($task_data['planned_duration'])) {
                $action_time = PluginProjectbridgeContract::getTicketsTotalActionTime($task_data['id']);
                $timediff = $action_time - $task_data['planned_duration'];
            }

            if ($expired || ($timediff >= 0 && $action_time !== null)) {
                $brige_task = new PluginProjectbridgeTask($task_data['id']);
                $newTicketIds = array_merge($newTicketIds, $brige_task->closeTask($expired, ($action_time !== null) ? $timediff : 0, $fromCronTask));

                if ($fromCronTask && $timediff > 0) {
                    $brige_task->createExcessTicket($timediff, $task_data['entities_id']);
                }
            }
        }

        return array_unique($newTicketIds);
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
        $newTicketIds = [];

        echo 'Fermeture de la tâche ' . $this->_task->getId() . "<br />\n";

        // close task
        $closed = $this->_task->update([
            'id' => $this->_task->getId(),
            'projectstates_id' => PluginProjectbridgeState::getProjectStateIdByStatus('closed'),
        ]);

        if ($closed) {
            $ticket_link = new ProjectTask_Ticket();
            $ticket_links = $ticket_link->find(['projecttasks_id' => $this->_task->getId()]);

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
                $elementsAssociateToExcessTicket = PluginProjectbridgeConfig::getConfValueByName('ElementsAssociateToExcessTicket') ?: [];

                foreach ($ticket_links as $ticket_link) {
                    $ticket = new Ticket();

                    if ($ticket->getFromDB($ticket_link['tickets_id']) && !in_array($ticket->fields['status'], $ticket_states_to_ignore) && $ticket->fields['is_deleted'] == 0) {
                        // use only not deleted not resolved not closed tickets
                        // close the ticket
                        $old_status = $ticket->fields['status'];

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
                            $additional_content = '(' . __('This ticket comes from an automatic copy of the ticket', 'projectbridge') . ' ' . $old_ticket_id . ' ' . __('following the overtaking of hours or the expiry of the maintenance contract', 'projectbridge') . ')';
                            $ticket_fields['content'] = $additional_content . $ticket_fields['content'];
                            $ticket_fields['content'] = str_replace("'", "\'", $ticket_fields['content']);
                            $ticket_fields['content'] = str_replace('"', '\"', $ticket_fields['content']);
                            $ticket_fields['actiontime'] = 0;
                            $ticket_fields['requesttypes_id'] = $ticket_request_type;
                            $ticket_fields['status'] = $old_status;

                            $ticket = new Ticket();

                            if ($ticket->add($ticket_fields)) {
                                // force ticket update
                                if ($old_status > 1) {
                                    $ticket->update([
                                        'id' => $ticket->getId(),
                                        'users_id_recipient' => $ticket_fields['users_id_recipient'],
                                    ]);
                                }

                                // link the clone to the old ticket
                                $ticket_link = new Ticket_Ticket();
                                $ticket_link->add([
                                    'tickets_id_1' => $ticket->getId(),
                                    'tickets_id_2' => $old_ticket_id,
                                    'link' => Ticket_Ticket::LINK_TO,
                                ]);

                                if (in_array('requester_groups', $elementsAssociateToExcessTicket) || in_array('assign_groups', $elementsAssociateToExcessTicket) || in_array('watcher_group', $elementsAssociateToExcessTicket)) {
                                    // link groups (requester_groups, watcher_group, assign_groups)
                                    $group_ticket = new Group_Ticket();
                                    $ticket_groups = $group_ticket->find(['tickets_id' => $old_ticket_id]);
                                    foreach ($ticket_groups as $ticket_group_data) {
                                        if ((in_array('requester_groups', $elementsAssociateToExcessTicket) && $ticket_group_data['type'] == CommonITILActor::REQUESTER) || (in_array('assign_groups', $elementsAssociateToExcessTicket) && $ticket_group_data['type'] == CommonITILActor::ASSIGN) || (in_array('watcher_group', $elementsAssociateToExcessTicket) && $ticket_group_data['type'] == CommonITILActor::OBSERVER)
                                        ) {
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
                                    }
                                }

                                if (in_array('requester', $elementsAssociateToExcessTicket) || in_array('assign_technician', $elementsAssociateToExcessTicket) || in_array('watcher_user', $elementsAssociateToExcessTicket)) {
                                    // link users (requesters, observers, technicians)
                                    $ticket_user = new Ticket_User();
                                    $ticket_users = $ticket_user->find(['tickets_id' => $old_ticket_id]);
                                    foreach ($ticket_users as $ticket_user_data) {
                                        if ((in_array('requester', $elementsAssociateToExcessTicket) && $ticket_user_data['type'] == CommonITILActor::REQUESTER)
                                            || (in_array('assign_technician', $elementsAssociateToExcessTicket) && $ticket_user_data['type'] == CommonITILActor::ASSIGN)
                                            || (in_array('watcher_user', $elementsAssociateToExcessTicket) && $ticket_user_data['type'] == CommonITILActor::OBSERVER)
                                          ) {

                                            // test if not exist aready in database
                                            $ticket_user_exist = $ticket_user->find(
                                                [
                                                        'tickets_id' => $ticket->getId(),
                                                        'users_id' => $ticket_user_data['users_id'],
                                                        'type' => $ticket_user_data['type']
                                                    ]
                                            );
                                            if (!count($ticket_user_exist)) {
                                                $ticket_user = new Ticket_User();
                                                $ticket_user->add([
                                                    'tickets_id' => $ticket->getId(),
                                                    'users_id' => $ticket_user_data['users_id'],
                                                    'type' => $ticket_user_data['type'],
                                                    'use_notification' => $ticket_user_data['use_notification'],
                                                    'alternative_email' => $ticket_user_data['alternative_email'],
                                                ]);
                                            }
                                        }
                                    }
                                }

                                if (in_array('tickets', $elementsAssociateToExcessTicket)) {
                                    // reproduce links to other tickets
                                    $ticket_link = new Ticket_Ticket();
                                    $ticket_links = $ticket_link->find(['tickets_id_1' => $old_ticket_id]);
                                    foreach ($ticket_links as $ticket_link_data) {
                                        $ticket_link = new Ticket_Ticket();
                                        $ticket_link->add([
                                            'tickets_id_1' => $ticket->getId(),
                                            'tickets_id_2' => $ticket_link_data['tickets_id_2'],
                                            'link' => $ticket_link_data['link'],
                                        ]);
                                    }
                                }

                                if (in_array('followups', $elementsAssociateToExcessTicket)) {
                                    // add followups
                                    $ticket_followup = new ITILFollowup();
                                    $ticket_followups = $ticket_followup->find(['items_id' => $old_ticket_id, 'itemtype' => 'Ticket']);
                                    foreach ($ticket_followups as $ticket_followup_data) {
                                        $ticket_new_followup_data = array_diff_key($ticket_followup_data, ['id' => null]);
                                        $ticket_new_followup_data['items_id'] = $ticket->getId();
                                        $ticket_new_followup_data['itemtype'] = 'Ticket';
                                        $ticket_new_followup_data['content'] = str_replace("'", "\'", $ticket_new_followup_data['content']);
                                        $ticket_new_followup_data['content'] = str_replace('"', '\"', $ticket_new_followup_data['content']);

                                        $ticket_followup = new ITILFollowup();
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
                                }

                                if (in_array('documents', $elementsAssociateToExcessTicket)) {
                                    // add documents
                                    $document_item = new Document_Item();
                                    $ticket_document_items = $document_item->find(
                                        [
                                                'items_id' => $old_ticket_id,
                                                'itemtype' => 'Ticket'
                                            ]
                                    );
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
                                }

                                if (in_array('tasks', $elementsAssociateToExcessTicket)) {
                                    // add tasks
                                    $ticket_task = new TicketTask();
                                    $ticket_tasks = $ticket_task->find(['tickets_id' => $old_ticket_id]);
                                    foreach ($ticket_tasks as $ticket_task_data) {
                                        $ticket_new_task_data = array_diff_key($ticket_task_data, ['id' => null, 'actiontime' => null, 'begin' => null, 'end' => null]);
                                        $ticket_new_task_data['tickets_id'] = $ticket->getId();
                                        $ticket_new_task_data['content'] = str_replace("'", "\'", $ticket_new_task_data['content']);
                                        $ticket_new_task_data['content'] = str_replace('"', '\"', $ticket_new_task_data['content']);
                                        $ticket_new_task_data['uuid'] = \Ramsey\Uuid\Uuid::uuid4();

                                        $ticket_task = new TicketTask();
                                        $ticket_task->add($ticket_new_task_data);
                                    }
                                }

                                if (in_array('solutions', $elementsAssociateToExcessTicket)) {
                                    // add solution
                                    $solution = new ITILSolution();
                                    $solutions = $solution->find(['items_id' => $old_ticket_id, 'itemtype' => 'Ticket']);
                                    foreach ($solutions as $solution_data) {
                                        $ticket_new_solution_data = array_diff_key($solution_data, ['id' => null]);
                                        $ticket_new_solution_data['items_id'] = $ticket->getId();
                                        $ticket_new_solution_data['itemtype'] = 'Ticket';
                                        $ticket_new_solution_data['content'] = str_replace("'", "\'", $ticket_new_solution_data['content']);
                                        $ticket_new_solution_data['content'] = str_replace('"', '\"', $ticket_new_solution_data['content']);

                                        $solution = new ITILFollowup();
                                        $ticket_solution_id = $solution->add($ticket_new_solution_data);

                                        if ($ticket_solution_id) {
                                            $solution->update([
                                                'id' => $ticket_solution_id,
                                                'date_approval' => $solution_data['date_approval'],
                                                'date_mod' => $solution_data['date_mod'],
                                                'date_creation' => $solution_data['date_creation'],
                                            ]);
                                        }
                                    }
                                }

                                if (in_array('logs', $elementsAssociateToExcessTicket)) {
                                    // add solution
                                    $log = new Log();
                                    $logs = $log->find(
                                        [
                                                'items_id' => $old_ticket_id,
                                                'id_search_option' => 24,
                                                'itemtype' => 'Ticket'
                                            ],
                                        ['id' => 'DESC'],
                                        1
                                    );
                                    if (!empty($logs)) {
                                        $ticket_new_log_data = array_diff_key(current($logs), ['id' => null,]);
                                        $ticket_new_log_data['items_id'] = $ticket->getId();
                                        $ticket_new_log_data['user_name'] = str_replace("'", "\'", $ticket_new_log_data['user_name']);
                                        $ticket_new_log_data['user_name'] = str_replace('"', '\"', $ticket_new_log_data['user_name']);
                                        $ticket_new_log_data['new_value'] = str_replace("'", "\'", $ticket_new_log_data['new_value']);
                                        $ticket_new_log_data['new_value'] = str_replace('"', '\"', $ticket_new_log_data['new_value']);

                                        $log_id = $log->add($ticket_new_log_data);

                                        if ($log_id) {
                                            $glpi_time_before = $_SESSION['glpi_currenttime'];
                                            $_SESSION['glpi_currenttime'] = $ticket_new_log_data['date_mod'];

                                            $log->update([
                                                'id' => $log_id,
                                                'date_mod' => $ticket_new_log_data['date_mod'],
                                            ]);

                                            $_SESSION['glpi_currenttime'] = $glpi_time_before;
                                        }
                                    }
                                }

                                // special case when old status is new and the ticket have assignments
                                if ($old_status == 1) {
                                    $ticket->update([
                                        'id' => $ticket->getId(),
                                        'users_id_recipient' => $ticket_fields['users_id_recipient'],
                                        'status' => $old_status,
                                    ]);
                                }
                                array_push($newTicketIds, $ticket->getId());
                                //$nb_successes++;
                            }
                        }
                    }
                }
            }
        }

        //if (count($newTicketIds) > 0) {
        // envoi des notifications
        $recipients = PluginProjectbridgeConfig::getRecipients();
        echo __('find', 'projectbridge') . count($recipients) . ' ' . __('person(s) to alert', 'projectbridge') . "<br />\n";

        if (count($recipients)) {
            global $CFG_GLPI;

            $contract = null;
            $projectId = $this->_task->fields['projects_id'];
            $project = new Project();
            $project->getFromDB($projectId);
            // search contract throw projectbridge_contracts
            $bridgeContract = new PluginProjectbridgeContract();
            $contractId = $bridgeContract->getFromDBByCrit(['project_id' => $projectId]);
            if ($contractId) {
                $contract = (new Contract())->find(['id'=> $contractId]);
            }

            $subject = __('project Task') . ' "' . $project->fields['name'] . '" ' . __('closed');

            $contract = null;
            $projectId = $this->_task->fields['projects_id'];
            $project = new Project();
            $project->getFromDB($projectId);
            // search contract throw projectbridge_contracts
//            $bridgeContract = new PluginProjectbridgeContract();
            $bridgeContracts = (new PluginProjectbridgeContract())->find(['project_id' => $projectId]);
            foreach ($bridgeContracts as $bridgeContract) {
                $contract = (new Contract())->getById($bridgeContract['contract_id']);
            }

            $subject = __('Project task') . ' "' . $project->fields['name'] . '" ' . __('Closed');

            $html_parts = [];
            $html_parts[] = '<p>' . "\n";
            $html_parts[] = __('Hello', 'projectbridge');
            $html_parts[] = ',<br />';
            $html_parts[] = __('The open task of the project', 'projectbridge') . ' <a href="' . rtrim($CFG_GLPI['url_base'], '/') . '/front/projecttask.form.php?id=' . $this->_task->getId() . '">' . $project->fields['name'] . '</a> ' . __('just closed', 'projectbridge');
            $html_parts[] = '<br />';
            //$html_parts[] = 'Client : ';
            if ($contract) {
                $html_parts[] = '<br />';
                $html_parts[] = 'Numéro de contrat : ' . $contract->getField('num');
                $html_parts[] = '<br />';
                $html_parts[] = 'URL du contrat =  :  <a href="' . rtrim($CFG_GLPI['url_base'], '/') . '/front/contract.form.php?id=' . $contract->getField('id') . '">' . $contract->getField('name') . '</a>';
            }
            $html_parts[] = '<br />';
            $html_parts[] = __('Reason', 'projectbridge') . '(s) :';
            $html_parts[] = '<br />';
            $html_parts[] = __('Expired_period', 'projectbridge') . ' : ' . ($expired ? __('Yes') : __('No'));
            $html_parts[] = '<br />';
            $html_parts[] = __('Overtaking_minutes_credit', 'projectbridge') . ' : ' . ($action_time > 0 ? __('Yes') : __('No'));

            $html_parts[] = '</p>' . "\n";

            foreach ($recipients as $recipient) {
                PluginProjectbridgeConfig::notify(implode('', $html_parts), $recipient['email'], $recipient['name'], $subject);
            }
        }
        //}
        // exec update percent crontask
        self::cronUpdateProgressPercent();


        return $newTicketIds;
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
            'name' => addslashes(__('Adjustment ticket', 'projectbridge')),
            'content' => addslashes(__('Time Adjustment ticket', 'projectbridge')),
            'actiontime' => 0,
            'requesttypes_id' => $ticket_request_type,
            'status' => Ticket::CLOSED,
        ];

        $ticket = new Ticket();
        $return = $ticket->add($ticket_fields);

        if ($return) {
            $ticket_task = new TicketTask();
            $ticket_task_data = [
                'actiontime' => $timediff,
                'tickets_id' => $ticket->getId(),
                'content' => addslashes(__('Adjustment task', 'projectbridge')),
                'state' => 2 // fait
            ];


            $ticket_task->add($ticket_task_data);

            PluginProjectbridgeTicket::deleteProjectLinks($ticket->getId());
        }

        return $ticket->getId();
    }

    /**
     * Cron action to process tasks (close if expired or quota reached)
     *
     * @param CronTask|null $cron_task for log, if NULL display (default NULL)
     * @return integer 1 if an action was done, 0 if not
     */
    public static function cronUpdateProgressPercent($cron_task = null) {
        if (class_exists('PluginProjectbridgeConfig')) {
            $plugin = new Plugin();

            if (!$plugin->isActivated(PluginProjectbridgeConfig::NAMESPACE)) {
                echo __('Disabled plugin') . "<br />\n";
                return 0;
            }
        } else {
            echo __('Plugin is not installed') . "<br />\n";
            return 0;
        }

        $nb_successes = 0;
        $taskInfos = [];
        global $DB;

        // search projectTaskId associate to project that are present on projectbridge_contract data table
        $projectTask = new ProjectTask();
        $projectbridgeContract = new PluginProjectbridgeContract();
        foreach ($DB->request([
            'SELECT' => ['pt.id', 'pbc.contract_id'],
            'DISTINCT' => true,
            'FROM' => $projectTask->getTable() . ' AS pt',
            'INNER JOIN' => [
                $projectbridgeContract->getTable() . ' AS pbc' => [
                    'FKEY' => [
                        'pt' => 'projects_id',
                        'pbc' => 'project_id'
                    ]
                ]
            ],
            'WHERE' => ['pt.projectstates_id' => PluginProjectbridgeState::getProjectStateIdByStatus('in_progress')]
        ]) as $data) {
            $taskInfos[] = $data;
        }

        foreach ($taskInfos as $row) {
            if ($cron_task) {
                echo __('re-calculuation for projectTask', 'projectbridge') . ' ' . $row['id'] . "<br />\n";
            }
            PluginProjectbridgeTask::updateProjectTaskProgressPercent($row['id'], $row['contract_id']);
            $nb_successes++;
        }
        if ($cron_task) {
            $cron_task->addVolume($nb_successes);

            echo __('Finish') . "<br />\n";
        }

        return ($nb_successes > 0) ? 1 : 0;
    }

    public static function updateProjectTaskProgressPercent($taskId, $contract_id) {
        $projectTask = new ProjectTask();
        $contract = new Contract();
        $contract->getFromDB($contract_id);

        $bridge_contract = new PluginProjectbridgeContract($contract);
        $nb_hours = $bridge_contract->getNbHours();
        $consumption = PluginProjectbridgeContract::getTicketsTotalActionTime($taskId) / 3600;
        $ratio = round(($consumption * 100) / $nb_hours);
        $projectTask->update([
            'id' => $taskId,
            'percent_done' => $ratio,
        ]);
    }

    /**
     * Customize the duration columns in a list of project tasks
     *
     * @param  Project $project
     * @return void
     */
    public static function customizeDurationColumns(Project $project)
    {
        $task = new ProjectTask();
        $tasks = $task->find(['projects_id' => $project->getId()]);
        if (!empty($tasks)) {
            $duration_data = [];

            foreach ($tasks as $task_data) {
                $effective_duration = ProjectTask::getTotalEffectiveDuration($task_data['id']);

                $duration_data[$task_data['id']] = [
                    'planned_duration' => round($task_data['planned_duration'] / 3600 * 100) / 100,
                    'effective_duration' => round($effective_duration / 3600 * 100) / 100,
                    'percent' => round($effective_duration * $task_data['planned_duration'] / 100),
                ];
            }

            if (!empty($duration_data)) {
                $js_block = '
                    var
                        duration_data = ' . json_encode($duration_data) . ',
                        table_rows = $(".glpi_tabs table tr:not(:last)")
                    ;
                    
                    console.log(duration_data);

                    if (table_rows.length > 1) {
                        var
                            header_row = $(table_rows.get(0)),
                            header_cells = $("th", header_row),
                            task_rows = table_rows.not(header_row),
                            cells_map = {},
                            cell_obj,
                            cell_text
                        ;

                        header_cells.each(function(idx, elm) {
                            cell_obj = $(elm);
                            cell_text = cell_obj.text();

                            if (
                                cell_text == "Tâches de projet"
                                || cell_text == "Durée planifiée"
                                || cell_text == "Durée effective"
                                || cell_text == "Pourcentage effectué"
                            ) {
                                cells_map[cell_text] = idx + 1;
                            }
                        });

                        var
                            task_row,
                            task_link,
                            task_id,
                            planned_duration_cell,
                            effective_duration_cell,
                            percent_cell
                        ;

                        task_rows.each(function() {
                            task_row = $(this);
                            task_link = $("td:first a", task_row).attr("href");
                            task_id = undefined;

                            if (task_link) {
                                task_id = parseInt(task_link.replace("projecttask.form.php?id=", ""));

                                if (task_id == 0) {
                                    task_id = undefined;
                                }
                            }

                            if (
                                task_id !== undefined
                                && duration_data[task_id] !== undefined
                            ) {
                                planned_duration_cell = $("td:nth-child(" + cells_map["Durée planifiée"] + ")", task_row);
                                effective_duration_cell = $("td:nth-child(" + cells_map["Durée effective"] + ")", task_row);
                                percent_cell = $("td:nth-child(" + cells_map["Pourcentage effectué"] + ")", task_row);

                                if (planned_duration_cell.length) {
                                    planned_duration_cell.text(duration_data[task_id].planned_duration + " heure(s)");
                                }

                                if (effective_duration_cell.length) {
                                    effective_duration_cell.text(duration_data[task_id].effective_duration + " heure(s)");
                                }
                                if (percent_cell.length) {
                                    percent_cell_cell.text(duration_data[task_id].percent + " %");
                                }
                            }
                        });
                    }
                ';

                echo Html::scriptBlock($js_block);
            }
        }
    }

    /**
     * Cron action to alert on contracts to renew
     *
     * @param CronTask|null $task for log, if NULL display (default NULL)
     * @return integer 1 if an action was done, 0 if not
     */
    public static function cronAlertContractsToRenew($task = null)
    {
        if (class_exists('PluginProjectbridgeConfig')) {
            $plugin = new Plugin();

            if (!$plugin->isActivated(PluginProjectbridgeConfig::NAMESPACE)) {
                echo __('Disabled plugin') . "<br />\n";
                return 0;
            }
        } else {
            echo __('Plugin is not installed', 'projectbridge') . "<br />\n";
            return 0;
        }

        $nb_successes = 0;
        $recipients = PluginProjectbridgeConfig::getRecipients();
        echo count($recipients) . ' ' . __('person(s) to alert', 'projectbridge') . "<br />\n";

        if (count($recipients)) {
            $contracts = PluginProjectbridgeContract::getContractsToRenew();
            echo count($contracts) . ' ' . __('contract(s) to renew', 'projectbridge') . "<br />\n";

            $subject = count($contracts) . ' ' . __('contract(s) to renew', 'projectbridge');

            $html_parts = [];
            $html_parts[] = '<p>' . "\n";
            $html_parts[] = count($contracts) . ' ' . __('contract(s) to renew', 'projectbridge') . ' :';
            $html_parts[] = '</p>' . "\n";

            $html_parts[] = '<ol>' . "\n";

            global $CFG_GLPI;

            foreach ($contracts as $contract_id => $contract_data) {
                $html_parts[] = '<li>' . "\n";

                $html_parts[] = '<strong>' . __('Name') . '</strong> : ';
                $html_parts[] = '<a href="' . rtrim($CFG_GLPI['url_base'], '/') . '/front/contract.form.php?id=' . $contract_id . '">';
                $html_parts[] = $contract_data['contract']->fields['name'];
                $html_parts[] = '</a>';
                $html_parts[] = '<br />' . "\n";

                $entity = new Entity();
                $entity->getFromDB($contract_data['contract']->fields['entities_id']);
                $html_parts[] = '<strong>' . __('Entity') . '</strong> : ';
                $html_parts[] = $entity->fields['name'];
                $html_parts[] = '<br />' . "\n";

                $bridge_contract = new PluginProjectbridgeContract($contract_data['contract']);
                $project_id = $bridge_contract->getProjectId();

                // first search if open projecttask exist
                $search_close = false;
                $projectTaskObject = $bridge_contract::getProjectTaskOject($project_id, $search_close);
                if (!$projectTaskObject) {
                    // if not finded, search closed projecttask
                    $search_close = true;
                    $projectTaskObject = $bridge_contract::getProjectTaskOject($project_id, $search_close);
                }
                if ($projectTaskObject) {
                    $plan_end_date = $bridge_contract::getProjectTaskFieldValue($project_id, $search_close, 'plan_end_date');
                    $html_parts[] = '<strong>';
                    $html_parts[] = __('Expected end date', 'projectbridge');
                    $html_parts[] = '</strong> : ';
                    $html_parts[] = date('d-m-Y', strtotime($plan_end_date));
                    $html_parts[] = '<br />' . "\n";

                    $consumption = $bridge_contract::getProjectTaskConsumption($project_id, $search_close);
                    $html_parts[] = '<strong>';
                    $html_parts[] = __('Effective duration');
                    $html_parts[] = '</strong> : ';
                    $html_parts[] = round($consumption, 2);
                    $html_parts[] = ' | ';

                    $task_duration = $bridge_contract::getProjectTaskPlannedDuration($project_id, $search_close);
                    $html_parts[] = '<strong>';
                    $html_parts[] = __('Planned duration');
                    $html_parts[] = '</strong> : ';
                    $html_parts[] = round($task_duration, 2);
                    $html_parts[] = '<br />' . "\n";
                }

                $html_parts[] = '<br />' . "\n";
                $html_parts[] = '</li>' . "\n";
            }

            $html_parts[] = '</ol>' . "\n";

            $html_parts[] = '<br /><br /><hr/>' . "\n";
            $html_parts[] = '<p><small>'.__('This Email si send automacitly by the plugin projectBridge', 'projectbridge') .' ('.PLUGIN_PROJECTBRIDGE_VERSION.')</small></p>.';

            foreach ($recipients as $recipient) {
                $success = PluginProjectbridgeConfig::notify(implode('', $html_parts), $recipient['email'], $recipient['name'], $subject);

                if ($success) {
                    $nb_successes++;
                    $task->addVolume(count($contracts));
                }
            }
        }

        echo __('Finish') . "<br />\n";

        return ($nb_successes > 0) ? 1 : 0;
    }

    public static function cronAlertContractsOverQuota($task = null)
    {
        if (class_exists('PluginProjectbridgeConfig')) {
            $plugin = new Plugin();

            if (!$plugin->isActivated(PluginProjectbridgeConfig::NAMESPACE)) {
                echo __('Disabled plugin') . "<br />\n";
                return 0;
            }
        } else {
            echo __('Plugin is not installed', 'projectbridge') . "<br />\n";
            return 0;
        }

        $nb_successes = 0;
        $recipients = PluginProjectbridgeConfig::getRecipients();
        echo count($recipients) . ' ' . __('person(s) to alert', 'projectbridge') . "<br />\n";

        if (count($recipients)) {
            // récupération des contrat en cours
            $contracts = PluginProjectbridgeContract::getContractsOverQuota();
            $subject =  __('Contract(s) over limit quota alert', 'projectbridge').' ('.count($contracts).')';

            $html_parts = [];
            $html_parts[] = '<p>' . "\n";
            $html_parts[] = __('Contract(s) over limit quota alert', 'projectbridge') .' ('.count($contracts).') :';
            $html_parts[] = '</p>' . "\n";

            $html_parts[] = '<ol>' . "\n";

            global $CFG_GLPI;

            foreach ($contracts as $contract_id => $contract_data) {
                $html_parts[] = '<li>' . "\n";

                $html_parts[] = '<strong>' . __('Name') . '</strong> : ';
                $html_parts[] = '<a href="' . rtrim($CFG_GLPI['url_base'], '/') . '/front/contract.form.php?id=' . $contract_id . '">';
                $html_parts[] = $contract_data['contract']->fields['name'];
                $html_parts[] = '</a>';
                $html_parts[] = '<br />' . "\n";

                $html_parts[] = '<strong>' . __('Quota', 'projectbridge') . '</strong> : ';
                $html_parts[] = $contract_data['ratio'] .'% ('.round($contract_data['consumption']).'/'.$contract_data['nb_hours'].')' ;
                $html_parts[] = '<br />' . "\n";

                $entity = new Entity();
                $entity->getFromDB($contract_data['contract']->fields['entities_id']);
                $html_parts[] = '<strong>' . __('Entity') . '</strong> : ';
                $html_parts[] = $entity->fields['name'];
                $html_parts[] = '<br />' . "\n";
                $html_parts[] = '</li>' . "\n";
            }
            $html_parts[] = '</ol>' . "\n";

            $html_parts[] = '<br /><br /><hr/>' . "\n";
            $html_parts[] = '<p><small>'.__('This Email si send automacitly by the plugin projectBridge', 'projectbridge') .' ('.PLUGIN_PROJECTBRIDGE_VERSION.')</small></p>.';

            if (count($contracts)) {
                foreach ($recipients as $recipient) {
                    $success = PluginProjectbridgeConfig::notify(implode('', $html_parts), $recipient['email'], $recipient['name'], $subject);

                    if ($success) {
                        $nb_successes++;
                    }
                }
            }
            $task->addVolume(count($contracts));
        }

        echo __('Finish') . "<br />\n";

        return ($nb_successes > 0) ? 1 : 0;
    }
}
