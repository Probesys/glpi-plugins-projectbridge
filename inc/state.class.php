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

class PluginProjectbridgeState extends CommonDBTM
{
   public static $table_name = 'glpi_plugin_projectbridge_states';

    /**
     * Get the GLPI projectstates_id by status name
     *
     * @param string $status
     * @return int|null
     */
   public static function getProjectStateIdByStatus($status) {
       $allowed_states = [
          'in_progress',
          'closed',
          'renewal',
       ];

       $project_state_id = null;

       if (in_array($status, $allowed_states)) {
           $state = new PluginProjectbridgeState();
           $state->getFromDBByCrit(['status' => $status]);

          if (isset($state->fields['projectstates_id'])) {
              $project_state_id = $state->fields['projectstates_id'];
          }
       }

       return $project_state_id;
   }
}
