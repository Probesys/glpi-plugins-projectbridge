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
 *  projectBridge is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  projectBridge is distributed in the hope that it will be useful,
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

include('../../../inc/includes.php');

/**
 * get value of POST variable
 * @param string $post_field
 * @return type
 */
function getPostDataFromField($post_field) {
    $value = null;

   if (isset($_POST[$post_field])) {
      if (!is_array($_POST[$post_field])) {
          $_POST[$post_field] = trim($_POST[$post_field]);
      }

       $value = $_POST[$post_field];
   }

    return $value;
}

/**
 * filter POST
 * @param array $post_fields
 * @return type
 */
function getPostDataFromFields(array $post_fields) {
    $post_data = [];

   foreach ($post_fields as $post_field) {
       $post_data[$post_field] = getPostDataFromField($post_field);
   }

    return $post_data;
}

$can_update = false;

if (class_exists('PluginProjectbridgeConfig')) {
    $plugin = new Plugin();

   if ($plugin->isActivated(PluginProjectbridgeConfig::NAMESPACE)) {
       $config = new Config();

      if ($config->canView() && $config->canUpdate()
       ) {
          $can_update = true;
      }
   }
}

global $CFG_GLPI;

Html::header(__('ProjectBridge Configuration', 'projectbridge'), $_SERVER['PHP_SELF'], 'config', 'plugins');
echo '<div align="center">' . "\n";
echo '<div class="card">' . "\n";
echo '<h1>';
echo __('ProjectBridge Configuration', 'projectbridge');
echo '</h1>' . "\n";


if ($can_update) {
    $post_fields = [
        'projectbridge_state_in_progress',
        'projectbridge_state_closed',
        'projectbridge_state_renewal',
        'projectbridge_save_states',
        'projectbridge_delete_recipient',
        'projectbridge_add_recipient',
        'projectbridge_add_recipient_submit',
        'projectbridge_config_countOnlyPublicTasks',
        'projectbridge_config_addContractSelectorOnCreatingTicketForm',
        'projectbridge_config_isRequiredContractSelectorOnCreatingTicketForm',
        'projectbridge_config_elementsAssociateToExcessTicket',
        'projectbridge_config_globalContractQuotaAlert'
    ];

    $post_data = getPostDataFromFields($post_fields);

    echo '<style>' . "\n";
    echo '    table td, table th { border-bottom: 1px solid #ccc; border-left: 1px solid #ccc; padding: 15px;}';
    echo '    table td:first-child, table th:first-child { border-left: 0px;}';
    echo '</style>' . "\n";

    echo '<a href="' . PLUGIN_PROJECTBRIDGE_WEB_DIR . '/front/projecttask.php">';
    echo __('Project Tasks', 'projectbridge');
    echo '</a>';

    // status config
    echo '<div class="card-header">' . "\n";
    echo '<h2>';
    echo __('Status Configuration', 'projectbridge');
    echo '</h2>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="card-body">' . "\n";

    echo '<p>';
    echo __('Please match the status names and their values in GLPI', 'projectbridge') . '.';
    echo '</p>' . "\n";

    echo '<form method="post" action="">' . "\n";
    echo '<table>' . "\n";

    echo '<thead>' . "\n";

    echo '<tr>' . "\n";
    echo '<th>';
    echo __('Status name', 'projectbridge');
    echo '</th>' . "\n";
    echo '<th>';
    echo __('Status type', 'projectbridge');
    echo '</th>' . "\n";
    echo '<th>';
    echo __('Corresponding status', 'projectbridge');
    echo '</th>' . "\n";
    echo '</tr>' . "\n";

    echo '</thead>' . "\n";

    echo '<tbody>' . "\n";

    if (!empty($post_data['projectbridge_save_states'])) {
        $state_in_progress_value = PluginProjectbridgeState::getProjectStateIdByStatus('in_progress');

       if ($post_data['projectbridge_state_in_progress'] !== $state_in_progress_value) {
           $state = new PluginProjectbridgeState();
           $state_data = [
               'status' => 'in_progress',
               'projectstates_id' => (int) $post_data['projectbridge_state_in_progress'],
           ];

           if ($state_in_progress_value === null) {
               $state->add($state_data);
           } else {
               $state = new PluginProjectbridgeState();
               $state->getFromDBByCrit(['status' => 'in_progress']);
               $state_data['id'] = $state->fields['id'];
               $state->update($state_data);
           }
       }

        $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');

       if ($post_data['projectbridge_state_closed'] !== $state_closed_value) {
           $state = new PluginProjectbridgeState();
           $state_data = [
               'status' => 'closed',
               'projectstates_id' => (int) $post_data['projectbridge_state_closed'],
           ];

           if ($state_closed_value === null) {
               $state->add($state_data);
           } else {
               $state = new PluginProjectbridgeState();
               $state->getFromDBByCrit(['status' => 'closed']);
               $state_data['id'] = $state->fields['id'];
               $state->update($state_data);
           }
       }

        $state_renewal_value = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

       if ($post_data['projectbridge_state_renewal'] !== $state_renewal_value) {
           $state = new PluginProjectbridgeState();
           $state_data = [
               'status' => 'renewal',
               'projectstates_id' => (int) $post_data['projectbridge_state_renewal'],
           ];

           if ($state_renewal_value === null) {
               $state->add($state_data);
           } else {
               $state = new PluginProjectbridgeState();
               $state->getFromDBByCrit(['status' => 'renewal']);
               $state_data['id'] = $state->fields['id'];
               $state->update($state_data);
           }
       }
    }

    $state_dropdown_conf = [
        'addicon' => false,
        'comments' => false,
    ];

    $state_in_progress_value = PluginProjectbridgeState::getProjectStateIdByStatus('in_progress');

    echo '<tr>' . "\n";
    echo '<td>';
    echo __('In progress', 'projectbridge');
    echo '</td>' . "\n";
    echo '<td>';
    echo __('Task');
    echo '</td>' . "\n";
    echo '<td>';
    ProjectState::dropdown($state_dropdown_conf + ['value' => $state_in_progress_value, 'name' => 'projectbridge_state_in_progress']);
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');

    echo '<tr>' . "\n";
    echo '<td>';
    echo __('Close');
    echo '</td>' . "\n";
    echo '<td>';
    echo __('Task');
    echo '</td>' . "\n";
    echo '<td>';
    ProjectState::dropdown($state_dropdown_conf + ['value' => $state_closed_value, 'name' => 'projectbridge_state_closed']);
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    $state_renewal_value = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

    echo '<tr>' . "\n";
    echo '<td>';
    echo __('Renewal', 'projectbridge');
    echo '</td>' . "\n";
    echo '<td>';
    echo __('Ticket');
    echo '</td>' . "\n";
    echo '<td>';
    RequestType::dropdown($state_dropdown_conf + ['value' => $state_renewal_value, 'name' => 'projectbridge_state_renewal']);
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    echo '<tr style="text-align: center">' . "\n";
    echo '<td colspan="3">';
    echo '<input type="submit" class="submit" name="projectbridge_save_states" value="' . __('Save') . '" />';
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    echo '</tbody>' . "\n";
    echo '</table>' . "\n";
    Html::closeForm();

    // recipients config
    $recipientIds = PluginProjectbridgeConfig::getConfValueByName('RecipientIds');
    if (!$recipientIds) {
        $recipientIds = [];
    }

    if (!empty($post_data['projectbridge_delete_recipient']) && is_array($post_data['projectbridge_delete_recipient'])) {
        $row_id = key($post_data['projectbridge_delete_recipient']);
       if (in_array($row_id, $recipientIds)) {
           // search key to delete after
           $key = array_search($row_id, $recipientIds);
           // delete key and value et reorganize array
           array_splice($recipientIds, $key, 1);
           PluginProjectbridgeConfig::updateConfValue('RecipientIds', $recipientIds);
       }
    } else if (!empty($post_data['projectbridge_add_recipient']) && !empty($post_data['projectbridge_add_recipient_submit']) && !isset($post_data[(int) $post_data['projectbridge_add_recipient']])) {
        $recipient_user_id = (int) $post_data['projectbridge_add_recipient'];

       if (!in_array($recipient_user_id, $recipientIds)) {
           // check if selected user have email address
           $user = (new User())->getById($recipient_user_id);

          if (strlen($user->getDefaultEmail()) > 0) {
              $recipientIds[] = $recipient_user_id;
              PluginProjectbridgeConfig::updateConfValue('RecipientIds', $recipientIds);
          } else {
              echo '<div class="warning">' . __('The selected user has no default email adress configured', 'projectbridge') . '</div>';
          }
       }
    }
    $recipients = PluginProjectbridgeConfig::getRecipients();
    echo '</div>' . "\n";
    echo '<div class="card-header">' . "\n";
    echo '<h2>';
    echo __('People receiving alerts', 'projectbridge');
    echo '</h2>' . "\n";
    echo '</div>' . "\n";
    echo '<div class="card-body">' . "\n";

    echo '<form method="post" action="">' . "\n";
    echo '<table>' . "\n";

    echo '<thead>' . "\n";

    echo '<tr>' . "\n";
    echo '<th style="min-width: 200px">';
    echo __('Name');
    echo '</th>' . "\n";
    echo '<th>';
    echo __('Action');
    echo '</th>' . "\n";
    echo '</tr>' . "\n";

    echo '</thead>' . "\n";

    echo '<tbody>' . "\n";
    $recipient_user_ids = [];

    foreach ($recipients as $row_id => $recipient) {
        $recipient_user_ids[] = $recipient['user_id'];

        echo '<tr>' . "\n";
        echo '<th>' . "\n";
        echo '<a href="' . $CFG_GLPI['root_doc'] . '/front/user.form.php?id=' . $recipient['user_id'] . '" />';
        echo $recipient['name'];
        echo '</a>' . "\n";
        echo '</td>' . "\n";
        echo '<td>';
        echo '<input type="submit" class="submit" name="projectbridge_delete_recipient[' . $row_id . ']" value="' . __('Delete') . '" />';
        echo '</td>' . "\n";
        echo '</tr>' . "\n";
    }

    if (empty($recipients)) {
        echo '<tr>' . "\n";
        echo '<td colspan="2" style="text-align: center">';
        echo __('Nobody receive alerts', 'projectbridge');
        echo '</td>' . "\n";
        echo '</tr>' . "\n";
    }


    echo '<tr">' . "\n";
    echo '<td>' . "\n";
    echo User::dropdown([
        'name' => 'projectbridge_add_recipient',
        'used' => $recipient_user_ids,
        'right' => 'all',
        'comments' => false,
        'display' => false,
    ]);
    echo '</td>' . "\n";
    echo '<td>';
    echo '<input type="submit" class="submit" name="projectbridge_add_recipient_submit" value="' . __('Add') . '" />';
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    echo '</tbody>' . "\n";

    echo '</table>' . "\n";
    Html::closeForm();

    echo '</div>' . "\n";
    echo '<div class="card-header">' . "\n";
    echo '<h2>' . __('General config', 'projectbridge') . '</h2>';
    echo '</div>' . "\n";
    echo '<div class="card-body">' . "\n";
    echo '<form method="post" action="">' . "\n";
    echo '<table>' . "\n";
    echo '<thead>' . "\n";
    echo '<tr>' . "\n";
    echo '<th style="min-width: 200px">';
    echo __('Name');
    echo '</th>' . "\n";
    echo '<th>';
    echo __('Value');
    echo '</th>' . "\n";
    echo '</tr>' . "\n";
    echo '<tbody>' . "\n";

    $countOnlyPublicTasks = PluginProjectbridgeConfig::getConfValueByName('CountOnlyPublicTasks');
   if (isset($post_data['projectbridge_config_countOnlyPublicTasks'])) {
       $countOnlyPublicTasks = $post_data['projectbridge_config_countOnlyPublicTasks'];
       PluginProjectbridgeConfig::updateConfValue('CountOnlyPublicTasks', $countOnlyPublicTasks);
   }
    echo '<tr>' . "\n";
    echo '<td>' . __('Count only public tasks in project', 'projectbridge') . '' . "\n";
    echo '</td>' . "\n";
    echo '<td>' . "\n";
    Dropdown::showYesNo('projectbridge_config_countOnlyPublicTasks', $countOnlyPublicTasks, []);
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    // AddContractSelectorOnCreatingTicketForm
    $addContractSelectorOnCreatingTicketForm = PluginProjectbridgeConfig::getConfValueByName('AddContractSelectorOnCreatingTicketForm');
   if (isset($post_data['projectbridge_config_addContractSelectorOnCreatingTicketForm'])) {
       $addContractSelectorOnCreatingTicketForm = $post_data['projectbridge_config_addContractSelectorOnCreatingTicketForm'];
       PluginProjectbridgeConfig::updateConfValue('AddContractSelectorOnCreatingTicketForm', $addContractSelectorOnCreatingTicketForm);
   }
    echo '<tr>' . "\n";
    echo '<td>' . __('Add contract selector on creating ticket form', 'projectbridge') . '' . "\n";
    echo '</td>' . "\n";
    echo '<td>' . "\n";
    Dropdown::showYesNo('projectbridge_config_addContractSelectorOnCreatingTicketForm', $addContractSelectorOnCreatingTicketForm, []);
    echo '</span>';
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    // isRequiredContractSelectorOnCreatingTicketForm
    $isRequiredContractSelectorOnCreatingTicketForm = PluginProjectbridgeConfig::getConfValueByName('isRequiredContractSelectorOnCreatingTicketForm');
   if (isset($post_data['projectbridge_config_isRequiredContractSelectorOnCreatingTicketForm'])) {
       $isRequiredContractSelectorOnCreatingTicketForm = $post_data['projectbridge_config_isRequiredContractSelectorOnCreatingTicketForm'];
       PluginProjectbridgeConfig::updateConfValue('isRequiredContractSelectorOnCreatingTicketForm', $isRequiredContractSelectorOnCreatingTicketForm);
   }
    $display = $isRequiredContractSelectorOnCreatingTicketForm?'block':'none';
    echo '<tr id="isRequiredContractSelectorOnCreatingTicketForm" style=\"display:'.$display.';\">' . "\n";
    echo '<td>' . __('Contract selector on creating ticket form is required', 'projectbridge') . '' . "\n";
    echo '</td>' . "\n";
    echo '<td>' . "\n";
    Dropdown::showYesNo('projectbridge_config_isRequiredContractSelectorOnCreatingTicketForm', $isRequiredContractSelectorOnCreatingTicketForm, []);
    echo '</span>';
    echo '</td>' . "\n";
    echo '</tr>' . "\n";


    // projectbridge_config_globalContractQuotaAlert
    $globalContractQuotaAlert = PluginProjectbridgeConfig::getConfValueByName('globalContractQuotaAlert');
   if (isset($post_data['projectbridge_config_globalContractQuotaAlert'])) {
       $globalContractQuotaAlert = $post_data['projectbridge_config_globalContractQuotaAlert'];
       PluginProjectbridgeConfig::updateConfValue('globalContractQuotaAlert', $globalContractQuotaAlert);
   }
    echo '<tr>' . "\n";
    echo '<td>' . __('Global percentage quota to send alert notification', 'projectbridge') . '' . "\n";
    echo '</td>' . "\n";
    echo '<td>' . "\n";
    Dropdown::showFromArray('projectbridge_config_globalContractQuotaAlert', range(0, 100), ['value' => $globalContractQuotaAlert]);
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    // select elements wich is copy during creating excess ticket
    $possibleElements = [
        'tasks' => _n('Task', 'Tasks', 2),
        'followups' => _n('Followup', 'Followup', 2),
        'documents' => _n('Document', 'Documents', 2),
        'solutions' => _n('Solution', 'Solutions', 2),
        'tickets' => _n('Ticket', 'Tickets', 2),
        'requester_groups' => _n('Requester group', 'Requester groups', 2),
        'requester' => __('Requester user'),
        'assign_groups' => __('Group in charge of the ticket'),
        'assign_technician' => __('Assigned to technicians'),
        'watcher_user' => __('Watcher user'),
        'watcher_group' => _n('Watcher group', 'Watcher groups', 2),
    ];
    $elementsAssociateToExcessTicket = PluginProjectbridgeConfig::getConfValueByName('ElementsAssociateToExcessTicket');

    if (!$elementsAssociateToExcessTicket) {
        // empty conf, populate with all values
        $elementsAssociateToExcessTicket = array_keys($possibleElements);
        PluginProjectbridgeConfig::updateConfValue('ElementsAssociateToExcessTicket', $elementsAssociateToExcessTicket);
    }
    if (isset($post_data['projectbridge_config_elementsAssociateToExcessTicket']) && is_array($post_data['projectbridge_config_elementsAssociateToExcessTicket'])) {
        $elementsAssociateToExcessTicket = $post_data['projectbridge_config_elementsAssociateToExcessTicket'];
        PluginProjectbridgeConfig::updateConfValue('ElementsAssociateToExcessTicket', $elementsAssociateToExcessTicket);
    }
    echo '<tr>' . "\n";
    echo '<td>' . __('Elements from previous ticket to be associated with renewed ticket', 'projectbridge') . '' . "\n";
    echo '</td>' . "\n";
    echo '<td>' . "\n";
    echo '<select name="projectbridge_config_elementsAssociateToExcessTicket[]" id="projectbridge_config_elementsAssociateToExcessTicket" class="select2" multiple>' . "\n";
    foreach ($possibleElements as $key => $value) {
        $select = in_array($key, $elementsAssociateToExcessTicket) ? 'selected' : '';
        echo '<option value="' . $key . '" ' . $select . '>' . $value . '</option>';
    }
    echo '</select>' . "\n";
    echo "<script type='text/javascript'>
    //<![CDATA[
    $(function() {
            $('select[name=\"projectbridge_config_addContractSelectorOnCreatingTicketForm\"]').change(function() {
                $('#isRequiredContractSelectorOnCreatingTicketForm').toggle();
            });

             $('#projectbridge_config_elementsAssociateToExcessTicket').select2({

                width: '100%',
                dropdownAutoWidth: true,
                quietMillis: 100,
                minimumResultsForSearch: 3,

                templateResult: templateResult,
                templateSelection: templateSelection,
             })
             .bind('setValue', function(e, value) {
                $('#projectbridge_config_elementsAssociateToExcessTicket').val(value).trigger('change');
             })
             $('label[for=projectbridge_config_elementsAssociateToExcessTicket]').on('click', function(){ $('#dropdown_projectbridge_config_elementsAssociateToExcessTicket1180093597').select2('open'); });
          });

    //]]>
    </script>";
    echo '</td>' . "\n";
    echo '</tr>' . "\n";

    echo '</tbody>' . "\n";
    echo '</table>' . "\n";
    echo '<input type="submit" class="submit" name="projectbridge_save_general_config" value="' . __('Save') . '" />';
    Html::closeForm();
} else {
    echo '<br/><br/>';
    echo '<img src="' . $CFG_GLPI['root_doc'] . '/pics/warning.png" alt="warning" />';
    echo '<br/><br/>';
    echo '<b>';
    echo __('Please activate the plugin or get the right of access', 'projectbridge') . '.';
    echo '</b>';
}

echo '</div>' . "\n";
echo '</div>' . "\n";
echo '</div>' . "\n";
Html::footer();
