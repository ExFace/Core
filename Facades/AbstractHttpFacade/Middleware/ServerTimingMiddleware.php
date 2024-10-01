<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware to add the Server-Time header to requests
 * 
 * E.g. `Server-Timing: total;dur=91.4521484375` where the float value is the number of milliseconds
 * from workbench initialization to the moment when the response is passed back through this
 * middleware.
 * 
 * The syntax is described here https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Server-Timing
 * 
 * If a facade needs to report processing times, just add this middleware to the its stack as
 * close to the beginning as possible (so that it is called as late as possible when passing
 * back the response).
 * 
 * IDEA show more metrics (only if debugging is enabled for a user) - e.g. CPU or memory consuption.
 * 
 * @author Andrej Kabachnik
 *
 */
class ServerTimingMiddleware implements MiddlewareInterface
{    
    private $facade = null;

    private $header = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Facades\HttpFacadeInterface $facade
     */
    public function __construct(HttpFacadeInterface $facade, string $header = 'Server-Timing')
    {
        $this->facade = $facade;
        $this->header = $header;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response->withAddedHeader($this->header, 'total;dur=' . $this->facade->getWorkbench()->getDebugger()->getTimeMsFromStart());
    }
}