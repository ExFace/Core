<?php

namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\CommonLogic\ContextManager;

/**
 * Returns the value of a context variable.
 *  
 */
class GetContextVar extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $variable = null, $namespace = null, string $contextScope = null)
    {
        if (! $variable) {
            return null;
        }
        
        $contextScope = $contextScope ?? ContextManager::CONTEXT_SCOPE_REQUEST;
        return $this->getWorkbench()->getContext()->getScope($contextScope)->getVariable($variable, $namespace);
    }
}
