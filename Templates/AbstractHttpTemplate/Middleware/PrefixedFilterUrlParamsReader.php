<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\Traits\TaskRequestTrait;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\Traits\DataEnricherTrait;

/**
 * This PSR-15 middleware reads inline-filters from the URL and passes them to the task
 * in the attributes of the request.
 * 
 * @author Andrej Kabachnik
 *
 */
class PrefixedFilterUrlParamsReader implements MiddlewareInterface
{
    use TaskRequestTrait;
    use DataEnricherTrait;
    
    private $template = null;
    
    private $taskAttributeName = null;
    
    private $paramPrefix = null;
    
    private $getterMethodName = null;
    
    private $setterMethodName = null;
    
    /**
     * 
     * @param HttpTemplateInterface $template
     * @param string $paramPrefix
     * @param string $dataGetterMethod
     * @param string $dataSetterMethod
     * @param string $taskAttributeName
     */
    public function __construct(HttpTemplateInterface $template, string $paramPrefix, string $dataGetterMethod, string $dataSetterMethod, string $taskAttributeName = 'task')
    {
        $this->template = $template;
        $this->taskAttributeName = $taskAttributeName;
        $this->paramPrefix = $paramPrefix;
        $this->getterMethodName = $dataGetterMethod;
        $this->setterMethodName = $dataSetterMethod;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $task = $this->getTask($request, $this->taskAttributeName, $this->template);
        $dataSheet = null;
        $prefix = strtolower($this->paramPrefix);
        $prefixLength = strlen($prefix);
        
        foreach ($request->getQueryParams() as $var => $val) {
            if (strtolower(substr($var, 0, $prefixLength)) === $prefix) {
                if (is_null($dataSheet)) {
                    $dataSheet = $this->getDataSheet($task, $this->getterMethodName);
                }
                $expr = urldecode(substr($var, $prefixLength));
                if (is_array($val)) {
                    foreach ($val as $v) {
                        $dataSheet->addFilterFromString($expr, $v);
                    }
                } else {
                    $dataSheet->addFilterFromString($expr, $val);
                }
            }
        }
        
        if (! is_null($dataSheet)) {
            $task = $this->updateTask($task, $this->setterMethodName, $dataSheet);
            return $handler->handle($request->withAttribute($this->taskAttributeName, $task));
        } else {
            return $handler->handle($request);
        }
    }
}