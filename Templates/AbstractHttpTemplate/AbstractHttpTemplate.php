<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\Templates\AbstractTemplate\AbstractTemplate;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\TaskReaderMiddleware;
use exface\Core\Exceptions\InternalError;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\ContextReaderMiddleware;

/**
 * Common base structure for HTTP templates.
 * 
 * Uses a middleware bus internally to transform incoming HTTP requests into tasks.
 * To standardise the middleware somehat, this template getter methods for names
 * of most important request attributes needed for tasks, page and action selectors,
 * etc.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractHttpTemplate extends AbstractTemplate implements HttpTemplateInterface
{
    const REQUEST_ATTRIBUTE_NAME_TASK = 'task';
    const REQUEST_ATTRIBUTE_NAME_PAGE = 'page';
    const REQUEST_ATTRIBUTE_NAME_ACTION = 'action';
    const REQUEST_ATTRIBUTE_NAME_RENDERING_MODE = 'rendering_mode';
    
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
        
        try {
            $task = $request->getAttribute($this->getRequestAttributeForTask());
            $result = $this->getWorkbench()->handle($task);
            return $this->createResponse($request, $result);
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            return $this->createResponseError($request, $e);
        }
    }
    
    /**
     * 
     * @return MiddlewareInterface[]
     */
    protected function getMiddleware() : array
    {
        return [
            $this->getMiddlewareTaskReader(),
            $this->getMiddlewareContextReader()
        ];
    }
    
    /**
     * 
     * @return MiddlewareInterface
     */
    protected function getMiddlewareContextReader() : MiddlewareInterface
    {
        return new ContextReaderMiddleware($this->getWorkbench()->context());
    }
    
    /**
     * 
     * @return MiddlewareInterface
     */
    protected function getMiddlewareTaskReader() : MiddlewareInterface
    {
        return new TaskReaderMiddleware($this, static::REQUEST_ATTRIBUTE_NAME_TASK);
    }
    
    /**
     *
     * @param ServerRequestInterface $request
     * @param TaskResultInterface $result
     * @return ResponseInterface
     */
    protected abstract function createResponse(ServerRequestInterface $request, TaskResultInterface $result): ResponseInterface;
    
    /**
     * 
     * @param ServerRequestInterface $request
     * @param \Throwable $exception
     * @param UiPageInterface $page
     * @return ResponseInterface
     */
    protected abstract function createResponseError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null) : ResponseInterface;
        
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\HttpTemplateInterface::getRequestAttributeForAction()
     */
    public function getRequestAttributeForAction() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_ACTION;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\HttpTemplateInterface::getRequestAttributeForTask()
     */
    public function getRequestAttributeForTask() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_TASK;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\HttpTemplateInterface::getRequestAttributeForPage()
     */
    public function getRequestAttributeForPage() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_PAGE;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\HttpTemplateInterface::getRequestAttributeForRenderingMode()
     */
    public function getRequestAttributeForRenderingMode() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_RENDERING_MODE;
    }
}