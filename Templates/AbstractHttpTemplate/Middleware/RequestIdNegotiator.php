<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

/**
 * This PSR-15 middleware makes sure the request allways has a request id.
 * 
 * If there is no X-Request-ID header, one will be added with a random request id.
 * 
 * @author Andrej Kabachnik
 *
 */
class RequestIdNegotiator implements MiddlewareInterface
{
    private $headerName = null;
    
    
    public function __construct($headerName = 'X-Request-ID')
    {
        $this->headerName = $headerName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $request->hasHeader($this->headerName)) {
            $request = $request->withHeader($this->headerName, Uuid::uuid1());
        }
        return $handler->handle($request);
    }
}