<?php

require('../../../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['task_id']) && !empty($_POST['contract_id'])) {
    $contract_id = (int) $_POST['contract_id'];
    $task_id = (int) $_POST['task_id'];
    $all_tickets = [];
    
    // creation d'un eventuel ticket de depassement
    createRenewalTicket($contract_id);
    
    
    $task = new ProjectTask();
    if ($task_id) {
        $task->getFromDB($task_id);
        $ticket = new Ticket();
        $all_tickets = $ticket->find([
          'entities_id'=>$task->fields['entities_id'],
          'is_deleted'=> 0
          ]
        );
    }
    
    

    $unlinked_tickets = [];

    if (!empty($all_tickets)) {
        $task_ticket = new ProjectTask_Ticket();
        $linked_tickets = $task_ticket->find([
          'tickets_id'=> ['IN'=> implode(', ', array_keys($all_tickets))]
        ]);

        $unlinked_tickets = $all_tickets;

        foreach ($linked_tickets as $ticket_link_data) {
            unset($unlinked_tickets[$ticket_link_data['tickets_id']]);
        }
    }

    global $CFG_GLPI;
    
    $html = '';

    $html .= '<form method="post" action="' . rtrim($CFG_GLPI['root_doc'], '/') . '/front/contract.form.php">' . "\n";
    $html .= '<input type="hidden" name="entities_id" value="' . $task->fields['entities_id'] . '" />' . "\n";
    $html .= '<input type="hidden" name="id" value="' . $contract_id . '" />' . "\n";
    $html .= '<h2 style="text-align: center">';
    $html .= 'Tickets';
    $html .= '</h2>' . "\n";
    $html .= '<p>';
    $html .= __('Select tickets in the entity (not deleted and unrelated to a project task) that you want to link to the new task', 'projectbridge') . '.';
    $html .= '</p>' . "\n";
    $html .= '<table class="tab_cadrehov">' . "\n";
    $html .= '<tr class="tab_bg_2">' . "\n";
    $html .= '<th>';
    $html .= '&nbsp;';
    $html .= '</th>' . "\n";
    $html .= '<th>';
    $html .= __('Name');
    $html .= '</th>' . "\n";
    $html .= '<th>';
    $html .= __('Time');
    $html .= '</th>' . "\n";
    $html .= '<th>';
    $html .= __('Open Date');
    $html .= '</th>' . "\n";
    $html .= '<th>';
    $html .= __('Close Date');
    $html .= '</th>' . "\n";
    $html .= '</tr>' . "\n";

    foreach ($unlinked_tickets as $ticket_data) {
        $html .= '<tr class="tab_bg_1">' . "\n";
        $html .= '<td>';
        $html .= Html::getCheckbox([
          'name' => 'ticket_ids[' . $ticket_data['id'] . ']',
        ]);
        $html .= '</td>' . "\n";
        $html .= '<td>';
        $html .= '<a href="' . rtrim($CFG_GLPI['root_doc'], '/') . '/front/ticket.form.php?id=' . $ticket_data['id'] . '" target="_blank">';
        $html .= $ticket_data['name'] . ' (' . $ticket_data['id'] . ')';
        $html .= '</a>';
        $html .= '</td>' . "\n";
        $html .= '<td>';
        $html .= round($ticket_data['actiontime'] / 3600, 2) . ' heure(s)';
        $html .= '</td>' . "\n";
        $html .= '<td>';
        $html .= $ticket_data['date'];
        $html .= '</td>' . "\n";
        $html .= '<td>';
        $html .= $ticket_data['closedate'];
        $html .= '</td>' . "\n";
        $html .= '</tr>' . "\n";
    }

    if (empty($unlinked_tickets)) {
        $html .= '<tr class="tab_bg_1">' . "\n";
        $html .= '<td colspan="5" style="text-align: center">';
        $html .= __('No ticket found');
        $html .= '</td>' . "\n";
        $html .= '</tr>' . "\n";
    }

    $html .= '<tr class="tab_bg_1">' . "\n";
    $html .= '<td colspan="5" style="text-align: center">';
    $html .= '<input type="submit" name="update" value="' . __('Link tickets to renewal', 'projectbridge') . '" class="submit" />';
    $html .= '</td>' . "\n";
    $html .= '</tr>' . "\n";
    $html .= '</table>' . "\n";
    
    echo $html;

    Html::closeForm();
}

function createRenewalTicket($contract_id) {
    // récupération des tâches de projets ouvertes avant la création de la nouvelle
    $contract = new Contract();
    $contract->getFromDB($contract_id);
    $bridge_contract = new PluginProjectbridgeContract($contract);
    $project_id = $bridge_contract->getProjectId();  
    $allActiveTasks = PluginProjectbridgeContract::getAllActiveProjectTasksForProject($project_id);
    // close previous active project taks
    if($allActiveTasks) {
        // call crontask function ( projectTask ) to close previous project task and create a new tikcet with exeed time if necessary
        $pluginProjectbridgeTask = new PluginProjectbridgeTask();
        $pluginProjectbridgeTask->closeTaskAndCreateExcessTicket($allActiveTasks, false);
    }
}


