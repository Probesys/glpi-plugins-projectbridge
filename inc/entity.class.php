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

class PluginProjectbridgeEntity extends CommonDBTM
{
    private $_entity;
    private $_contract_id;

   public static $table_name = 'glpi_plugin_projectbridge_entities';

    /**
     * Constructor
     *
     * @param Entity $entity
     */
   public function __construct(Entity $entity = null) {
       $this->_entity = $entity;
   }

    /**
     * Get the id of the default contract linked to the entity
     *
     * @param void
     * @return integer|null
     */
   public function getContractId($entityId = null) {
      if ($this->_contract_id === null) {
         if (!$entityId) {
            $entityId = $this->_entity->getId();
         }
          $result = $this->getFromDBByCrit(['entity_id' => $entityId]);

         if ($result) {
             $this->_contract_id = (int) $this->fields['contract_id'];
         }
      }

       return $this->_contract_id;
   }

    /**
     * Display HTML after entity has been shown
     *
     * @param  Entity $entity
     * @return void
     */
   public static function postShow(Entity $entity) {
       global $CFG_GLPI;
       $bridge_entity = new PluginProjectbridgeEntity($entity);
       $contract_id = $bridge_entity->getContractId();

       $contract_config = [
          'value' => $contract_id,
          'name' => 'projectbridge_contract_id',
          'display' => false,
          'entity' => $entity->getId(),
          'entity_sons'  => (!empty($_SESSION['glpiactive_entity_recursive'])) ? true : false,
          'nochecklimit' => true,
          'expired' => true,
       ];

       $html_parts = [];
       $html_parts[] = '<div style="display: none;">' . "\n";
       $html_parts[] = '<div class="form-field row col-12 col-sm-6  mb-2" id="projectbridge_config">' . "\n";
       $html_parts[] = '<label class="col-form-label col-xxl-5 text-xxl-end">';
       $html_parts[] = __('Default contract', 'projectbridge');
       $html_parts[] = '</label>' . "\n";
       $html_parts[] = '<div class="col-xxl-7  field-container">' . "\n";
       $html_parts[] = Contract::dropdown($contract_config);

       if (!empty($contract_id)) {
           $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/contract.form.php?id=' . $contract_id . '" style="margin-left: 5px;" target="_blank">';
           $html_parts[] = __('Default contract access', 'projectbridge');
           $html_parts[] = '</a>' . "\n";
       } else {
           $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/setup.templates.php?itemtype=Contract&add=1" style="margin-left: 5px;" target="_blank">';
           $html_parts[] = __('Create a new contract', 'projectbridge').' ?';
           $html_parts[] = '</a>' . "\n";

           $html_parts[] = '<small>';
           $html_parts[] = __('Remember to refresh this page after creating the contract', 'projectbridge');
           $html_parts[] = '</small>' . "\n";
       }

       $html_parts[] = '</div>' . "\n";

       $html_parts[] = '</div>' . "\n";
       $html_parts[] = '</div>' . "\n";

       echo implode('', $html_parts);
       echo Html::scriptBlock('$(document).ready(function() {
            var projectbridge_config = $("#projectbridge_config");
            $("#mainformtable .card-body").first().append(projectbridge_config.clone());
            projectbridge_config.remove();

            $("#projectbridge_config .select2-container").remove();
            $("#projectbridge_config select").select2({
                width: \'\',
                dropdownAutoWidth: true
            });
            $("#projectbridge_config .select2-container").show();
        });');
   }
}
