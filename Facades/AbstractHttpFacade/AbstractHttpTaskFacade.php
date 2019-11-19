<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestContextReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\RequestIdNegotiator;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;

/**
 * Common base structure for HTTP facades designed to handle tasks.
 *
 * Uses a middleware bus internally to transform incoming HTTP requests into tasks.
 * To standardise the middleware somehat, this facade getter methods for names
 * of most important request attributes needed for tasks, page and action selectors,
 * etc.
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractHttpTaskFacade extends AbstractHttpFacade
{
    const REQUEST_ATTRIBUTE_NAME_TASK = 'task';
    const REQUEST_ATTRIBUTE_NAME_PAGE = 'page';
    const REQUEST_ATTRIBUTE_NAME_ACTION = 'action';
    const REQUEST_ATTRIBUTE_NAME_RENDERING_MODE = 'rendering_mode';
    
    protected function init()
    {
        parent::init();
        if (! $this->getWorkbench()->isStarted()){
            $this->getWorkbench()->start();
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if ($request->getAttribute($this->getRequestAttributeForTask()) === null) {
            $handler = new HttpRequestHandler($this);
            foreach ($this->getMiddleware() as $middleware) {
                $handler->add($middleware);
            }
            // TODO Throw event to allow adding middleware from outside (e.g. a PhpDebugBar or similar)
            return $handler->handle($request);
        }
        return $this->createResponse($request);
    }
    
    /**
     * Makes the facade create an HTTP response for the given request - after all middlewares were run.
     * 
     * This method retrieves the task from the request attributes and attempts to let the workbench
     * handle it. If it succeseeds, the task result is passed on to createResponseFromTaskResult(),
     * otherwise, any exception caught is passed to createResponseFromError(). These methods are
     * reponsible for the actual rendering of the response and differ from facade to facade,
     * while the generic createResponse() method can mostly be used as-is.
     * 
     * @see createResponseFromTaskResult()
     * @see createResponseFromError()
     * 
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            $task = $request->getAttribute($this->getRequestAttributeForTask());
            $result = $this->getWorkbench()->handle($task);
            return $this->createResponseFromTaskResult($request, $result);
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            return $this->createResponseFromError($request, $e);
        }
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
            new AuthenticationMiddleware($this->getWorkbench())
        ];
    }
    
    /**
     * Creates and returns an HTTP response from the given task result.
     * 
     * @param ServerRequestInterface $request
     * @param ResultInterface $result
     * @return ResponseInterface
     */
    protected abstract function createResponseFromTaskResult(ServerRequestInterface $request, ResultInterface $result): ResponseInterface;
    
    /**
     * Creates and returns an HTTP response from the given exception.
     * 
     * @param ServerRequestInterface $request
     * @param \Throwable $exception
     * @param UiPageInterface $page
     * @return ResponseInterface
     */
    protected abstract function createResponseFromError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null) : ResponseInterface;
    
    /**
     * 
     * @return string
     */
    public function getRequestAttributeForAction() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_ACTION;
    }
    
    /**
     * 
     * @return string
     */
    public function getRequestAttributeForTask() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_TASK;
    }

    /**
     * 
     * @return string
     */
    public function getRequestAttributeForPage() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_PAGE;
    }
    
    /**
     * 
     * @return string
     */
    public function getRequestAttributeForRenderingMode() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_RENDERING_MODE;
    }
}