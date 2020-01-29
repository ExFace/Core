<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

class Not extends Formula
{
    function run($value)
    {
        return !$value;
    }
}
