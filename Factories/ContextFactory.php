<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Selectors\ContextSelectorInterface;
use exface\Core\CommonLogic\Selectors\ContextSelector;

/**
 * This factory produces contexts
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class ContextFactory extends AbstractSelectableComponentFactory
{

    /**
     * Instantieates a new context without a scope.
     * 
     * @param ContextSelectorInterface $selector            
     * @return ContextInterface
     */
    public static function create(ContextSelectorInterface $selector) : ContextInterface
    {
        return static::createFromSelector($selector);
    }
    
    /**
     * Instantiates a new context in the given scope.
     * 
     * @param ContextSelectorInterface $selector
     * @param ContextScopeInterface $context_scope
     * @return \exface\Core\Interfaces\Contexts\ContextInterface
     */
    public static function createInScope(ContextSelectorInterface $selector, ContextScopeInterface $context_scope) : ContextInterface
    {
        $context = static::create($selector);
        $context->setScope($context_scope);
        return $context;
    }
    
    /**
     * Instantiates a new context specified by it's qualified alias.
     *  
     * @param Workbench $workbench
     * @param string $selectorString
     * @param ContextScopeInterface $context_scope
     * @return \exface\Core\Interfaces\Contexts\ContextInterface
     */
    public static function createFromString(Workbench $workbench, string $selectorString, ContextScopeInterface $context_scope = null)
    {
        $selector = new ContextSelector($workbench, $selectorString);
        if ($context_scope === null){
            return static::create($selector);
        } else {
            return static::createInScope($selector, $context_scope);
        }
    }
}
?>