<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope;

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
            $requestId = $this::generateRequestId();
            $request = $request->withHeader($this->headerName, $requestId);
        } else {
            $requestId = $request->getHeaderLine($headerName);
        }
        $response = $handler->handle($request);
        if (! $response->hasHeader($this->headerName)) {
            $response = $response->withHeader($this->headerName, $requestId);
        }
        return $response;
    }
    
    /**
     * 
     * @return string
     */
    public static function generateRequestId() : string
    {
        return RequestContextScope::generateRequestId();
    }
}