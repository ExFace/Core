<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired before a data sheet starts creating it's data in the corresponding data sources.
 * 
 * @event exface.Core.DataSheet.OnBeforeCreateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeCreateDataEvent extends AbstractDataSheetEvent
{
    private $preventCreate = false;
    
    /**
     * Prevents the default create operation.
     *
     * Use this if the event handler fills the data sheet.
     *
     * @return OnBeforeCreateDataEvent
     */
    public function preventCreate() : OnBeforeCreateDataEvent
    {
        $this->preventCreate = true;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isPreventCreate() : bool
    {
        return $this->preventCreate;
    }
}