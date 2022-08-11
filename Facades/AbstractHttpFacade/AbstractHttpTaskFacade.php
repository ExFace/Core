<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;

/**
 * Common base structure for HTTP facades designed to handle workbench tasks.
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractHttpTaskFacade extends AbstractHttpFacade
{
    const REQUEST_ATTRIBUTE_NAME_TASK = 'task';
    const REQUEST_ATTRIBUTE_NAME_PAGE = 'page';
    const REQUEST_ATTRIBUTE_NAME_ACTION = 'action';
    const REQUEST_ATTRIBUTE_NAME_WIDGET = 'element';
    
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
     * Creates and returns an HTTP response from the given task result.
     * 
     * @param ServerRequestInterface $request
     * @param ResultInterface $result
     * @return ResponseInterface
     */
    protected abstract function createResponseFromTaskResult(ServerRequestInterface $request, ResultInterface $result): ResponseInterface;
    
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
    public function getRequestAttributeForWidget() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_WIDGET;
    }
}