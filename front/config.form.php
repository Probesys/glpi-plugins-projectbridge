<?php

include('../../../inc/includes.php');

function getPostDataFromField($post_field)
{
    $value = null;

    if (isset($_POST[$post_field])) {
        if (!is_array($_POST[$post_field])) {
            $_POST[$post_field] = trim($_POST[$post_field]);
        }

        $value = $_POST[$post_field];
    }

    return $value;
}

function getPostDataFromFields(array $post_fields)
{
    $post_data = array();

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

        if (
            $config->canView()
            && $config->canUpdate()
        ) {
            $can_update = true;
        }
    }
}

global $CFG_GLPI;

Html::header('ProjectBridge Configuration', $_SERVER['PHP_SELF'], 'config', 'plugins');
echo '<div align="center">' . "\n";

echo '<h1>';
echo 'ProjectBridge Configuration';
echo '</h1>' . "\n";

echo '<hr />' . "\n";

if ($can_update) {
    $post_fields = array(
        'projectbridge_state_in_progress',
        'projectbridge_state_closed',
        'projectbridge_state_renewal',
        'projectbridge_save_states',

        'projectbridge_delete_recipient',
        'projectbridge_add_recipient',
        'projectbridge_add_recipient_submit',
    );

    $post_data = getPostDataFromFields($post_fields);

    echo '<style>' . "\n";
    echo '    table td, table th { border-bottom: 1px solid #ccc; border-left: 1px solid #ccc; padding: 15px;}';
    echo '    table td:first-child, table th:first-child { border-left: 0px;}';
    echo '</style>' . "\n";

    echo '<a href="' . rtrim($CFG_GLPI['root_doc'], '/') . '/plugins/projectbridge/front/projecttask.php">';
    echo 'Tâches de projet';
    echo '</a>';

    if (true) {
        // status config

        echo '<h2>';
        echo 'Configuration des statuts';
        echo '</h2>' . "\n";

        echo '<p>';
        echo 'Veuillez faire la correspondance entre les noms de statut et leurs valeurs dans GLPI.';
        echo '</p>' . "\n";

        echo '<form method="post" action="">' . "\n";
        echo '<table>' . "\n";

        if (true) {
            echo '<thead>' . "\n";

            echo '<tr>' . "\n";

            echo '<th>';
            echo 'Nom du statut';
            echo '</th>' . "\n";

            echo '<th>';
            echo 'Type de statut';
            echo '</th>' . "\n";

            echo '<th>';
            echo 'Statut correspondant';
            echo '</th>' . "\n";

            echo '</tr>' . "\n";

            echo '</thead>' . "\n";
        }

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
                    $state->getFromDBByQuery("WHERE TRUE AND status = 'in_progress'");
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
                    $state->getFromDBByQuery("WHERE TRUE AND status = 'closed'");
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
                    $state->getFromDBByQuery("WHERE TRUE AND status = 'renewal'");
                    $state_data['id'] = $state->fields['id'];
                    $state->update($state_data);
                }
            }


        }

        $state_dropdown_conf = [
            'addicon' => false,
            'comments' => false,
        ];

        if (true) {
            $state_in_progress_value = PluginProjectbridgeState::getProjectStateIdByStatus('in_progress');

            echo '<tr>' . "\n";

            echo '<td>';
            echo 'En cours';
            echo '</td>' . "\n";

            echo '<td>';
            echo 'Tâche';
            echo '</td>' . "\n";

            echo '<td>';
            ProjectState::dropdown($state_dropdown_conf + ['value' => $state_in_progress_value, 'name' => 'projectbridge_state_in_progress']);
            echo '</td>' . "\n";

            echo '</tr>' . "\n";
        }

        if (true) {
            $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');

            echo '<tr>' . "\n";

            echo '<td>';
            echo 'Clos';
            echo '</td>' . "\n";

            echo '<td>';
            echo 'Tâche';
            echo '</td>' . "\n";

            echo '<td>';
            ProjectState::dropdown($state_dropdown_conf + ['value' => $state_closed_value, 'name' => 'projectbridge_state_closed']);
            echo '</td>' . "\n";

            echo '</tr>' . "\n";
        }

        if (true) {
            $state_renewal_value = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');

            echo '<tr>' . "\n";

            echo '<td>';
            echo 'Renouvellement';
            echo '</td>' . "\n";

            echo '<td>';
            echo 'Ticket';
            echo '</td>' . "\n";

            echo '<td>';
            RequestType::dropdown($state_dropdown_conf + ['value' => $state_renewal_value, 'name' => 'projectbridge_state_renewal']);
            echo '</td>' . "\n";

            echo '</tr>' . "\n";
        }

        if (true) {
            echo '<tr style="text-align: center">' . "\n";

            echo '<td colspan="3">';
            echo '<input type="submit" class="submit" name="projectbridge_save_states" value="Enregistrer" />';
            echo '</td>' . "\n";

            echo '</tr>' . "\n";
        }

        echo '</tbody>' . "\n";
        echo '</table>' . "\n";
        Html::closeForm();

        echo '<hr />' . "\n";
    }

    if (true) {
        // recipients config

        $recipients = PluginProjectbridgeConfig::getRecipients();

        if (
            !empty($post_data['projectbridge_delete_recipient'])
            && is_array($post_data['projectbridge_delete_recipient'])
        ) {
            $row_id = key($post_data['projectbridge_delete_recipient']);

            if (isset($recipients[$row_id])) {
                $config = new PluginProjectbridgeConfig();

                if ($config->delete(array('id' => $row_id))) {
                    unset($recipients[$row_id]);
                }
            }
        } else if (
            !empty($post_data['projectbridge_add_recipient'])
            && !empty($post_data['projectbridge_add_recipient_submit'])
            && !isset($post_data[(int) $post_data['projectbridge_add_recipient']])
        ) {
            $recipient_user_id = (int) $post_data['projectbridge_add_recipient'];
            $config = new PluginProjectbridgeConfig();

            if ($config->add(array('user_id' => $recipient_user_id))) {
                $recipients = PluginProjectbridgeConfig::getRecipients();
            }
        }

        echo '<h2>';
        echo 'Personnes recevant les alertes';
        echo '</h2>' . "\n";

        echo '<form method="post" action="">' . "\n";
        echo '<table>' . "\n";

        if (true) {
            echo '<thead>' . "\n";

            echo '<tr>' . "\n";

            echo '<th style="min-width: 200px">';
            echo 'Nom';
            echo '</th>' . "\n";

            echo '<th>';
            echo 'Action';
            echo '</th>' . "\n";

            echo '</tr>' . "\n";

            echo '</thead>' . "\n";
        }

        if (true) {
            global $CFG_GLPI;

            echo '<tbody>' . "\n";
            $recipient_user_ids = array();

            foreach ($recipients as $row_id => $recipient) {
                $recipient_user_ids[] = $recipient['user_id'];

                echo '<tr>' . "\n";

                echo '<th>' . "\n";
                echo '<a href="' . $CFG_GLPI['root_doc'] . '/front/user.form.php?id=' . $recipient['user_id'] . '" />';
                echo $recipient['name'];
                echo '</a>' . "\n";
                echo '</td>' . "\n";

                echo '<td>';
                echo '<input type="submit" class="submit" name="projectbridge_delete_recipient[' . $row_id . ']" value="Supprimer" />';
                echo '</td>' . "\n";

                echo '</tr>' . "\n";
            }

            if (empty($recipients)) {
                echo '<tr>' . "\n";

                echo '<td colspan="2" style="text-align: center">';
                echo 'Personne ne reçoit les alertes';
                echo '</td>' . "\n";

                echo '</tr>' . "\n";
            }

            if (true) {
                echo '<tr">' . "\n";

                echo '<td>' . "\n";
                echo User::dropdown(array(
                    'name' => 'projectbridge_add_recipient',
                    'used' => $recipient_user_ids,
                    'right' => 'all',
                    'comments' => false,
                    'display' => false,
                ));
                echo '</td>' . "\n";

                echo '<td>';
                echo '<input type="submit" class="submit" name="projectbridge_add_recipient_submit" value="Ajouter" />';
                echo '</td>' . "\n";

                echo '</tr>' . "\n";
            }

            echo '</tbody>' . "\n";
        }

        echo '</table>' . "\n";
        Html::closeForm();
    }
} else {
    echo '<br/><br/>';
    echo '<img src="' . $CFG_GLPI['root_doc'] . '/pics/warning.png" alt="warning" />';
    echo '<br/><br/>';
    echo '<b>';
    echo 'Veuillez activer le plugin ou obtenir le droit d\'accès.';
    echo '</b>';
}

echo '</div>' . "\n";
Html::footer();
