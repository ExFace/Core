<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use exface\Core\Facades\AbstractFacade\AbstractFacade;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\Debugger\HttpMessageDebugWidgetRenderer;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestIdNegotiator;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestContextReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use Psr\Http\Server\MiddlewareInterface;
use exface\Core\Events\Facades\OnHttpRequestReceivedEvent;
use exface\Core\Events\Facades\OnHttpRequestHandlingEvent;
use exface\Core\Events\Facades\OnHttpBeforeResponseSentEvent;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Exceptions\InternalError;

/**
 * Common base structure for HTTP facades.
 *
 * Provides methods to register routes, generate URLs and add PSR-7 middleware to handle 
 * the HTTP request.
 *
 * Uses a middleware bus internally to transform incoming HTTP requests into tasks.
 * To standardise the middleware somehat, this facade getter methods for names
 * of most important request attributes needed for tasks, page and action selectors,
 * etc.
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractHttpFacade extends AbstractFacade implements HttpFacadeInterface
{
    const REQUEST_ATTRIBUTE_NAME_FACADE = 'facade';
    
    private $urlRelative = null;
    
    private $urlAbsolute = null;
    
    /**
     * Handles an HTTP request transforming it into a PSR-7 response.
     * 
     * The AbstractHttpFacade will do this by instantiating a bus of PSR-15 middleware a passing
     * the request through it. At the end, the `createResponse()` will be called. Right before
     * that method, an `OnHttpRequestHandlingEvent` is triggered to allow external listeners
     * to do further processing of the request - e.g. an authorization point to check the current
     * users permissions.
     * 
     * @triggers \exface\Core\Events\Facades\OnHttpRequestHandlingEvent
     * 
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            $workbench = $this->getWorkbench();
            if (! $workbench->isStarted()) {
                $workbench->start();
            }
            $eventMgr = $workbench->eventManager();
            
            // If no processing was done yet, pass the request through a middleware bus
            if ($request->getAttribute($this->getRequestAttributeForFacade()) === null) {
                // Log the request as-is
                if ($workbench->getConfig()->getOption('DEBUG.SHOW_REQUEST_DUMP') === true) {
                    $workbench->getLogger()->debug('HTTP request to "' . $request->getUri()->getPath() . '" received', [], new HttpMessageDebugWidgetRenderer($request));
                }
                
                // Save the facade in the request attributes, to mark the request as being processed
                $request = $request->withAttribute($this->getRequestAttributeForFacade(), $this);
                
                // Arm the middleware bus
                $handler = new HttpRequestHandler($this);
                foreach ($this->getMiddleware() as $middleware) {
                    $handler->add($middleware);
                }
                
                // Fire the request-received event to allow listeners to add their own middleware (e.g. a PhpDebugBar or similar)
                $eventMgr->dispatch(new OnHttpRequestReceivedEvent($this, $request));
                
                // Run the middleware. Since the facade itself is the fallback handler of the bus, the
                // handle method of the facade will be called again after all middleware is done and we
                // will end up outside of this if() since the request will already have the facade
                // attached in this case.
                return $handler->handle($request);
            }
            
            $eventMgr->dispatch(new OnHttpRequestHandlingEvent($this, $request));
            
            $response = $this->createResponse($request);
            
            $eventMgr->dispatch(new OnHttpBeforeResponseSentEvent($this, $request, $response));
        } catch (\Throwable $e) {
            switch (true) {
                // If it is not a workbench exception, wrap an internal error around it
                case ! $e instanceof ExceptionInterface: 
                    $e = new InternalError($e->getMessage(), null, $e);
                    break;
                // If the user is not logged on an the permission is denied, wrap the error in an
                // AuthenticationFailedError to tell the facade to handle it as an unauthenticated-error
                case $e instanceof AuthorizationExceptionInterface && $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->isAnonymous():
                    $e = new AuthenticationFailedError($this->getWorkbench()->getSecurity(), $e->getMessage(), null, $e);
                    break;
            }
            
            $this->getWorkbench()->getLogger()->logException($e);
            $response = $this->createResponseFromError($e, $request);
        }
        
        return $response;
    }
    
    /**
     *
     * @return string
     */
    public function getRequestAttributeForFacade() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_FACADE;
    }
    
    /**
     * Returns the middleware stack to use in the request handler.
     *
     * Override this method to add/change middleware. For example, facade can add their own
     * middleware for custom authentication tokens or to read specific URL parameters built-in 
     * the used UI frameworks.
     * 
     * Here is how to add HTTP basic auth:
     * 
     * ```
     *  protected function getMiddleware() : array
     *  {
     *      return array_merge(parent::getMiddleware(), [
     *          new AuthenticationMiddleware($this, [
     *              [
     *                 AuthenticationMiddleware::class, 'extractBasicHttpAuthToken'
     *              ]
     *          ])
     *      ]);
     *  }
     *  
     * ```
     *
     * @return MiddlewareInterface[]
     */
    protected function getMiddleware() : array
    {
        return [
            new RequestIdNegotiator(), // make sure, there is a X-Request-ID header
            new RequestContextReader($this->getWorkbench()->getContext()), // Pass request data to the request context
            new AuthenticationMiddleware($this)
        ];
    }
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    abstract protected function createResponse(ServerRequestInterface $request) : ResponseInterface;
    
    /**
     * Returns the default route to the pattern: e.g. "api/docs" for the DocsFacade.
     * @return string
     */
    abstract public function getUrlRouteDefault() : string;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::buildUrlToSiteRoot()
     */
    public function buildUrlToSiteRoot() : string
    {
        return $this->getWorkbench()->getUrl();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::buildUrlToFacade()
     */
    public function buildUrlToFacade(bool $relativeToSiteRoot = false) : string
    {
        if ($this->urlRelative === null || $this->urlAbsolute === null) {
            if (! $this->getWorkbench()->isStarted()) {
                $this->getWorkbench()->start();
            }
            $this->urlAbsolute = $this->buildUrlToSiteRoot() . $this->getUrlRouteDefault();
            $this->urlRelative = ltrim($this->getUrlRouteDefault(), "/");
        }
        return $relativeToSiteRoot === true ? $this->urlRelative : $this->urlAbsolute;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Facades\HttpFacadeInterface::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns() : array
    {
        return [
            '/' . preg_quote('/' . $this->getUrlRouteDefault(), '/') . '[\/?]' . '/'
        ];
    }
    
    /**
     * Creates and returns an HTTP response from the given exception.
     *
     * @param \Throwable $exception
     * @param ServerRequestInterface|NULL $request
     * 
     * @return ResponseInterface
     */
    protected function createResponseFromError(\Throwable $exception, ServerRequestInterface $request = null) : ResponseInterface
    {
        $code = ($exception instanceof ExceptionInterface) ? $exception->getStatusCode() : 500;
        $headers = $this->buildHeadersCommon();
        if ($this->getWorkbench()->getSecurity()->getAuthenticatedToken()->isAnonymous()) {
            return new Response($code, $headers);
        }
        return new Response($code, $headers, $exception->getMessage());
    }
    
    /**
     * 
     * @return array
     */
    protected function buildHeadersCommon() : array
    {
        return [];
    }
}