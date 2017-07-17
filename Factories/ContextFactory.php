<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;

/**
 * This factory produces contexts
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class ContextFactory extends AbstractNameResolverFactory
{

    /**
     * Instantieates a new context without a scope.
     * 
     * @param NameResolverInterface $name_resolver            
     * @return ContextInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        $class = $name_resolver->getClassNameWithNamespace();
        return new $class($name_resolver);
    }
    
    /**
     * Instantiates a new context in the given scope.
     * 
     * @param NameResolverInterface $name_resolver
     * @param ContextScopeInterface $context_scope
     * @return \exface\Core\Interfaces\Contexts\ContextInterface
     */
    public static function createInScope(NameResolverInterface $name_resolver, ContextScopeInterface $context_scope)
    {
        $context = static::create($name_resolver);
        $context->setScope($context_scope);
        return $context;
    }
    
    /**
     * Instantiates a new context specified by it's qualified alias.
     *  
     * @param Workbench $workbench
     * @param unknown $alias_with_namespace
     * @param ContextScopeInterface $context_scope
     * @return \exface\Core\Interfaces\Contexts\ContextInterface
     */
    public static function createFromString(Workbench $workbench, $alias_with_namespace, ContextScopeInterface $context_scope = null)
    {
        $name_resolver = NameResolver::createFromString($alias_with_namespace, NameResolver::OBJECT_TYPE_CONTEXT, $workbench);
        if (is_null($context_scope)){
            return static::create($name_resolver);
        } else {
            return static::createInScope($name_resolver, $context_scope);
        }
    }
}
?>