<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Excel-like "IF" formula: `=If(<condition>, <then>, <else>)`
 * 
 * E.g.
 * 
 * - `=If(CREATED_BY_USER == USER('UID'), true, false)` => `true` if the user currently logged on is
 * also the one, who create the data row or false otherwise.
 * - `=If(CREATED_BY_USER == USER('UID'), 'You', CREATED_BY_USER__USERNAME)` => will show the username
 * of the user, who craeated the object or the string `You` if it was created by the current user
 * - `=If(CREATED_BY_USER == USER('UID'), Translate('my.App', 'YOU'), CREATED_BY_USER__USERNAME)` => in
 * addition to the previous example, this formula will translate the `You` string.
 * 
 * This formula is similar to `=Calc(CREATED_BY_USER == USER('UID') ? true : false)`, but it is easier to use
 * and has improved result data type detection. While `=Calc()` always returns the generic string data type,
 * `=If()` will check if the data type of then- and else-expressions can be determined and use that type
 * if possible. This is important if you plan to use the result of the formula in a way, that requires
 * a certain type of value.
 * 
 * TODO the improved data type dection does not work yet.
 * 
 * @author Andrej Kabachnik
 */
class IfThenElse extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($condition = null, $then = null, $else = null)
    {
        if ($condition === null || $condition === '') {
            $switch = false;
        } else {
            $switch = BooleanDataType::cast($condition);
        }
        if ($switch === true) {
            return $then;
        } else {
            return $else;
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        /* TODO determine the actual data type of $then and $else. This did not
         * work rirght away because getArgumentType() only seems to work for 
         * attributes and formulas and only while evaluating the formula in data
         * context (non-static).
        $thenType = $this->getArgumentType(1);
        $elseType = $this->getArgumentType(2);
        // See if the data types of then or else can be determined. If so AND they are compatible,
        // return one of them
        if ($elseType !== null && $thenType !== null) {
            if ($elseType->is($thenType)) {
                return $elseType;
            }
            if ($thenType->is($elseType)) {
                return $thenType;
            }
        }*/
        // Otherwise return the generic string data type
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), BooleanDataType::class);
    }
}