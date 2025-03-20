<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Returns TRUE if the passed value is a truthly value (`true`, `1`, `'true'`, etc.) or FALSE for any other value
 * 
 * E.g.
 * 
 * - `=IsTrue(CREATED_BY_USER == USER('UID'))` => `true`
 * 
 * **NOTE:** this formula will always return a boolean value (`true` or `false`) in contrast to the more
 * generic `=Calc(CREATED_BY_USER == USER('UID') ? true : false)`, which will produce a string because
 * it cannot reliably determine, what type the result will be of - at least not before being evaluated.
 * 
 * @author Andrej Kabachnik
 */
class IsTrue extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($value = null): bool
    {
        return BooleanDataType::cast($value) === true;
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