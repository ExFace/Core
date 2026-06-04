<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use exface\Core\CommonLogic\Tasks\HttpTask;
use exface\Core\Exceptions\Facades\HttpBadRequestError;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Facades\AbstractHttpFacade\FacadeResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Exceptions\Facades\FacadeIncompatibleError;
use GuzzleHttp\Psr7\Response;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\UiPage\UiPageNotFoundError;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Exceptions\DataSources\DataConnectionFailedError;

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
        $resolver = new FacadeResolver($this->workbench, $request->getUri());
        $facade = $resolver->getFacadeFromRoutesConfig();
        if ($facade === null) {
            if (! $this->workbench->isStarted()) {
                // TODO add more specific response for additional exception types          
                try {
                    $this->workbench->start();
                } catch (\Exception $e) {
                    $this->workbench->getLogger()->logException($e);
                    if ($e instanceof DataConnectionFailedError) {
                        return new Response(500, [], "Workbench couldn't start. Could not connect to metamodel database!");
                    }
                    return new Response(500, [], "Workbench couldn't start. Undefined error when starting workbench!");
                }
            }
            try {
                $page = $resolver->getPage();
                $facade = $page->getFacade();
                $request = $request
                    ->withAttribute(HttpTask::REQUEST_ATTRIBUTE_NAME_ACTION, 'exface.Core.ShowWidget')
                    ->withAttribute(httptask::REQUEST_ATTRIBUTE_NAME_PAGE, $page->getSelector()->__toString());
                try {
                    if ($page->getWidgetRoot()) {
                        $request = $request->withAttribute(HttpTask::REQUEST_ATTRIBUTE_NAME_WIDGET, $page->getWidgetRoot()->getId());
                    }
                } catch (\Throwable $e) {
                    if ($facade instanceof AbstractAjaxFacade) {
                        return $facade->createResponseFromError($e, $request);
                    } else {
                        throw $e;
                    }
                }
            } catch (UiPageNotFoundError $ePage) {
                $eRequest = new HttpBadRequestError($request, 'No route can be found for URL "' . $request->getUri()->getPath() . '" - please check system configuration option FACADES.ROUTES or reinstall your facade!', null, $ePage);
                $logLevel = null;
                $uri = $request->getUri()->__toString();
                switch (true) {
                    // Lower log level for JS-map URLs often happening in browser developer console.
                    case StringDataType::endsWith($uri, '.js.map', false): 
                    case StringDataType::endsWith($uri, 'map.js', false):
                        $logLevel = LoggerInterface::NOTICE;
                        break;
                    default:
                        $logLevel = LoggerInterface::ERROR;
                        break;
                }
                $this->workbench->getLogger()
                    ->logException($eRequest, $logLevel);
                return new Response(404, [], $eRequest->getMessage());
            }
        }
        
        $this->checkBaseUrls($request, $facade);
        
        if (! ($facade instanceof RequestHandlerInterface)) {
            throw new FacadeIncompatibleError('Facade "' . $facade->getAliasWithNamespace() . '" is cannot be used as a standard HTTP request handler - please check system configuration option FACADES.ROUTES!');
        }
        
        return $facade->handle($request);
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