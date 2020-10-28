<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\Throwable;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Interfaces\Tasks\ResultTextContentInterface;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Queue\TaskQueueRouter;

class HttpTaskFacade extends AbstractAjaxFacade
{
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {        
        $uri = $request->getUri();
        $path = $uri->getPath();
        $topics = explode('/',substr(StringDataType::substringAfter($path, $this->getUrlRouteDefault()), 1));
        $task = $request->getAttribute($this->getRequestAttributeForTask());
        $router = new TaskQueueRouter($this->getWorkbench());
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
        try {
            $result = $router->handle($task, $topics, $producer, $requestId);
            return $this->createResponseFromTaskResult($request, $result);
        } catch (\Throwable $exception) {
            return $this->createResponseFromError($request, $exception);
        }        
    }
    
    public function createResponseFromError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null): ResponseInterface
    {
        if ($exception instanceof ExceptionInterface ) {
            $status_code = is_numeric($exception->getStatusCode()) ? $exception->getStatusCode() : 500;
        } else {
            $status_code = 500;
        }
        $headers = $this->buildHeadersAccessControl();
        $body = $this->encodeData($this->buildResponseDataError($exception));
        $headers['Content-Type'] = ['application/json;charset=utf-8'];
        $this->getWorkbench()->getLogger()->logException($exception);
        
        return new Response($status_code, $headers, $body);
    }

    protected function createResponseFromTaskResult(ServerRequestInterface $request, ResultInterface $result): ResponseInterface
    {
        if ($result instanceof ResultWidgetInterface || $result instanceof ResultTextContentInterface) {
            $headers = $this->buildHeadersAccessControl();
            $json = [
                'success' => $result->getMessage()
            ];
            if ($result->isContextModified() && $result->getTask()->isTriggeredOnPage()) {
                $context_bar = $result->getTask()->getPageTriggeredOn()->getContextBar();
                $json['extras']['ContextBar'] = $this->getElement($context_bar)->buildJsonContextBarUpdate();
            }
            $headers['Content-type'] = ['application/json;charset=utf-8'];
            $body = $this->encodeData($json);
            $response = new Response($result->getResponseCode(), $headers, $body);
        } else {
            $response = parent::createResponseFromTaskResult($request, $result);
        }
        
        return $response;
    }

    public function getUrlRouteDefault(): string
    {
        return 'api/task';
    }
    
    public function buildResponseData(DataSheetInterface $data_sheet, WidgetInterface $widget = null)
    {
        return [];
    }

    protected function getPageTemplateFilePathDefault(): string
    {
        return '';
    }
}