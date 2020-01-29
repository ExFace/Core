<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

class NullValue extends Formula
{
    function run()
    {
        return null;
    }
}
