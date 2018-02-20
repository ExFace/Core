<?php
error_reporting(E_ALL & ~E_NOTICE);

// instantiate the main class
require_once('CommonLogic/Workbench.php');
$workbench = new \exface\Core\CommonLogic\Workbench();
$workbench->start();
$workbench->processRequest();