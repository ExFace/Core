<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Returns TRUE if the passed value is NULL or an empty string and FALSE otherwise.
 * 
 * E.g.
 * 
 * - `=IsNull(null)` => `true`
 * - `=IsNull("")` => `true`
 * - `=IsNull("NULL")` => `true`
 * - `=IsNull("null")` => `true`
 * - `=IsNull(0)` => `false`
 * - `=IsNull(false)` => `false`
 * 
 * @author Andrej Kabachnik
 */
class IsNull extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($value = null, bool $treatNullStringAsNull = true)
    {
        if ($value === null || $value === '') {
            return true;
        }
        if ($treatNullStringAsNull === true && mb_strtoupper($value) === EXF_LOGICAL_NULL) {
            return true;
        }
        return false;
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