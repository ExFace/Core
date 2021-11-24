<?php
namespace exface\Core\Actions;

use exface\Core\Contexts\ObjectBasketContext;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Actions\iModifyContext;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Widgets\Data;

/**
 * Adds the input rows to the object basket in a specified context_scope (by default, the window scope)
 *
 * @method ObjectBasketContext getContext()
 *        
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketAdd extends AbstractAction implements iModifyContext
{
    use ContextActionTrait {
        getContextScope as parentGetContextScope;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
        $this->setIcon(Icons::SHOPPING_BASKET);
        $this->setContextAlias('exface.Core.ObjectBasketContext');
        $this->setContextScope(ContextManagerInterface::CONTEXT_SCOPE_SESSION);
        
        // Disable buttons if widget cannot provide UID data (nothing to put into the object basket)
        if ($triggerWidget = $this->getWidgetDefinedIn()) {
            if (($triggerWidget instanceof iUseInputWidget) && ($inputWidget = $triggerWidget->getInputWidget()) && $inputWidget instanceof iHaveButtons) {
                switch (true) {
                    // The above is the case if there simply is no UID column
                    case ! $inputWidget->hasUidData():
                    // ...or if there are any aggregations
                    // IDEA theoretically there may be aggregations that include the UID column (e.g. aggregated via UID)
                    // perhaps it might be possible to detect these cases and allow object basket actions
                    case ($inputWidget instanceof Data) && ($inputWidget->hasAggregations() || $inputWidget->hasAggregateAll()):
                    case ($inputWidget instanceof iUseData) && ($inputWidget->getData()->hasAggregations() || $inputWidget->getData()->hasAggregateAll()):
                        $triggerWidget->setDisabled(true, $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.OBJECTBASKET.BUTTON_WITHOUT_UID_DISABLED_REASON'));
                        break;
                }
            }
        }
    }

    /**
     * 
     * @return ContextScopeInterface
     */
    public function getContextScope(TaskInterface $task = null) : ContextScopeInterface
    {
        if (! $this->parentGetContextScope()) {
            $this->setContextScope(ContextManagerInterface::CONTEXT_SCOPE_SESSION);
        }
        return $this->parentGetContextScope();
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $input = $this->getInputDataSheet($task);
        $this->getContext($task)->add($input);
        
        $message = $this->getResultMessageText() ?? $this->translate('RESULT', ['%context_name%' => $this->getContext($task)->getName(), '%number%' => $input->countRows()], $input->countRows());
        $result = ResultFactory::createMessageResult($task, $message);
        $result->setContextModified(true);
        
        return $result;
    }
}
?>