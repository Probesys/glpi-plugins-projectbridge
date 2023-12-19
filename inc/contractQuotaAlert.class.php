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

class PluginProjectbridgeContractQuotaAlert extends CommonDBTM {

    private $_contract;
    private $quotaalert;
   public static $table_name = 'glpi_plugin_projectbridge_contracts_quotaAlert';

    //put your code here

    /**
     * Constructor
     *
     * @param Contract|null $contract
     */
   public function __construct($contract = null) {
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
   public static function updateContractQuotaAlert($contractId, $quota) {
       global $DB;
       $contractQuotaAlert = self::getContractQuotaAlertByContractID($contractId);
      if ($contractQuotaAlert) {
          $DB->update(
                  PluginProjectbridgeContractQuotaAlert::$table_name,
                  [
                      'quotaAlert' => (int) $quota
                  ],
                  [
                      'contract_id' => (int) $contractId
                  ]
          );
      } else {
          $DB->insert(
                  PluginProjectbridgeContractQuotaAlert::$table_name,
                  [
                      'quotaAlert' => (int) $quota,
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
   public static function getContractQuotaAlertByContractID($contractId) {
       global $DB;
       $contractQuotaAlert = null;
       $req = $DB->request([
           'SELECT' => ['*'],
           'FROM' => PluginProjectbridgeContractQuotaAlert::$table_name,
           'WHERE' => ['contract_id' => $contractId]
       ]);

      foreach ($req as $row) {
          $contractQuotaAlert = $row;
      }

       return $contractQuotaAlert;
   }

}
