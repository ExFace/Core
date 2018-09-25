<?php
namespace exface\Core\Events\Widget;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Event fired before a widget is prefilled.
 * 
 * @event exface.Core.Widget.OnBeforePrefill
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforePrefillEvent extends AbstractWidgetEvent
{
    private $prefillSheet = null;
    
    public function __construct(WidgetInterface $widget, DataSheetInterface $prefillSheet)
    {
        parent::__construct($widget);
        $this->prefillSheet = $prefillSheet;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getPrefillSheet() : DataSheetInterface
    {
        return $this->prefillSheet;
    }
}