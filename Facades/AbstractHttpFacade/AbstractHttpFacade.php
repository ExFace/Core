<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use exface\Core\Facades\AbstractFacade\AbstractFacade;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\Debugger\HttpMessageDebugWidgetRenderer;
use exface\Core\Events\Facades\OnFacadeReceivedHttpRequestEvent;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestIdNegotiator;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestContextReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use Psr\Http\Server\MiddlewareInterface;
use exface\Core\Events\Facades\OnHttpRequestReceivedEvent;

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
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            $workbench = $this->getWorkbench();
            if (! $workbench->isStarted()) {
                $workbench->start();
            }
            
            // If no processing was done yet, pass the request through a middleware bus
            if ($request->getAttribute($this->getRequestAttributeForFacade()) === null) {
                // Log the request as-is
                if ($this->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_REQUEST_DUMP') === true) {
                    $this->getWorkbench()->getLogger()->debug('HTTP request to "' . $request->getUri()->getPath() . '" received', [], new HttpMessageDebugWidgetRenderer($request));
                }
                
                // Save the facade in the request attributes, to mark the request as being processed
                $request = $request->withAttribute($this->getRequestAttributeForFacade(), $this);
                
                // Arm the middleware bus
                $handler = new HttpRequestHandler($this);
                foreach ($this->getMiddleware() as $middleware) {
                    $handler->add($middleware);
                }
                
                // Fire the request-received event to allow listeners to add their own middleware (e.g. a PhpDebugBar or similar)
                $this->getWorkbench()->eventManager()->dispatch(new OnHttpRequestReceivedEvent($this, $request));
                
                // Run the middleware. Since the facade itself is the fallback handler of the bus, the
                // handle method of the facade will be called again after all middleware is done and we
                // will end up outside of this if() since the request will already have the facade
                // attached in this case.
                return $handler->handle($request);
            }
            
            return $this->createResponse($request);
        } catch (\Throwable $e) {
            return $this->createResponseFromError($request, $e);
        }
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
     * middleware to read specific URL parameters built-in the used UI frameworks.
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
     * @param ServerRequestInterface $request
     * @param \Throwable $exception
     * 
     * @return ResponseInterface
     */
    protected function createResponseFromError(ServerRequestInterface $request, \Throwable $exception) : ResponseInterface
    {
        $code = ($exception instanceof ExceptionInterface) ? $exception->getStatusCode() : 500;
        if ($this->getWorkbench()->getSecurity()->getAuthenticatedToken()->isAnonymous()) {
            $this->getWorkbench()->getLogger()->logException($exception);
            return new Response($code);
        }
        throw $exception;
    }
}