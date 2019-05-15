<?php

require('../../../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST'
    && !empty($_POST['task_id'])
    && !empty($_POST['contract_id'])
) {
    $contract_id = (int) $_POST['contract_id'];
    $task_id = (int) $_POST['task_id'];
    $task = new ProjectTask();
    $task->getFromDB($task_id);

    $ticket = new Ticket();
    $all_tickets = $ticket->find("
        TRUE
        AND entities_id = " . $task->fields['entities_id'] . "
        AND is_deleted = 0
    ");

    $unlinked_tickets = [];

   if (!empty($all_tickets)) {
       $task_ticket = new ProjectTask_Ticket();
       $linked_tickets = $task_ticket->find("
            TRUE
            AND tickets_id IN (" . implode(', ', array_keys($all_tickets)) . ")
        ");

       $unlinked_tickets = $all_tickets;

      foreach ($linked_tickets as $ticket_link_data) {
         unset($unlinked_tickets[$ticket_link_data['tickets_id']]);
      }
   }

   if (true) {
       global $CFG_GLPI;

       echo '<form method="post" action="' . rtrim($CFG_GLPI['root_doc'], '/') . '/front/contract.form.php">' . "\n";

       echo '<input type="hidden" name="entities_id" value="' . $task->fields['entities_id'] . '" />' . "\n";
       echo '<input type="hidden" name="id" value="' . $contract_id . '" />' . "\n";

       echo '<h2 style="text-align: center">';
       echo 'Tickets';
       echo '</h2>' . "\n";

       echo '<p>';
       echo __('Select tickets in the entity (not deleted and unrelated to a project task) that you want to link to the new task', 'projectbridge').'.';
       echo '</p>' . "\n";

       echo '<table class="tab_cadrehov">' . "\n";

      if (true) {
          echo '<tr class="tab_bg_2">' . "\n";

          echo '<th>';
          echo '&nbsp;';
          echo '</th>' . "\n";

          echo '<th>';
          echo __('Name');
          echo '</th>' . "\n";

          echo '<th>';
          echo __('Time');
          echo '</th>' . "\n";

          echo '<th>';
          echo __('Open Date');
          echo '</th>' . "\n";

          echo '<th>';
          echo __('Close Date');
          echo '</th>' . "\n";

          echo '</tr>' . "\n";
      }

      foreach ($unlinked_tickets as $ticket_data) {
         echo '<tr class="tab_bg_1">' . "\n";

         echo '<td>';
         echo Html::getCheckbox([
             'name' => 'ticket_ids[' . $ticket_data['id'] . ']',
         ]);

          echo '</td>' . "\n";

          echo '<td>';
          echo '<a href="' . rtrim($CFG_GLPI['root_doc'], '/') . '/front/ticket.form.php?id=' . $ticket_data['id'] . '" target="_blank">';
          echo $ticket_data['name'] . ' (' . $ticket_data['id'] . ')';
          echo '</a>';
          echo '</td>' . "\n";

          echo '<td>';
          echo round($ticket_data['actiontime'] / 3600, 2) . ' heure(s)';
          echo '</td>' . "\n";

          echo '<td>';
          echo $ticket_data['date'];
          echo '</td>' . "\n";

          echo '<td>';
          echo $ticket_data['closedate'];
          echo '</td>' . "\n";

          echo '</tr>' . "\n";
      }

      if (empty($unlinked_tickets)) {
         echo '<tr class="tab_bg_1">' . "\n";

         echo '<td colspan="5" style="text-align: center">';
         echo __('No ticket found');
         echo '</td>' . "\n";

         echo '</tr>' . "\n";
      }

      if (true) {
         echo '<tr class="tab_bg_1">' . "\n";

         echo '<td colspan="5" style="text-align: center">';
         echo '<input type="submit" name="update" value="'.__('Link tickets to renewal', 'projectbridge').'" class="submit" />';
         echo '</td>' . "\n";

         echo '</tr>' . "\n";
      }

         echo '</table>' . "\n";

         Html::closeForm();
   }
}
