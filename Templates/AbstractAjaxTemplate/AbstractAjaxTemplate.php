<?php
namespace exface\Core\Templates\AbstractAjaxTemplate;

use exface\Core\CommonLogic\AbstractTemplate;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\WidgetLink;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use exface\Core\Exceptions\Templates\TemplateOutputError;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Templates\TemplateRequestParsingError;
use exface\Core\Events\WidgetEvent;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\InternalError;
use exface\Core\Interfaces\Actions\iModifyContext;

abstract class AbstractAjaxTemplate extends AbstractTemplate
{

    private $elements = array();

    private $class_prefix = '';

    private $class_namespace = '';

    protected $subrequest_id = null;

    protected $request_paging_offset = 0;

    protected $request_paging_rows = NULL;

    protected $request_filters_array = array();

    protected $request_quick_search_value = NULL;

    protected $request_sorting_sort_by = NULL;

    protected $request_sorting_direction = NULL;

    protected $request_widget_id = NULL;

    protected $request_page_id = NULL;

    protected $request_action_alias = NULL;

    protected $request_prefill_data = NULL;

    protected $request_system_vars = array();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractTemplate::init()
     */
    protected function init()
    {
        parent::init();
        $this->getWorkbench()->eventManager()->addListener('#.Widget.Remove.After', function (WidgetEvent $event) {
            $this->removeElement($event->getWidget());
        });
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractTemplate::draw()
     */
    function draw(\exface\Core\Widgets\AbstractWidget $widget)
    {
        $output = '';
        try {
            $output = $this->generateHtml($widget);
            $js = $this->generateJs($widget);
        } catch (\Throwable $e) {
            if ($this->getWorkbench()->getConfig()->getOption('DEBUG.DISABLE_TEMPLATE_ERROR_HANDLERS')) {
                throw $e;
            }
            
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            
            $this->setResponseFromError($e, $widget->getPage());
            $output = $this->getResponse();
        }
        if ($js) {
            $output .= "\n" . '<script type="text/javascript">' . $js . '</script>';
        }
        
        return $output;
    }

    /**
     * Generates the JavaScript for a given Widget
     *
     * @param \exface\Core\Widgets\AbstractWidget $widget            
     */
    function generateJs(\exface\Core\Widgets\AbstractWidget $widget)
    {
        $instance = $this->getElement($widget);
        return $instance->generateJs();
    }

    /**
     * Generates the HTML for a given Widget
     *
     * @param \exface\Core\Widgets\AbstractWidget $widget            
     */
    function generateHtml(\exface\Core\Widgets\AbstractWidget $widget)
    {
        $instance = $this->getElement($widget);
        return $instance->generateHtml();
    }

    /**
     * Generates the declaration of the JavaScript sources
     *
     * @return string
     */
    public function drawHeaders(\exface\Core\Widgets\AbstractWidget $widget)
    {
        try {
            $instance = $this->getElement($widget);
            $result = implode("\n", array_unique($instance->generateHeaders()));
        } catch (ErrorExceptionInterface $e) {
            // TODO Is there a way to display errors in the header nicely?
            /*
             * $ui = $this->getWorkbench()->ui();
             * $page = UiPageFactory::create($ui, 0);
             * return $this->getWorkbench()->getDebugger()->printException($e, false);
             */
            throw $e;
        }
        return $result;
    }

    /**
     * Creates a template element for a given ExFace widget.
     * Elements are cached within the template engine, so multiple calls to this method do
     * not cause the element to get recreated from scratch. This improves performance.
     *
     * @param WidgetInterface $widget            
     * @return AbstractJqueryElement
     */
    function getElement(\exface\Core\Widgets\AbstractWidget $widget)
    {
        if (! array_key_exists($widget->getPageId(), $this->elements) || ! array_key_exists($widget->getId(), $this->elements[$widget->getPageId()])) {
            $elem_class = $this->getClass($widget);
            $instance = new $elem_class($widget, $this);
            // $this->elements[$widget->getPageId()][$widget->getId()] = $instance;
        }
        
        return $this->elements[$widget->getPageId()][$widget->getId()];
    }

    public function removeElement(AbstractWidget $widget)
    {
        unset($this->elements[$widget->getPageId()][$widget->getId()]);
    }

    public function registerElement($element)
    {
        $this->elements[$element->getWidget()->getPageId()][$element->getWidget()->getId()] = $element;
        return $this;
    }

    protected function getClass(WidgetInterface $widget)
    {
        $elem_class_prefix = $this->getClassNamespace() . '\\Elements\\' . $this->getClassPrefix();
        $elem_class = $elem_class_prefix . $widget->getWidgetType();
        if (! class_exists($elem_class)) {
            $widget_class = get_parent_class($widget);
            $elem_class = $elem_class_prefix . AbstractWidget::getWidgetTypeFromClass($widget_class);
            while (! class_exists($elem_class)) {
                if ($widget_class = get_parent_class($widget_class)) {
                    $elem_class = $elem_class_prefix . AbstractWidget::getWidgetTypeFromClass($widget_class);
                } else {
                    break;
                }
            }
            
            if (class_exists($elem_class)) {
                $reflection = new \ReflectionClass($elem_class);
                if ($reflection->isAbstract()) {
                    $elem_class = $elem_class_prefix . 'BasicElement';
                }
            } else {
                $elem_class = $elem_class_prefix . 'BasicElement';
            }
        }
        // if the required widget is not found, create an abstract widget instead
        return $elem_class;
    }

    /**
     * Creates a template element for a widget of the give resource, specified by the
     * widget's ID.
     * It's just a shortcut in case you do not have the widget object at
     * hand, but know it's ID and the resource, where it resides.
     *
     * @param string $widget_id            
     * @param string $page_id            
     * @return AbstractJqueryElement
     */
    public function getElementByWidgetId($widget_id, $page_id)
    {
        if ($elem = $this->elements[$page_id][$widget_id]) {
            return $elem;
        } else {
            if ($widget_id = $this->getWorkbench()->ui()->getWidget($widget_id, $page_id)) {
                return $this->getElement($widget_id);
            } else {
                return false;
            }
        }
    }

    public function getElementFromWidgetLink(WidgetLink $link)
    {
        return $this->getElementByWidgetId($link->getWidgetId(), $link->getPageId());
    }

    public function createLinkInternal($page_id, $url_params = '')
    {
        return $this->getWorkbench()->getCMS()->createLinkInternal($page_id, $url_params);
    }

    public function getDataSheetFromRequest($object_id = NULL, $widget = NULL)
    {
        if (! $this->request_data_sheet) {
            // Look for actual data rows in the request
            if ($this->getWorkbench()->getRequestParams()['data']) {
                if (! is_array($this->getWorkbench()->getRequestParams()['data'])) {
                    if ($decoded = @json_decode($this->getWorkbench()->getRequestParams()['data'], true));
                    $this->getWorkbench()->setRequestParam('data', $decoded);
                }
                $request_data = $this->getWorkbench()->getRequestParams()['data'];
                // If there is a data request parameter, create a data sheet from it
                if (is_array($request_data) && $request_data['oId']) {
                    // Remove rows as they may need to be split a few lines later
                    if (is_array($request_data['rows'])) {
                        $rows = $request_data['rows'];
                        unset($request_data['rows']);
                    }
                    // Create a data sheet from the JSON passed via data parameter
                    $data_sheet = DataSheetFactory::createFromUxon($this->getWorkbench(), UxonObject::fromArray($request_data));
                    // Now take care of the rows, we split off before
                    if ($rows) {
                        // If there is only one row and it has a UID column, check if the only UID cell has a concatennated value
                        if (count($rows) == 1) {
                            $rows = $this->splitRowsByMultivalueFields($rows, $data_sheet);
                        }
                        $data_sheet->addRows($rows);
                    }
                }
            }
            
            // Look for filter data
            $filters = $this->getRequestFilters();
            // Add filters for quick search
            if ($widget && $quick_search = $this->getRequestQuickSearchValue()) {
                $quick_search_filter = $widget->getMetaObject()->getLabelAlias();
                if ($widget->is('Data') && count($widget->getAttributesForQuickSearch()) > 0) {
                    foreach ($widget->getAttributesForQuickSearch() as $attr) {
                        $quick_search_filter .= ($quick_search_filter ? EXF_LIST_SEPARATOR : '') . $attr;
                    }
                }
                if ($quick_search_filter) {
                    $filters[$quick_search_filter][] = $quick_search;
                } else {
                    throw new TemplateRequestParsingError('Cannot perform quick search on object "' . $widget->getMetaObject()->getAliasWithNamespace() . '": either mark one of the attributes as a label in the model or set inlude_in_quick_search = true for one of the filters in the widget definition!', '6T6HSL4');
                }
            }
            
            // TODO this is a dirty hack. The special treatment for trees needs to move completely to the respective class
            if ($widget && $widget->getWidgetType() == 'DataTree' && ! $filters['PARENT']) {
                $filters['PARENT'][] = $widget->getTreeRootUid();
            }
            
            /* @var $data_sheet \exface\Core\CommonLogic\DataSheets\DataSheet */
            if (! $data_sheet || $data_sheet->isEmpty()) {
                if ($widget) {
                    $data_sheet = $widget->prepareDataSheetToRead($data_sheet);
                } elseif ($object_id) {
                    $data_sheet = $this->getWorkbench()->data()->createDataSheet($this->getWorkbench()->model()->getObject($object_id));
                } else {
                    return null;
                }
            }
            
            // Set filters
            foreach ($filters as $fltr_attr => $fltr) {
                if (is_array($fltr)) {
                    foreach ($fltr as $val) {
                        $data_sheet->addFilterFromString($fltr_attr, $val);
                    }
                }
            }
            
            // Set sorting options
            $sort_by = $this->getRequestSortingSortBy();
            $order = $this->getRequestSortingDirection();
            if ($sort_by && $order) {
                $sort_by = explode(',', $sort_by);
                $order = explode(',', $order);
                foreach ($sort_by as $nr => $sort) {
                    $data_sheet->getSorters()->addFromString($sort, $order[$nr]);
                }
            }
            
            // Set pagination options
            $data_sheet->setRowOffset($this->getRequestPagingOffset());
            $data_sheet->setRowsOnPage($this->getRequestPagingRows());
            
            $this->request_data_sheet = $data_sheet;
        }
        
        return $this->request_data_sheet;
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
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractTemplate::processRequest()
     */
    public function processRequest($page_id = NULL, $widget_id = NULL, $action_alias = NULL, $disable_error_handling = false)
    {
        // Look for basic request parameters
        $called_in_resource_id = $page_id ? $page_id : $this->getRequestPageId();
        $called_by_widget_id = $widget_id ? $widget_id : $this->getRequestWidgetId();
        $action_alias = $action_alias ? $action_alias : $this->getRequestActionAlias();
        
        $object_id = $this->getRequestObjectId();
        if ($this->getSubrequestId())
            $this->getWorkbench()->context()->getScopeRequest()->setSubrequestId($this->getSubrequestId());
        
        if ($called_in_resource_id) {
            try {
                $this->getWorkbench()->ui()->setPageIdCurrent($called_in_resource_id);
                $this->getWorkbench()->ui()->getPageCurrent();
            } catch (\Throwable $e) {
                if (! $disable_error_handling) {
                    if (! $e instanceof ExceptionInterface){
                        $e = new InternalError($e->getMessage(), null, $e);
                    }
                    $this->setResponseFromError($e, UiPageFactory::createEmpty($this->getWorkbench()->ui()));
                    return $this->getResponse();
                } else {
                    throw $e;
                }
            }
        }
        
        // Remove system variables from the request. These are ones a tempalte always adds to the request for it's own needs.
        // They should be defined in the init() method of the template
        foreach ($this->getRequestSystemVars() as $var) {
            $this->getWorkbench()->removeRequestParam($var);
        }
        
        // Do the actual processing
        try {
            if ($called_in_resource_id) {
                if ($called_by_widget_id) {
                    $widget = $this->getWorkbench()->ui()->getWidget($called_by_widget_id, $called_in_resource_id);
                } else {
                    $widget = $this->getWorkbench()->ui()->getPage($called_in_resource_id)->getWidgetRoot();
                }
                if (! $object_id && $widget)
                    $object_id = $widget->getMetaObject()->getId();
                if ($widget instanceof iTriggerAction && (! $action_alias || ($widget->getAction() && strcasecmp($action_alias, $widget->getAction()->getAliasWithNamespace()) === 0))) {
                    $action = $widget->getAction();
                }
            }
            
            if (! $action) {
                $exface = $this->getWorkbench();
                $action = ActionFactory::createFromString($exface, $action_alias, ($widget ? $widget : null));
            }
            
            // Give
            $action->setTemplateAlias($this->getAliasWithNamespace());
            
            // See if the widget needs to be prefilled
            if ($action->implementsInterface('iUsePrefillData')) {
                if (! $widget && $action->implementsInterface('iShowWidget')) {
                    $widget = $action->getWidget();
                }
                if ($widget && $prefill_data = $this->getRequestPrefillData($widget)) {
                    $action->setPrefillDataSheet($prefill_data);
                }
            }
            
            if (! $action) {
                throw new TemplateRequestParsingError('Action not specified in request!', '6T6HSAO');
            }
            
            // Read the input data from the request
            $data_sheet = $this->getDataSheetFromRequest($object_id, $widget);
            if ($data_sheet) {
                if ($action->getInputDataSheet()) {
                    $action->getInputDataSheet()->importRows($data_sheet);
                } else {
                    $action->setInputDataSheet($data_sheet);
                }
            }
            // Check, if the action has a widget. If not, give it the widget from the request
            if ($action->implementsInterface('iShowWidget') && ! $action->isWidgetDefined() && $widget) {
                $action->setWidget($widget);
            }
            
            $this->setResponseFromAction($action);
        } catch (ErrorExceptionInterface $e) {
            if (! $disable_error_handling && ! $this->getWorkbench()->getConfig()->getOption('DEBUG.DISABLE_TEMPLATE_ERROR_HANDLERS')) {
                if (! $e instanceof ExceptionInterface){
                    $e = new InternalError($e->getMessage(), null, $e);
                }
                $this->setResponseFromError($e, UiPageFactory::create($this->getWorkbench()->ui(), 0));
            } else {
                throw $e;
            }
        }
        
        return $this->getResponse();
    }

    protected function setResponseFromAction(ActionInterface $action)
    {
        $output = $action->getResultOutput();
        if (! $output && $action->getResultMessage()) {
            $response = array();
            $response['success'] = $action->getResultMessage();
            if ($action->isUndoable()) {
                $response['undoable'] = '1';
            }
            // check if result is a properly formed link
            if (is_string($action->getResult())) {
                $url = filter_var($action->getResult(), FILTER_SANITIZE_STRING);
                if (substr($url, 0, 4) == 'http') {
                    $response['redirect'] = $url;
                }
            }
            // Encode the response object to JSON converting <, > and " to HEX-values (e.g. \u003C). Without that conversion
            // there might be trouble with HTML in the responses (e.g. jEasyUI will break it when parsing the response)
            $output = $this->encodeData($response, $action instanceof iModifyContext ? true : false);
        }
        
        $this->setResponse($output);
        return $this;
    }

    protected function setResponseFromError(ErrorExceptionInterface $exception, UiPageInterface $page)
    {
        $http_status_code = is_numeric($exception->getStatusCode()) ? $exception->getStatusCode() : 500;
        if (is_numeric($http_status_code)) {
            http_response_code($http_status_code);
        } else {
            http_response_code(500);
        }
        
        try {
            $debug_widget = $exception->createWidget($page);
            if ($page->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_ERROR_DETAILS_TO_ADMINS_ONLY') && ! $page->getWorkbench()->context()->getScopeUser()->isUserAdmin()) {
                foreach ($debug_widget->getTabs() as $nr => $tab) {
                    if ($nr > 0) {
                        $tab->setHidden(true);
                    }
                }
            }
            $output = $this->drawHeaders($debug_widget) . "\n" . $this->draw($debug_widget);
        } catch (\Throwable $e) {
            // If anything goes wrong when trying to prettify the original error, drop prettifying
            // and throw the original exception wrapped in a notice about the failed prettification
            throw new RuntimeException('Failed to create error report widget: "' . $e->getMessage() . '"! See orignal error detail below.', null, $exception);
        } catch (FatalThrowableError $e) {
            // If anything goes wrong when trying to prettify the original error, drop prettifying
            // and throw the original exception wrapped in a notice about the failed prettification
            throw new RuntimeException('Failed to create error report widget: "' . $e->getMessage() . '"! See orignal error detail below.', null, $exception);
        }
        
        $this->getWorkbench()->getLogger()->log($exception->getLogLevel(), $exception->getMessage(), array(), $exception);
        
        $this->setResponse($output);
        return $this;
    }

    /**
     * Returns the prefill data from the request or FALSE if no prefill data was sent
     *
     * @param AbstractWidget $widget_to_prefill            
     * @return DataSheetInterface | boolean
     */
    public function getRequestPrefillData(AbstractWidget $widget_to_prefill)
    {
        // Look for prefill data
        if ($prefill_string = $this->getWorkbench()->getRequestParams()['prefill']) {
            $prefill_uxon = UxonObject::fromAnything($prefill_string);
            if ($prefill_string && $prefill_uxon->isEmpty()) {
                throw new TemplateRequestParsingError('Invalid prefill URL parameter "' . $prefill_string . '"!');
            }
        }
        if ($prefill_uxon && ! $prefill_uxon->isEmpty()) {
            $exface = $this->getWorkbench();
            if (! $prefill_uxon->getProperty('meta_object_id') && $prefill_uxon->getProperty('oId')) {
                $prefill_uxon->setProperty('meta_object_id', $prefill_uxon->getProperty('oId'));
            }
            $prefill_data = DataSheetFactory::createFromUxon($exface, $prefill_uxon);
            $this->getWorkbench()->removeRequestParam('prefill');
            
            if ($prefill_data) {
                // Add columns to be prefilled to the data sheet from the request
                $prefill_data = $widget_to_prefill->prepareDataSheetToPrefill($prefill_data);
                // If new colums are added, the sheet is marked as outdated, so we need to fetch the data from the data source
                if (! $prefill_data->isFresh()) {
                    $prefill_data->addFilterInFromString($prefill_data->getMetaObject()->getUidAlias(), $prefill_data->getColumnValues($prefill_data->getMetaObject()->getUidAlias()));
                    $prefill_data->dataRead();
                }
                
                $this->request_prefill_data = $prefill_data;
            } else {
                $this->request_prefill_data = false;
            }
        }
        
        // It is important to save the prefill data sheet in the request, because multiple action can be performed in one request
        // and they all will need the prefill data, not just the first one.
        return $this->request_prefill_data;
    }

    /**
     * Returns an array of key-value-pairs for filters contained in the current HTTP request (e.g.
     * [ "DATE_FROM" => ">01.01.2010", "LABEL" => "axenox", ... ]
     *
     * @return array
     */
    public function getRequestFilters()
    {
        // Filters a passed as request values with a special prefix: fltr01_, fltr02_, etc.
        if (empty($this->request_filters_array)) {
            foreach ($this->getWorkbench()->getRequestParams() as $var => $val) {
                if (strpos($var, 'fltr') === 0) {
                    $this->request_filters_array[urldecode(substr($var, 7))][] = urldecode($val);
                    $this->getWorkbench()->removeRequestParam($var);
                }
            }
        }
        return $this->request_filters_array;
    }

    public function getRequestQuickSearchValue()
    {
        if (! $this->request_quick_search_value) {
            $this->request_quick_search_value = ! is_null($this->getWorkbench()->getRequestParams()['q']) ? $this->getWorkbench()->getRequestParams()['q'] : NULL;
            $this->getWorkbench()->removeRequestParam('q');
        }
        return $this->request_quick_search_value;
    }

    public function getClassPrefix()
    {
        return $this->class_prefix;
    }

    public function setClassPrefix($value)
    {
        $this->class_prefix = $value;
        return $this;
    }

    public function getClassNamespace()
    {
        return $this->class_namespace;
    }

    public function setClassNamespace($value)
    {
        $this->class_namespace = $value;
    }

    public function getRequestPagingRows()
    {
        if (! $this->request_paging_rows) {
            $this->request_paging_rows = ! is_null($this->getWorkbench()->getRequestParams()['rows']) ? intval($this->getWorkbench()->getRequestParams()['rows']) : 0;
            $this->getWorkbench()->removeRequestParam('rows');
        }
        return $this->request_paging_rows;
    }

    public function getRequestSortingSortBy()
    {
        if (! $this->request_sorting_sort_by) {
            $this->request_sorting_sort_by = ! is_null($this->getWorkbench()->getRequestParam('sort')) ? strval($this->getWorkbench()->getRequestParam('sort')) : '';
            $this->getWorkbench()->removeRequestParam('sort');
        }
        return $this->request_sorting_sort_by;
    }

    public function getRequestSortingDirection()
    {
        if (! $this->request_sorting_direction) {
            $this->request_sorting_direction = ! is_null($this->getWorkbench()->getRequestParam('order')) ? strval($this->getWorkbench()->getRequestParam('order')) : '';
            $this->getWorkbench()->removeRequestParam('order');
        }
        return $this->request_sorting_direction;
    }

    public function getRequestPagingOffset()
    {
        if (! $this->request_paging_offset) {
            $page = ! is_null($this->getWorkbench()->getRequestParams()['page']) ? intval($this->getWorkbench()->getRequestParams()['page']) : 1;
            $this->getWorkbench()->removeRequestParam('page');
            $this->request_paging_offset = ($page - 1) * $this->getRequestPagingRows();
        }
        return $this->request_paging_offset;
    }

    public function getRequestObjectId()
    {
        if (! $this->request_object_id) {
            $this->request_object_id = ! is_null($this->getWorkbench()->getRequestParams()['object']) ? $this->getWorkbench()->getRequestParams()['object'] : $_POST['data']['oId'];
            $this->getWorkbench()->removeRequestParam('object');
        }
        return $this->request_object_id;
    }

    public function getRequestPageId()
    {
        if (! $this->request_page_id) {
            $this->request_page_id = ! is_null($this->getWorkbench()->getRequestParams()['resource']) ? intval($this->getWorkbench()->getRequestParams()['resource']) : NULL;
            $this->getWorkbench()->removeRequestParam('resource');
        }
        return $this->request_page_id;
    }

    public function getRequestWidgetId()
    {
        if (! $this->request_widget_id) {
            $this->request_widget_id = ! is_null($this->getWorkbench()->getRequestParams()['element']) ? urldecode($this->getWorkbench()->getRequestParams()['element']) : '';
            $this->getWorkbench()->removeRequestParam('element');
        }
        return $this->request_widget_id;
    }

    public function getRequestActionAlias()
    {
        if (! $this->request_action_alias) {
            $this->request_action_alias = urldecode($this->getWorkbench()->getRequestParams()['action']);
            $this->getWorkbench()->removeRequestParam('action');
        }
        return $this->request_action_alias;
    }

    public function getRequestSystemVars()
    {
        return $this->request_system_vars;
    }

    public function setRequestSystemVars(array $var_names)
    {
        $this->request_system_vars = $var_names;
        return $this;
    }

    public function getSubrequestId()
    {
        if (! $this->subrequest_id) {
            $this->subrequest_id = urldecode($this->getWorkbench()->getRequestParams()['exfrid']);
            $this->getWorkbench()->removeRequestParam('exfrid');
        }
        return $this->subrequest_id;
    }
    
    /**
     * 
     * @param unknown $serializable_data
     * @param string $add_extras
     * @throws TemplateOutputError
     * @return string
     */
    public function encodeData($serializable_data, $add_extras = false)
    {
        if ($add_extras){
            $serializable_data['extras'] = [
                'ContextBar' => $this->buildResponseExtraForContextBar()
            ];
        }
        
        $result = json_encode($serializable_data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT);
        if (! $result) {
            throw new TemplateOutputError('Error encoding data: ' . json_last_error() . ' ' . json_last_error_msg());
        }
        return $result;
    }
    
    public function buildResponseExtraForContextBar()
    {
        $extra = [];
        try {
            $contextBar = $this->getWorkbench()->ui()->getPageCurrent()->getContextBar();
            foreach ($contextBar->getButtons() as $btn){
                $btn_element = $this->getElement($btn);
                $context = $contextBar->getContextForButton($btn);
                $extra[$btn_element->getId()] = [
                    'visibility' => $context->getVisibility(),
                    'icon' => $btn_element->buildCssIconClass($btn->getIconName()),
                    'color' => $context->getColor(),
                    'hint' => $btn->getHint(),
                    'indicator' => ! is_null($context->getIndicator()) ? $contextBar->getContextForButton($btn)->getIndicator() : '',
                    'bar_widget_id' => $btn->getId()
                ];
            }
        } catch (\Throwable $e){
            $this->getWorkbench()->getLogger()->logException($e);
        }
        return $extra;
    }
}
?>