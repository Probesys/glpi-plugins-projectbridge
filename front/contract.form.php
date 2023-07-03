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

include('../../../inc/includes.php');
//print_r($_POST);

global $CFG_GLPI;

if (isset($_POST) && array_key_exists('id', $_POST)) {
    $contractId = $_POST['id'];
    $contract = new Contract();
    $contract->getFromDB($contractId);
    $quota = null;
   if (array_key_exists('percentage_quota', $_POST)) {
       $quota = $_POST['percentage_quota'];
   }
    $contractQuotaAlertObject = new PluginProjectbridgeContractQuotaAlert();
    $contractQuotaAlertObject::updateContractQuotaAlert($contractId, $quota);


    Html::redirect($CFG_GLPI['root_doc']."/front/contract.form.php?id=".$contractId);
}
