<?php

/*
  -------------------------------------------------------------------------
  GLPI - Gestionnaire Libre de Parc Informatique
  Copyright (C) 2003-2011 by the INDEPNET Development Team.

  http://indepnet.net/   http://glpi-project.org
  -------------------------------------------------------------------------

  LICENSE

  This file is part of GLPI.

  GLPI is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  GLPI is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with GLPI. If not, see <http://www.gnu.org/licenses/>.
  --------------------------------------------------------------------------
 */

/**
 * Summary of PluginProjectbridgeItemForm
 * 
 * @see http://glpi-developer-documentation.rtfd.io/en/master/plugins/hooks.html#items-display-related
 * */
class PluginProjectbridgeItemForm {

    /**
     * Display contents at the begining of item forms.
     *
     * @param array $params Array with "item" and "options" keys
     *
     * @return void
     */
    static public function preItemForm($params) {
        $item = $params['item'];
        $options = $params['options'];
        $out = "";
        if( PluginProjectbridgeConfig::getConfValueByName('AddContractSelectorOnCreatingTicketForm') ) {
            // only for create new ticket form
            if ($item::getType() == Ticket::getType() && $options['id'] == 0) {
                // récupération entité courante
                $entityID = $options['entities_id'];
                $out = self::getEntityContractsSelector($entityID);
            }
        }

        echo $out;
    }

    private static function getEntityContractsSelector($entityID) {
        global $DB;
        // récupération du contrat par défaut
        $entity = new Entity();
        $entityObject = $entity->getById($entityID);
        $bridge_entity = new PluginProjectbridgeEntity($entityObject);
        $defaultContractID = $bridge_entity->getContractId();
        $bridge_contract = new PluginProjectbridgeContract();
        $project = new Project();
        $contract = new Contract();
        $projectTask = new ProjectTask();
        $state_in_progress_value = PluginProjectbridgeState::getProjectStateIdByStatus('in_progress');
        $state_closed_value = PluginProjectbridgeState::getProjectStateIdByStatus('closed');
        $state_renewal_value = PluginProjectbridgeState::getProjectStateIdByStatus('renewal');
        $now = new DateTime();

        $contract_datas = [];

        foreach ($DB->request([
            'SELECT' => [$bridge_contract->getTable().'.contract_id', $project->getTable().'.name AS name', $bridge_contract->getTable().'.project_id', $projectTask->getTable().'.plan_end_date AS plan_end_date',],
            'FROM' => $bridge_contract->getTable(),
            'INNER JOIN' => [
                $contract->getTable() => [
                    'FKEY' => [
                        $bridge_contract->getTable() => 'contract_id',
                        $contract->getTable() => 'id'
                    ]
                ],
                $project->getTable() => [
                    'FKEY' => [
                        $bridge_contract->getTable() => 'project_id',
                        $project->getTable() => 'id'
                    ]
                ],
                $projectTask->getTable() => [
                    'FKEY' => [
                        $project->getTable() => 'id',
                        $projectTask->getTable() => 'projects_id'
                    ]
                ]
            ],
            'WHERE' => [
                $contract->getTable().'.entities_id' => $entityID,
                $contract->getTable().'.is_deleted' => 0,
                $contract->getTable().'.is_template' => 0,
                $projectTask->getTable().'.projectstates_id' => [$state_in_progress_value, $state_renewal_value],
                $projectTask->getTable().'.plan_end_date' => ['>=', 'NOW()']
            ]
        ]) as $data) {
            $contract_datas[] = $data;
        }

        $contract_list = [
            null => Dropdown::EMPTY_VALUE,
        ];
        foreach ($contract_datas as $contract_data) {
            $contract_list[$contract_data['contract_id']] = $contract_data['name'] ;
        }
        $config = [
            'value' => $defaultContractID,
            'display' => false,
            'values' => $contract_list,
            'class' => 'required',
            'noselect2' => false
        ];

        $html_parts = Dropdown::showFromArray('projectbridge_contract_id', $contract_list, $config);
        $requiredSpan = '';
        if(count($contract_datas)) {
            // if at least one contract was found, add required attribute
            $html_parts = str_replace('<select', '<select required', $html_parts);
            $requiredSpan = '<span class="required">*</span>';
        }
        
        $out = '<tr tab_bg_1>';
        $out .= '<th>' . __('Associated contract') .$requiredSpan. '</th>';
        // récupération des contrats associés à l'entité
        $out .= '<td>' . $html_parts . '</td>';
        $out .= '<th></th><td></td>';
        $out .= '</tr>';
        

        return $out;
    }

    /**
     * Display contents at the begining of item forms.
     *
     * @param array $params Array with "item" and "options" keys
     *
     * @return void
     */
    static public function postItemForm($params) {
        $item = $params['item'];
        $options = $params['options'];

        $out = '';

        echo $out;
    }

}
