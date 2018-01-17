<?php

class PluginProjectbridgeConfig extends CommonDBTM
{
    const NAMESPACE = 'projectbridge';
    const MIN_GLPI_VERSION = '9.2';

    public static $table_name = 'glpi_plugin_projectbridge_configs';

    /**
     * Get all recipients of alerts
     *
     * @return array
     */
    public static function getRecipients()
    {
        global $DB;
        $recipients = array();

        $get_all_recipients_query = "
            SELECT
                id,
                user_id
            FROM
                " . PluginProjectbridgeConfig::$table_name . "
            ORDER BY
                id ASC
        ";

        $result = $DB->query($get_all_recipients_query);

        if (
            $result
            && $DB->numrows($result)
        ) {
            while ($row = $DB->fetch_assoc($result)) {
                $user_id = (int) $row['user_id'];
                $user = new User();
                $user->getFromDB($user_id);

                if ($user->getId()) {
                    $default_email = $user->getDefaultEmail();

                    if (!empty($default_email)) {
                        $recipients[(int) $row['id']] = array(
                            'user_id' => $user_id,
                            'name' => $user->fields['name'],
                            'email' => $user->getDefaultEmail(),
                        );
                    }
                }
            }
        }

        return $recipients;
    }
}
