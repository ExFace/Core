<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Formula class to use if the expression does not contain any other formula.
 * This formula returns the given string evaluated beforehand by the ExpressionLanguage,
 * e.g. `=Calc(MODIFIED_ON > '01.01.2021' ? 'true' : 'false')` will return either `true` or `false`
 * 
 * @author ralf.mulansky
 *
 */
class Calc extends Formula
{
    public function run($string)
    {
        return $string;
    }
}