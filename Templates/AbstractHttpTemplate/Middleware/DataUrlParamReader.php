<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * This PSR-15 middleware...
 * 
 * @author Andrej Kabachnik
 *
 */
class DataUrlParamReader implements MiddlewareInterface
{
    use TaskRequestTrait;
    
    private $template = null;
    
    private $taskAttributeName = null;
    
    private $urlParamData = null;
    
    private $methodName = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(HttpTemplateInterface $template, $readUrlParam, $passToMethod, $taskAttributeName = 'task')
    {
        $this->template = $template;
        $this->taskAttributeName = $taskAttributeName;
        $this->urlParamData = $readUrlParam;
        $this->methodName = $passToMethod;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $task = $this->getTask($request, $this->taskAttributeName, $this->template);
        $data = $request->getQueryParams()[$this->urlParamData];
        
        if (is_null($data)) {
            $data = $request->getParsedBody()[$this->urlParamData];
        }
        
        if ($data === null || $data === '') {
            return $handler->handle($request);
        }
        
        $task = $this->updateTask($task, $this->methodName, $this->parseRequestData($data, $task->getWorkbench()));
        
        return $handler->handle($request->withAttribute($this->taskAttributeName, $task));
    }
    
    protected function parseRequestData($requestParam, WorkbenchInterface $workbench)
    {
        $data_sheet = null;
        
        // Look for actual data rows in the request
        $uxon = UxonObject::fromAnything($requestParam);
        // If there is a data request parameter, create a data sheet from it
        if (! $uxon->isEmpty()) {
            // Remove rows as they may need to be split a few lines later
            if ($uxon->hasProperty('rows')) {
                $rows = $uxon->getProperty('rows')->toArray();
                $uxon->unsetProperty('rows');
            }
            // Create a data sheet from the UXON object
            $data_sheet = DataSheetFactory::createFromUxon($workbench, $uxon);
            // Now take care of the rows, we split off before
            if ($rows) {
                // If there is only one row and it has a UID column, check if the only UID cell has a concatennated value
                if (count($rows) == 1) {
                    $rows = $this->splitRowsByMultivalueFields($rows, $data_sheet);
                }
                $data_sheet->addRows($rows);
            }
        }
        
        return $data_sheet;
    }
    
    /**
     * This method takes care of single-row data, that has columns with delimited
     * lists or arrays.
     *
     * If there are multiple rows, they will be returned as is. In case of a
     * single row, it will be split if it contains values for valid attributes,
     * that
     * - are arrays or
     * - represent attributes, that are UIDs of their object or relations and
     *   contain the value list delimiter of their respective attribute.
     *
     * Splitting a row will result in as many rows as separate values were found,
     * each containing one of the split values and the same set of values in all
     * other columns.
     *
     * @param array $rows
     * @param DataSheetInterface $data_sheet
     * @return array
     */
    protected function splitRowsByMultivalueFields(array $rows, DataSheetInterface $data_sheet)
    {
        $result = $rows;
        if (count($rows) == 1) {
            $row = reset($rows);
            foreach ($row as $field => $val) {
                if ($data_sheet->getMetaObject()->hasAttribute($field)){
                    $attr = $data_sheet->getMetaObject()->getAttribute($field);
                    if (is_string($val) && ($attr->isUidForObject() || $attr->isRelation())){
                        $delim = $attr->getValueListDelimiter();
                        if (strpos($val, $delim)){
                            $val = explode($delim, $val);
                        }
                    }
                }
                if (is_array($val)) {
                    if ($attr || $data_sheet->getMetaObject()->hasAttribute($field)) {
                        $result_before = $result;
                        foreach ($result_before as $nr => $r){
                            unset($result[$nr]);
                            $result = array_values($result);
                            foreach ($val as $v) {
                                $result[] = array_merge($r, [$field => $v]);
                            }
                        }
                    } else {
                        $result[0][$field] = implode(EXF_LIST_SEPARATOR, $val);
                    }
                }
            }
        }
        return $result;
    }
}