<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired before a data sheet starts deleting it's data in the corresponding data sources.
 * 
 * This event can be used to modify or check data before it is deleted and also to provide custom
 * delete logic. 
 * 
 * Use `$event->getDataSheet()` to get the data about to be deleted and `$event->getTransaction()`
 * to get the data transaction used. 
 * 
 * The data sheet is passed by reference, so any changes to it will have effect on all subsequent
 * logic.
 * 
 * Use `$event->preventDelete()` to cancel default data sheet delete logic - i.e. the delete query 
 * for the data source and eventual cascading deletes. Use `$event->preventDelete(false)` to only
 * cancel the delte query and still allow cascading deletes (e.g. if the delete logic for a specific
 * meta object is different).
 * 
 * @event exface.Core.DataSheet.OnBeforeDeleteData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeDeleteDataEvent extends AbstractDataSheetEvent
{
    private $preventDelete = false;
    
    private $preventDeleteCascade = false;
    
    /**
     * Prevents the default delete operation.
     *
     * Use this if the event handler actually deletes the data. Set the parameter $preventCascadingDeletesToo
     * to FALSE if you still want the regular cascading delete logic to be used.
     *
     * @param bool $preventCascadingDeletesToo
     * @return OnBeforeDeleteDataEvent
     */
    public function preventDelete(bool $preventCascadingDeletesToo = true) : OnBeforeDeleteDataEvent
    {
        $this->preventDelete = true;
        $this->preventDeleteCascade = $preventCascadingDeletesToo;
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
    
    /**
     *
     * @return bool
     */
    public function isPreventDeleteCascade() : bool
    {
        return $this->preventDeleteCascade;
    }
}