<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Interfaces\Actions\iModifyContext;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;
use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Factories\ResultFactory;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Contexts\ContextScopeInterface;
use exface\Core\Exceptions\Actions\ActionInputMissingError;

/**
 * This action provides a RESTful API to work with contexts. 
 * 
 * Using this action, you can call any context method using request parameters
 * or the regular action API.
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextApi extends AbstractAction implements iModifyContext
{
    const TASK_PARAMETER_CONTEXT_TYPE = 'ctype';
    const TASK_PARAMETER_CONTEXT_SCOPE = 'cscope';
    const TASK_PARAMETER_OPERATION = 'cop';

    use ContextActionTrait {
        getContextAlias as getContextAliasViaTrait;
        getContextScope as getContextScopeViaTrait;
    }
    
    private $operation = null;

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
    
    public function getContextScope(TaskInterface $task = null) : ContextScopeInterface
    {
        try{
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
        $operation = $this->getOperation($task);
        if (!method_exists($this->getContext($task), $operation)){
            throw new ActionConfigurationError($this, 'Invalid operation "' . $operation . '" for context "' . $this->getContext($task)->getAlias() . '": method not found!');
        }
        $return_value = call_user_func(array($this->getContext($task), $operation));
        if (is_string($return_value)){
            $result = ResultFactory::createMessageResult($task, $return_value);
        } elseif ($return_value instanceof ContextInterface) { 
            $operation_name = ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $operation)));
            $result = ResultFactory::createMessageResult($task, $this->translate('RESULT', ['%operation_name%' => $operation_name]));
        } else {
            $result = ResultFactory::createTextContentResult($task, $return_value);
        }
        $result->setContextModified(true);
        return $result;
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
     * @return \exface\Core\Actions\ContextApi
     */
    public function setOperation(string $method_name) : ContextApi
    {
        $this->operation = $method_name;
        return $this;
    }
}
?>