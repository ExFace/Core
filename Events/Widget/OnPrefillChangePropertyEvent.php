<?php
namespace exface\Core\Events\Widget;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\DataSheets\DataPointerInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;

/**
 * Event fired after a prefill changes the value of a widget property.
 * 
 * In contranst to `exface.Core.Widget.OnPrefill` this event is only fired when a property
 * of the widget actually changes. It contains a data pointer to the value, that is being set
 * and also a copy of the previous value of the property, that is being overwritten.
 * 
 * This event will be fired for every property changed by a prefill: e.g. if a prefill changes the
 * value and the text of an InputSelect, there will be two events fired with the respective
 * values for each of the properties. On the other hand, there will be no `OnPrefillChangeProperty` 
 * event fired if the prefill does not change anything in the widget - while `OnPrefill` will be 
 * still fired to indicate, that the widget received prefill data.
 * 
 * @event exface.Core.Widget.OnPrefillChangeProperty
 *
 * @author Andrej Kabachnik
 *        
 */
class OnPrefillChangePropertyEvent extends AbstractWidgetEvent implements DataSheetEventInterface
{
    private $pointer = null;
    
    private $propertyName = null;
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param string $uxonPropertyName
     * @param DataPointerInterface $valuePointer
     */
    public function __construct(WidgetInterface $widget, string $uxonPropertyName, DataPointerInterface $valuePointer)
    {
        parent::__construct($widget);
        $this->pointer = $valuePointer;
        $this->propertyName = $uxonPropertyName;
    }
    
    /**
     * Returns a data pointer to the new value, that the property is getting.
     * 
     * @return DataPointerInterface
     */
    public function getPrefillValuePointer() : DataPointerInterface
    {
        return $this->pointer;
    }
    
    /**
     * Returns the data sheet being used for prefill
     * 
     * @return DataSheetInterface
     */
    public function getPrefillData() : DataSheetInterface
    {
        return $this->pointer->getDataSheet();
    }
    
    /**
     * Returns the name of the UXON property of the widget, that is being changed.
     * 
     * @return string
     */
    public function getPropertyName() : string
    {
        return $this->propertyName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\DataSheetEventInterface::getDataSheet()
     */
    public function getDataSheet() : DataSheetInterface
    {
        return $this->getPrefillData();
    }
    
    /**
     * Returns the new (current) property value.
     * 
     * @return mixed
     */
    public function getPrefillValue()
    {
        return $this->getPrefillValuePointer()->getValue();
    }
}