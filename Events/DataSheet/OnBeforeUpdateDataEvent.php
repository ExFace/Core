<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired before a data sheet starts updating it's data in the corresponding data sources.
 * 
 * @event exface.Core.DataSheet.OnBeforeUpdateData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeUpdateDataEvent extends AbstractDataSheetEvent
{
    private $preventUpdate = false;
    
    /**
     * Prevents the default update operation.
     *
     * Use this if the event handler fills the data sheet.
     *
     * @return OnBeforeUpdateDataEvent
     */
    public function preventUpdate() : OnBeforeUpdateDataEvent
    {
        $this->preventUpdate = true;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isPreventUpdate() : bool
    {
        return $this->preventUpdate;
    }
}