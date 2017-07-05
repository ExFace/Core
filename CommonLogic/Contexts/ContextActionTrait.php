<?php
namespace exface\Core\CommonLogic\Contexts;

trait ContextActionTrait 
{  
    private $context_alias = null;
    
    private $context_scope = null;
    
    public function getContextAlias()
    {
        return $this->context_alias;
    }
    
    public function setContextAlias($value)
    {
        $this->context_alias = $value;
        return $this;
    }
    
    public function getContextScope()
    {
        return $this->context_scope;
    }
    
    public function setContextScope($value)
    {
        $this->context_scope = $value;
        return $this;
    }
    
    /**
     * Returns the context addressed in this action
     *
     * @return AbstractContext
     */
    public function getContext()
    {
        return $this->getApp()->getWorkbench()->context()->getScope($this->getContextScope())->getContext($this->getContextAlias());
    }
}