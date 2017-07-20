<?php
namespace exface\Core\CommonLogic\Contexts;

use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\Actions\ActionInputTypeError;

/**
 * This trait contains typical methods for actions working with contexts.
 * 
 * It is important to use the same UXON syntax for the same properties of
 * similar actions. Allows to set basic context properties like context_alias
 * and context scope.
 * 
 * @author Andrej Kabachnik
 *
 */
trait ContextActionTrait 
{   
    /** @var string */
    private $context_alias = null;
    
    /** @var ContextScopeInterface */
    private $context_scope = null;
    
    /**
     * Returns the full alias of the context (with namespace)
     * 
     * @return string
     */
    public function getContextAlias()
    {
        return $this->context_alias;
    }
    
    /**
     * Sets the alias of the context to be used (with namespace)
     * 
     * @uxon-property context_alias
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\CommonLogic\Contexts\ContextActionTrait
     */
    public function setContextAlias($value)
    {
        $this->context_alias = $value;
        return $this;
    }
    
    /**
     * Returns the context scope to be used in this action.
     * 
     * @return ContextScopeInterface
     */
    public function getContextScope()
    {
        return $this->context_scope;
    }
    
    /**
     * Sets the name of context scope to be used in this action: e.g. User, Window, Session, etc.
     * 
     * @uxon-property context_scope
     * @uxon-type string
     * 
     * @param ContextScopeInterface|string $scope_or_string_name
     * @return \exface\Core\CommonLogic\Contexts\ContextActionTrait
     */
    public function setContextScope($scope_or_string_name)
    {
        if (is_string($scope_or_string_name)){
            $this->context_scope = $this->getWorkbench()->context()->getScope($scope_or_string_name);
        } elseif ($scope_or_string_name instanceof ContextScopeInterface){
            $this->context_scope = $scope_or_string_name;
        } else {
            throw new ActionInputTypeError($this, 'Cannot set context scope for "' . $this->getAliasWithNamespace() . '": expecting string or instantiated context scope, ' . gettype($scope_or_string_name) . ' given instead!');
        }
        return $this;
    }
    
    /**
     * Returns the context addressed in this action
     *
     * @return AbstractContext
     */
    public function getContext()
    {
        return $this->getContextScope()->getContext($this->getContextAlias());
    }
}