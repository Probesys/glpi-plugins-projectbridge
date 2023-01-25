<?php
header('Content-type: text/javascript');
define('GLPI_ROOT', '../../..');
include(GLPI_ROOT."/inc/includes.php");
Html::header_nocache();
echo "$(document).ready(function() {";
echo "    console.log('projectbridge js');";
if (isset($_SESSION['glpiactiveprofile']) && $_SESSION['glpiactiveprofile']['interface']=="central") {
    echo "$('.search-results th ').each(function( index ) {"
    ."var content = $(this).html();"
    //."console.log(content);"
    ."var newcontent = content.replace('"._n('Plugin', 'Plugins', Session::getPluralNumber())." - ','');"
    //."console.log(newcontent);"
    ."$(this).html(newcontent);"
    . "})";
}
echo " });";
