<?php

include('../../../inc/includes.php');
//print_r($_POST);

global $CFG_GLPI;

if (isset($_POST) && array_key_exists('id', $_POST)) {
    $contractId = $_POST['id'];
    $contract = new Contract();
    $contract->getFromDB($contractId);
    $quota = null;
    if (array_key_exists('percentage_quota', $_POST)) {
        $quota = $_POST['percentage_quota'];
    }
    $contractQuotaAlertObject = new PluginProjectbridgeContractQuotaAlert();
    $contractQuotaAlertObject::updateContractQuotaAlert($contractId, $quota);


    Html::redirect($CFG_GLPI['root_doc']."/front/contract.form.php?id=".$contractId);
}
