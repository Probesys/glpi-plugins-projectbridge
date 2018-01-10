<?php

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
    public function __construct(Entity $entity)
    {
        $this->_entity = $entity;
    }

    /**
     * Get the id of the default contract linked to the entity
     *
     * @param void
     * @return integer|null
     */
    public function getContractId()
    {
        if ($this->_contract_id === null) {
            $result = $this->getFromDBByQuery("WHERE entity_id = " . $this->_entity->getId());

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
    public static function postShow(Entity $entity)
    {
        $bridge_entity = new PluginProjectbridgeEntity($entity);
        $contract_id = $bridge_entity->getContractId();

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

        $html_parts[] = '<td colspan="2">';
        $html_parts[] = Contract::dropdown($contract_config);

        if (!empty($contract_id)) {
            global $CFG_GLPI;

            $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/contract.form.php?id=' . $contract_id . '" style="margin-left: 5px;" target="_blank">';
            $html_parts[] = 'Accéder au contrat par défaut'; // todo: i18n
            $html_parts[] = '</a>';
        }

        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '<td>';
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
