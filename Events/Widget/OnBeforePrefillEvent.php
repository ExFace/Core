<?php
namespace exface\Core\Events\Widget;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;

/**
 * Event fired before a widget is prefilled.
 * 
 * @event exface.Core.Widget.OnBeforePrefill
 *
 * @author Andrej Kabachnik
 *        
 */
class OnBeforePrefillEvent extends AbstractWidgetEvent implements DataSheetEventInterface
{
    private $prefillSheet = null;
    
    public function __construct(WidgetInterface $widget, DataSheetInterface $prefillSheet)
    {
        parent::__construct($widget);
        $this->prefillSheet = $prefillSheet;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnBeforePrefill';
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    public function getPrefillData() : DataSheetInterface
    {
        return $this->prefillSheet;
    }
    
    
    public function getDataSheet() : DataSheetInterface
    {
        return $this->prefillSheet;
    }
}