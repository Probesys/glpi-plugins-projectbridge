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
   public function __construct($task_id = null) {
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
   public static function getTypeName($nb = 0) {
       return 'ProjectBridge';
   }

    /**
     * Add menu content
     *
     * @return array
     */
   public static function getMenuContent() {
       $menu = parent::getMenuContent();

       $menu['title'] = __('ProjectBridge project tasks', 'projectbridge');
       $menu['page'] = '/plugins/projectbridge/front/projecttask.php';

       return $menu;
   }

    /**
     * Give cron information
     *
     * @param $name string Cron name
     * @return array of information
     */
   public static function cronInfo($name) {
      switch ($name) {
         case 'ProcessTasks':
              return [
                  'description' => __('Project task treatment', 'projectbridge'),
              ];

              break;

         case 'UpdateProgressPercent':
               return [
                  'description' => __('Update percentage counters performed in project tasks', 'projectbridge'),
               ];
      }

         return [];
   }

    /**
     * Cron action to process tasks (close if expired or quota reached)
     *
     * @param CronTask|null $cron_task for log, if NULL display (default NULL)
     * @return integer 1 if an action was done, 0 if not
     */
   public static function cronProcessTasks($cron_task = null) {
      if (class_exists('PluginProjectbridgeConfig')) {
          $plugin = new Plugin();

         if (!$plugin->isActivated(PluginProjectbridgeConfig::NAMESPACE)) {
            echo __('Disabled plugin'). "<br />\n";
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
         $tasks = $task->find("
            TRUE
            AND projectstates_id != " . $state_closed_value . "
        ");

      foreach ($tasks as $task_data) {
          $expired = false;
          $timediff = 0;
          $action_time = null;

         if (!empty($task_data['plan_end_date'])
              && time() >= strtotime($task_data['plan_end_date'])
          ) {
            $expired = true;
         }

         if (!empty($task_data['planned_duration'])) {
             $action_time = PluginProjectbridgeContract::getTicketsTotalActionTime($task_data['id']);
             $timediff = $action_time - $task_data['planned_duration'];
         }

         if ($expired
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

         echo __('Finish') . "<br />\n";

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
   public function closeTask($expired = false, $action_time = 0) {
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

               if ($ticket->getFromDB($ticket_link['tickets_id'])
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
                     $additional_content = '('.__('This ticket comes from an automatic copy of the ticket', 'projectbridge').' ' . $old_ticket_id .' '. __('following the overtaking of hours or the expiry of the maintenance contract', 'projectbridge').')';
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
         echo __('find', 'projectbridge') . count($recipients) .' '. __('person(s) to alert', 'projectbridge') . "<br />\n";

         if (count($recipients)) {
             global $CFG_GLPI;

             $project = new Project();
             $project->getFromDB($this->_task->fields['projects_id']);

             $subject = __('project Task').' "' . $project->fields['name'] . '" '.__('closed');

             $html_parts = [];
             $html_parts[] = '<p>' . "\n";
             $html_parts[] = __('Hello');
             $html_parts[] = '<br />';
             $html_parts[] = __('The open task of the project', 'projectbridge').' <a href="' . rtrim($CFG_GLPI['url_base'], '/') . '/front/project.form.php?id=' . $this->_task->getId() . '">' . $project->fields['name'] . '</a> '.__('just closed', 'projectbridge');
             $html_parts[] = '<br />';
             $html_parts[] = '<br />';
             $html_parts[] = __('Reason', 'projectbridge').'(s) :';
             $html_parts[] = '<br />';
             $html_parts[] = __('Expired').' : ' . ($expired ? __('yes') : __('no'));
             $html_parts[] = '<br />';
             $html_parts[] = __('Overtaking', 'projectbridge').' : ' . ($action_time > 0 ? __('yes') : __('no'));

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
   public function createExcessTicket($timediff, $entities_id) {
       $ticket_request_type = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

       $ticket_fields = [
           'entities_id' => $entities_id,
           'name' => __('Adjustment ticket', 'projectbridge'),
           'content' => __('Time Adjustment ticket', 'projectbridge'),
           'actiontime' => 0,
           'requesttypes_id' => $ticket_request_type,
           'status' => Ticket::CLOSED,
       ];

       $ticket = new Ticket();

       if ($ticket->add($ticket_fields)) {
           $ticket_task_data = [
               'actiontime' => $timediff,
               'tickets_id' => $ticket->getId(),
               'content' => __('Adjustment task', 'projectbridge'),
           ];

           $ticket_task = new TicketTask();
           $ticket_task->add($ticket_task_data);

           PluginProjectbridgeTicket::deleteProjectLinks($ticket->getId());
         }
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
         echo __('Plugin is not installed'). "<br />\n";
         return 0;
      }

         $nb_successes = 0;

         $ticket_task = new TicketTask();
         $ticket_tasks = $ticket_task->find("TRUE AND actiontime > 0");

         echo __('find').' ' . count($ticket_tasks) . ' '.__('tasks with time', 'projectbridge') . "<br />\n";

      foreach ($ticket_tasks as $ticket_task_data) {
         echo __('re-calculuation for ticket task', 'projectbridge').' ' . $ticket_task_data['tickets_id'] . "<br />\n";

         // use the existing time to force an update of the percent_done in the tasks linked to the tickets
         $nb_successes += PluginProjectbridgeTask::updateProgressPercent($ticket_task_data['tickets_id']);
      }

         $cron_task->addVolume($nb_successes);

         echo __('Finish') . "<br />\n";

         return ($nb_successes > 0) ? 1 : 0;
   }

    /**
     * Update the progress percentage of tasks linked to a ticket
     *
     * @param  integer $ticket_id
     * @param  integer $timediff
     * @return integer
     */
   public static function updateProgressPercent($ticket_id, $timediff = 0) {
      static $task_list;
       $nb_successes = 0;

      if ($task_list === null) {
          $task_list = [];
      }

         $task_link = new ProjectTask_Ticket();
         $task_links = $task_link->find("
            TRUE
            AND tickets_id = " . $ticket_id . "
        ");

      if (!empty($task_links)) {
         foreach ($task_links as $task_link) {
            if (!isset($task_list[$task_link['projecttasks_id']])) {
                $task_list[$task_link['projecttasks_id']] = new ProjectTask();
                $task_list[$task_link['projecttasks_id']]->getFromDB($task_link['projecttasks_id']);
            }

              $task = $task_list[$task_link['projecttasks_id']];

            if ($task->getId()) {
                $total_actiontime = ProjectTask_Ticket::getTicketsTotalActionTime($task->getId());

                $target = $total_actiontime + $timediff;
                $planned_duration = $task->fields['planned_duration'];

               if (empty($planned_duration)) {
                     $planned_duration = 1;
               }

                  $target_percent = round(($target / $planned_duration) * 100);

               if ($target_percent > 100) {
                   $target_percent = 100;
               } else if ($target_percent < 0) {
                  $target_percent = 0;
               }

                  $success = $task->update([
                    'id' => $task->getId(),
                    'percent_done' => $target_percent,
                  ]);

               if ($success) {
                  $nb_successes++;
               }
            }
         }
      }

         return $nb_successes;
   }

    /**
     * Customize the duration columns in a list of project tasks
     *
     * @param  Project $project
     * @return void
     */
   public static function customizeDurationColumns(Project $project) {
       $task = new ProjectTask();
       $tasks = $task->find("TRUE AND projects_id = " . $project->getId());

      if (!empty($tasks)) {
          $duration_data = [];

         foreach ($tasks as $task_data) {
            $effective_duration = ProjectTask::getTotalEffectiveDuration($task_data['id']);

            $duration_data[$task_data['id']] = [
                'planned_duration' => round($task_data['planned_duration'] / 3600 * 100) / 100,
                'effective_duration' => round($effective_duration / 3600 * 100) / 100,
            ];
         }

         if (!empty($duration_data)) {
             $js_block = '
                    var
                        duration_data = ' . json_encode($duration_data) . ',
                        table_rows = $(".glpi_tabs table tr:not(:last)")
                    ;

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
                            ) {
                                cells_map[cell_text] = idx + 1;
                            }
                        });

                        var
                            task_row,
                            task_link,
                            task_id,
                            planned_duration_cell,
                            effective_duration_cell
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

                                if (planned_duration_cell.length) {
                                    planned_duration_cell.text(duration_data[task_id].planned_duration + " heure(s)");
                                }

                                if (effective_duration_cell.length) {
                                    effective_duration_cell.text(duration_data[task_id].effective_duration + " heure(s)");
                                }
                            }
                        });
                    }
                ';

             echo Html::scriptBlock($js_block);
         }
      }
   }
}
