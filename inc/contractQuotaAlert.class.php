<?php

class PluginProjectbridgeContractQuotaAlert extends CommonDBTM
{
    private $_contract;
    private $quotaalert;
    public static $table_name = 'glpi_plugin_projectbridge_contracts_quotaAlert';
    //put your code here

    /**
     * Constructor
     *
     * @param Contract|null $contract
     */
    public function __construct($contract = null)
    {
        if ($contract !== null || $contract instanceof Contract) {
            $this->_contract = $contract;
        }
        $this->quotaalert = 0;
    }

    /**
     * update value of a conf, if not exist, insert it
     * @global type $DB
     * @param type $name
     * @param type $newValue
     */
    public static function updateContractQuotaAlert($contractId, $quota)
    {
        global $DB;
        $contractQuotaAlert = self::getContractQuotaAlertByContractID($contractId);
        if ($contractQuotaAlert) {
            $DB->update(
                PluginProjectbridgeContractQuotaAlert::$table_name,
                [
                   'quotaAlert'    => (int) $quota
                ],
                [
                   'contract_id' => (int) $contractId
                ]
            );
        } else {
            $DB->insert(
                PluginProjectbridgeContractQuotaAlert::$table_name,
                [
                   'quotaAlert'    => (int) $quota,
                   'contract_id' => (int) $contractId
                ]
            );
        }
    }

    /**
     * get contractQuotaAlert entry by contractId
     * @global type $DB
     * @param string $contractId
     * @return type
     */
    public static function getContractQuotaAlertByContractID($contractId)
    {
        global $DB;
        $contractQuotaAlert = null;
        $req = $DB->request([
          'SELECT' => ['*'],
          'FROM' => PluginProjectbridgeContractQuotaAlert::$table_name,
          'WHERE' => ['contract_id' => $contractId]
        ]);
        if ($row = $req->next()) {
            $contractQuotaAlert = $row;
        }

        return $contractQuotaAlert;
    }
}
