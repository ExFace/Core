<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Contexts\ContextManagerInterface;

/**
 * This PSR-15 middleware fills the request context scope based on information in
 * the processed server request.
 * 
 * @author Andrej Kabachnik
 *
 */
class RequestContextReader implements MiddlewareInterface
{
    private $context = null;
    
    /**
     * 
     * @param ContextManagerInterface $context
     */
    public function __construct(ContextManagerInterface $context)
    {
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestScope = $this->context->getScopeRequest();
        
        $requestScope->setRequestProcessed($request);
        
        $rId = $this->getHeaderValue($request, 'X-Request-ID');
        if ($rId !== null) {
            $requestScope->setRequestId($rId);
        }
        
        $srId = $this->getHeaderValue($request, 'X-Request-ID-Subrequest');
        if ($srId !== null) {
            $requestScope->setSubrequestId($srId);
        }
        
        // IDEA add other parameters like request time or IP
        
        return $handler->handle($request);
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @param string $headerName
     * @return string|NULL
     */
    protected function getHeaderValue(ServerRequestInterface $request, string $headerName) : ?string
    {
        if ($request->hasHeader($headerName)) {
            return $request->getHeaders()[$headerName][0];
        }
        return null;
    }
}