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
     * @param Contract|null $contract
     */
    public function __construct($contract = null)
    {
        if (
            $contract !== null
            || $contract instanceof Contract
        ) {
            $this->_contract = $contract;
        }
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

        $html_parts = [];
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
            projectbridge_config = $("#projectbridge_config");

            $(".select2-container", projectbridge_config).remove();
            $("select", projectbridge_config).select2({
                dropdownAutoWidth: true
            });
            $(".select2-container", projectbridge_config).show();
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
        $html_parts = [];

        $html_parts[] = 'Créer le projet :';
        $html_parts[] = '&nbsp;';
        $html_parts[] = Dropdown::showYesNo('projectbridge_create_project', 1, -1, ['display' => false]);

        $html_parts[] = PluginProjectbridgeContract::_getPostShowHoursHtml(0);

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
        $search_filters = [
            'TRUE',
            '`is_deleted` = 0',
        ];

        if (!empty($_SESSION['glpiactiveentities'])) {
            $search_filters[] = "`entities_id` IN (" . implode(', ', $_SESSION['glpiactiveentities']) . ")";
        }

        $bridge_contract = new PluginProjectbridgeContract($contract);
        $project_id = $bridge_contract->getProjectId();

        $project = new Project();
        $project_results = $project->find(implode(' AND ', $search_filters));
        $project_list = [
            null => Dropdown::EMPTY_VALUE,
        ];

        foreach ($project_results as $project_data) {
            $project_list[$project_data['id']] = $project_data['name'] . ' (' . $project_data['id'] . ')';
        }

        $project_config = [
            'value' => $project_id,
            'display' => false,
            'values' => $project_list,
        ];

        $html_parts = [];
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
                $search_closed = false;
            } else {
                $search_closed = true;
            }

            $consumption_ratio = 0;
            $nb_hours = $bridge_contract->getNbHours();

            if ($nb_hours) {
                $planned_duration = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'task_duration', $search_closed);
                $consumption = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'consumption', $search_closed);

                if ($planned_duration) {
                    $consumption_ratio = $consumption / $planned_duration;
                }

                $html_parts[] = 'Consommation : ';
                $html_parts[] = round($consumption, 2) . '/' . round($planned_duration, 2) . ' heures';
                $html_parts[] = '&nbsp;';
                $html_parts[] = '(' . round($consumption_ratio * 100) . '%)';
            }

            $plan_end_date = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'plan_end_date', $search_closed);
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
                $search_closed
                && PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', true)
            ) {
                $html_parts[] = '&nbsp;';
                $html_parts[] = '-';
                $html_parts[] = '&nbsp;';

                $html_parts[] = '<input type="submit" value="Renouveler le contrat" class="submit projectbridge-renewal-trigger" />' . "\n";

                if (true) {
                    $renewal_data = $bridge_contract->getRenewalData();
                    $html_parts[] = '<table class="projectbridge-renewal-data" style="display: none; margin-top: 15px; padding: 15px; background: #cacaca;">' . "\n";

                    if (true) {
                        $html_parts[] = '<tr>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = 'Date de début';
                        $html_parts[] = '</td>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = Html::showDateField('projecttask_begin_date', [
                            'value' => $renewal_data['begin_date'],
                            'maybeempty' => false,
                            'display' => false,
                        ]);
                        $html_parts[] = '</td>' . "\n";
                        $html_parts[] = '</tr>' . "\n";
                    }

                    if (true) {
                        $html_parts[] = '<tr>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = 'Date de fin';
                        $html_parts[] = '</td>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = Html::showDateField('projecttask_end_date', [
                            'value' => $renewal_data['end_date'],
                            'maybeempty' => false,
                            'display' => false,
                        ]);
                        $html_parts[] = '</td>' . "\n";

                        $html_parts[] = '</tr>' . "\n";
                    }

                    if (true) {
                        $html_parts[] = '<tr>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = 'Nombre d\'heures';
                        $html_parts[] = '</td>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = '<input type="number" min="0" max="99999" name="projectbridge_nb_hours_to_use" value="' . $renewal_data['nb_hours_to_use'] . '" style="width: 50px" step="any" />';
                        $html_parts[] = '</td>' . "\n";

                        $html_parts[] = '</tr>' . "\n";
                    }

                    if (true) {
                        $html_parts[] = '<tr>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = '<input type="submit" name="update" value="Confirmer le renouvellement" class="submit projectbridge-renewal-tickets" />';
                        $html_parts[] = '</td>' . "\n";

                        $html_parts[] = '<td>';
                        $html_parts[] = '<input type="submit" name="update" value="Annuler" class="submit projectbridge-renewal-cancel" />';
                        $html_parts[] = '</td>' . "\n";

                        $html_parts[] = '</tr>' . "\n";
                    }

                    $html_parts[] = '</table>' . "\n";
                }

                $modal_url = rtrim($CFG_GLPI['root_doc'], '/') . '/plugins/projectbridge/ajax/get_renewal_tickets.php';
                $html_parts[] = Ajax::createModalWindow('renewal_tickets_modal', $modal_url, [
                    'display' => false,
                    'extraparams' => [
                        'task_id' => PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'task_id', $search_closed),
                        'contract_id' => $contract->getId(),
                    ],
                ]);

                $js_block = '
                    window.projectbridge_datepicker_init = true;
                    window._renewal_modal_js = undefined;

                    /**
                     * Trigger a timeout until a modal is open
                     *
                     * @param jQueryObject modal
                     * @param function callback
                     */
                    function timeoutUntilModalOpen(modal, callback)
                    {
                        if ($("form", modal).length) {
                            callback();
                        } else {
                            window.setTimeout(function() {
                                timeoutUntilModalOpen(modal, callback);
                            }, 300);
                        }
                    }

                    $(document).on("click", ".projectbridge-renewal-trigger", function(e) {
                        e.preventDefault();

                        if (window.projectbridge_datepicker_init) {
                            // delete datepicker settings & reload its JS

                            var
                                renewal_container = $(".projectbridge-renewal-data"),
                                datepicker_triggers = $(".ui-datepicker-trigger", renewal_container),
                                datepicker_parents = []
                            ;

                            datepicker_triggers.each(function() {
                                datepicker_parents.push($(this).parents("td").get(0));
                            });

                            datepicker_triggers.remove();
                            $(".hasDatepicker", renewal_container).removeClass("hasDatepicker");

                            var datepickerJS;
                            $(datepicker_parents).find("script").each(function() {
                                datepickerJS = new Function($(this).html());
                                datepickerJS();
                            });

                            window.projectbridge_datepicker_init = false;
                        }

                        $(".projectbridge-renewal-data").show();
                        $(this).hide();
                        return false;
                    })
                    .on("click", ".projectbridge-renewal-cancel", function(e) {
                        e.preventDefault();
                        $(".projectbridge-renewal-data").hide();
                        $(".projectbridge-renewal-trigger").show();
                        return false;
                    })
                    .on("click", ".projectbridge-renewal-tickets", function(e) {
                        e.preventDefault();

                        if (renewal_tickets_modal === undefined) {
                            if (window._renewal_modal_js === undefined) {
                                window._renewal_modal_js = new Function($(".projectbridge-renewal-data").next().html() + "return renewal_tickets_modal;");
                            }

                            renewal_tickets_modal = window._renewal_modal_js();
                        }

                        renewal_tickets_modal.dialog("open");

                        var data_to_add_to_modal = {
                            projectbridge_project_id: $("[id^=dropdown_projectbridge_project_id]").val(),
                            _projecttask_begin_date: $("input[name=_projecttask_begin_date]").val(),
                            _projecttask_end_date: $("input[name=_projecttask_end_date]").val(),
                            projectbridge_nb_hours_to_use: $("input[name=projectbridge_nb_hours_to_use]").val()
                        };

                        var html_to_add_to_modal = "";

                        for (var data_name in data_to_add_to_modal) {
                            html_to_add_to_modal += "<input type=\"hidden\" name=\"" + data_name + "\" value=\"" + data_to_add_to_modal[data_name] + "\" />";
                        }

                        timeoutUntilModalOpen(renewal_tickets_modal, function() {
                            $("form", renewal_tickets_modal).prepend(html_to_add_to_modal);
                            renewal_tickets_modal = undefined;
                        });

                        return false;
                    });
                ';
                $html_parts[] = Html::scriptBlock($js_block);
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
        $html_parts = [];

        $html_parts[] = '<br />';
        $html_parts[] = '<br />';

        $html_parts[] = 'Nombre d\'heures :';
        $html_parts[] = '&nbsp;';
        $html_parts[] = '<input type="number" min="0" max="99999" step="1" name="projectbridge_project_hours" value="' . $nb_hours . '" style="width: 50px" />';

        return implode('', $html_parts);
    }

    /**
     * Get data from a project's task
     *
     * @param  integer $project_id The project to get the data from
     * @param  string $data_field The data to get
     * @param  boolean $search_closed
     * @return mixed
     */
    public static function getProjectTaskDataByProjectId($project_id, $data_field, $search_closed = false)
    {
        static $project_tasks;

        if ($project_tasks === null) {
            $project_tasks = [];
        }

        if (!isset($project_tasks[$project_id][$search_closed])) {
            $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');

            if (empty($state_closed_value)) {
                global $CFG_GLPI;
                $redirect_url = rtrim($CFG_GLPI['root_doc'], '/') . '/plugins/projectbridge/front/config.form.php';

                Session::addMessageAfterRedirect('Veuillez définir la correspondance du statut "Clos".', false, ERROR);
                Html::redirect($redirect_url);
                return null;
            }

            if ($search_closed) {
                $projectstate_filter = "projectstates_id = " . $state_closed_value;
            } else {
                $projectstate_filter = "projectstates_id != " . $state_closed_value;
            }

            $project_tasks[$project_id][$search_closed] = new ProjectTask();
            $project_tasks[$project_id][$search_closed]->getFromDBByQuery("
                WHERE TRUE
                    AND projects_id = " . $project_id . "
                    AND " . $projectstate_filter . "
                ORDER BY
                    plan_end_date DESC,
                    id DESC
                LIMIT 1
            ");
        }

        $return = null;

        switch ($data_field) {
            case 'exists':
                if ($project_tasks[$project_id][$search_closed]->getId() > 0) {
                    $return = true;
                } else {
                    $return = false;
                }

                break;

            case 'task_id':
                $return = $project_tasks[$project_id][$search_closed]->getId();
                break;

            case 'task':
                if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', $search_closed)) {
                    $return = $project_tasks[$project_id][$search_closed];
                }

                break;

            case 'consumption':
                $return = 0;

                if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', $search_closed)) {
                    $action_time = ProjectTask_Ticket::getTicketsTotalActionTime($project_tasks[$project_id][$search_closed]->getId());

                    if ($action_time > 0) {
                        $return = $action_time / 3600;
                    }
                }

                break;

            case 'plan_start_date':
                if (
                    PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', $search_closed)
                    && !empty($project_tasks[$project_id][$search_closed]->fields['plan_start_date'])
                ) {
                    $return = $project_tasks[$project_id][$search_closed]->fields['plan_start_date'];
                }

                break;

            case 'plan_end_date':
                if (
                    PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', $search_closed)
                    && !empty($project_tasks[$project_id][$search_closed]->fields['plan_end_date'])
                ) {
                    $return = $project_tasks[$project_id][$search_closed]->fields['plan_end_date'];
                }

                break;

            case 'task_duration':
                $return = 0;

                if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', $search_closed)) {
                    $return = $project_tasks[$project_id][$search_closed]->fields['planned_duration'] / 3600;
                }

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

        if ($project_id <= 0) {
            return;
        }

        $state_in_progress_value = PluginProjectbridgeState::getProjectStateIdByStatus('in_progress');

        if (empty($state_in_progress_value)) {
            Session::addMessageAfterRedirect('La correspondance pour le statut "En cours" n\'a pas été définie. Le contrat n\'a pas pu être renouvelé.', false, ERROR);
            return false;
        }

        $renewal_data = $this->getRenewalData($use_input_data = true);

        $project_task_data = [
            // data from contract
            'name' => date('Y-m'),
            'entities_id' => $this->_contract->fields['entities_id'],
            'is_recursive' => $this->_contract->fields['is_recursive'],
            'projects_id' => $project_id,
            'content' => addslashes($this->_contract->fields['comment']),
            'comment' => '',
            'plan_start_date' => date('Y-m-d H:i:s', strtotime($renewal_data['begin_date'])),
            'plan_end_date' => date('Y-m-d H:i:s', strtotime($renewal_data['end_date'])),
            'planned_duration' => $renewal_data['nb_hours_to_use'] * 3600, // in seconds
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
        ];

        // create the new project's task
        $project_task = new ProjectTask();
        $task_id = $project_task->add($project_task_data);

	if ($task_id) {
	      Event::log($task_id, "projectbridge", 4, "projectbridge",
              sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $name));
        }

        if (
            $task_id
            && !empty($this->_contract->input['ticket_ids'])
            && is_array($this->_contract->input['ticket_ids'])
        ) {
            // link selected tickets
            foreach ($this->_contract->input['ticket_ids'] as $ticket_id => $selected) {
                if ($selected) {
                    $project_task_ticket = new ProjectTask_Ticket();
                    $project_task_ticket->add([
                        'tickets_id' => $ticket_id,
                        'projecttasks_id' => $task_id,
                    ]);
                }
            }
        }
    }

    /**
     * Get data used to renew the contract
     *
     * @param boolean $use_input_data
     * @return array
     */
    public function getRenewalData($use_input_data = false)
    {
        $project_id = $this->getProjectId();
        $open_exists = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', false);
        $closed_exists = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', true);
        $use_closed = false;

        if (
            !$use_input_data
            && $closed_exists
            && !$open_exists
        ) {
            $use_closed = true;

            $previous_task_start = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'plan_start_date', true);
            $previous_task_end = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'plan_end_date', true);

            $datediff = ceil((strtotime($previous_task_end) - strtotime($previous_task_start)) / 3600 / 24);
            $task_start_date = date('Y-m-d', strtotime($previous_task_end . ' + 1 day'));
            $task_end_date = date('Y-m-d', strtotime($task_start_date . ' + ' . $datediff . ' days'));
        } else {
            if (empty($this->_contract->input['_projecttask_begin_date'])) {
                $task_start_date = date('Y-m-d');
            } else {
                $task_start_date = $this->_contract->input['_projecttask_begin_date'];
                $use_closed = true;
            }

            if (empty($this->_contract->input['_projecttask_end_date'])) {
                $task_end_date = (
                    !empty($this->_contract->fields['duration'])
                        ? Infocom::getWarrantyExpir(date('Y-m-d', strtotime($task_start_date)), $this->_contract->fields['duration'])
                        : ''
                );
                $use_closed = true;
            } else {
                $task_end_date = $this->_contract->input['_projecttask_end_date'];
            }
        }

        $nb_hours = $this->getNbHours();
        $nb_hours_to_use = $nb_hours;
        $delta_hours_to_use = 0;
        $consumption = PluginProjectbridgeContract::getProjectTaskDataByProjectId($this->getProjectId(), 'consumption', $use_closed);

        if ($consumption > $nb_hours) {
            $delta_hours_to_use = $consumption - $nb_hours;
        }

        if (!empty($this->_contract->input['projectbridge_nb_hours_to_use'])) {
            $nb_hours_to_use = $this->_contract->input['projectbridge_nb_hours_to_use'];
        }

        $renewal_data = [
            'begin_date' => $task_start_date,
            'end_date' => $task_end_date,
            'nb_hours_to_use' => $nb_hours_to_use,
            'delta_hours_to_use' => $delta_hours_to_use,
        ];

        return $renewal_data;
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
            case 'AlertContractsToRenew':
                return [
                    'description' => 'Alerte des contrats à renouveler',
                ];

                break;
        }

        return [];
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
                echo 'Plugin n\'est pas actif' . "<br />\n";
                return 0;
            }
        } else {
            echo 'Plugin n\'est pas installé' . "<br />\n";
            return 0;
        }

        $nb_successes = 0;
        $recipients = PluginProjectbridgeConfig::getRecipients();
        echo 'Trouvé ' . count($recipients) . ' personne(s) à alerter' . "<br />\n";

        if (count($recipients)) {
            $contracts = PluginProjectbridgeContract::getContractsToRenew();
            echo 'Trouvé ' . count($contracts) . ' contrats à renouveler' . "<br />\n";

            $subject = 'Contrats : ' . count($contracts) . ' à renouveler';

            $html_parts = [];
            $html_parts[] = '<p>' . "\n";
            $html_parts[] = 'Il y a ' . count($contracts) . ' contrats à renouveler :';
            $html_parts[] = '</p>' . "\n";

            $html_parts[] = '<ol>' . "\n";

            global $CFG_GLPI;

            foreach ($contracts as $contract_id => $contract_data) {
                $html_parts[] = '<li>' . "\n";

                $html_parts[] = '<strong>Nom</strong> : ';
                $html_parts[] = '<a href="' . rtrim($CFG_GLPI['url_base'], '/') . '/front/contract.form.php?id=' . $contract_id . '">';
                $html_parts[] = $contract_data['contract']->fields['name'];
                $html_parts[] = '</a>';
                $html_parts[] = '<br />' . "\n";

                $entity = new Entity();
                $entity->getFromDB($contract_data['contract']->fields['entities_id']);
                $html_parts[] = '<strong>Entité</strong> : ';
                $html_parts[] = $entity->fields['name'];
                $html_parts[] = '<br />' . "\n";

                $bridge_contract = new PluginProjectbridgeContract($contract_data['contract']);
                $project_id = $bridge_contract->getProjectId();

                if (PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', true)) {
                    $plan_end_date = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'plan_end_date', true);
                    $html_parts[] = '<strong>';
                    $html_parts[] = 'Date de fin prévue';
                    $html_parts[] = '</strong> : ';
                    $html_parts[] = date('d-m-Y', strtotime($plan_end_date));
                    $html_parts[] = '<br />' . "\n";

                    $consumption = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'consumption', true);
                    $html_parts[] = '<strong>';
                    $html_parts[] = 'Durée effective';
                    $html_parts[] = '</strong> : ';
                    $html_parts[] = round($consumption, 2);
                    $html_parts[] = ' | ';

                    $task_duration = PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'task_duration', true);
                    $html_parts[] = '<strong>';
                    $html_parts[] = 'Durée planifiée';
                    $html_parts[] = '</strong> : ';
                    $html_parts[] = round($task_duration, 2);
                    $html_parts[] = '<br />' . "\n";
                }

                $html_parts[] = '<br />' . "\n";
                $html_parts[] = '</li>' . "\n";
            }

            $html_parts[] = '</ol>' . "\n";

            foreach ($recipients as $recipient) {
                $success = PluginProjectbridgeConfig::notify(implode('', $html_parts), $recipient['email'], $recipient['name'], $subject);

                if ($success) {
                    $nb_successes++;
                    $task->addVolume(count($contracts));
                }
            }
        }

        echo 'Fini' . "<br />\n";

        return ($nb_successes > 0) ? 1 : 0;
    }

    /**
     * Get the contracts to renew
     *
     * @return array
     */
    public static function getContractsToRenew()
    {
        global $DB;

        // todo: use Contract::find()
        $get_contracts_query = "
            SELECT
                id
            FROM
                glpi_contracts
            WHERE TRUE
                AND is_deleted = 0
                AND is_template = 0
        ";

        $result = $DB->query($get_contracts_query);
        $contracts = [];

        if ($result) {
            while ($row = $DB->fetch_assoc($result)) {
                $contract = new Contract();
                $contract->getFromDB($row['id']);

                $bridge_contract = new PluginProjectbridgeContract($contract);
                $project_id = $bridge_contract->getProjectId();

                $project = new Project();
                $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');

                if (
                    $project->getFromDB($project_id)
                    && $project->fields['projectstates_id'] != $state_closed_value
                    && !PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', false)
                    && PluginProjectbridgeContract::getProjectTaskDataByProjectId($project_id, 'exists', true)
                ) {
                    $contracts[$contract->getId()] = [
                        'contract' => $contract,
                    ];
                }
            }
        }

        return $contracts;
    }

    /**
     * Display HTML after project has been shown
     *
     * @param  Project $project
     * @return void
     */
    public static function postShowProject(Project $project)
    {
        $project_id = $project->getId();

        if (!empty($project_id)) {
            $bridge_contract = new PluginProjectbridgeContract();
            $contract_bridges = $bridge_contract->find("TRUE AND project_id = " . $project_id);

            $html_parts = [];
            $html_parts[] = '<div class="spaced">' . "\n";

            if (!empty($contract_bridges)) {
                global $CFG_GLPI;
                $contract_url = rtrim($CFG_GLPI['root_doc'], '/') . '/front/contract.form.php?id=';

                foreach ($contract_bridges as $contract_bridge_data) {
                    $contract = new Contract();

                    if ($contract->getFromDB($contract_bridge_data['contract_id'])) {
                        $html_parts[] = '<a href="' . $contract_url . $contract->getId() . '" target="_blank">';
                        $html_parts[] = 'Contrat "' . $contract->fields['name'] . '"';
                        $html_parts[] = '</a>';
                    } else {
                        $html_parts[] = 'Lien vers contrat inexistant : contrat n°' . $contract->getId();
                    }
                }
            } else {
                $html_parts[] = 'Pas de contrat lié';
            }

            $html_parts[] = '</div>' . "\n";

            echo implode(' ', $html_parts);
        }
    }
}
