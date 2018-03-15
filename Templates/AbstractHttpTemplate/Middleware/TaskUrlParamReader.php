<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\Traits\TaskRequestTrait;

/**
 * This PSR-15 middleware passes the value of a given URL/body parameter or attribute to the specified
 * setter method of the HttpTask in the a attributes of the request.
 * 
 * If the constructor parameter $valueAttributeName is specified and the corresponding request attribute
 * exists, it's value will be used. This is usefull if some other code had already determined the value
 * and the middleware must noch change it. For example, the CMS will typically set action and page
 * parameters explicitly, so they do not need to be read from the request by this middleware.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskUrlParamReader implements MiddlewareInterface
{
    use TaskRequestTrait;
    
    private $template = null;
    
    private $taskAttributeName = null;
    
    private $valueAttributeName = null;
    
    private $methodName = null;
    
    private $paramName = null;
    
    /**
     * 
     * @param HttpTemplateInterface $template
     * @param string $readUrlParam
     * @param string $passToMethod
     * @param string $valueAttributeName
     * @param string $taskAttributeName
     */
    public function __construct(HttpTemplateInterface $template, string $readUrlParam, string $passToMethod, string $valueAttributeName = null, string $taskAttributeName = 'task')
    {
        $this->template = $template;
        $this->taskAttributeName = $taskAttributeName;
        $this->valueAttributeName = $valueAttributeName;
        $this->methodName = $passToMethod;
        $this->paramName = $readUrlParam;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $value = null;
        
        if (! is_null($this->valueAttributeName)) {
            $value = $request->getAttribute($this->valueAttributeName);
        }
        
        if (is_null($value)) {
            $value = $request->getQueryParams()[$this->paramName];
        }
        
        if (is_null($value)) {
            $value = $request->getParsedBody()[$this->paramName];
        }
        
        if (! is_null($value)) {
            $task = $this->getTask($request, $this->taskAttributeName, $this->template);
            $request = $request->withAttribute($this->taskAttributeName, $this->updateTask($task, $this->methodName, $value));
        }
        
        return $handler->handle($request);
    }
}