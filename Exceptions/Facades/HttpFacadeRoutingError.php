<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\CommonLogic\Debugger\HttpMessageDebugger;
use exface\Core\Interfaces\Exceptions\HttpServerRequestExceptionInterface;
use exface\Core\Widgets\DebugMessage;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Exception thrown when routing an HTTP request to or within a facade fails.
 *
 * @author Andrej Kabachnik
 */
class HttpFacadeRoutingError extends FacadeRoutingError implements HttpServerRequestExceptionInterface
{
    private ServerRequestInterface $request;

    /**
     * @param ServerRequestInterface $request
     * @param string $message
     * @param string|null $alias
     * @param \Throwable|null $previous
     */
    public function __construct(ServerRequestInterface $request, $message, $alias = null, $previous = null)
    {
        $this->request = $request;
        parent::__construct($message, $alias, $previous);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\HttpServerRequestExceptionInterface::getRequest()
     */
    public function getRequest() : ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);
        $debugRenderer = new HttpMessageDebugger($this->getRequest());
        return $debugRenderer->createDebugWidget($debugWidget);
    }
}