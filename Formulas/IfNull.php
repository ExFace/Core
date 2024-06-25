<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Replaces a NULL or empty string value with the given expression
 * 
 * E.g.
 * 
 * - `=IfNull(null, '0')` => `0`
 * - `=IfNull('', '0')` => `0`
 * - `=IfNull(null, MY_ATTRIBUTE)` => value of MY_ATTRIBUTE for this data row
 * - `=IfNull(1, '0')` => `1`
 * 
 * @author Andrej Kabachnik
 */
class IfNull extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($valueToCheck = null, $valueIfNull = null, bool $treatNullStringAsNull = true)
    {
        if ($valueToCheck === null || $valueToCheck === '') {
            return $valueIfNull;
        }
        if ($treatNullStringAsNull === true && mb_strtoupper($valueToCheck) === EXF_LOGICAL_NULL) {
            return $valueIfNull;
        }
        return $valueToCheck;
    }
}