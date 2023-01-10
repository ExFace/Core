<?php
namespace exface\Core\Events\DataSheet;

/**
 * Event fired before a data sheet starts replacing it's data in the corresponding data sources.
 * 
 * @event exface.Core.DataSheet.OnBeforeReplaceData
 * 
 * @author Andrej Kabachnik
 *
 */
class OnBeforeReplaceDataEvent extends AbstractDataSheetEvent
{
    /**
     * Prevents the default replace operation.
     *
     * Use this if the event handler fills the data sheet.
     *
     * @return OnBeforeReplaceDataEvent
     */
    public function preventReplace() : OnBeforeReplaceDataEvent
    {
        $this->preventDefault();
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function isPreventReplace() : bool
    {
        return $this->isDefaultPrevented();
    }
}