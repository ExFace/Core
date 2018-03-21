<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;

/**
 * Shows a popup for the user to interact with a context (e.g. list of favorites for the favorites context).
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowContextPopup extends ShowPopup
{
    
    private $context_scope_name = null;
    
    private $context_alias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        if ($task->hasParameter(ContextApi::TASK_PARAMETER_CONTEXT_SCOPE)) {
            $this->setContextScope($task->getParameter(ContextApi::TASK_PARAMETER_CONTEXT_SCOPE));
        }
        return parent::perform($task, $transaction);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowPopup::createPopupContainer()
     */
    protected function createPopupContainer(AbstractWidget $contained_widget = NULL) : iContainOtherWidgets
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
    public function getContextScope(TaskInterface $task = null) : ContextScopeInterface
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
    public function getContextAlias(TaskInterface $task = null) : string
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