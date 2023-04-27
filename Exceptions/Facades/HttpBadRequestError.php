<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Widgets\DebugMessage;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Exceptions\HttpServerRequestExceptionInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Debugger\HttpMessageDebugWidgetRenderer;

/**
 * Exception thrown if the facade fails to read data from the current HTTP request.
 *
 * @author Andrej Kabachnik
 *        
 */
class HttpBadRequestError extends InvalidArgumentException implements HttpServerRequestExceptionInterface
{
    private $request = null;
    
    public function __construct(ServerRequestInterface $request, $message, $alias = null, $previous = null)
    {
        $this->request = $request;
        parent::__construct($message, $alias, $previous);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\ExceptionInterface::getStatusCode()
     */
    public function getStatusCode()
    {
        return 400;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\HttpServerRequestExceptionInterface::getRequest()
     */
    public function getRequest() : ServerRequestInterface
    {
        return $this->request;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);
        $debugRenderer = new HttpMessageDebugWidgetRenderer($this->getRequest());
        $debugWidget = $debugRenderer->createDebugWidget($debugWidget);
        return $debugWidget;
    }
}