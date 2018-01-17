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

if ($can_update) {
    $post_fields = array(
        'projectbridge_delete_recipient',
        'projectbridge_add_recipient',
        'projectbridge_add_recipient_submit',
    );

    $post_data = getPostDataFromFields($post_fields);
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

    echo '<style>' . "\n";
    echo '    table td, table th { border-bottom: 1px solid #ccc;}';
    echo '</style>' . "\n";

    echo '<form method="post" action="">' . "\n";
    echo '<table>' . "\n";

    if (true) {
        echo '<thead>' . "\n";

        echo '<tr>' . "\n";

        echo '<th style="min-width: 200px">';
        echo 'Nom';
        echo '</th>' . "\n";

        echo '<th>';
        echo '&nbsp;';
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
            echo '&nbsp;';
            echo '</td>' . "\n";

            echo '<td>';
            echo '<input type="submit" class="submit" name="projectbridge_delete_recipient[' . $row_id . ']" value="Supprimer" />';
            echo '</td>' . "\n";

            echo '</tr>' . "\n";
        }

        if (empty($recipients)) {
            echo '<tr>' . "\n";

            echo '<td colspan="3">';
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
            echo '&nbsp;';
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
