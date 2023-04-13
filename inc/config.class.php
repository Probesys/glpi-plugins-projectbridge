<?php

class PluginProjectbridgeConfig extends CommonDBTM
{
    const NAMESPACE = 'projectbridge';

    public static $table_name = 'glpi_plugin_projectbridge_configs';

    /**
     * Get all recipients of alerts
     *
     * @return array
     */
    public static function getRecipients()
    {
        $recipients = [];
        $recipientIds = self::getRecipientIds();

        foreach ($recipientIds as $user_id) {
            $user = new User();
            $user->getFromDB($user_id);
            if ($user->getId()) {
                $default_email = $user->getDefaultEmail();

                if (!empty($default_email)) {
                    $recipients[$user_id] = [
                      'user_id' => $user_id,
                      'name' => $user->fields['name'],
                      'email' => $user->getDefaultEmail(),
                    ];
                }
            }
        }

        return $recipients;
    }

    /**
     * Get all Recipients IDS
     * @return type
     */
    public static function getRecipientIds()
    {
        return self::getConfValueByName('RecipientIds')?self::getConfValueByName('RecipientIds'):[];
    }
    
    /**
     * get conf entry by name
     * @global type $DB
     * @param string $name
     * @return type
     */
    public static function getConfByName($name)
    {
        global $DB;
        $conf = null;
        $req = $DB->request([
          'SELECT' => ['*'],
          'FROM' => PluginProjectbridgeConfig::$table_name,
          'WHERE' => ['name' => $name]
        ]);
        foreach ($req as $row) {
            return $row;
        }
        
        return $conf;
    }
    
    /**
     * get value of a conf entry by name
     * @param type $name
     * @return type
     */
    public static function getConfValueByName($name)
    {
        $value = false;
        $conf = self::getConfByName($name);
        if ($conf) {
            $value = json_decode($conf['value']);
        }
        
        return $value;
    }
    
    /**
     * update value of a conf, if not exist, insert it
     * @global type $DB
     * @param type $name
     * @param type $newValue
     */
    public static function updateConfValue($name, $newValue)
    {
        global $DB;
        $conf = self::getConfByName($name);
        if ($conf) {
            $DB->update(
                PluginProjectbridgeConfig::$table_name,
                [
                   'value'      => json_encode($newValue)
                ],
                [
                   'id' => (int) $conf['id']
                ]
            );
        } else {
            $DB->insert(
                PluginProjectbridgeConfig::$table_name,
                [
                   'value'      => json_encode($newValue),
                   'name'      => $name
                ]
            );
        }
    }

    /**
     * Notify a recipient
     *
     * @param  string $html_content
     * @param  string $recepient_email
     * @param  string $recepient_name
     * @param  string $subject
     * @return boolean
     */
    public static function notify($html_content, $recepient_email, $recepient_name, $subject)
    {
        global $CFG_GLPI;

        $mailer = new GLPIMailer();

        $mailer->AddCustomHeader('Auto-Submitted: auto-generated');
        // For exchange
        $mailer->AddCustomHeader('X-Auto-Response-Suppress: OOF, DR, NDR, RN, NRN');

        $mailer->SetFrom($CFG_GLPI['admin_email'], $CFG_GLPI['admin_email_name'], false);

        $mailer->isHTML(true);
        $mailer->Body = $html_content . '<br /><br />' . $CFG_GLPI['mailing_signature'];

        $mailer->AddAddress($recepient_email, $recepient_name);
        $mailer->Subject = '[GLPI] ' . $subject;

        return $mailer->Send();
    }
}
