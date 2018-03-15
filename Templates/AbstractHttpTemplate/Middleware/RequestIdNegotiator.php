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
    const X_REQUEST_ID = 'X-Request-ID';
    
    private $headerName = null;
    
    public function __construct($headerName = self::X_REQUEST_ID)
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
        $headerName = $this->headerName;
        if (! $request->hasHeader($headerName)) {
            $request = $this->addRequestId($request);
        }
        return $handler->handle($request);
    }
    
    public function addRequestId(ServerRequestInterface $request)
    {
        return $request->withHeader($this->headerName, Uuid::uuid1());
    }
}