<?php
/**
 * ---------------------------------------------------------------------
 *  projectBridge is a plugin allows to count down time from contracts
 *  by linking tickets with project tasks and project tasks with contracts.
 *  ---------------------------------------------------------------------
 *  LICENSE
 *
 *  This file is part of projectBridge.
 *
 *  rgpdTools is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  rgpdTools is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 *  ---------------------------------------------------------------------
 *  @copyright Copyright © 2022-2023 probeSys'
 *  @license   http://www.gnu.org/licenses/agpl.txt AGPLv3+
 *  @link      https://github.com/Probesys/glpi-plugins-projectbridge
 *  @link      https://plugins.glpi-project.org/#/plugin/projectbridge
 *  ---------------------------------------------------------------------
 */

require('../../../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['task_id']) && !empty($_POST['contract_id'])) {
    $contract_id = (int) $_POST['contract_id'];
    $task_id = (int) $_POST['task_id'];

    // creation d'un eventuel ticket de depassement
    createRenewalTicket($contract_id);

    $unlinked_tickets = [];
    $linkedTicketsids = [];
    $task = new ProjectTask();
    // n'afficher que les tickets qui n'ont pas de tâche de projet lié et qui sont associé à l'entité
   if ($task_id) {
       global $DB;
       $task->getFromDB($task_id);
       $entity_id = $task->fields['entities_id'];
       $ticket = new Ticket();
       $task_ticket = new ProjectTask_Ticket();

       // récupération des tickets liés à une tâche de projet, à l'entité et non supprimés
      foreach ($DB->request([
           'SELECT' => $ticket->getTable() . '.id',
           'FROM' => $task_ticket->getTable(),
           'INNER JOIN' => [
               $ticket->getTable() => [
                   'FKEY' => [
                       $task_ticket->getTable() => 'tickets_id',
                       $ticket->getTable() => 'id'
                   ]
               ]
           ],
           'WHERE' => [
               'entities_id' => $entity_id,
               'is_deleted' => 0
           ]
       ]) as $data) {
          $linkedTicketsids[] = $data['id'];
      }

       // récupération des tickets associés à l'entité, non supprimés et non associés à une tâche de projet
      if (count($linkedTicketsids)) {
          $unlinked_tickets = $ticket->find([
              'NOT' => ['id' => $linkedTicketsids],
              'entities_id' => $entity_id,
              'is_deleted' => 0,
                  ], 'date DESC');
      }
   }

    global $CFG_GLPI;

    $html = '';
    $html .= '<form method="post" id="renewal_tickets_form" action="' . rtrim($CFG_GLPI['root_doc'], '/') . '/front/contract.form.php">' . "\n";
    $html .= '<input type="hidden" name="entities_id" value="' . $task->fields['entities_id'] . '" />' . "\n";
    $html .= '<input type="hidden" name="id" value="' . $contract_id . '" />' . "\n";
    $html .= '<div id="moreDataContainer"></div>';
    $html .= '<h2 style="text-align: center">';
    $html .= 'Tickets';
    $html .= '</h2>' . "\n";
    $html .= '<p>';
    $html .= __('Select tickets in the entity (not deleted and unrelated to a project task) that you want to link to the new task', 'projectbridge') . '.';
    $html .= '</p>' . "\n";
    $html .= '<table class="tab_cadrehov">' . "\n";
    $html .= '<tr class="tab_bg_2">' . "\n";
    $html .= '<th class=""><div class="form-group-checkbox">
                  <input title="Tout cocher" type="checkbox" class="new_checkbox" name="_checkall_1702981631" id="checkall_1702981631" onclick="if ( checkAsCheckboxes(\'checkall_1702981631\', \'renewal_tickets_form\')) {return true;}">
                  <label class="label-checkbox" for="checkall_1702981631" title="Tout cocher">
                     <span class="check"></span>
                     <span class="box"></span>
                  </label>
               </div>
               <script type="text/javascript">
            //<![CDATA[
            $(function() {$(\'#renewal_tickets_form input[type="checkbox"]\').shiftSelectable();});
            //]]>
            </script></th>'. "\n";
    $html .= '<th>';
    $html .= __('Name');
    $html .= '</th>' . "\n";
    $html .= '<th>';
    $html .= __('Time');
    $html .= '</th>' . "\n";
    $html .= '<th>';
    $html .= __('Opening date');
    $html .= '</th>' . "\n";
    $html .= '<th>';
    $html .= __('Closing date');
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
    $html .= '<input type="submit" name="update" id="renewal_tickets_form_submit" disabled value="' . __('Link tickets to renewal', 'projectbridge') . '" class="submit" />';
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

    // call crontask function ( projectTask ) to create a new tikcet with exeed time if necessary
   foreach ($allActiveTasks as $task_data) {
       $expired = false;
       $timediff = 0;
       $action_time = null;

      if (!empty($task_data['plan_end_date']) && time() >= strtotime($task_data['plan_end_date'])
       ) {
          $expired = true;
      }

      if (!empty($task_data['planned_duration'])) {
          $action_time = PluginProjectbridgeContract::getTicketsTotalActionTime($task_data['id']);
          $timediff = $action_time - $task_data['planned_duration'];
      }

      if ($expired || ($timediff >= 0 && $action_time !== null)) {
          $brige_task = new PluginProjectbridgeTask($task_data['id']);
         if ($timediff > 0) {
             $brige_task->createExcessTicket($timediff, $task_data['entities_id']);
         }
      }
   }
}
