<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\TaskRequestTrait;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\DataEnricherTrait;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * This PSR-15 middleware reads inline-filters from the URL and passes them to the task
 * in the attributes of the request.
 * 
 * @author Andrej Kabachnik
 *
 */
class JqueryDataTablesUrlParamsReader implements MiddlewareInterface
{
    use TaskRequestTrait;
    use DataEnricherTrait;
    
    private $facade = null;
    
    private $taskAttributeName = null;
    
    private $getterMethodName = null;
    
    private $setterMethodName = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(HttpFacadeInterface $facade, string $dataGetterMethod, string $dataSetterMethod, $taskAttributeName = 'task')
    {
        $this->facade = $facade;
        $this->taskAttributeName = $taskAttributeName;
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
        
        $requestParams = $request->getQueryParams();
        if (is_array($request->getParsedBody()) || $request->getParsedBody()) {
            $requestParams = array_merge($requestParams, $request->getParsedBody());
        }
        
        $result = $this->readSortParams($task, $requestParams);
        $result = $this->readPaginationParams($task, $requestParams, $result);
        
        if ($result !== null) {
            $task = $this->updateTask($task, $this->setterMethodName, $result);
            return $handler->handle($request->withAttribute($this->taskAttributeName, $task));
        } else {
            return $handler->handle($request);
        }
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param array $params
     * @param DataSheetInterface $dataSheet
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface|NULL
     */
    protected function readSortParams (TaskInterface $task, array $params, DataSheetInterface $dataSheet = null) 
    {
        $order = is_array($params['order']) ? $params['order'] : null;
        
        if (is_null($order)) {
            return null;
        }
        
        $dataSheet = $dataSheet ? $dataSheet : $this->getDataSheet($task, $this->getterMethodName);
        $requestCols = $params['columns'];
        foreach ($order as $sorter) {
            if (! is_null($sorter['column'])) { // sonst wird nicht nach der 0. Spalte sortiert (0 == false)
                if ($sort_attr = $requestCols[$sorter['column']]['name']) {
                    $dataSheet->getSorters()->addFromString($sort_attr, $sorter['dir']);
                }
            } elseif ($sorter['attribute_alias']) {
                $dataSheet->getSorters()->addFromString($sorter['attribute_alias'], $sorter['dir']);
            }
        }
        
        return $dataSheet;
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param array $params
     * @param DataSheetInterface $dataSheet
     * @return \exface\Core\Interfaces\DataSheets\DataSheetInterface
     */
    protected function readPaginationParams (TaskInterface $task, array $params, DataSheetInterface $dataSheet = null) 
    {
        if (array_key_exists('length', $params) ||  array_key_exists('start', $params)) {
            $dataSheet = $dataSheet ? $dataSheet : $this->getDataSheet($task, $this->getterMethodName);
            $dataSheet->setRowsOffset(isset($params['start']) ? intval($params['start']) : 0);
            $dataSheet->setRowsLimit(isset($params['length']) ? intval($params['length']) : 0);
        }
        return $dataSheet;
    }
}