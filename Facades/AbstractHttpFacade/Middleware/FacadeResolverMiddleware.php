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
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Interfaces\AppInterface;

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
    const REQUEST_ATTRIBUTE_PAGE = 'uipage';
    
    private $workbench = null;
    
    private $pageAttributeAlias = null;
    
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
            $facade = $this->getFacadeFromUri($request->getUri());
        } catch (FacadeRoutingError $eRouter) {
            $this->workbench->start();
            try {
                $page = $this->getPageFromUri($request->getUri());
                $facade = $page->getFacade();
                $request = $request
                ->withAttribute($facade->getRequestAttributeForAction(), 'exface.Core.ShowWidget')
                ->withAttribute($facade->getRequestAttributeForPage(), $page->getSelector()->__toString());
            } catch (FacadeRoutingError $ePage) {
                $this->workbench->getLogger()->logException($eRouter)->logException($ePage);
                return new Response(500, [], $eRouter->getMessage());
            }
        }
        
        $this->checkBaseUrls($request, $facade);
        
        if (! ($facade instanceof RequestHandlerInterface)) {
            throw new FacadeIncompatibleError('Facade "' . $facade->getAliasWithNamespace() . '" is cannot be used as a standard HTTP request handler - please check system configuration option FACADES.ROUTES!');
        }
        
        return $facade->handle($request);
    }
    
    protected function getPageFromUri(UriInterface $uri) : UiPageInterface
    {
        // If not, see if the URL matches a page alias
        // Get the last part of the path in the URI
        $aliasFromUrl = StringDataType::substringAfter($uri->getPath(), '/', '', false, true);
        // Remove the file extension
        $aliasFromUrl = StringDataType::substringBefore($aliasFromUrl, '.', $aliasFromUrl, false, true);
        
        try {
            if ($aliasFromUrl === '') {
                $aliasFromUrl = $this->workbench->getConfig()->getOption('SERVER.INDEX_PAGE_SELECTOR');
            }
            return UiPageFactory::createFromModel($this->workbench, $aliasFromUrl);
        } catch (UiPageNotFoundError $e) {            
            throw new FacadeRoutingError('No route can be found for URL "' . $uri->getPath() . '" - please check system configuration option FACADES.ROUTES or reinstall your facade!', null, $e);
        }
    }
    
    /**
     * 
     * @param UriInterface $uri
     * @throws FacadeRoutingError
     * @return HttpFacadeInterface
     */
    protected function getFacadeFromUri(UriInterface $uri) : HttpFacadeInterface
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
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @param HttpFacadeInterface $facade
     * @return FacadeResolverMiddleware
     */
    protected function checkBaseUrls(ServerRequestInterface $request, HttpFacadeInterface $facade) : FacadeResolverMiddleware
    {
        $config = $this->workbench->getConfig();
        $baseUrls = $config->getOption('SERVER.BASE_URLS')->toArray();
        
        // If the configuration has no base urls yet, add the current one. This is likely to
        // be the URL called after installation - a good value to start with.
        if (empty($baseUrls)) {
            $uri = $request->getUri();
            $facadeUrl = $facade->buildUrlToFacade();
            $base = StringDataType::substringBefore($uri->__toString(), $facadeUrl, false);
            if ($base !== false) {
                $baseUrls[] = rtrim($base, "/") . "/";
                $config->setOption('SERVER.BASE_URLS', $baseUrls, AppInterface::CONFIG_SCOPE_SYSTEM);
            }
        }
        return $this;
    }
}