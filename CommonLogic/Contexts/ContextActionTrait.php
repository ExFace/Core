<?php
namespace exface\Core\CommonLogic\Contexts;

use exface\Core\Interfaces\Contexts\ContextScopeInterface;

trait ContextActionTrait 
{  
    private $context_alias = null;
    
    private $context_scope_name = null;
    
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
        return $this->getApp()->getWorkbench()->context()->getScope($this->context_scope_name);
    }
    
    /**
     * Sets the name of context scope to be used in this action: e.g. User, Window, Session, etc.
     * 
     * @uxon-property context_scope
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\CommonLogic\Contexts\ContextActionTrait
     */
    public function setContextScope($value)
    {
        $this->context_scope_name = $value;
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