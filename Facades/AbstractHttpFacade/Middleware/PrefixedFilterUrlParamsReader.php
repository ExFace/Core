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
        $params = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            $params = array_merge($parsedBody, $params);
        }
        
        foreach ($params as $var => $val) {
            if (strtolower(substr($var, 0, $prefixLength)) === $prefix) {
                if (is_null($dataSheet)) {
                    $dataSheet = $this->getDataSheet($task, $this->getterMethodName);
                }
                $expr = urldecode(substr($var, $prefixLength));
                // Quick filters are not meant to be applied to data within aggregated. If we have a table with stock
                // per location (STOCK:SUM) and we use a quick filter over product, we do not want the stock numbers
                // to change because they are calculated only from stock of that product - instead we just want those
                // locations, that have this product (with their total stock). For regular filters you can control this
                // using `apply_to_aggregates`, but quick filters (e.g. those in column headers) do not apply to
                // aggregates by default. 
                $applyToAggregates = false;
                if (is_array($val)) {
                    foreach ($val as $v) {
                        $dataSheet->getFilters()->addConditionFromString($expr, $v, null, null, $applyToAggregates);
                    }
                } else {
                    $dataSheet->getFilters()->addConditionFromString($expr, $val, null, null, $applyToAggregates);
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