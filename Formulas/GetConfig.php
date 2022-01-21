<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;

/**
 * Returns the config option with the given key.
 * 
 * Searches the core config by default. To access the config of another app, pass the app alias
 * as the second parameter.
 *  
 */
class GetConfig extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $key = null, string $appAlias = null)
    {
        if (! $key) {
            return null;
        }
        if ($appAlias !== null) {
            return $this->getWorkbench()->getApp($appAlias)->getConfig()->getOption($key);
        } else {
            return $this->getWorkbench()->getConfig()->getOption($key);
        }
    }
}
