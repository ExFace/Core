<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Actions\iModifyContext;
use exface\Core\Interfaces\Contexts\ContextInterface;
use exface\Core\CommonLogic\Contexts\ContextActionTrait;
use exface\Core\Exceptions\Contexts\ContextScopeNotFoundError;

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

    use ContextActionTrait {
        getContextAlias as traitGetContextAlias;
        getContextScope as traitGetContextScope;
    }
    
    private $operation = null;

    public function getContextAlias()
    {
        if (is_null($this->traitGetContextAlias())){
            $this->setContextAlias($this->getRequest()->getQueryParams()['ctype']);
        }
        return $this->traitGetContextAlias();
    }
    
    public function getContextScope()
    {
        try{
            $this->traitGetContextScope();
        } catch (ContextScopeNotFoundError $e){
            $this->setContextScope($this->getRequest()->getQueryParams()['cscope']);
        }
        return $this->traitGetContextScope();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform()
    {
        if (!method_exists($this->getContext(), $this->getOperation())){
            throw new ActionConfigurationError($this, 'Invalid operation "' . $this->getOperation() . '" for context "' . $this->getContext()->getAlias() . '": method not found!');
        }
        $return_value = call_user_func(array($this->getContext(), $this->getOperation()));
        if (is_string($return_value)){
            $this->setResult('');
            $this->setResultMessage($return_value);
        } elseif ($return_value instanceof ContextInterface) { 
            $this->setResult('');
            $operation_name = ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $this->getOperation())));
            $this->setResultMessage($this->translate('RESULT', ['%operation_name%' => $operation_name]));
        } else {
            $this->setResult($return_value);
        }
        return;
    }
    
    /**
     * Returns the method name to be called in the context.
     * 
     * @return string
     */
    public function getOperation()
    {
        if (is_null($this->operation)){
            $this->setOperation($this->getRequest()->getQueryParams()['cop']);
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
    public function setOperation($method_name)
    {
        $this->operation = $method_name;
        return $this;
    }
    
    /**
     * Returns the current PSR-7 ServerRequest
     * 
     * TODO Move this method to AbstractAction once the new PSR7-based API is available
     * 
     * @return ServerRequestInterface
     */
    public function getRequest(){
        return ServerRequest::fromGlobals();
    }
}
?>