<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\TaskRequestTrait;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\DataEnricherTrait;

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
    
    private $facade = null;
    
    private $taskAttributeName = null;
    
    private $paramPrefix = null;
    
    private $getterMethodName = null;
    
    private $setterMethodName = null;
    
    /**
     * 
     * @param HttpFacadeInterface $facade
     * @param string $paramPrefix
     * @param string $dataGetterMethod
     * @param string $dataSetterMethod
     * @param string $taskAttributeName
     */
    public function __construct(HttpFacadeInterface $facade, string $paramPrefix, string $dataGetterMethod, string $dataSetterMethod, string $taskAttributeName = 'task')
    {
        $this->facade = $facade;
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
        $task = $this->getTask($request, $this->taskAttributeName, $this->facade);
        $dataSheet = null;
        $prefix = strtolower($this->paramPrefix);
        $prefixLength = strlen($prefix);
        $params = array_merge($request->getParsedBody(), $request->getQueryParams());
        
        foreach ($params as $var => $val) {
            if (strtolower(substr($var, 0, $prefixLength)) === $prefix) {
                if (is_null($dataSheet)) {
                    $dataSheet = $this->getDataSheet($task, $this->getterMethodName);
                }
                $expr = urldecode(substr($var, $prefixLength));
                if (is_array($val)) {
                    foreach ($val as $v) {
                        $dataSheet->getFilters()->addConditionFromString($expr, $v);
                    }
                } else {
                    $dataSheet->getFilters()->addConditionFromString($expr, $val);
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