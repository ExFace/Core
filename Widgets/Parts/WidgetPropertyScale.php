<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Exceptions\Widgets\WidgetPropertyUnknownError;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveValue;
use exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface;

/**
 * This trait contains methods to work with value-based hint scales.
 * 
 * @author Andrej Kabachnik
 *
 */
class WidgetPropertyScale implements WidgetPropertyScaleInterface 
{
    use ImportUxonObjectTrait;

    private $valueScale = null;

    private $dataType = null;

    private $widget = null;

    public function __construct(WidgetInterface $widget, DataTypeInterface $dataType, UxonObject $uxon = null)
    {
        $this->widget = $widget;
        $this->workbench = $widget->getWorkbench();
        $this->dataType = $dataType;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }

    /**
     * 
     * {@inheritDoc}
     * @see iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass(): ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface::getScaleValues()
     */
    public function getScaleValues() : array
    {
        if ($this->valueScale === null && $this->dataType instanceof EnumDataTypeInterface) {
            $this->valueScale = $this->dataType->getValueHints();
        }
        return $this->valueScale ?? [];
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface::isEmpty()
     */
    public function isEmpty() : bool
    {
        return empty($this->getScaleValues());
    }
    
    /**
     * Map of scale values
     *
     * The hint map must be an object with values as keys and CSS hint codes as values.
     * The hint code will be applied to all values between it's value and the previous
     * one. In the below example, all values <= 10 will be red, values > 10 and <= 20
     * will be hinted yellow, those > 20 and <= 99 will have no special hint and values 
     * starting with 100 (actually > 99) will be green.
     *
     * ```
     * {
     *  "10": "This project was not started yet",
     *  "50": "At least one progress report was submitted",
     *  "99" : "Waiting for final approvement from the management",
     *  "100": "All tasks are completed or cancelled"
     * }
     *
     * ```
     *
     * @uxon-property scale
     * @uxon-type string[]
     * @uxon-template {"// <key>": "<value>"}
     *
     * @param UxonObject $value
     * @return WidgetPropertyScale
     */
    protected function setScale(UxonObject $value) : WidgetPropertyScale
    {
        $this->valueScale = $value->toArray();
        ksort($this->valueScale);
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPropertyScaleInterface::findValue()
     */
    public function findValue($value = null) : ?string
    {
        $scale = $this->getScaleValues();
        
        if ($this->isRangeBased() === true) {
            ksort($scale);
            foreach ($scale as $max => $border) {
                if ($value <= $max) {
                    return $border;
                }
            }
        } else {
            foreach ($scale as $scaleVal => $border) {
                if (strcasecmp($value, $scaleVal) === 0) {
                    return $border;
                }
            }
        }
        
        return $border ?? '';
    }

    /**
     * 
     * {@inheritdoc}
     * @see iHaveHintScale::getHintForValue()
     * @throws \exface\Core\Exceptions\Widgets\WidgetPropertyUnknownError
     */
    public function isRangeBased() : bool
    {
        switch (true) {
            case $this->dataType instanceof NumberDataType:
            case $this->dataType instanceof DateDataType:
                return true;
        }
        
        return false;
    }
}