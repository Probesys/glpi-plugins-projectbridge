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

Session::checkLoginUser();

Html::header(__('Project Tasks', 'projectbridge'), $_SERVER['PHP_SELF'], 'tools', 'projecttask');

// force GLPI to point to this page
global $CFG_GLPI;
$list_url = PLUGIN_PROJECTBRIDGE_WEB_DIR . '/front/projecttask.php';
$_GET['target'] = $list_url;

Search::show('projecttask');

Html::footer();
