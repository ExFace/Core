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
use exface\Core\Factories\ResultFactory;
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
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\CommonLogic\Tasks\ResultRedirect;
use function GuzzleHttp\Psr7\uri_for;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\Selectors\UserSelector;
use exface\Core\Exceptions\Security\AccessPermissionDeniedError;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\Login;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractAjaxFacade extends AbstractHttpTaskFacade implements HtmlPageFacadeInterface
{
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
    
    private $pageTemplateFilePath = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractFacade\AbstractFacade::init()
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
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::handle()
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
        if ($cacheKey = $request->getAttribute('result_cache_key')) {
            $this->requestIdCache[$cacheKey] = $result;
        }
        
        /* @var $headers array [header_name => array_of_values] */
        $headers = [];
        /* @var $status_code int */
        $status_code = $result->getResponseCode();
        
        if ($result->isEmpty()) {
            return new Response($status_code, $headers);
        }
        
        switch (true) {
            case $result instanceof ResultDataInterface:
                $json = $this->buildResponseData($result->getData(), $result->getTask()->getWidgetTriggeredBy());
                $json["success"] = $result->getMessage();
                $headers = array_merge($headers, $this->buildHeadersAccessControl());
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
                $url = HttpFileServerFacade::buildUrlForDownload($this->getWorkbench(), $result->getPathAbsolute());
                $message = 'Download ready. If it does not start automatically, click <a href="' . $url . '" download>here</a>.';
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
                
                if ($result->getOpenInNewWindow()) {
                    $uri = $uri->withQuery($uri->getQuery() ."target=_blank");
                }
                
                $json = [
                    "success" => $result->getMessage(),
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
     *
     * @param array|\stdClass $serializable_data
     * @param string $add_extras
     * @throws FacadeOutputError
     * @return string
     */
    public function encodeData($serializable_data)
    {        
        $result = json_encode($serializable_data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_QUOT);
        if (! $result) {
            throw new FacadeOutputError('Error encoding data: ' . json_last_error() . ' ' . json_last_error_msg());
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponseFromError()
     */
    public function createResponseFromError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null) : ResponseInterface 
    {
        if ($exception instanceof ExceptionInterface) {
            $status_code = is_numeric($exception->getStatusCode()) ? $exception->getStatusCode() : 500;
        } else {
            $status_code = 500;
        }
        
        // If the user is not logged on an the permission is denied, wrap the error in an
        // AuthenticationFailedError to tell the facade to handle it as an unauthorized-error
        if ($exception instanceof AccessPermissionDeniedError && $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->isAnonymous()) {
            $exception = new AuthenticationFailedError($this->getWorkbench()->getSecurity(), $exception->getMessage(), null, $exception);
        }
        
        // If the error goes back to failed authorization (HTTP status 401), see if a login-prompt should be shown.
        if ($exception instanceof ExceptionInterface && $exception->getStatusCode() == 401) {
            // Don't show the login-prompt if the request is a login-action itself. In this case,
            // it originates from a login form, so we don't need another one.
            /* @var $task \exface\Core\CommonLogic\Tasks\HttpTask */
            $task = $request->getAttribute($this->getRequestAttributeForTask());
            if ($task && ! (ActionFactory::createEmpty($task->getActionSelector()) instanceof Login)) {
                // See if the method createResponseUnauthorized() can handle this exception.
                // If not, continue with the regular error handling.
                $response = $this->createResponseUnauthorized($request, $exception, $page);
                if ($response !== null) {
                    return $response;
                }
            }
        }
        
        $headers = [];
        $body = '';
        
        switch (true) {
            case $this->isShowingErrorDetails() === true:
            case $exception instanceof AccessPermissionDeniedError && $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->isAnonymous():
                // If details needed, render a widget
                $body = $this->buildHtmlFromError($request, $exception, $page);
                $headers['Content-Type'] = ['text/html;charset=utf-8'];
                break;
            default:
                if ($this->isRequestAjax($request)) {
                    // Render error data for AJAX requests, so the JS can interpret it.
                    $body = $this->encodeData($this->buildResponseDataError($exception));
                    $headers['Content-Type'] = ['application/json;charset=utf-8'];
                } else {
                    // If we were rendering a widget, return HTML even for non-detail cases
                    $body = $this->buildHtmlFromError($request, $exception, $page);
                    $headers['Content-Type'] = ['text/html;charset=utf-8'];
                }
        }     
        
        $this->getWorkbench()->getLogger()->logException($exception);
        
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
     * @param UiPageInterface $page
     * @return ResponseInterface
     */
    protected function createResponseUnauthorized(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null) : ?ResponseInterface
    {
        $page = ! is_null($page) ? $page : UiPageFactory::createEmpty($this->getWorkbench());
        
        $authErr = $exception;
        while (! ($authErr instanceof AuthenticationFailedError)) {
            $authErr = $authErr->getPrevious();
        }
        if ($authErr !== null) {
            $this->getWorkbench()->getLogger()->logException($exception);
            /* @var $loginPrompt \exface\Core\Widgets\LoginPrompt */
            $loginPrompt = WidgetFactory::create($page, 'LoginPrompt');
            $loginPrompt->setObjectAlias('exface.Core.LOGIN_DATA');
            $loginFormCreated = false;
            
            $provider = $authErr->getAuthenticationProvider();
            if ($provider instanceof DataConnectionInterface) {
                // Saving connection credentials is only possible if a user is authenticated!
                if ($this->getWorkbench()->getSecurity()->getAuthenticatedToken()->isAnonymous() === false) {
                    $loginPrompt = $provider->createLoginWidget($loginPrompt, true, new UserSelector($this->getWorkbench(), $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername()));
                    $loginPrompt->setCaption($this->getWorkbench()->getCoreApp()->getTranslator()->translate('SECURITY.CONNECTIONS.LOGIN_TITLE'));
                    $loginPrompt->getMessageList()->addError($authErr->getMessage());
                    $loginFormCreated = true;
                }
            } else {
                $loginPrompt = $provider->createLoginWidget($loginPrompt);
                $loginPrompt->getMessageList()->addError($authErr->getMessage());
                $loginFormCreated = true;
            }
                  
            if ($loginFormCreated === true) {
                $renderer = new FacadePageTemplateRenderer($this, $this->getPageTemplateFilePathForUnauthorized(), $loginPrompt);
                $requestBody = $renderer->render();
                return new Response(401, $this->buildHeadersAccessControl(), $requestBody);
            }
        }
        return null;
    }
    
    /**
     * Returns TRUE if error detail widgets are to be shown.
     * 
     * @return bool
     */
    protected function isShowingErrorDetails() : bool
    {
        try {
            $onlyAdmins = BooleanDataType::cast($this->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_ERROR_DETAILS_TO_ADMINS_ONLY'));
            $isAdmin = $this->getWorkbench()->getContext()->getScopeUser()->getUserCurrent()->isUserAdmin();
            return  ! $onlyAdmins || ($onlyAdmins && $isAdmin);
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
            return false;
        }
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
    protected function buildHtmlFromError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null) : string 
    {
        $page = ! is_null($page) ? $page : UiPageFactory::createEmpty($this->getWorkbench());
        $body = '';
        
        try {
            $debug_widget = $exception->createWidget($page);
            switch (true) {
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
        
        // If using the cache, we can store the error widget in that cache to make sure it is shown.
        // Otherwise if the error only occurs in certain modes, it might never get really shown!
        if ($cacheKey = $request->getAttribute('result_cache_key')) {
            $task = $request->getAttribute($this->getRequestAttributeForTask());
            $this->requestIdCache[$cacheKey] = ResultFactory::createWidgetResult($task, $debug_widget);
        }
        return $body;
    }
    
    /**
     * Returns a serializable version of the given exception.
     * 
     * @param \Throwable $exception
     * @return mixed
     */
    public function buildResponseDataError(\Throwable $exception)
    {
        $error = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
        
        if ($exception instanceof ExceptionInterface) {
            $wb = $this->getWorkbench();
            $error['code'] = $exception->getAlias();
            $error['logid'] = $exception->getId();
            $error['title'] = $exception->getMessageTitle($wb);
            $error['hint'] = $exception->getMessageHint($wb);
            $error['description'] = $exception->getMessageDescription($wb);
            $error['type'] = $exception->getMessageType($wb);
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
    
    protected function buildUrlToVendorFile(string $pathInVendorFolder) : string
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
    
    protected function buildHtmlHeadCommonIncludes() : array
    {
        return [];
    }
    
    /**
     * Returns an array of allowed origins for AJAX requests to the facade.
     * 
     * The core config key FACADES.AJAX.ACCESS_CONTROL_ALLOW_ORIGIN provides basic configuration
     * for all AJAX facades. Facades are free to use their own configuration though - please
     * refer to the documentation of the facade used.
     * 
     * @return string[]
     */
    protected function buildHeadersAccessControl() : array
    {
        $headers = $this->getConfig()->getOption('FACADE.AJAX.ACCESS_CONTROL_HEADERS')->toArray();
        return array_filter($headers);
    }
    
    protected function buildHtmlHeadIcons() : array
    {
        $tags = [];
        $icons = $this->getWorkbench()->getConfig()->getOption('SERVER.ICONS')->toArray();
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
    
    protected function buildHtmlPage(WidgetInterface $widget) : string
    {
        $renderer = new FacadePageTemplateRenderer($this, $this->getPageTemplateFilePath(), $widget);
        return $renderer->render();
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
}