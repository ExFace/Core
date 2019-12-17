<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired before a data sheet starts deleting it's data in the corresponding data sources.
 * 
 * @event exface.Core.DataSheet.OnBeforeDeleteData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeDeleteDataEvent extends AbstractDataSheetEvent
{
    private $preventDelete = false;
    
    /**
     * Prevents the default delete operation.
     *
     * Use this if the event handler actually deletes the data.
     *
     * @return OnBeforeDeleteDataEvent
     */
    public function preventDelete() : OnBeforeDeleteDataEvent
    {
        $this->preventDelete = true;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isPreventDelete() : bool
    {
        return $this->preventDelete;
    }
}