<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Returns TRUE if the passed value is NULL or an empty string and FALSE otherwise.
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
    public function run($value = null)
    {
        if ($value == null || $value == '') {
            return true;
        }
        return false;
    }
}
