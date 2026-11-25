<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\CommonLogic\Contexts\Scopes\RequestContextScope;

/**
 * This PSR-15 middleware makes sure the request always has a request id.
 * 
 * If there is no X-Request-ID header, reuse the request scope ID so earlier log entries
 * remain correlated. Without a request scope, generate a random request ID.
 * When a scope is provided, copy the negotiated ID and optional X-Request-ID-Subrequest
 * header into it before invoking the next handler.
 * 
 * @author Andrej Kabachnik
 *
 */
class RequestIdNegotiator implements MiddlewareInterface
{
    const X_REQUEST_ID = 'X-Request-ID';

    const X_REQUEST_ID_SUBREQUEST = 'X-Request-ID-Subrequest';
    
    private $headerName = null;
    
    private ?RequestContextScope $requestScope;

    /**
     *
     * @param string $headerName
     * @param RequestContextScope|null $requestScope
     */
    public function __construct($headerName = self::X_REQUEST_ID, ?RequestContextScope $requestScope = null)
    {
        $this->headerName = $headerName;
        $this->requestScope = $requestScope;
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
            $requestId = $this->requestScope !== null
                ? $this->requestScope->getRequestId()
                : $this::generateRequestId();
            $request = $request->withHeader($this->headerName, $requestId);
        } else {
            $requestId = $request->getHeaderLine($headerName);
        }

        if ($this->requestScope !== null) {
            $this->requestScope->setRequestId($requestId);
            if ($request->hasHeader(self::X_REQUEST_ID_SUBREQUEST)) {
                $this->requestScope->setSubrequestId($request->getHeaderLine(self::X_REQUEST_ID_SUBREQUEST));
            }
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