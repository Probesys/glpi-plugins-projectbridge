<?php

class PluginProjectbridgeContract extends CommonDBTM
{
    private $_contract;
    private $_project_id;

    public static $table_name = 'glpi_plugin_projectbridge_contracts';

    /**
     * Constructor
     *
     * @param Contract $contract
     */
    public function __construct(Contract $contract)
    {
        $this->_contract = $contract;
    }

    /**
     * Get the id of the project linked to the contract
     *
     * @param void
     * @return integer|null
     */
    public function getProjectId()
    {
        if ($this->_project_id === null) {
            $result = $this->getFromDBByQuery("WHERE contract_id = " . $this->_contract->getId());

            if ($result) {
                $this->_project_id = (int) $this->fields['project_id'];
            }
        }

        return $this->_project_id;
    }

    /**
     * Display HTML after contract has been shown
     *
     * @param  Contract $contract
     * @return void
     */
    public static function postShow(Contract $contract)
    {
        $search_filters = array();

        if (!empty($_SESSION['glpiactiveentities'])) {
            $search_filters[] = "`entities_id` IN (" . implode(', ', $_SESSION['glpiactiveentities']) . ")";
        }

        $bridge_contract = new PluginProjectbridgeContract($contract);
        $project_id = $bridge_contract->getProjectId();

        $project = new Project();
        $project_results = $project->find(implode(' ', $search_filters));
        $project_list = array(
            null => Dropdown::EMPTY_VALUE,
        );

        foreach ($project_results as $project_data) {
            $project_list[$project_data['id']] = $project_data['name'];
        }

        $project_config = array(
            'value' => $project_id,
            'display' => false,
            'values' => $project_list,
        );

        $html_parts = array();
        $html_parts[] = '<div style="display: none;">' . "\n";
        $html_parts[] = '<table>' . "\n";
        $html_parts[] = '<tr id="projectbridge_config">' . "\n";

        $html_parts[] = '<td>';
        $html_parts[] = 'Projet lié'; // todo: i18n
        $html_parts[] = '</td>' . "\n";

        $html_parts[] = '<td colspan="2">' . "\n";
        $html_parts[] = Dropdown::showFromArray('projectbridge_project_id', $project_list, $project_config);

        global $CFG_GLPI;

        if (!empty($project_id)) {
            $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/project.form.php?id=' . $project_id . '" style="margin-left: 5px;" target="_blank">';
            $html_parts[] = 'Accéder au projet lié'; // todo: i18n
            $html_parts[] = '</a>' . "\n";
        } else {
            $html_parts[] = '<a href="' . $CFG_GLPI['root_doc'] . '/front/setup.templates.php?itemtype=Project&add=1" style="margin-left: 5px;" target="_blank">';
            $html_parts[] = 'Créer un projet ?'; // todo: i18n
            $html_parts[] = '</a>' . "\n";

            $html_parts[] = '<small>';
            $html_parts[] = 'Pensez à rafraîchir cette page après avoir créé le projet';
            $html_parts[] = '</small>' . "\n";

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
            var target = $("#mainformtable tr.footerRow").next();

            if (!target.length) {
                target = $("#mainformtable tr:last");
            }

            target.before(projectbridge_config.clone());
            projectbridge_config.remove();

            $("#projectbridge_config .select2-container").remove();
            $("#projectbridge_config select").select2({
                dropdownAutoWidth: true
            });
            $("#projectbridge_config .select2-container").show();
        });');
    }
}
