<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Common interface for events fired while an action handles a task
 * 
 * @author Andrej Kabachnik
 *
 */
interface ActionRuntimeEventInterface extends ActionEventInterface, TaskEventInterface
{
    /**
     * Returns a data sheet with the fully resolved input data incl. all mappers, checks, etc.
     * 
     * @return DataSheetInterface
     */
    public function getActionInputData() : DataSheetInterface;
}