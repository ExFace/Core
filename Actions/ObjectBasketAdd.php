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
        $this->setContextScope(ContextManagerInterface::CONTEXT_SCOPE_WINDOW);
    }

    /**
     * 
     * @return ContextScopeInterface
     */
    public function getContextScope(TaskInterface $task = null) : ContextScopeInterface
    {
        if (! $this->parentGetContextScope()) {
            $this->setContextScope(ContextManagerInterface::CONTEXT_SCOPE_WINDOW);
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