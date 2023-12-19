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

chdir(__DIR__);
require_once('../../../inc/includes.php');
require_once('../hook.php');

$ticket_task = new TicketTask();
$ticket_tasks = $ticket_task->find("TRUE AND actiontime > 0");

echo 'Trouvé ' . count($ticket_tasks) . ' tâches avec du temps' . "\n";

foreach ($ticket_tasks as $ticket_task_data) {
    echo 'Re-calcul pour la tâche liée au ticket ' . $ticket_task_data['tickets_id'] . "\n";

    // use the existing time to force an update of the percent_done in the tasks linked to the tickets
    PluginProjectbridgeTask::updateProgressPercent($ticket_task_data['tickets_id']);
}
