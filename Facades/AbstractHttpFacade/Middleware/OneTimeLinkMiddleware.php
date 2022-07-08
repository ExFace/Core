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
    
    const OTL_FLAG = 'otl';
    
    const OTL_CACHE_NAME = '_onetimelink';
    
    public function __construct(HttpFileServerFacade $facade)
    {
        $this->facade = $facade;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $exface = $this->facade->getWorkbench();
        $uri = $request->getUri();
        $path = ltrim(StringDataType::substringAfter($uri->getPath(), $this->facade->getUrlRouteDefault()), "/");
        
        $pathParts = explode('/', $path);
        $otlFlag = urldecode($pathParts[0]);
        if ($otlFlag !== self::OTL_FLAG) {
           return $handler->handle($request); 
        }
        if (! $ident = $pathParts[1]) {
           return $handler->handle($request);
        }
        $cacheName = self::OTL_CACHE_NAME;
        if ($exface->getCache()->hasPool($cacheName)) {
            $cache = $exface->getCache()->getPool($cacheName, false);
        } else {
            return $handler->handle($request);
        }
        if ($cache->hasItem($ident) === false) {
            return $handler->handle($request);
        }
        $data = $cache->getItem($ident)->get();
        $cache->getItem($ident);
        $objSel = $data['object_alias'];
        $uid = $data['uid'];
        $params = $data['params'];
        
        
        return $this->facade->createResponseFromValues($objSel, $uid, $params);        
    }
}