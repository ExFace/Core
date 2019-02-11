<?php
namespace exface\Core\Templates\AbstractAjaxTemplate;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;
use exface\Core\Interfaces\Tasks\ResultUriInterface;
use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Exceptions\Templates\TemplateOutputError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Templates\HttpFileServerTemplate;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\TaskUrlParamReader;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\DataUrlParamReader;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\QuickSearchUrlParamReader;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\PrefixedFilterUrlParamsReader;
use exface\Core\Factories\ResultFactory;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\ContextBarApi;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Widget\OnRemoveEvent;
use exface\Core\Interfaces\Tasks\ResultTextContentInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Templates\AbstractAjaxTemplate\Formatters\JsTimeFormatter;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractAjaxTemplate extends AbstractHttpTemplate
{
    const MODE_HEAD = 'HEAD';
    const MODE_BODY = 'BODY';
    const MODE_FULL = '';

    private $elements = [];
    
    private $requestIdCache = [];
    
    /**
     * [ widget_type => qualified class name]
     * @var array
     */
    private $classes_by_widget_type = [];

    private $class_prefix = '';

    private $class_namespace = '';
    
    private $data_type_formatters = [];

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Templates\AbstractTemplate\AbstractTemplate::init()
     */
    protected function init()
    {
        parent::init();
        $this->getWorkbench()->eventManager()->addListener(OnRemoveEvent::getEventName(), function (OnRemoveEvent $event) {
            $this->removeElement($event->getWidget());
        });
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::handle()
     */
    public function handle(ServerRequestInterface $request, $useCacheKey = null) : ResponseInterface
    {
        if (! is_null($useCacheKey)) {
            $request = $request->withAttribute('result_cache_key', $useCacheKey);
        }
        
        if ($cache = $this->requestIdCache[$request->getAttribute('result_cache_key')]) {
            if ($cache instanceof ResultInterface) {
                return $this->createResponseFromTaskResult($request, $cache);
            }
        }
        
        return parent::handle($request);
    }

    /**
     * Returns the HTML/JS-code for the given widget to be placed in the BODY of the page
     * 
     * @param WidgetInterface $widget
     * @return string
     */
    public function buildHtmlBody(WidgetInterface $widget)
    {
        $output = $this->buildHtml($widget);
        $js = $this->buildJs($widget);
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
    public function buildJs(\exface\Core\Widgets\AbstractWidget $widget)
    {
        $instance = $this->getElement($widget);
        return $instance->buildJs();
    }

    /**
     * Generates the HTML for a given Widget
     *
     * @param WidgetInterface $widget            
     */
    public function buildHtml(WidgetInterface $widget)
    {
        $instance = $this->getElement($widget);
        return $instance->buildHtml();
    }

    /**
     * Returns the HTML/JS-code for the given widget to be placed in the BODY of the page
     *
     * @param WidgetInterface $widget
     * 
     * @return string
     */
    public function buildHtmlHead(WidgetInterface $widget, $includeCommonLibs = false)
    {
        $result = '';
        if ($includeCommonLibs) {
            $result .= implode("\n", $this->buildHtmlHeadCommonIncludes());
        }
        try {
            $instance = $this->getElement($widget);
            $result .= implode("\n", array_unique($instance->buildHtmlHeadTags()));
        } catch (ErrorExceptionInterface $e) {
            // TODO Is there a way to display errors in the header nicely?
            // Maybe print the exception in plain text within a comment and add JavaScript to display a warning?
            $this->getWorkbench()->getLogger()->logException($e);
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
    public function getElement(\exface\Core\Widgets\AbstractWidget $widget)
    {
        if (empty($this->elements[$widget->getPage()->getAliasWithNamespace()]) || empty($this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()])) {
            $instance = $this->createElement($widget);
            // $this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()] = $instance;
        }
        
        return $this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()];
    }
    
    /**
     * 
     * @param WidgetInterface $widget
     * @return AbstractJqueryElement
     */
    protected function createElement(WidgetInterface $widget) : AbstractJqueryElement
    {
        $elem_class = $this->getClass($widget);
        $instance = new $elem_class($widget, $this);
        return $instance;
    }

    /**
     * 
     * @param AbstractWidget $widget
     * @return AbstractAjaxTemplate
     */
    public function removeElement(WidgetInterface $widget)
    {
        unset($this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()]);
        return $this;
    }

    /**
     * 
     * @param AbstractJqueryElement $element
     * @return \exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate
     */
    public function registerElement(AbstractJqueryElement $element)
    {
        $this->elements[$element->getWidget()->getPage()->getAliasWithNamespace()][$element->getWidget()->getId()] = $element;
        return $this;
    }

    /**
     * 
     * @param WidgetInterface $widget
     * @return string
     */
    protected function getClass(WidgetInterface $widget) : string
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

    /**
     * 
     * @param string $page_or_id_or_alias
     * @param string $url_params
     * @return string
     */
    public function buildUrlToPage($page_or_id_or_alias, $url_params = '')
    {
        return $this->getWorkbench()->getCMS()->buildUrlToPage($page_or_id_or_alias, $url_params);
    }

    /**
     * 
     * @return string
     */
    protected function getClassPrefix() : string
    {
        return $this->class_prefix;
    }

    /**
     * 
     * @param string $value
     * @return \exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate
     */
    protected function setClassPrefix($value) : AbstractAjaxTemplate
    {
        $this->class_prefix = $value;
        return $this;
    }

    /**
     * 
     * @return string
     */
    protected function getClassNamespace() : string
    {
        return $this->class_namespace;
    }

    /**
     * 
     * @param string $value
     */
    protected function setClassNamespace($value)
    {
        $this->class_namespace = $value;
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
            case $dataType instanceof TimeDataType: return new JsTimeFormatter($dataType);
            case $dataType instanceof BooleanDataType: return new JsBooleanFormatter($dataType);
        }
        return new JsTransparentFormatter($dataType);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::getMiddleware()
     */
    protected function getMiddleware() : array
    {
        $middleware = parent::getMiddleware();
        
        $middleware[] = new ContextBarApi($this);
        
        $middleware[] = new TaskUrlParamReader($this, 'action', 'setActionSelector', $this->getRequestAttributeForAction(), $this->getRequestAttributeForTask());
        $middleware[] = new TaskUrlParamReader($this, 'resource', 'setPageSelector', $this->getRequestAttributeForPage(), $this->getRequestAttributeForTask());
        $middleware[] = new TaskUrlParamReader($this, 'object', 'setMetaObjectSelector');
        $middleware[] = new TaskUrlParamReader($this, 'element', 'setWidgetIdTriggeredBy');
        
        $middleware[] = new DataUrlParamReader($this, 'data', 'setInputData');
        $middleware[] = new QuickSearchUrlParamReader($this, 'q', 'getInputData', 'setInputData');
        $middleware[] = new PrefixedFilterUrlParamsReader($this, $this->getUrlFilterPrefix(), 'getInputData', 'setInputData');
        
        $middleware[] = new DataUrlParamReader($this, 'prefill', 'setPrefillData');
        
        return $middleware;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::createResponseFromTaskResult()
     */
    protected function createResponseFromTaskResult(ServerRequestInterface $request, ResultInterface $result) : ResponseInterface
    {
        if ($cacheKey = $request->getAttribute('result_cache_key')) {
            $this->requestIdCache[$cacheKey] = $result;
        }
        
        $mode = $request->getAttribute($this->getRequestAttributeForRenderingMode(), static::MODE_FULL);
        
        /* @var $headers array [header_name => array_of_values] */
        $headers = [];
        /* @var $status_code int */
        $status_code = $result->getResponseCode();
        
        if ($result->isEmpty()) {
            // Empty results must still produce output if rendering HTML HEAD - the common includes must still
            // be there for the template to work.
            if ($mode === static::MODE_HEAD) {
                $body = implode("\n", $this->buildHtmlHeadCommonIncludes());
            } else {
                $body = null;
            }
            return new Response($status_code, $headers, $body);
        }
        
        switch (true) {
            case $result instanceof ResultDataInterface:
                $json = $this->buildResponseData($result->getData(), $result->getTask()->getWidgetTriggeredBy());
                $json["success"] = $result->getMessage();
                $headers = array_merge($headers, $this->buildHeadersAccessControl());
                break;
                
            case $result instanceof ResultWidgetInterface:
                $widget = $result->getWidget();
                switch ($mode) {
                    case static::MODE_HEAD:
                        $body = $this->buildHtmlHead($widget, true);
                        break;
                    case static::MODE_BODY:
                        $body = $this->buildHtmlBody($widget);
                        break;
                    case static::MODE_FULL:
                    default:
                        $body = $this->buildHtmlHead($widget) . "\n" . $this->buildHtmlBody($widget);
                }
                break;
                
            case $result instanceof ResultFileInterface:
                $url = HttpFileServerTemplate::buildUrlForDownload($this->getWorkbench(), $result->getPathAbsolute());
                $message = 'Download ready. If it does not start automatically, click <a href="' . $url . '">here</a>.';
                $json = [
                    "success" => $message,
                    "redirect" => $url
                ];
                break;   
                
            case $result instanceof ResultUriInterface:
                $uri = $result->getUri();
                if ($result->getOpenInNewWindow()) {
                    $uri = $uri->withQuery($uri->getQuery() ."target=_blank");
                }
                $json = [
                    "redirect" => $uri->__toString()
                ];
                break;  
            case $result instanceof ResultTextContentInterface:
                $headers['Content-type'] = $result->getMimeType();
                $body = $result->getContent();
                break;
            default:
                $json['success'] = $result->getMessage();
                if ($result->isUndoable()) {
                    $json['undoable'] = '1';
                }
                // check if result is a properly formed link
                if ($result instanceof ResultUriInterface) {
                    $url = filter_var($result->getUri()->__toString(), FILTER_SANITIZE_STRING);
                    if (substr($url, 0, 4) == 'http') {
                        $json['redirect'] = $url;
                    }
                }
        }
        
        // Encode the response object to JSON converting <, > and " to HEX-values (e.g. \u003C). Without that conversion
        // there might be trouble with HTML in the responses (e.g. jEasyUI will break it when parsing the response)
        if (! empty($json)) {
            if ($result->isContextModified()) {
                $context_bar = $result->getTask()->getWidgetTriggeredBy()->getPage()->getContextBar();
                $json['extras']['ContextBar'] = $this->getElement($context_bar)->buildJsonContextBarUpdate();
            }
            $headers['Content-type'] = ['application/json;charset=utf-8'];
            $body = $this->encodeData($json);
        }
        
        return new Response($status_code, $headers, $body);
    }
    
    /**
     * Returns a serializable version of the given data sheet.
     * 
     * @param DataSheetInterface $data_sheet
     * @param WidgetInterface $widget
     */
    abstract public function buildResponseData(DataSheetInterface $data_sheet, WidgetInterface $widget = null);
    
    /**
     *
     * @param array|\stdClass $serializable_data
     * @param string $add_extras
     * @throws TemplateOutputError
     * @return string
     */
    public function encodeData($serializable_data)
    {        
        $result = json_encode($serializable_data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT);
        if (! $result) {
            throw new TemplateOutputError('Error encoding data: ' . json_last_error() . ' ' . json_last_error_msg());
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::createResponseFromError()
     */
    protected function createResponseFromError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null) : ResponseInterface {
        $page = ! is_null($page) ? $page : UiPageFactory::createEmpty($this->getWorkbench());
        
        $status_code = is_numeric($exception->getStatusCode()) ? $exception->getStatusCode() : 500;
        $headers = [];
        $body = '';
        
        try {
            $debug_widget = $exception->createWidget($page);
            if ($page->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_ERROR_DETAILS_TO_ADMINS_ONLY') && ! $page->getWorkbench()->getContext()->getScopeUser()->getUserCurrent()->isUserAdmin()) {
                foreach ($debug_widget->getTabs() as $nr => $tab) {
                    if ($nr > 0) {
                        $tab->setHidden(true);
                    }
                }
            }
            $mode = $request->getAttribute($this->getRequestAttributeForRenderingMode(), static::MODE_FULL);
            switch ($mode) {
                case static::MODE_HEAD:
                    $body = $this->buildHtmlHead($debug_widget, true);
                    break;
                case static::MODE_BODY:
                    $body = $this->buildHtmlBody($debug_widget);
                    break;
                case static::MODE_FULL:
                default:
                    $body = $this->buildHtmlHead($debug_widget) . "\n" . $this->buildHtmlBody($debug_widget);
            }
        } catch (\Throwable $e) {
            // If anything goes wrong when trying to prettify the original error, drop prettifying
            // and throw the original exception wrapped in a notice about the failed prettification
            $this->getWorkbench()->getLogger()->logException($e);
            $log_id = $e instanceof ExceptionInterface ? $e->getId() : '';
            throw new RuntimeException('Failed to create error report widget: "' . $e->getMessage() . '" - see ' . ($log_id ? 'log ID ' . $log_id : 'logs') . ' for more details! Find the orignal error detail below.', null, $exception);
        }
        
        $this->getWorkbench()->getLogger()->logException($exception);
        
        // If using the cache, we can store the error widget in that cache to make sure it is shown.
        // Otherwise if the error only occurs in certain modes, it might never get really shown!
        if ($cacheKey = $request->getAttribute('result_cache_key')) {
            $task = $request->getAttribute($this->getRequestAttributeForTask());
            $this->requestIdCache[$cacheKey] = ResultFactory::createWidgetResult($task, $debug_widget);
        }
        
        return new Response($status_code, $headers, $body);
    }
    
    /**
     * Returns the prefix to use for inline URL filters.
     * 
     * E.g. if &filter_MY_ATTRIBUTE=xxx is a valid inline URL filter, the prefix is "filter_".
     * 
     * @return string
     */
    public function getUrlFilterPrefix() : string
    {
        return 'filter_';
    }
    
    /**
     * 
     * @param string $configOption
     * @return string
     */
    public function buildUrlToSource(string $configOption) : string
    {
        $path = $this->getConfig()->getOption($configOption);
        if (StringDataType::startsWith($path, 'https:', false) || StringDataType::startsWith($path, 'http:', false)) {
            return $path;
        } else {
            return $this->getWorkbench()->getCMS()->buildUrlToInclude($path);
        }
    }
    
    protected function buildHtmlHeadCommonIncludes() : array
    {
        return [];
    }
    
    /**
     * Returns an array of allowed origins for AJAX requests to the template.
     * 
     * The core config key TEMPLATES.AJAX.ACCESS_CONTROL_ALLOW_ORIGIN provides basic configuration
     * for all AJAX templates. Templates are free to use their own configuration though - please
     * refer to the documentation of the template used.
     * 
     * @return string[]
     */
    protected function buildHeadersAccessControl() : array
    {
        if (! $this->getConfig()->hasOption('TEMPLATES.AJAX.HEADERS.ACCESS_CONTROL')) {
            $headers = $this->getWorkbench()->getConfig()->getOption('TEMPLATES.AJAX.HEADERS.ACCESS_CONTROL')->toArray();
        } else {
            $headers = $this->getConfig()->getOption('TEMPLATES.AJAX.HEADERS.ACCESS_CONTROL')->toArray();
        }
        return array_filter($headers);
    }
    
    protected function buildHtmlHeadIcons() : array
    {
        $tags = [];
        $icons = $this->getWorkbench()->getCMS()->getFavIcons();
        $favicon = $icons[0];
        if (is_array($favicon)) {
            $tags[] = '<link rel="shortcut icon" href="' . $favicon['src'] . '">';
        }
        foreach ($icons as $icon) {
            if ($icon['sizes'] == '192x192') {
                $tags[] = '<link rel="icon" type="' . $icon['type'] . '" href="' . $icon['src'] . '" sizes="192x192">';
                $tags[] = '<link rel="apple-touch-icon" type="' . $icon['type'] . '" href="' . $icon['src'] . '" sizes="192x192">';
            }  
        }
        return $tags;
    }
}
?>