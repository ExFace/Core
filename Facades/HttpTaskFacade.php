<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Interfaces\Tasks\ResultTextContentInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Queue\TaskQueueBroker;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Exceptions\Facades\FacadeRequestParsingError;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpTaskFacade;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\Interfaces\Tasks\ResultUriInterface;
use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\CommonLogic\Tasks\ResultRedirect;
use exface\Core\Facades\AbstractHttpFacade\Middleware\TaskUrlParamReader;
use exface\Core\Facades\AbstractHttpFacade\Middleware\DataUrlParamReader;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Facades\AbstractHttpFacade\Middleware\JsonBodyParser;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;

/**
 * This facade allows to perform actions via HTTP requests (JSON or urlencoded like AbstractAjaxFacade).
 * 
 * It is compatible with facades based on AbstractAjaxFacade in order to allow them to send deferred
 * requests without chaning the syntax - e.g. when flushing offline queues.
 * 
 * Technically, this facade extracts the task from a request and passes it to the taks queue broker.
 * The broker then routes the task to the responsible queue for the actual hanling.
 * 
 * ## Request examples
 * 
 * ### Calling an action via URL - simplest case without data or additional parameters
 * 
 * ```
 * POST path-to-workbench/api/task/yourqueue?action=exface.Core.ClearCache
 * 
 * ```
 * 
 * ### Calling an action with data
 * 
 * ```
 * POST path-to-workbench/api/task/yourqueue
 * Content-Type: application/json
 * 
 *  {
 *      "action": "exface.Core.CreateData",
 *      "data": {
 *          "object_alias": "...",
 *          "rows": [
 *              {"attribute1": "value1", ...}
 *          ]
 *      }
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpTaskFacade extends AbstractHttpTaskFacade
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpTaskFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {        
        try {
            $uri = $request->getUri();
            $path = $uri->getPath();
            $topics = explode('/',substr(StringDataType::substringAfter($path, $this->getUrlRouteDefault()), 1));
            $task = $request->getAttribute($this->getRequestAttributeForTask());
            if ($task === null) {
                throw new FacadeRequestParsingError('Cannot read task from request!');
            }
            $router = new TaskQueueBroker($this->getWorkbench());
            if ($request->hasHeader('X-Client-ID')) {
                $producer = $request->getHeader('X-Client-ID')[0];
            } else {
                $producer = $this->getAliasWithNamespace();
            }
            if ($request->hasHeader('X-Request-ID')) {
                $requestId = $request->getHeader('X-Request-ID')[0];
            } else {
                $requestId = null;
            }
            $result = $router->handle($task, $topics, $producer, $requestId, PhpClassDataType::findClassNameWithoutNamespace($this));
            return $this->createResponseFromTaskResult($request, $result);
        } catch (\Throwable $exception) {
            return $this->createResponseFromError($exception, $request);
        }        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade::createResponseFromError()
     */
    public function createResponseFromError(\Throwable $exception, ServerRequestInterface $request = null, UiPageInterface $page = null): ResponseInterface
    {
        if ($exception instanceof ExceptionInterface ) {
            $status_code = is_numeric($exception->getStatusCode()) ? $exception->getStatusCode() : 500;
        } else {
            $status_code = 500;
        }
        
        $headers = $this->buildHeadersCommon();
        $headers['Content-Type'] = ['application/json;charset=utf-8'];
        
        $body = $this->encodeData($this->buildResponseDataError($exception));
        $this->getWorkbench()->getLogger()->logException($exception);
        
        return new Response($status_code, $headers, $body);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade::createResponseFromTaskResult()
     */
    protected function createResponseFromTaskResult(ServerRequestInterface $request, ResultInterface $result): ResponseInterface
    {
        /* @var $headers array [header_name => array_of_values] */
        $headers = $this->buildHeadersCommon();
        
        /* @var $status_code int */
        $status_code = $result->getResponseCode();
        
        if ($result->isEmpty()) {
            return new Response($status_code, $headers);
        }
        
        switch (true) {
            case $result instanceof ResultDataInterface:
                $json = $result->getData()->exportUxonObject()->toArray();
                $json["success"] = $result->getMessage();
                break;
                
            case $result instanceof ResultWidgetInterface:                
                $json = [
                    'success' => $result->getMessage()
                ];
                if ($result->isContextModified() && $result->getTask()->isTriggeredOnPage()) {
                    $context_bar = $result->getTask()->getPageTriggeredOn()->getContextBar();
                    $json['extras']['ContextBar'] = $this->getElement($context_bar)->buildJsonContextBarUpdate();
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
                    return new Response(302, ['Location' => $url]);
                }
                break;
                
            case $result instanceof ResultTextContentInterface:
                $headers['Content-type'] = $result->getMimeType();
                $body = $result->getContent();
                break;
                
            default:
                $json['success'] = $result->getMessage();
        }
        
        if (! empty($json)) {
            $headers['Content-type'] = ['application/json;charset=utf-8'];
            $body = $this->encodeData($json);
        }
        
        return new Response($result->getResponseCode(), $headers, $body);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/task';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see AbstractHttpFacade::getMiddleware()
     */
    protected function getMiddleware() : array
    {
        $middleware = parent::getMiddleware();
        
        // Make sure pure JSON requests are treated the same as mixed ones used by AbstractAjaxFacade, where
        // action, page, object, etc. are send urlencoded and data as JSON
        $middleware[] = new JsonBodyParser();
        
        $allowBasicAuth = $this->getWorkbench()->getConfig()->getOption('FACADES.HTTPTASKFACADE.ALLOW_HTTP_BASIC_AUTH');
        if ($allowBasicAuth === true) {
            $middleware[] = new AuthenticationMiddleware(
                $this,
                [
                    [AuthenticationMiddleware::class, 'extractBasicHttpAuthToken']
                ]
            );
        }
        
        // Read common task properties like the AbstractAjaxFacade does
        $middleware[] = new TaskUrlParamReader($this, 'action', 'setActionSelector', $this->getRequestAttributeForAction(), $this->getRequestAttributeForTask());
        $middleware[] = new TaskUrlParamReader($this, 'resource', 'setPageSelector', $this->getRequestAttributeForPage(), $this->getRequestAttributeForTask());
        $middleware[] = new TaskUrlParamReader($this, 'object', 'setMetaObjectSelector');
        $middleware[] = new TaskUrlParamReader($this, 'element', 'setWidgetIdTriggeredBy');
        $middleware[] = new DataUrlParamReader($this, 'data', 'setInputData');
        
        return $middleware;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function buildHeadersCommon() : array
    {
        return array_filter($this->getConfig()->getOption('FACADES.HTTPTASKFACADE.HEADERS.COMMON')->toArray());
    }
    
    /**
     *
     * @param array|\stdClass $serializable_data
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
     * @param \Throwable $exception
     * @return string[]
     */
    public function buildResponseDataError(\Throwable $exception) : array
    {
        $error = [
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
        
        if ($exception instanceof ExceptionInterface) {
            $error['code'] = $exception->getAlias();
            $error['logid'] = $exception->getId();
            
            $wb = $this->getWorkbench();
            $msg = $exception->getMessageModel($wb);
            $error['title'] = $msg->getTitle();
            $error['hint'] = $msg->getHint();
            $error['description'] = $msg->getDescription();
            $error['type'] = $msg->getType();
        }
        
        return [
            'error' => $error
        ];
    }
}