<?php

include ('../../../inc/includes.php');

Session::checkLoginUser();

Html::header('Tâches de projet', $_SERVER['PHP_SELF'], 'tools', 'projecttask');
Search::show('projecttask');

Html::footer();
