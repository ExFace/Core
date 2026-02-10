<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use exface\Core\DataTypes\DateDataType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\TaskRequestTrait;
use exface\Core\DataTypes\ArrayDataType;
use exface\Core\Exceptions\Facades\FacadeRequestParsingError;

/**
 * This PSR-15 middleware reads a DataSheet from the given URL or body parameter
 * and saves it into an HttpTask in the designated attribute of the request.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataUrlParamReader implements MiddlewareInterface
{
    use TaskRequestTrait;
    
    private $facade = null;
    
    private $taskAttributeName = null;
    
    private $urlParamData = null;
    
    private $methodName = null;
    
    /**
     * 
     * @param HttpFacadeInterface $facade
     * @param string $readUrlParam
     * @param string $passToMethod
     * @param string $taskAttributeName
     */
    public function __construct(HttpFacadeInterface $facade, string $readUrlParam, string $passToMethod = 'setInputData', string $taskAttributeName = 'task')
    {
        $this->facade = $facade;
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
        $task = $this->getTask($request, $this->taskAttributeName, $this->facade);
        $data = $request->getQueryParams()[$this->urlParamData] ?? null;
        
        if (is_null($data)) {
            $data = $request->getParsedBody()[$this->urlParamData] ?? null;
        }
        
        if ($data === null || $data === '') {
            return $handler->handle($request);
        }
        
        $dataSheet = $this->parseRequestData($data, $task->getWorkbench());
        if (! $dataSheet->isBlank() || ! $dataSheet->getColumns()->isEmpty()) {
            $task = $this->updateTask($task, $this->methodName, $dataSheet);
        }
        
        return $handler->handle($request->withAttribute($this->taskAttributeName, $task));
    }
    
    /**
     * Creates a DataSheet from the contents of the given value.
     * 
     * @param string|UxonObject $requestParam
     * @param WorkbenchInterface $workbench
     * @return DataSheetInterface
     */
    protected function parseRequestData($requestParam, WorkbenchInterface $workbench) : DataSheetInterface
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
                if (count($rows) === 1) {
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
     * If there are multiple rows, they will be returned as-is. In case of a
     * single row, it will be split if it contains values for valid attributes,
     * that
     * - are arrays or
     * - represent attributes, that are UIDs of their object or relations and
     *   contain the value list delimiter of their respective attribute.
     *
     * Splitting a row will result in as many rows as separate values were found,
     * each containing one of the split values. The other columns (non-array, non-uid)
     * will be only split if any of the key columns was split AND they have data
     * types, that can be split reliably: for now that's only dates!
     * 
     * TODO this only works for very simple cases like a row with a single column with
     * comma-separated UIDs, that are numbers or HEX values. We need another approach to 
     * split rows - maybe a special mapper? There are multiple problems:
     * 
     * - Even if we crate a mapper, we cannot change the default behavior here much,
     * because lots of action rely on this row split here
     * - Only certain values can be split reliably: int, date, time, hex. Floats and
     * strings can always contain their list delimiters "by coincidence". Most designers
     * never change the default list delimiter of attributes even if their values can
     * contain a comma. So this logic here must be VERY careful to avoid falsly splits.
     * - Initially we only splitted UIDs and relation keys (assuming, that those will
     * always have correct delimiters). Later dates were added because we also needed
     * the columns of the TimeStampingBehavior. Maybe this code here should only split
     * system columns? And let mappers split other columns if necessary?
     *
     * @param array $rows
     * @param DataSheetInterface $data_sheet
     * @return array
     */
    protected function splitRowsByMultivalueFields(array $rows, DataSheetInterface $data_sheet)
    {
        $result = $rows;
        // Don't split anything unless we have exactly one row
        if (count($rows) !== 1) {
            return $result;
        }
        
        $row = reset($rows);
        $obj = $data_sheet->getMetaObject();
        /** @var array[] $splitAttrVals splittable key values */
        $splitKeyVals = [];
        /** @var array[] $splitAttrVals splittable non-key values */
        $splitAttrVals = []; 
        foreach ($row as $colName => $val) {
            $attr = null;
            if (ArrayDataType::isAssociative($row) === false) {
                throw new FacadeRequestParsingError('Cannot parse URL parameter "data": invalid row format!');
            }
            if ($obj->hasAttribute($colName)){
                $attr = $obj->getAttribute($colName);
                // If the column is an attribute, see if we can split its value
                if (is_string($val)) {
                    $delim = $attr->getValueListDelimiter();
                    $attrType = $attr->getDataType();
                    switch (true) {
                        // If it is a key-value (UID or relation), split the rows 
                        // FIXME wouldn't it be better to gather the splittable keys first and split the rows
                        // after we checked all columns? We would then know exactly, how many rows we are going
                        // to get: count(keys1) * count(keys2) * ... That would make it easier to split the
                        // non-key rows
                        case $attr->isUidForObject():
                        case $attr->isRelation():
                            if (mb_strpos($val, $delim) !== false) {
                                $val = explode($delim, $val);
                                $splitKeyVals[$colName] = $val;
                            }
                            break;
                        // Also deal with direct system attributes like MODIFIED_ON because behaviors
                        // (e.g. TimeStampingBehavior) will rely on as many separate values of these attributes
                        // as we have rows.
                        // TODO we have issues here however: we cannot split these values reliably because there
                        // may be a different amount of values here than in the key columns. For example, if loading
                        // 3 rows into a ShowMassEditDialog with two of them having the same MODIFIED_ON, we will have
                        // 3 UID values and 2 MODIFIED_ON values because the multi-value hidden date field will 
                        // only return unique values. That is why we take the max of these values instead of a split.
                        case $attr->isSystem() && ! $attr->isRelated():
                            if (mb_strpos($val, $delim) !== false) {
                                $splitVals = explode($delim, $val);
                                $val = max($splitVals);
                                foreach ($result as $i => $resultRow) {
                                    $result[$i][$colName] = $val;
                                }
                                // $splitAttrVals[$colName] = explode($delim, $val);
                            }
                            break;
                    }
                }
            }
            // Now we have an array if $val was split, or we had an array there from the beginning
            if (is_array($val) === true && ArrayDataType::isSequential($val) === true) {
                if ($attr || $obj->hasAttribute($colName)) {
                    $result_before = $result;
                    // For every row in the current $result, replace it with as many rows, as we have values
                    foreach ($result_before as $nr => $r){
                        // remove the old row
                        unset($result[$nr]);
                        // Append the new split rows
                        // FIXME why append? Shouldn't they be put into the exact same place?
                        foreach ($val as $v) {
                            $result[] = array_merge($r, [$colName => $v]);
                        }
                    }
                    $result = array_values($result);
                } else {
                    $result[0][$colName] = implode(EXF_LIST_SEPARATOR, $val);
                }
            }
        }
        // Now, that we have split the rows by key columns, try to add the other splittable columns.
        // TODO this will not work if we had multiple key columns because we will have more rows in the
        // $result, than we have in the $splitAttrVals.
        $splitCnt = count($result);
        if ($splitCnt > 1 && ! empty($splitAttrVals)) {
            foreach ($splitAttrVals as $colName => $vals) {
                if (count($vals) === $splitCnt) {
                    foreach ($result as $i => $row) {
                        $result[$i][$colName] = $vals[$i];
                    }
                }
            }
        }
        return $result;
    }
}