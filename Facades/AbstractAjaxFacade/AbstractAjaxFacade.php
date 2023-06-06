<?php
namespace exface\Core\Facades\AbstractAjaxFacade;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsNumberFormatter;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsDateFormatter;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsTransparentFormatter;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsEnumFormatter;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsBooleanFormatter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;
use exface\Core\Interfaces\Tasks\ResultUriInterface;
use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Facades\HttpFileServerFacade;
use exface\Core\Facades\AbstractHttpFacade\Middleware\TaskUrlParamReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\DataUrlParamReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\QuickSearchUrlParamReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\PrefixedFilterUrlParamsReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\ContextBarApi;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\Widget\OnRemoveEvent;
use exface\Core\Interfaces\Tasks\ResultTextContentInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsTimeFormatter;
use exface\Core\Interfaces\Widgets\CustomWidgetInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpTaskFacade;
use Psr\Http\Message\RequestInterface;
use exface\Core\Facades\AbstractAjaxFacade\Templates\FacadePageTemplateRenderer;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\CommonLogic\Tasks\ResultRedirect;
use function GuzzleHttp\Psr7\uri_for;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\Login;
use exface\Core\Widgets\LoginPrompt;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Exceptions\AuthorizationExceptionInterface;
use exface\Core\Contexts\DebugContext;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Facades\AbstractAjaxFacade\Formatters\JsStringFormatter;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\DataTypes\HtmlDataType;
use exface\Core\Exceptions\Security\AuthenticationIncompleteError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractAjaxFacade extends AbstractHttpTaskFacade implements HtmlPageFacadeInterface
{
    private $elements = [];
    
    /**
     * [ widget_type => qualified class name]
     * @var array
     */
    private $classes_by_widget_type = [];

    private $class_prefix = '';

    private $class_namespace = '';
    
    private $data_type_formatters = [];
    
    private $pageTemplateFilePath = null;
    
    private $sematic_colors = [];
    
    private $fileVersionHash = null;
    
    public function __construct(FacadeSelectorInterface $selector)
    {
        parent::__construct($selector);
        $this->getWorkbench()->eventManager()->addListener(OnRemoveEvent::getEventName(), function (OnRemoveEvent $event) {
            $this->removeElement($event->getWidget());
        });
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
        $instance = $this->getElement($widget);
        $result .= implode("\n", array_unique($instance->buildHtmlHeadTags()));
        return $result;
    }
    
    /**
     * Renders a HTML tag for the <head> to represent the given error.
     * 
     * By default, this produces an alert with error details. If a facade is capable to display
     * a nicer message even if an error occurred when rendering the page head, this method can
     * be overridden to render that nicer message.
     * 
     * @param \Throwable $e
     * @return string
     */
    protected function buildHtmlHeadError(\Throwable $e) : string
    {
        if ($e instanceof ExceptionInterface) {
            $logHint = '. See log ID ' . $e->getId();
        } else {
            $logHint = '';
        }
        
        $file = addslashes($e->getFile());
        $msg = addslashes($e->getMessage());
        
        return <<<HTML

<script type="text/javascript">
    (function(){
        alert("Error rendering HTML headers{$logHint}:\\n\\n{$msg}\\n\\nIn file {$file} on line {$e->getLine()}.");
    })();
</script>

HTML;
    }

    /**
     * Creates a facade element for a given ExFace widget.
     * Elements are cached within the facade engine, so multiple calls to this method do
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
        $elem_class = $this->getElementClassForWidget($widget);
        $instance = new $elem_class($widget, $this);
        
        if ($widget instanceof CustomWidgetInterface) {
            $instance = $widget->createFacadeElement($this, $instance);
        }
        
        return $instance;
    }

    /**
     * 
     * @param AbstractWidget $widget
     * @return AbstractAjaxFacade
     */
    public function removeElement(WidgetInterface $widget)
    {
        unset($this->elements[$widget->getPage()->getAliasWithNamespace()][$widget->getId()]);
        return $this;
    }

    /**
     * 
     * @param AbstractJqueryElement $element
     * @return \exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade
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
    protected function getElementClassForWidget(WidgetInterface $widget) : string
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
     * Creates a facade element for a widget of the give resource, specified by the
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
     * @param UiPageInterface|UiPageSelectorInterface|string $pageOrSelectorOrString
     * @param string $url_params
     * @return string
     */
    public function buildUrlToPage($pageOrSelectorOrString, string $url_params = '') : string
    {
        switch (true) {
            case $pageOrSelectorOrString instanceof UiPageInterface:
                $alias = $pageOrSelectorOrString->getAliasWithNamespace();
                break;
            case is_string($pageOrSelectorOrString):
                $pageOrSelectorOrString = new UiPageSelector($this->getWorkbench(), $pageOrSelectorOrString);
                // Don't break here: continue with the selector-logic
            case $pageOrSelectorOrString instanceof UiPageSelectorInterface:
                if ($pageOrSelectorOrString->isAlias()) {
                    $alias = $pageOrSelectorOrString->toString();
                } else {
                    $alias = UiPageFactory::createFromModel($this->getWorkbench(), $pageOrSelectorOrString)->getAliasWithNamespace();
                }
                break;
            default:
                throw new InvalidArgumentException('Cannot create URL for page "' . $pageOrSelectorOrString . '": invalid type of input!');
        } 
        $url = mb_strtolower($alias) . $this->getPageFileExtension();
        $params = ltrim($url_params, "?");
        return $url . ($params ? '?' . $params : '');
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
     * @return \exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade
     */
    protected function setClassPrefix($value) : AbstractAjaxFacade
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
            case $dataType instanceof StringDataType: return new JsStringFormatter($dataType);
        }
        return new JsTransparentFormatter($dataType);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getMiddleware()
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
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponseFromTaskResult()
     */
    protected function createResponseFromTaskResult(ServerRequestInterface $request, ResultInterface $result) : ResponseInterface
    {
        /* @var $headers array [header_name => array_of_values] */
        $headers = $this->buildHeadersCommon();
        if ($this->isRequestAjax($request)) {
            $headers = array_merge($headers, $this->buildHeadersForAjax());
        } else {
            $headers = array_merge($headers, $this->buildHeadersForHtml());
        }
        
        /* @var $status_code int */
        $status_code = $result->getResponseCode();
        
        if ($result->isEmpty()) {
            return new Response($status_code, $headers);
        }
        
        switch (true) {
            case $result instanceof ResultDataInterface:
                $json = $this->buildResponseData($result->getData(), ($result->getTask()->isTriggeredByWidget() ? $result->getTask()->getWidgetTriggeredBy() : null));
                $json["success"] = $result->getMessage();
                break;
                
            case $result instanceof ResultWidgetInterface:
                $widget = $result->getWidget();
                switch (true) {
                    case $this->isRequestFrontend($request) === true:
                        $body = $this->buildHtmlPage($widget);
                        break;
                    //case $this->isRequestAjax($request) === true:
                    default:
                        $body = $this->buildHtmlHead($widget) . "\n" . $this->buildHtmlBody($widget);
                }
                break;
                
            case $result instanceof ResultFileInterface:
                $url = HttpFileServerFacade::buildUrlToDownloadFile($this->getWorkbench(), $result->getPathAbsolute());
                $message = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.DOWNLOADFILE.RESULT_WITH_LINK', ['%url%' => $url]);
                // Use extra response property "download" here instead of redirect, because if facades
                // use simple redirects for downloads, this won't work for text-files or unknown mime types
                $json = [
                    "success" => $message,
                    "download" => $url
                ];
                break;   
                
            case $result instanceof ResultUriInterface:
                if ($result instanceof ResultRedirect && $result->hasTargetPage()) {
                    $uri = uri_for($this->buildUrlToPage($result->getTargetPageSelector()));
                } else {
                    $uri = $result->getUri();
                }
                
                if ($result->isDownload()) {
                    $json = [
                        "success" => $result->getMessage() ? $result->getMessage() : $this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.DOWNLOADFILE.RESULT_WITH_LINK', ['%url%' => $url]),
                        "download" => $uri->__toString()
                    ];
                } else {
                    if ($result->isOpenInNewWindow()) {
                        $uri = $uri->withQuery($uri->getQuery() ."target=_blank");
                    }
                    
                    $json = [
                        "success" => $result->getMessage(),
                        "redirect" => $uri->__toString()
                    ];
                }
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
        
        if (! empty($json)) {
            if ($result->isContextModified() && $result->getTask()->isTriggeredOnPage()) {
                $context_bar = $result->getTask()->getPageTriggeredOn()->getContextBar();
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
     * Returns an array of data rows with sanitized values, that are safe to put publish as HTML
     * 
     * @param DataSheetInterface $data_sheet
     * @return array
     */
    protected function buildResponseDataRowsSanitized(DataSheetInterface $data_sheet, bool $decrypt = true, $forceHtmlEntities = true) : array
    {
        $rows = $decrypt ? $data_sheet->getRowsDecrypted() : $data_sheet->getRows();
        if (empty($rows)) {
            return $rows;
        }
        
        foreach ($data_sheet->getColumns() as $col) {
            $colName = $col->getName();
            $colType = $col->getDataType();
            switch (true) {
                case $colType instanceof HtmlDataType:
                    // FIXME #xss-protection sanitize HTML here!
                    break;
                case $colType instanceof JsonDataType:
                    // FIXME #xss-protection sanitize JSON here!
                    break;
                case $colType instanceof StringDataType:
                    if ($forceHtmlEntities) {
                        foreach ($rows as $i => $row) {
                            $val = $row[$colName];
                            if ($val !== null && $val !== '') {
                                $rows[$i][$colName] = htmlspecialchars($val, ENT_NOQUOTES);
                            }
                        }
                    }
                    break;
            }
        }
        
        return $rows;
    }
    
    /**
     *
     * @param array|\stdClass $serializable_data
     * @throws FacadeOutputError
     * @return string
     */
    public function encodeData($serializable_data)
    {        
        // Encode the response object to JSON converting <, > and " to HEX-values (e.g. \u003C). Without that conversion
        // there might be trouble with HTML in the responses (e.g. jEasyUI will break it when parsing the response)
        $result = json_encode($serializable_data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT);
        if (! $result) {
            throw new FacadeOutputError('Error encoding data: ' . json_last_error() . ' ' . json_last_error_msg());
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpTaskFacade::createResponseFromError()
     */
    public function createResponseFromError(\Throwable $exception, ServerRequestInterface $request = null, UiPageInterface $page = null) : ResponseInterface 
    {
        if ($exception instanceof ExceptionInterface) {
            $status_code = is_numeric($exception->getStatusCode()) ? $exception->getStatusCode() : 500;
        } else {
            $status_code = 500;
        }
        
        // If the error goes back to failed authorization (HTTP status 401), see if a login-prompt should be shown.
        if ($exception instanceof ExceptionInterface && $status_code == 401) { 
            // Don't show the login-prompt if the request is a login-action itself. In this case,
            // it originates from a login form, so we don't need another one.
            /* @var $task \exface\Core\CommonLogic\Tasks\HttpTask */
            $task = $request !== null ? $request->getAttribute($this->getRequestAttributeForTask()) : null;
            if (! $task 
            || ($task->getActionSelector() && ! (ActionFactory::create($task->getActionSelector()) instanceof Login))
            || $exception instanceof AuthenticationIncompleteError) {
                // See if the method createResponseUnauthorized() can handle this exception.
                // If not, continue with the regular error handling.
                $response = $this->createResponseUnauthorized($exception, $request, $page);
                if ($response !== null) {
                    return $response;
                }
            }
        }
        
        $headers = $this->buildHeadersCommon();
        $body = '';
        
        switch (true) {
            case $this->isShowingErrorDetails() === true:
            case $exception instanceof AuthorizationExceptionInterface && $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->isAnonymous():
                // If details needed, render a widget
                $body = $this->buildHtmlFromError($exception, $request, $page);
                $headers = array_merge($headers, $this->buildHeadersForHtml());
                $headers['Content-Type'] = ['text/html;charset=utf-8'];
                break;
            default:
                if ($request !== null && $this->isRequestAjax($request)) {
                    // Render error data for AJAX requests, so the JS can interpret it.
                    $body = $this->encodeData($this->buildResponseDataError($exception));
                    $headers = array_merge($headers, $this->buildHeadersForAjax());
                    $headers['Content-Type'] = ['application/json;charset=utf-8'];
                } else {
                    // If we were rendering a widget, return HTML even for non-detail cases
                    $body = $this->buildHtmlFromError($exception, $request, $page);
                    $headers = array_merge($headers, $this->buildHeadersForHtml());
                    $headers['Content-Type'] = ['text/html;charset=utf-8'];
                }
        }
        
        $headers = array_merge($headers, $this->buildHeadersForErrors());
        
        return new Response($status_code, $headers, $body);
    }
    
    /**
     * Renders 401-errors with login-prompts if needed.
     * 
     * By default, the login-prompt is shown whenever the error has an AuthentificationFailedError 
     * exception in it's stack trace. This type of exception has a link to the authentification 
     * provider, that caused the error, so a new login-form for this provider can be rendered.
     * 
     * Override this method, if a specific facade needs special treatment for unauthorized-exceptions.
     * 
     * @param \Throwable $exception
     * @param ServerRequestInterface|NULL $request
     * @param UiPageInterface $page|NULL
     * @return ResponseInterface|NULL
     */
    protected function createResponseUnauthorized(\Throwable $exception, ServerRequestInterface $request = null, UiPageInterface $page = null) : ?ResponseInterface
    {
        $page = ! is_null($page) ? $page : UiPageFactory::createEmpty($this->getWorkbench());
        
        try {
            $loginPrompt = LoginPrompt::createFromException($page, $exception);
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::ERROR);
            return null;
        }
        
        if ($request !== null && $this->isRequestAjax($request)) {
            $responseBody = $this->buildHtmlHead($loginPrompt) . "\n" . $this->buildHtmlBody($loginPrompt);
        } else {
            $responseBody = $this->buildHtmlPage($loginPrompt, $this->getPageTemplateFilePathForUnauthorized());
        }
        
        $headers = array_merge(
            $this->buildHeadersCommon(), 
            $this->buildHeadersForHtml(),
            $this->buildHeadersForErrors()
        );
        
        return new Response($exception instanceof AuthorizationExceptionInterface ? $exception->getStatusCode() : 401, $headers, $responseBody);
    }
    
    /**
     * Returns TRUE if error detail widgets are to be shown.
     * 
     * @return bool
     */
    protected function isShowingErrorDetails() : bool
    {
        return $this->getWorkbench()->getContext()->getScopeWindow()->hasContext(DebugContext::class);
    }
    
    /**
     * Renders the given exception as HTML widget.
     * 
     * @param ServerRequestInterface $request
     * @param \Throwable $exception
     * @param UiPageInterface $page
     * @throws RuntimeException
     * @return string
     */
    protected function buildHtmlFromError(\Throwable $exception, ServerRequestInterface $request = null, UiPageInterface $page = null) : string 
    {
        $page = ! is_null($page) ? $page : UiPageFactory::createEmpty($this->getWorkbench());
        $body = '';
        
        try {
            $debug_widget = $exception->createWidget($page);
            switch (true) {
                case $request === null:
                case $this->isRequestFrontend($request) === true:
                    $body = $this->buildHtmlPage($debug_widget);
                    break;
                // case $this->isRequestAjax($request) === true:
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
        
        return $body;
    }
    
    /**
     * Returns a serializable version of the given exception.
     * 
     * @param \Throwable $exception
     * @return mixed
     */
    public function buildResponseDataError(\Throwable $exception, bool $forceHtmlEntities = true)
    {
        $error = [
            'code' => $exception->getCode(),
            'message' => $forceHtmlEntities ? htmlspecialchars($exception->getMessage()) : $exception->getMessage(),
        ];
        
        if ($exception instanceof ExceptionInterface) {
            $error['code'] = $exception->getAlias();
            $error['logid'] = $exception->getId();
            
            $wb = $this->getWorkbench();
            $msg = $exception->getMessageModel($wb);
            $error['title'] = $forceHtmlEntities ? htmlspecialchars($msg->getTitle()) : $msg->getTitle();
            $error['hint'] = $forceHtmlEntities ? htmlspecialchars($msg->getHint()) : $msg->getHint();
            $error['description'] = $forceHtmlEntities ? htmlspecialchars($msg->getDescription()) : $msg->getDescription();
            $error['type'] = $msg->getType();
        }
        
        return [
            'error' => $error
        ];
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
    
    public function buildUrlToVendorFile(string $pathInVendorFolder) : string
    {
        return 'vendor/' . $pathInVendorFolder;
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
            return $this->buildUrlToVendorFile($path);
        }
    }
    
    public function getFileVersionHash(string $filename = null) : string
    {
        return $this->fileVersionHash ?? 'v' . str_replace(['-', ' ', ':'], '', $this->getWorkbench()->getContext()->getScopeInstallation()->getVariable('last_metamodel_install'));
    }
    
    /**
     * Returns the common script/css tags to include in the <head> of the HTML page.
     * 
     * By default, the built-in JS-library exfTools is always included
     * 
     * @return string[]
     */
    protected function buildHtmlHeadCommonIncludes() : array
    {
        $includes = JsDateFormatter::buildHtmlHeadMomentIncludes($this);
        $includes[] = '<script type="text/javascript" src="' . $this->buildUrlToSource('LIBS.EXFTOOLS.JS') . '"></script>';
        return $includes;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::buildHeadersCommon()
     */
    protected function buildHeadersCommon() : array
    {
        return array_filter($this->getConfig()->getOption('FACADE.HEADERS.COMMON')->toArray());
    }
    
    /**
     * 
     * @return array
     */
    protected function buildHeadersForHtml() : array
    {
        $headers = array_filter($this->getConfig()->getOption('FACADE.HEADERS.HTML')->toArray());
                
        $workbenchHosts = [];
        foreach ($this->getWorkbench()->getConfig()->getOption('SERVER.BASE_URLS') as $url) {
            $host = UrlDataType::findHost($url);
            if ($host) {
                $workbenchHosts[] = $host;
            }
        }
        
        $cspString = '';
        foreach ($this->getConfig()->getOptionGroup('FACADE.HEADERS.CONTENT_SECURITY_POLICY', true) as $directive => $values) {
            // Skip the directive if the config option has no value (thus removing the directive)
            if (empty($values)) {
                continue;
            }
            // Otherwise add this directive to the policy
            $directive = str_replace('_', '-', mb_strtolower($directive));
            if ($directive === 'flags') {
                $cspString .= $values . ' ; ';
            } else {
                // Add the hosts of the workbench base URLs to every directive to aviod issues
                // with workbenches behind reverse proxies, where the same workbench can be
                // reached through different URLs.
                $cspString .= $directive . ' ' . implode(' ', $workbenchHosts) . ' ' . $values . ' ; ';
            }
        }
        
        return array_merge(['Content-Security-Policy' => $cspString], $headers);
    }
    
    /**
     * 
     * @return array
     */
    protected function buildHeadersForAjax() : array
    {
        $headers = $this->getConfig()->getOption('FACADE.HEADERS.AJAX')->toArray();
        return array_filter($headers);
    }
    
    /**
     *
     * @return array
     */
    protected function buildHeadersForErrors() : array
    {
        return [
            'Cache-Control' => ['no-cache', 'no-store', 'must-revalidate'],
            'Pragma' => ['no-cache'],
            'Expires' => [0]
        ];
    }
    
    protected function buildHtmlHeadIcons() : array
    {
        $tags = [];
        $icons = $this->getWorkbench()->getConfig()->getOption('SERVER.ICONS');
        if (! $icons) {
            return $tags;
        }
        if (! ($icons instanceof UxonObject)) {
            $icons = new UxonObject([$icons]);
        }
        foreach ($icons->getPropertiesAll() as $icon) {
            if (($icon instanceof UxonObject) && $src = $icon->getProperty('src')) {
                $props = '';
                
                $rel = $icon->getProperty('rel');
                $props .= ' rel="' . ($rel ? $rel : 'icon') . '"';
                
                $type = $icon->getProperty('type');
                $props .= $type ? ' type="' . $type . '"' : '';
                
                $sizes = $icon->getProperty('sizes');
                $props .= $sizes ? ' sizes="' . $sizes . '"' : '';
                
                $tags[] = '<link ' . $props . ' href="' . $src . '">';
            } elseif (is_string($icon) && $icon !== '') {
                $tags[] = '<link rel="shortcut icon" href="' . $icon . '">';
            }
        }
        return $tags;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault() : string
    {
        return $this->getConfig()->getOption('FACADE.AJAX.BASE_URL');
    }
    
    /**
     * Returns the path to the default template file to render a page (absolute or relative to the vendor folder)
     * 
     * @return string
     */
    protected abstract function getPageTemplateFilePathDefault() : string;
    
    /**
     * Returns the path to the unauthorized-page template file (absolute or relative to the vendor folder)
     *
     * @return string
     */
    protected function getPageTemplateFilePathForUnauthorized() : string
    {
        return $this->getPageTemplateFilePathDefault();
    }
    
    /**
     * Returns the path to the unauthorized-page template file (absolute or relative to the vendor folder)
     *
     * @return string
     */
    protected function getPageTemplateFilePathForErrors() : string
    {
        return $this->getPageTemplateFilePathDefault();
    }
    
    /**
     *
     * @return string
     */
    protected function getPageTemplateFilePath() : string
    {
        if (! $path = $this->pageTemplateFilePath) {
            return $this->getPageTemplateFilePathDefault();
        }
        return $path;
    }
    
    /**
     * Use a specific template file to render pages.
     * 
     * The path can either be absolute or relative to the `vendor` folder.
     * 
     * @uxon-property page_template_file_path
     * @uxon-type string
     * 
     * @param string $value
     * @return AbstractAjaxFacade
     */
    public function setPageTemplateFilePath(string $value) : AbstractAjaxFacade
    {
        $this->pageTemplateFilePath = $value;
        return $this;
    }
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param string $pageTemplateFilePath
     * @return string
     */
    protected function buildHtmlPage(WidgetInterface $widget, string $pageTemplateFilePath = null) : string
    {
        $renderer = $this->getTemplateRenderer($widget);
        return $renderer->render($pageTemplateFilePath ?? $this->getPageTemplateFilePath());
    }
    
    /**
     * Instantiates a template renderer for the given widget - override this method to customize the renderer
     * 
     * To add additional placeholders, override this method and call 
     * `$renderer->addPlaceholderResolver()`.
     * 
     * @param WidgetInterface $widget
     * @return FacadePageTemplateRenderer
     */
    protected function getTemplateRenderer(WidgetInterface $widget) : FacadePageTemplateRenderer
    {
        return new FacadePageTemplateRenderer($this, $widget);
    }
    
    /**
     * Returns TRUE if the given request is an AJAX-request, that came over the API.
     * 
     * @return bool
     */
    protected function isRequestAjax(RequestInterface $request) : bool
    {
        return stripos($request->getUri()->getPath(), 'api/') !== false;
    }
    
    /**
     * 
     * @param RequestInterface $request
     * @return bool
     */
    protected function isRequestFrontend(RequestInterface $request) : bool
    {
        return $this->isRequestAjax($request) === false;
    }
    
    /**
     * 
     */
    protected function getPageFileExtension() : string
    {
        return '.html';
    }
    
    public function getIconSets() : array
    {
        try {
            return $this->getConfig()->getOption('ICONS.ICON_SETS');
        } catch (ConfigOptionNotFoundError $e) {
            return ['fa' => 'Font Awesome'];
        }
    }
    
    public function getSemanticColors() : array
    {
        return $this->sematic_colors;
    }
    
    /**
     * CSS color values for each semantic color
     * 
     * @uxon-property semantic_colors
     * @uxon-type object
     * @uxon-template {"~OK": "", "~WARNING": "", "~ERROR"}
     * 
     * @param UxonObject|array $keyToHtmlColorArray
     * @throws FacadeLogicError
     * @return AbstractAjaxFacade
     */
    protected function setSemanticColors($keyToHtmlColorArray) : AbstractAjaxFacade
    {
        switch (true) {
            case $keyToHtmlColorArray instanceof UxonObject:
                $array = $keyToHtmlColorArray->toArray();
                break;
            case is_array($keyToHtmlColorArray):
                $array = $keyToHtmlColorArray;
                break;
            default:
                throw new FacadeLogicError('Invalid value for `semantic_colors` in configuration of facade "' . $this->getAliasWithNamespace() . '": expecting an array!');
        }
        $this->sematic_colors = $array;
        return $this;
    }
}