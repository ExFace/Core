<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Executes the not operator on the value and returns the result.
 */
class Not extends Formula
{
    function run($value)
    {
        return !$value;
    }
}
