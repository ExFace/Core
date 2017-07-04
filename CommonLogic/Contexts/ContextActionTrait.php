<?php
namespace exface\Core\CommonLogic\Contexts;

trait ContextActionTrait 
{  
    private $context_type = null;
    
    private $context_scope = null;
    
    public function getContextType()
    {
        return $this->context_type;
    }
    
    public function setContextType($value)
    {
        $this->context_type = $value;
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
        return $this->getApp()->getWorkbench()->context()->getScope($this->getContextScope())->getContext($this->getContextType());
    }
}