<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Exceptions\Facades\HttpBadRequestError;

/**
 * Common base structure for HTTP facades designed to handle workbench tasks.
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractHttpTaskFacade extends AbstractHttpFacade
{
    const REQUEST_ATTRIBUTE_NAME_TASK = 'task';
    
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
        $task = $request->getAttribute($this->getRequestAttributeForTask());
        // Make sure the task was successfully read from the request
        if (! ($task instanceof TaskInterface)) {
            // There have been issues with the server configuration, when large requests with file uploads
            // exceeded the post_max_size in php.ini - in this case, the request body was there, but $_POST
            // and $request->getParsedBody() were empty. This does not lead to an error, so we double-check
            // here and throw a differen exception if this might be the case.
            if ($request->getBody()->getSize() > (100 * 1024) && empty($request->getParsedBody()) && empty($request->getUploadedFiles())) {
                throw new HttpBadRequestError($request, 'Could not parse large request: max. POST size exceeded? Check post_max_size and server configuration.');
            }
            // In any case, if there is no task - throw an error!
            throw new HttpBadRequestError($request, 'No task data found in HTTP request');
        }
        $result = $this->getWorkbench()->handle($task);
        return $this->createResponseFromTaskResult($request, $result);
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
    public function getRequestAttributeForTask() : string
    {
        return static::REQUEST_ATTRIBUTE_NAME_TASK;
    }
}