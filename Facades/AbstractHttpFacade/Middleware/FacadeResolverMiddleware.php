<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use Psr\Http\Message\UriInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Exceptions\Facades\FacadeIncompatibleError;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Factories\FacadeFactory;
use GuzzleHttp\Psr7\Response;

/**
 * This PSR-15 middleware will look for a facade responsible for the given request
 * based on the routing configuration in the key FACADES.ROUTES of System.config.json.
 * 
 * If one of the facade URL patterns matches the URI of the request, the middleware
 * will pass the request to the facade handler. If not, the request will be passed
 * on along the responsibilty chain.
 * 
 * Using this middleware, ExFace can be easily integrated into any PSR-15 comilant
 * framework by merely adding the middleware to the stack.
 * 
 * @author Andrej Kabachnik
 *
 */
class FacadeResolverMiddleware implements MiddlewareInterface
{
    private $workbench = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $facade = $this->getFacadeForUri($request->getUri());
        } catch (FacadeRoutingError $e) {
            $this->workbench->getLogger()->logException($e);
            return new Response(500, [], $e->getMessage());
        }
        
        if (! ($facade instanceof RequestHandlerInterface)) {
            throw new FacadeIncompatibleError('Facade "' . $facade->getAliasWithNamespace() . '" is cannot be used as a standard HTTP request handler - please check system configuration option FACADES.ROUTES!');
        }
        
        return $facade->handle($request);
    }
    
    /**
     * 
     * @param UriInterface $uri
     * @throws FacadeRoutingError
     * @return HttpFacadeInterface
     */
    protected function getFacadeForUri(UriInterface $uri) : HttpFacadeInterface
    {
        $url = $uri->getPath() . '?' . $uri->getQuery();
        $routes = $this->workbench->getConfig()->getOption('FACADES.ROUTES');
        if ($routes->isEmpty()) {
            throw new FacadeRoutingError('No route configuration found is system config option FACADES.ROUTES - (re)install at least one facade!');
        }
        foreach ($routes as $pattern => $facadeAlias) {
            if (preg_match($pattern, $url) === 1) {
                return FacadeFactory::createFromString($facadeAlias, $this->workbench);
            }
        }
        throw new FacadeRoutingError('No route can be found for URL "' . $url . '" - please check system configuration option FACADES.ROUTES or reinstall your facade!');
    }
}