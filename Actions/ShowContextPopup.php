<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;

class ShowContextPopup extends ShowPopup
{
    
    private $context_scope_name = null;
    
    private $context_alias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowPopup::createPopupContainer()
     */
    protected function createPopupContainer(AbstractWidget $contained_widget = NULL)
    {
        $popup = parent::createPopupContainer($contained_widget);
        
        // Make sure, each popup has it's own id space. This is important because
        // a single context can produce multiple popups (in different scopes) and
        // this would lead to id-collisions without different id scopes.
        $popup->setIdSpace($popup->getParent()->getId());
        
        // Give to popup to the context to fill with widgets
        $popup = $this->getContext()->getContextBarPopup($popup);
        
        return $popup;
    }
    
    /**
     * 
     * @return ContextScopeInterface
     */
    public function getContextScope()
    {
        return $this->getWorkbench()->context()->getScope($this->context_scope_name);
    }
    
    /**
     * 
     * @param string $context_scope
     * @return \exface\Core\Actions\ShowContextPopup
     */
    public function setContextScope($scope_name)
    {
        $this->context_scope_name = $scope_name;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getContextAlias()
    {
        return $this->context_alias;
    }

    /**
     * 
     * @param string $context_alias
     * @return \exface\Core\Actions\ShowContextPopup
     */
    public function setContextAlias($context_alias)
    {
        $this->context_alias = $context_alias;
        return $this;
    }
    
    /**
     * 
     * @return \exface\Core\Interfaces\Contexts\ContextInterface
     */
    public function getContext()
    {
        return $this->getContextScope()->getContext($this->getContextAlias());
    }
    
}
?>