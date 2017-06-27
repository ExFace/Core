<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

/**
 * This action provides a RESTful API to work with contexts. 
 *
 * @author Andrej Kabachnik
 *        
 */
class ContextApi extends AbstractAction
{

    private $context_type = null;

    private $scope = null;
    
    private $operation = null;

    public function getContextType()
    {
        return $this->context_type;
    }

    public function setContextType($value)
    {
        if (is_null($this->context_type)){
            $this->setContextType($this->getRequest()->getQueryParams()['ctype']);
        }
        $this->context_type = $value;
        return $this;
    }

    public function getScope()
    {
        if (is_null($this->scope)){
            $this->setScope($this->getRequest()->getQueryParams()['cscope']);
        }
        return $this->scope;
    }

    public function setScope($value)
    {
        $this->scope = $value;
        return $this;
    }

    /**
     * Returns the context addressed in this action
     *
     * @return AbstractContext
     */
    public function getContext()
    {
        return $this->getApp()->getWorkbench()->context()->getScope($this->getScope())->getContext($this->getContextType());
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
        $this->setResult($return_value);
        return;
    }
    
    /**
     * 
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }
    
    /**
     * 
     * @param string $operation
     * @return \exface\Core\Actions\ContextApi
     */
    public function setOperation($method_name)
    {
        if (is_null($this->operation)){
            $this->setOperation($this->getRequest()->getQueryParams()['cop']);
        }
        $this->operation = $method_name;
        return $this;
    }
    
    /**
     * 
     * @return ServerRequestInterface
     */
    public function getRequest(){
        return ServerRequest::fromGlobals();
    }
 
}
?>