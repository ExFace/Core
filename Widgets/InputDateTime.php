<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

/**
 * An date-input incl. time.
 * 
 * @author Andrej Kabachnik
 *
 */
class InputDateTime extends InputDate
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\InputDate::getFormatDefault()
     */
    protected function getFormatDefault() : string
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class)->getFormat();
    }
}