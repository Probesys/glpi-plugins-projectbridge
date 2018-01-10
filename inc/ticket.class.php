<?php

class PluginProjectbridgeTicket extends CommonDBTM
{
    private $_ticket;

    public static $table_name = 'glpi_plugin_projectbridge_entities'; // todo

    public function __construct(Ticket $ticket)
    {
        $this->_ticket = $ticket;
    }

    public static function postShow(Ticket $ticket)
    {
        $bridge_contract = new PluginProjectbridgeEntity($entity);
        $contract_id = $bridge_contract->getContractId();

        $contract_config = array(
            'value' => $contract_id,
            'name' => 'projectbridge_contract_id',
            'display' => false,
            'entity' => $entity->getId(),
            'entity_sons'  => (!empty($_SESSION['glpiactive_entity_recursive'])) ? true : false,
            'nochecklimit' => true,
        );

        $html_parts = array();
        $html_parts[] = '<div style="display: none;">' . "\n";
        $html_parts[] = '<table>' . "\n";
        $html_parts[] = '<tr id="projectbridge_config">' . "\n";

        $html_parts[] = '<td>';
        $html_parts[] = 'Contrat par défaut'; // todo: i18n
        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '<td>';
        $html_parts[] = Contract::dropdown($contract_config);
        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '<td colspan="2">';
        $html_parts[] = '&nbsp;';
        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '</tr>' . "\n";
        $html_parts[] = '</table>' . "\n";
        $html_parts[] = '</div>' . "\n";

        echo implode('', $html_parts);
        echo Html::scriptBlock('$(document).ready(function() {
            var projectbridge_config = $("#projectbridge_config");
            $("#mainformtable .footerRow").before(projectbridge_config.clone());
            projectbridge_config.remove();

            $("#projectbridge_config .select2-container").remove();
            $("#projectbridge_config select").select2({
                dropdownAutoWidth: true
            });
            $("#projectbridge_config .select2-container").show();
        });');
    }
}
