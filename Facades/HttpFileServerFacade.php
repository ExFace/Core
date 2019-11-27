<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;

/**
 * Facade to upload and download files using virtual pathes.
 * 
 * Currently only a stub - no real implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpFileServerFacade extends AbstractHttpFacade
{    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $absolutePath
     * @return string
     */
    public static function buildUrlForDownload(WorkbenchInterface $workbench, string $absolutePath)
    {
        return $workbench->getCMS()->buildUrlToFile($absolutePath);
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/files';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handler = new HttpRequestHandler(new NotFoundHandler());
        
        // Authenticate users
        $handler->add(new AuthenticationMiddleware($this->getWorkbench()));
        
        // TODO need to implement downloading files based on some internal virtual path here!
        // This virtual path should be used by buildUrlForDownload() too.
        $handler->handle($request);
    }
}