<?php
namespace exface\Core\Templates\AbstractAjaxTemplate;

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
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsNumberFormatter;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsDateFormatter;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsTransparentFormatter;
use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsEnumFormatter;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsBooleanFormatter;
use exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate;
use Psr\Http\Server\MiddlewareInterface;

abstract class AbstractAjaxTemplate extends AbstractHttpTemplate
{

    private $elements = array();
    
    /**
     * [ widget_type => qualified class name]
     * @var array
     */
    private $classes_by_widget_type = [];

    private $class_prefix = '';

    private $class_namespace = '';
    
    private $data_type_formatters = [];

    protected $subrequest_id = null;

    protected $request_paging_offset = 0;

    protected $request_paging_rows = NULL;

    protected $request_filters_array = array();

    protected $request_quick_search_value = NULL;

    protected $request_sorting_sort_by = NULL;

    protected $request_sorting_direction = NULL;

    protected $request_widget_id = NULL;

    protected $request_page_alias = NULL;

    protected $request_action_alias = NULL;

    protected $request_prefill_data = NULL;

    protected $request_system_vars = array();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Templates\AbstractTemplate\AbstractTemplate::init()
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
     * @see \exface\Core\Templates\AbstractTemplate\AbstractTemplate::buildWidget()
     */
    function buildWidget(WidgetInterface $widget)
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
    public function buildIncludes(WidgetInterface $widget)
    {
        try {
            $instance = $this->getElement($widget);
            $result = implode("\n", array_unique($instance->generateHeaders()));
        } catch (ErrorExceptionInterface $e) {
            // TODO Is there a way to display errors in the header nicely?
            /*
             * $ui = $this->getWorkbench()->ui();
             * $page = UiPageFactory::create($ui, '');
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
        if (! array_key_exists($widget->getPage()->getAliasWithNamespace(), $this->elements) || ! array_key_exists($widget->getId(), $this->elements[$widget->getPage()->getAliasWithNamespace()])) {
            $elem_class = $this->getClass($widget);
            $instance = new $elem_class($widget, $this);
            // $this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()] = $instance;
        }
        
        return $this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()];
    }

    public function removeElement(AbstractWidget $widget)
    {
        unset($this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()]);
    }

    public function registerElement($element)
    {
        $this->elements[$element->getWidget()->getPage()->getAliasWithNamespace()][$element->getWidget()->getId()] = $element;
        return $this;
    }

    protected function getClass(WidgetInterface $widget)
    {
        $elem_class = $this->classes_by_widget_type[$widget->getWidgetType()];
        if (is_null($elem_class)) {
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
                    // if the required widget is not found, create an abstract widget instead
                    $elem_class = $elem_class_prefix . 'BasicElement';
                }
            }
            $this->classes_by_widget_type[$widget->getWidgetType()] = $elem_class;
        }
        return $elem_class;
    }

    /**
     * Creates a template element for a widget of the give resource, specified by the
     * widget's ID.
     * It's just a shortcut in case you do not have the widget object at
     * hand, but know it's ID and the resource, where it resides.
     *
     * @param string $widget_id            
     * @param UiPageInterface $page            
     * @return AbstractJqueryElement
     */
    public function getElementByWidgetId($widget_id, UiPageInterface $page)
    {
        if ($elem = $this->elements[$page->getAliasWithNamespace()][$widget_id]) {
            return $elem;
        } elseif ($widget = $page->getWidget($widget_id)) {
            return $this->getElement($widget);
        } else {
            return false;
        }
    }

    public function getElementFromWidgetLink(WidgetLink $link)
    {
        return $this->getElementByWidgetId($link->getWidgetId(), $link->getPage());
    }

    public function createLinkInternal($page_or_id_or_alias, $url_params = '')
    {
        return $this->getWorkbench()->getCMS()->createLinkInternal($page_or_id_or_alias, $url_params);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Templates\AbstractTemplate\AbstractTemplate::processRequest()
     */
    public function processRequest($page_alias = NULL, $widget_id = NULL, $action_alias = NULL, $disable_error_handling = false)
    {
        // Look for basic request parameters
        $called_in_resource_alias = $page_alias ? $page_alias : $this->getRequestPageAlias();
        $trigger_widget_id = $widget_id ? $widget_id : $this->getRequestWidgetId();
        $action_alias = $action_alias ? $action_alias : $this->getRequestActionAlias();
        
        $object_id = $this->getRequestObjectId();
        
        // TODO #api-v4
        if ($this->getSubrequestId())
            $this->getWorkbench()->context()->getScopeRequest()->setSubrequestId($this->getSubrequestId());
        
        /*
        if ($called_in_resource_alias) {
            try {
                $this->getWorkbench()->ui()->setPageCurrent($this->getWorkbench()->ui()->getPage($called_in_resource_alias));
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
        }*/
        
        // Do the actual processing
        try {
            if ($called_in_resource_alias) {
                if ($trigger_widget_id) {
                    $widget = $this->getWorkbench()->ui()->getPage($called_in_resource_alias)->getWidget($trigger_widget_id);
                } else {
                    $widget = $this->getWorkbench()->ui()->getPage($called_in_resource_alias)->getWidgetRoot();
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
                    $action->setInputDataSheet($action->getInputDataSheet()->importRows($data_sheet));
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
                $this->setResponseFromError($e, UiPageFactory::create($this->getWorkbench()->ui(), ''));
            } else {
                throw $e;
            }
        }
        
        return $this->getResponse();
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

    public function getRequestPageAlias()
    {
        if (! $this->request_page_alias) {
            $this->request_page_alias = ! is_null($this->getWorkbench()->getRequestParams()['resource']) ? $this->getWorkbench()->getRequestParams()['resource'] : NULL;
            $this->getWorkbench()->removeRequestParam('resource');
        }
        return $this->request_page_alias;
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
     * Returns the data type formatter for the given data type.
     * 
     * @param DataTypeInterface $dataType
     * @return JsDataTypeFormatterInterface
     */
    public function getDataTypeFormatter(DataTypeInterface $dataType)
    {
        switch (true) {
            case $dataType instanceof EnumDataTypeInterface: return new JsEnumFormatter($dataType);
            case $dataType instanceof NumberDataType: return new JsNumberFormatter($dataType);
            case $dataType instanceof DateDataType: return new JsDateFormatter($dataType);
            case $dataType instanceof BooleanDataType: return new JsBooleanFormatter($dataType);
        }
        return new JsTransparentFormatter($dataType);
    }
    
    protected function getTaskReaderMiddleware($attributeName = 'task') : MiddlewareInterface
    {
        $reader = parent::getTaskReaderMiddleware($attributeName);
        
        $reader->setParamNameAction('action');
        $reader->setParamNameObject('object');
        $reader->setParamNamePage('resource');
        $reader->setParamNameWidget('widget');
        $reader->setParamNameData('data');
        $reader->setParamNamePrefill('action');
        
        return $reader;
    }
}
?>