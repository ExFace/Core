<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Interface for events that provide access to old and new data in case it changes
 * 
 * TODO add other methods from OnBeforeUpdateDataEvent here: e.g. getChanges(), willChange(), etc.
 * TODO add this interface to OnBeforeDelete and possibly other events
 * 
 * @author Andrej Kabachnik
 */
interface DataChangeEventInterface extends DataSheetEventInterface
{
    /**
     * Returns the events data sheet in the previous state - before the change
     * 
     * @return DataSheetInterface
     */
    public function getDataSheetWithOldData() : ?DataSheetInterface;
}