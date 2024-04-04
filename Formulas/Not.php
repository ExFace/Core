<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Executes the not operator on the value and returns the result.
 */
class Not extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($value = null)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return ! BooleanDataType::cast($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), BooleanDataType::class);
    }
}
