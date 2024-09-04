<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use exface\Core\CommonLogic\Tasks\HttpTask;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Instantiates a task and saves it in the given request attribute
 * 
 * The task is instantiated using a callable with the following structure:
 * `function($facade, $request) : HttpTaskInterface`.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskReader implements MiddlewareInterface
{    
    private $taskConstructor = null;

    private $taskAttributeName = null;

    private $facade = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Facades\HttpFacadeInterface $facade
     * @param string $taskAttributeName
     * @param callable|null $taskConstructor function($facade, $request) : HttpTaskInterface
     */
    public function __construct(HttpFacadeInterface $facade, string $taskAttributeName, callable $taskConstructor = null)
    {
        $this->facade = $facade;
        $this->taskAttributeName = $taskAttributeName;
        // If no custom constructor is provided, create a generic HTTP task
        $this->taskConstructor = $taskConstructor ?? function(HttpFacadeInterface $facade, ServerRequestInterface $request) {
            return new HttpTask($facade->getWorkbench(), $facade, $request);
        };
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $constructor = $this->taskConstructor;
        $task = $constructor($this->facade, $request);   
        $request = $request->withAttribute($this->taskAttributeName, $task);
        return $handler->handle($request);
    }
}