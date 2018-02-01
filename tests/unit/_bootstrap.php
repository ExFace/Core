<?php

global $exface;
if (! $exface) {
    $exface = new exface\Core\CommonLogic\Workbench();
    $exface->start();
}
