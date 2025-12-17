<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Widgets\DebugMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Debugger\HttpMessageDebugWidgetRenderer;

/**
 * Exception thrown if an HTTP response does not have the expected structure.
 *
 * @author Andrej Kabachnik
 *        
 */
class HttpBadResponseError extends InvalidArgumentException
{
    private $response = null;
    
    public function __construct(ResponseInterface $request, $message, $alias = null, $previous = null)
    {
        $this->response = $request;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\HttpServerRequestExceptionInterface::getResponse()
     */
    public function getResponse() : ServerRequestInterface
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
        $debugRenderer = new HttpMessageDebugWidgetRenderer($this->getResponse());
        $debugWidget = $debugRenderer->createDebugWidget($debugWidget);
        return $debugWidget;
    }
}