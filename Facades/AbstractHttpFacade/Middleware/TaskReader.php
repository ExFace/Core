<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use exface\Core\CommonLogic\Tasks\HttpTask;
use exface\Core\CommonLogic\UxonObject;
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
 * In addition to a custom constructor, you can also specify a mapping to convert
 * request/body parameters to UXON properties of the task model. Here is an example:
 * 
 * ```
 * new TaskReader($facade, 'task', null, [
 *  'oId' => 'object_alias',
 *  'resource' => 'page_alias'
 * ]);
 * 
 * ```
 * 
 * TODO replace all TaskUrlParamReader middlewares with this one as a single task
 * reader using the UXON mapping
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskReader implements MiddlewareInterface
{    
    private $taskConstructor = null;

    private $taskAttributeName = null;

    private $facade = null;

    private $requestUxonMap = [];

    /**
     * 
     * @param \exface\Core\Interfaces\Facades\HttpFacadeInterface $facade
     * @param string $taskAttributeName
     * @param callable|null $taskConstructor function($facade, $request) : HttpTaskInterface
     * @param string[] $requestToUxonPropMap UrlParam => UxonProperty
     * @return void
     */
    public function __construct(HttpFacadeInterface $facade, string $taskAttributeName, callable $taskConstructor = null, array $requestToUxonPropMap = [])
    {
        $this->facade = $facade;
        $this->taskAttributeName = $taskAttributeName;
        // If no custom constructor is provided, create a generic HTTP task
        $this->taskConstructor = $taskConstructor ?? function(HttpFacadeInterface $facade, ServerRequestInterface $request) {
            return new HttpTask($facade->getWorkbench(), $facade, $request);
        };
        $this->requestUxonMap = $requestToUxonPropMap; 
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
        if (! empty($this->requestUxonMap)) {
            $array = [];
            foreach ($this->requestUxonMap as $param => $uxonProp) {      
                $value = $request->getQueryParams()[$param] ?? null;
                if (null === $value) {
                    $value = $request->getParsedBody()[$param] ?? null;
                }
                if ($value !== null) {
                    $array[$uxonProp] = $value;
                }
            }
            if (! empty($array)) {
                $task->importUxonObject(new UxonObject($array));
            }
        } 
        $request = $request->withAttribute($this->taskAttributeName, $task);
        return $handler->handle($request);
    }
}