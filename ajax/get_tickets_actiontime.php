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

require('../../../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['ticket_ids']) && is_array($_POST['ticket_ids'])
) {
    $onlypublicTasks = PluginProjectbridgeConfig::getConfValueByName('CountOnlyPublicTasks');

    $tickets_actiontime = [];

   foreach ($_POST['ticket_ids'] as $ticketID) {
       $whereConditionsArray = [];
       $totalActiontime = 0;
       $whereConditionsArray = ['tickets_id' => $ticketID];
       //if ($onlypublicTasks) {
      if (!Session::haveRight("task", CommonITILTask::SEEPRIVATE) || $onlypublicTasks) {
          $whereConditionsArray['is_private'] = 0;
      }

       $req = $DB->request([
           'SELECT' => new QueryExpression('SUM(' . TicketTask::getTable() . '.actiontime) AS duration'),
           'FROM' => TicketTask::getTable(),
           'WHERE' => $whereConditionsArray
       ]);
      foreach ($req as $row) {
         $totalActiontime = (int) $row['duration'];
      }
      if (!empty($totalActiontime)) {
          $totalActiontime = round($totalActiontime / 3600 * 100, 1) / 100;
      }

       $tickets_actiontime[$ticketID]['totalDuration'] = $totalActiontime;

       // récupération durée privée
       $privateActiontime = 0;
      if (Session::haveRight("task", CommonITILTask::SEEPRIVATE) && !$onlypublicTasks) {
          $whereConditionsArray['is_private'] = 1;
          $req = $DB->request([
              'SELECT' => new QueryExpression('SUM(' . TicketTask::getTable() . '.actiontime) AS duration'),
              'FROM' => TicketTask::getTable(),
              'WHERE' => $whereConditionsArray
          ]);
         foreach ($req as $row) {
            $privateActiontime = (int) $row['duration'];
         }
         if (!empty($privateActiontime)) {
             $privateActiontime = round($privateActiontime / 3600 * 100, 1) / 100;
         }
      }

       $tickets_actiontime[$ticketID]['privateDuration'] = $privateActiontime;
   }




    echo json_encode($tickets_actiontime);
}
