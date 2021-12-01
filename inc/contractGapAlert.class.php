<?php

class PluginProjectbridgeContractGapAlert extends CommonDBTM
{
    private $_contract;
    private $gapalert;
    public static $table_name = 'glpi_plugin_projectbridge_contracts_gapAlert';
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
        $this->gapalert = 0;
    }

    /**
     * update value of a conf, if not exist, insert it
     * @global type $DB
     * @param type $name
     * @param type $newValue
     */
    public static function updateContractGapAlert($contractId, $gap)
    {
        global $DB;
        $contractGapAlert = self::getContractGapAlertByContractID($contractId);
        if ($contractGapAlert) {
            $DB->update(
                PluginProjectbridgeContractGapAlert::$table_name,
                [
                   'gapAlert'    => (int) $gap
                ],
                [
                   'contract_id' => (int) $contractId
                ]
            );
        } else {
            $DB->insert(
                PluginProjectbridgeContractGapAlert::$table_name,
                [
                   'gapAlert'    => (int) $gap,
                   'contract_id' => (int) $contractId
                ]
            );
        }
    }

    /**
     * get contractGapAlert entry by contractId
     * @global type $DB
     * @param string $contractId
     * @return type
     */
    public static function getContractGapAlertByContractID($contractId)
    {
        global $DB;
        $contractGapAlert = null;
        $req = $DB->request([
          'SELECT' => ['*'],
          'FROM' => PluginProjectbridgeContractGapAlert::$table_name,
          'WHERE' => ['contract_id' => $contractId]
        ]);
        if ($row = $req->next()) {
            $contractGapAlert = $row;
        }

        return $contractGapAlert;
    }
}
