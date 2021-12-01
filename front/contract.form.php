<?php

include('../../../inc/includes.php');
//print_r($_POST);

global $CFG_GLPI;

if (isset($_POST) && array_key_exists('id', $_POST)) {
    $contractId = $_POST['id'];
    $contract = new Contract();
    $contract->getFromDB($contractId);
    $gap = null;
    if (array_key_exists('percentage_gap', $_POST)) {
        $gap = $_POST['percentage_gap'];
    }
    $contractGapAlertObject = new PluginProjectbridgeContractGapAlert();
    $contractGapAlertObject::updateContractGapAlert($contractId, $gap);


    Html::redirect($CFG_GLPI['root_doc']."/front/contract.form.php?id=".$contractId);
}
