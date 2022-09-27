<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\iModifyContext;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;
use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

/**
 * This action passes tasks to contexts thus providing an API to interact with them. 
 *
 * @author Andrej Kabachnik
 *        
 */
class CallContext extends AbstractAction implements iModifyContext
{
    const TASK_PARAMETER_CONTEXT_TYPE = 'context';
    const TASK_PARAMETER_CONTEXT_SCOPE = 'scope';
    const TASK_PARAMETER_OPERATION = 'operation';

    use ContextActionTrait {
        getContextAlias as getContextAliasViaTrait;
        getContextScope as getContextScopeViaTrait;
    }
    
    private $operation = null;

    /**
     * 
     * @param TaskInterface $task
     * @throws ActionInputMissingError
     * @return string
     */
    public function getContextAlias(TaskInterface $task = null) : string
    {
        if (is_null($this->getContextAliasViaTrait())){
            if ($task->hasParameter($this::TASK_PARAMETER_CONTEXT_TYPE)) {
                $this->setContextAlias($task->getParameter($this::TASK_PARAMETER_CONTEXT_TYPE));
            } else {
                throw new ActionInputMissingError($this, 'No context type defined for action ' . $this->getAliasWithNamespace() . ': either set a scope programmatically or pass it via task/request parameter "' . $this::TASK_PARAMETER_CONTEXT_TYPE . '"!');
            }
        }
        return $this->getContextAliasViaTrait();
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @throws ActionInputMissingError
     * @return ContextScopeInterface
     */
    public function getContextScope(TaskInterface $task = null) : ContextScopeInterface
    {
        try {
            $this->getContextScopeViaTrait();
        } catch (ContextScopeNotFoundError $e){
            if ($task->hasParameter($this::TASK_PARAMETER_CONTEXT_SCOPE)) {
                $this->setContextScope($task->getParameter($this::TASK_PARAMETER_CONTEXT_SCOPE));
            } else {
                throw new ActionInputMissingError($this, 'No context scope defined for action ' . $this->getAliasWithNamespace() . ': either set a scope programmatically or pass it via task/request parameter "' . $this::TASK_PARAMETER_CONTEXT_SCOPE . '"!');
            }
            
        }
        return $this->getContextScopeViaTrait();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        return $this->getContext($task)->handle($task, $this->getOperation($task));
    }
    
    /**
     * Returns the method name to be called in the context.
     * 
     * @return string
     */
    public function getOperation(TaskInterface $task) : string
    {
        if (is_null($this->operation)){
            if ($task->hasParameter($this::TASK_PARAMETER_OPERATION)) {
                $this->setOperation($task->getParameter($this::TASK_PARAMETER_OPERATION));
            } else {
                throw new ActionInputMissingError($this, 'No operation defined for action ' . $this->getAliasWithNamespace() . ': either set a scope programmatically or pass it via task/request parameter "' . $this::TASK_PARAMETER_OPERATION . '"!');
            }
        }
        return $this->operation;
    }
    
    /**
     * Sets the name of the method in the context to be called.
     * 
     * Use the URL parameter "cop=" to specify the operation in a RESTful URL.
     * 
     * @param string $operation
     * @return \exface\Core\Actions\CallContext
     */
    public function setOperation(string $method_name) : CallContext
    {
        $this->operation = $method_name;
        return $this;
    }
}
?>