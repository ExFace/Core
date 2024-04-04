<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Returns null.
 */
class NullValue extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run()
    {
        return null;
    }
}
