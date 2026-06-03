<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Widgets\DebugMessage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Debugger\HttpMessageDebugger;

/**
 * Exception thrown if an HTTP response does not have the expected structure.
 *
 * @author Andrej Kabachnik
 *        
 */
class HttpBadResponseError extends InvalidArgumentException
{
    private ResponseInterface $response;
    private ?RequestInterface $request = null;
    
    public function __construct(ResponseInterface $response, $message, $alias = null, $previous = null, RequestInterface $request = null)
    {
        $this->response = $response;
        $this->request = $request;
        parent::__construct($message, $alias, $previous);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode(int $default = 400) : int
    {
        return parent::getStatusCode($default);
    }
    
    public function getRequest() : ?RequestInterface
    {
        return $this->request;
    }
    
    public function getResponse() : ResponseInterface
    {
        return $this->response;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);
        $debugRenderer = new HttpMessageDebugger($this->getRequest(), $this->getResponse());
        return $debugRenderer->createDebugWidget($debugWidget);
    }
}