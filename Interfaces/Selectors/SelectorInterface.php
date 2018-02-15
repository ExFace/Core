<?php
namespace exface\Core\Interfaces\Selectors;

use exface\Core\CommonLogic\Workbench;

interface SelectorInterface
{
    /**
     * A selector class can be created from the selector string and the target workbench.
     * 
     * @param Workbench $workbench
     * @param string $selectorString
     */
    public function __construct(Workbench $workbench, $selectorString);
}