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
        $requestScope->setSubrequestId($this->getSubrequestId($request));
        $requestScope->setRequestId($this->getHeaderValue($request, 'X-Request-ID'));
        // TODO add other parameters like request time or IP
        
        return $handler->handle($request);
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function getSubrequestId(ServerRequestInterface $request) : string
    {
        return $this->getHeaderValue($request, 'X-Request-ID-Subrequest');
    }
    
    protected function getHeaderValue(ServerRequestInterface $request, string $headerName) : string
    {
        if ($request->hasHeader($headerName)) {
            return $request->getHeaders()[$headerName][0];
        }
        return '';
    }
}