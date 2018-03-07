<?php
namespace exface\Core\Templates\AbstractHttpTemplate\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\CommonLogic\Tasks\HttpTask;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\MetaObjectSelector;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Templates\TemplateRequestParsingError;
use exface\Core\Exceptions\DomainException;

/**
 * This PSR-15 middleware...
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskReaderMiddleware implements MiddlewareInterface
{
    private $template = null;
    
    private $attributeNameRequest = null;
    
    private $attributeNameAction = null;
    
    private $attributeNamePage = null;
    
    private $attributeNameMode = null;
    
    private $paramNameAction = null;
    
    private $paramNameObject = null;
    
    private $paramNamePage = null;
    
    private $paramNameWidget = null;
    
    private $paramNameQuickSearch = null;
    
    private $paramNameData = null;
    
    private $paramNamePrefill = null;
    
    private $paramNamePagingOffset = null;
    
    private $paramNamePagingLength = null;
    
    private $dataParserStack = [];
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(HttpTemplateInterface $template, $requestAttributeName = 'task', $pageAttributeName = 'page', $actionAttributeName = 'action', $modeAttributeName = 'rendering_mode')
    {
        $this->template = $template;
        $this->attributeNameRequest = $requestAttributeName;
        $this->attributeNamePage = $pageAttributeName;
        $this->attributeNameAction = $actionAttributeName;
        $this->attributeNameMode = $modeAttributeName;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $task = $request->getAttribute($this->attributeNameRequest);
        if ($task === null) {
            $task = new HttpTask($this->template, $request);
        }
        
        $task = $this->readActionSelector($request, $task);
        $task = $this->readPageSelector($request, $task);
        $task = $this->readObjectSelector($request, $task);
        $task = $this->readWidgetId($request, $task);
        $task = $this->readInputData($request, $task);
        $task = $this->readPrefillData($request, $task);
        
        return $handler->handle($request->withAttribute($this->attributeNameRequest, $task));
    }
    
    protected function getTask() : HttpTaskInterface
    {
        return $this->task;
    }
    
    protected function readActionSelector(ServerRequestInterface $request, HttpTaskInterface $task) : HttpTaskInterface
    {
        if ($action = $request->getAttribute($this->attributeNameAction)) {
            $task->setActionSelector(new ActionSelector($task->getWorkbench(), $action));
        } else {
            $param = $this->getParamNameAction();
            if ($task->hasParameter($param)) {
                $task->setActionSelector(new ActionSelector($this->template->getWorkbench(), $task->getParameter($param)));
            }
        }
        return $task;
    }
    
    protected function readObjectSelector(ServerRequestInterface $request, HttpTaskInterface $task) : HttpTaskInterface
    {
        $param = $this->getParamNameObject();
        if ($task->hasParameter($param)) {
            $task->setMetaObjectSelector(new MetaObjectSelector($this->template->getWorkbench(), $task->getParameter($param)));
        }
        return $task;
    }
    
    protected function readPageSelector(ServerRequestInterface $request, HttpTaskInterface $task) : HttpTaskInterface
    {
        if ($page = $request->getAttribute($this->attributeNamePage)) {
            $task->setPageSelector(new UiPageSelector($task->getWorkbench(), $page));
        } else {
            $param = $this->getParamNamePage();
            if ($task->hasParameter($param)) {
                $task->setPageSelector(new UiPageSelector($this->template->getWorkbench(), $task->getParameter($param)));
            }
        }
        return $task;
    }
    
    protected function readWidgetId(ServerRequestInterface $request, HttpTaskInterface $task) : HttpTaskInterface
    {
        $param = $this->getParamNameWidget();
        if ($task->hasParameter($param)) {
            $task->setWidgetIdTriggeredBy($task->getParameter($param));
        }
        return $task;
    }
    
    protected function readInputData(ServerRequestInterface $request, HttpTaskInterface $task) : HttpTaskInterface
    {
        $param = $this->getParamNameData();
        if ($task->hasParameter($param)) {
            $data_sheet = $this->parseRequestData($task->getParameter($param), $task->getWorkbench());
        } else {
            $data_sheet = $task->getInputData();
        }
        
        // Parse additional request parameters relevant for the input data
        foreach ($this->dataParserStack as $callback) {
            $data_sheet = call_user_func($callback, $task->getParameters(), $data_sheet);
            if (! ($data_sheet instanceof DataSheetInterface)) {
                throw new DomainException('Invalid return type "' . gettype($data_sheet) . '" of request sorter parser: expecting a data sheet!');
            }
        }
        
        // Add filter for quick search
        // TODO replace this by $widget->getQuickSearchFilterCondition($value) or similar. The widget
        // should be responsible for how to perform the quick search - not the template. After all,
        // the quick search filters are defined in the UXON of the widget.
        $filters = [];
        if ($task->isTriggeredByWidget() && $quick_search = $task->getParameter($this->getParamNameQuickSearch())) {
            $widget = $task->getWidgetTriggeredBy();
            $quick_search_filter = $widget->getMetaObject()->getLabelAttributeAlias();
            if ($widget->is('Data') && count($widget->getAttributesForQuickSearch()) > 0) {
                foreach ($widget->getAttributesForQuickSearch() as $attr) {
                    $quick_search_filter .= ($quick_search_filter ? EXF_LIST_SEPARATOR : '') . $attr;
                }
            }
            if ($quick_search_filter) {
                $filters[][] = $quick_search;
                $data_sheet->addFilterFromString($quick_search_filter, $quick_search);
            } else {
                throw new TemplateRequestParsingError('Cannot perform quick search on object "' . $widget->getMetaObject()->getAliasWithNamespace() . '": either mark one of the attributes as a label in the model or set inlude_in_quick_search = true for one of the filters in the widget definition!', '6T6HSL4');
            }
        }
        
        $task->setInputData($data_sheet);
        return $task;
    }
    
    protected function readPrefillData(ServerRequestInterface $request, HttpTaskInterface $task) : HttpTaskInterface
    {
        $param = $this->getParamNamePrefill();
        if ($task->hasParameter($param)) {
            $task->setPrefillData($this->parseRequestData($param));
        }
        return $task;
    }
    
    /**
     * Returns the name of the URL parameter holding the action selector or NULL
     * if no such URL parameter exists (e.g. the action is derived from the path).
     *
     * @return string|null
     */
    protected function getParamNameAction()
    {
        return $this->paramNameAction;
    }
    
    /**
     * 
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNameAction($string) : TaskReaderMiddleware
    {
        $this->paramNameAction = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the object selector or NULL
     * if no such URL parameter exists (e.g. the object is derived from the path).
     *
     * @return string|null
     */
    protected function getParamNameObject()
    {
        return $this->paramNameObject;
    }
    
    /**
     *
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNameObject($string) : TaskReaderMiddleware
    {
        $this->paramNameObject = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the page selector or NULL
     * if no such URL parameter exists (e.g. the page is derived from the path).
     *
     * @return string|null
     */
    protected function getParamNamePage()
    {
        return $this->paramNamePage;
    }
    
    /**
     *
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNamePage($string) : TaskReaderMiddleware
    {
        $this->paramNamePage = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the widget selector or NULL
     * if no such URL parameter exists (e.g. the widget is derived from the path).
     *
     * @return string|null
     */
    protected function getParamNameWidget()
    {
        return $this->paramNameWidget;
    }
    
    /**
     *
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNameWidget($string) : TaskReaderMiddleware
    {
        $this->paramNameWidget = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the input data sheet.
     *
     * @return string
     */
    protected function getParamNameData()
    {
        return $this->paramNameData;
    }
    
    /**
     *
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNameData($string) : TaskReaderMiddleware
    {
        $this->paramNameData = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the prefill data sheet.
     *
     * @return string
     */
    protected function getParamNamePrefill()
    {
        return $this->paramNamePrefill;
    }
    
    /**
     *
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNamePrefill($string) : TaskReaderMiddleware
    {
        $this->paramNamePrefill = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the quick search string or NULL
     * if no such URL parameter exists (i.e. the template does not support quick search).
     *
     * @return string|null
     */
    protected function getParamNameQuickSearch()
    {
        return $this->paramNameQuickSearch;
    }
    
    /**
     *
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNamePagingOffset($string) : TaskReaderMiddleware
    {
        $this->paramNamePagingOffset = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the pagination offset or NULL
     * if no such URL parameter exists (i.e. the template does not support explicit
     * paging options).
     *
     * @return string|null
     */
    protected function getParamNamePagingOffset()
    {
        return $this->paramNamePagingOffset;
    }
    
    /**
     *
     * @param string $string
     * @return TaskReaderMiddleware
     */
    public function setParamNamePagingLength($string) : TaskReaderMiddleware
    {
        $this->paramNamePagingLength = $string;
        return $this;
    }
    
    /**
     * Returns the name of the URL parameter holding the pagination page length or NULL
     * if no such URL parameter exists (i.e. the template does not support explicit
     * paging options).
     *
     * @return string|null
     */
    protected function getParamNamePagingLength()
    {
        return $this->paramNamePagingLength;
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
                $rows = $uxon->getProperty('rows');
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
    
    /**
     * Sets a callback function to be used to add filters from the request parameters to a given data sheet.
     * 
     * The callback must return a data sheet will be called with 2 arguments:
     * - an array with request parameters [parameter => value]
     * - the data sheet, that should get the filters from the request
     * 
     * @param callable $function
     * @return TaskReaderMiddleware
     */
    public function setFilterParser(callable $callback) : TaskReaderMiddleware
    {
        $this->dataParserStack[] = $callback;
        return $this;
    }
    
    /**
     * Sets a callback function to be used to add sorters from the request parameters to a given data sheet.
     * 
     * The callback must return a data sheet will be called with 2 arguments:
     * - an array with request parameters [parameter => value]
     * - the data sheet, that should get the filters from the request
     * 
     * @param callable $function
     * @return TaskReaderMiddleware
     */
    public function setSorterParser(callable $callback) : TaskReaderMiddleware
    {
        $this->dataParserStack[] = $callback;
        return $this;
    }
    
    /**
     * Sets a callback function to be used to add pagination from the request parameters to a given data sheet.
     *
     * The callback must return a data sheet will be called with 2 arguments:
     * - an array with request parameters [parameter => value]
     * - the data sheet, that should get the filters from the request
     *
     * @param callable $function
     * @return TaskReaderMiddleware
     */
    public function setPaginationParser(callable $callback) : TaskReaderMiddleware
    {
        $this->dataParserStack[] = $callback;
        return $this;
    }
}