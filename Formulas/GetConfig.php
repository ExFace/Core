<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Get the config entry with the given key.
 */
class GetConfig extends Formula
{
    function run($key)
    {
        return $this->getWorkbench()->getConfig()->getOption($key);
    }
}
