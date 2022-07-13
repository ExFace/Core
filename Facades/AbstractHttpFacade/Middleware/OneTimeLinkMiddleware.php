<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\DataTypes\StringDataType;

class OneTimeLinkMiddleware implements MiddlewareInterface
{
    private $facade = null;
    
    private $otlPathPart = null;
        
    public function __construct(HttpFileServerFacade $facade, string $otlPathPart)
    {
        $this->facade = $facade;
        $this->otlPathPart = $otlPathPart;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->otlPathPart === null || $this->otlPathPart === '') {
            return $handler->handle($request);
        }
        $uri = $request->getUri();
        $path = ltrim(StringDataType::substringAfter($uri->getPath(), $this->facade->getUrlRouteDefault()), "/");
        
        $pathParts = explode('/', $path);
        $otlFlag = urldecode($pathParts[0]);        
        if ($otlFlag !== $this->otlPathPart) {
           return $handler->handle($request); 
        }
        if (! $ident = $pathParts[1]) {
           return $handler->handle($request);
        }
        
        return $this->facade->createResponseFromOneTimeLinkIdent($ident);        
    }
}