<?php
namespace exface\Core\Actions;

use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\RuntimeException;

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
        if ($task->hasParameter(CallContext::TASK_PARAMETER_CONTEXT_SCOPE)) {
            $this->setContextScope($task->getParameter(CallContext::TASK_PARAMETER_CONTEXT_SCOPE));
        }
        
        // If the task is not triggered on a specific page (e.g. happens on error pages) we can still
        // create a blank page and use it's context bar.
        if ($task->isTriggeredOnPage() === false) {
            $page = UiPageFactory::createEmpty($this->getWorkbench());
            $task->setPage($page);
            if ($task->isTriggeredByWidget() === false) {
                throw new RuntimeException('Cannot determine context bar button for the context popup!');
            }
            $button = $task->getWidgetTriggeredBy();
            $this->setWidgetDefinedIn($button);
            // The following is a workaround needed because the context bar
            // modifies the actions of it's buttons in ContextBar::createButtonForContext()
            // TODO: make the action get it's context from the context bar always!
            // Just ask the context bar in getContext(), getContextAlias(), etc.
            $context = $page->getContextBar()->getContextForButton($button);
            $this->setContextAlias($context->getAliasWithNamespace());
            $this->setContextScope($context->getScope()->getName());
        }
        return parent::perform($task, $transaction);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowPopup::createPopupContainer()
     */
    protected function createPopupContainer(UiPageInterface $page, WidgetInterface $contained_widget = NULL) : iContainOtherWidgets
    {
        $popup = parent::createPopupContainer($page, $contained_widget);
        
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
        return $this->getWorkbench()->getContext()->getScope($this->context_scope_name);
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