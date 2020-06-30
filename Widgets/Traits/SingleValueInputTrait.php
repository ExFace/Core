<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Widgets\Input;

/**
 * This trait makes an input widget only accept a single value (no `multiple_values_allowed`)
 * 
 * @author Andrej Kabachnik
 *
 */
trait SingleValueInputTrait
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Input::getMultipleValuesAllowed()
     */
    public function getMultipleValuesAllowed() : bool
    {
        return false;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Input::setMultipleValuesAllowed()
     */
    public function setMultipleValuesAllowed(bool $value) : Input
    {
        if ($value === true) {
            throw new WidgetConfigurationError($this, 'Multi-value input not supported in widget "' . $this->getWidgetType() . "!");
        }
        return parent::setMultipleValuesAllowed($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Input::setMultipleValuesDelimiter()
     */
    public function setMultipleValuesDelimiter(string $value) : Input
    {
        throw new WidgetConfigurationError($this, 'Multi-value input not supported in widget "' . $this->getWidgetType() . "!");
    }
}