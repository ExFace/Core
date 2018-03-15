<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;

/**
 * This PSR-15 middleware...
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
     * @param WorkbenchInterface $workbench
     */
    public function __construct(HttpTemplateInterface $template, string $readUrlParam, string $passToMethod, $valueAttributeName = null, $taskAttributeName = 'task')
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