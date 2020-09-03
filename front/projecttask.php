<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

Html::header(__('Project Tasks', 'projectbridge'), $_SERVER['PHP_SELF'], 'tools', 'projecttask');

// force GLPI to point to this page
global $CFG_GLPI;
$list_url = Plugin::getPhpDir('projectbridge', false) . '/front/projecttask.php';
$_GET['target'] = $list_url;

Search::show('projecttask');

Html::footer();
