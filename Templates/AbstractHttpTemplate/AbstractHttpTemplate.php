<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\Templates\AbstractTemplate\AbstractTemplate;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\CommonLogic\Tasks\TaskResultData;
use exface\Core\CommonLogic\Tasks\TaskResultWidget;
use exface\Core\Exceptions\Templates\TemplateOutputError;
use exface\Core\Interfaces\Tasks\TaskResultUriInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Psr\Http\Server\MiddlewareInterface;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\TaskReaderMiddleware;
use exface\Core\Exceptions\InternalError;
use exface\Core\Templates\AbstractHttpTemplate\Middleware\ContextReaderMiddleware;

abstract class AbstractHttpTemplate extends AbstractTemplate implements HttpTemplateInterface
{
    const REQUEST_ATTRIBUTE_NAME_TASK = 'task';
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if ($request->getAttribute(static::REQUEST_ATTRIBUTE_NAME_TASK) === null) {
            $handler = new HttpRequestHandler($this);
            foreach ($this->getMiddleware() as $middleware) {
                $handler->add($middleware);
            }
            // TODO Throw event to allow adding middleware from outside (e.g. a PhpDebugBar or similar)
            return $handler->handle($request);
        }        
        
        try {
            $task = $request->getAttribute(static::REQUEST_ATTRIBUTE_NAME_TASK);
            $result = $this->getWorkbench()->handle($task);
            return $this->createResponse($result);
        } catch (\Throwable $e) {
            if (! $e instanceof ExceptionInterface){
                $e = new InternalError($e->getMessage(), null, $e);
            }
            return $this->createResponseError($e);
        }
    }
    
    protected function getMiddleware() : array
    {
        return [
            $this->getMiddlewareTaskReader(),
            $this->getMiddlewareContextReader()
        ];
    }
    
    protected function getMiddlewareContextReader()
    {
        return new ContextReaderMiddleware($this->getWorkbench()->context());
    }
    
    protected function getMiddlewareTaskReader() : MiddlewareInterface
    {
        return new TaskReaderMiddleware($this, static::REQUEST_ATTRIBUTE_NAME_TASK);
    }
    
    /**
     * 
     * @param TaskResultInterface $result
     * @return ResponseInterface
     */
    protected function createResponse(TaskResultInterface $result)
    {
        $headers = [];
        $status_code = $result->getResponseCode();
        // $body = $result->getTask()->getActionSelector()->toString() . ' Done!';
        $template = $result->getTask()->getTemplate();
        switch (true) {
            case $result instanceof TaskResultData:
                $elem = $template->getElement($result->getTask()->getOriginWidget());
                $data = $elem->prepareData($result->getData());
                $body = $template->encodeData($data);
                break;
            case $result instanceof TaskResultWidget:
                $body = $template->buildWidget($result->getWidget());
                break;
            default:
                $response = array();
                $response['success'] = $result->getMessage();
                if ($result->isUndoable()) {
                    $response['undoable'] = '1';
                }
                // check if result is a properly formed link
                if ($result instanceof TaskResultUriInterface) {
                    $url = filter_var($result->getUri()->__toString(), FILTER_SANITIZE_STRING);
                    if (substr($url, 0, 4) == 'http') {
                        $response['redirect'] = $url;
                    }
                }
                // Encode the response object to JSON converting <, > and " to HEX-values (e.g. \u003C). Without that conversion
                // there might be trouble with HTML in the responses (e.g. jEasyUI will break it when parsing the response)
                $body = $this->encodeData($response, $result->isContextModified() ? true : false);
        }
        return new Response($status_code, $headers, $body);
    }
    
    /**
     *
     * @param array|\stdClass $serializable_data
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
                    'icon' => $btn_element->buildCssIconClass($btn->getIcon()),
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
    
    /**
     * 
     * @param \Throwable $e
     * @return ResponseInterface
     */
    protected function createResponseError(\Throwable $exception, UiPageInterface $page = null) {
        $page = ! is_null($page) ? $page : UiPageFactory::createEmpty($this->getWorkbench()->ui());
        
        $status_code = is_numeric($exception->getStatusCode()) ? $exception->getStatusCode() : 500;
        $headers = [];
        $body = '';
        
        try {
            $debug_widget = $exception->createWidget($page);
            if ($page->getWorkbench()->getConfig()->getOption('DEBUG.SHOW_ERROR_DETAILS_TO_ADMINS_ONLY') && ! $page->getWorkbench()->context()->getScopeUser()->getUserCurrent()->isUserAdmin()) {
                foreach ($debug_widget->getTabs() as $nr => $tab) {
                    if ($nr > 0) {
                        $tab->setHidden(true);
                    }
                }
            }
            $body = $this->buildIncludes($debug_widget) . "\n" . $this->buildWidget($debug_widget);
        } catch (\Throwable $e) {
            // If anything goes wrong when trying to prettify the original error, drop prettifying
            // and throw the original exception wrapped in a notice about the failed prettification
            $this->getWorkbench()->getLogger()->logException($e);
            $log_id = $e instanceof ExceptionInterface ? $e->getId() : '';
            throw new RuntimeException('Failed to create error report widget: "' . $e->getMessage() . '" - see ' . ($log_id ? 'log ID ' . $log_id : 'logs') . ' for more details! Find the orignal error detail below.', null, $exception);
        } catch (FatalThrowableError $e) {
            // If anything goes wrong when trying to prettify the original error, drop prettifying
            // and throw the original exception wrapped in a notice about the failed prettification
            $this->getWorkbench()->getLogger()->logException($e);
            $log_id = $e instanceof ExceptionInterface ? $e->getId() : '';
            throw new RuntimeException('Failed to create error report widget: "' . $e->getMessage() . '" - see ' . ($log_id ? 'log ID ' . $log_id : 'logs') . ' for more details! Find the orignal error detail below.', null, $exception);
        }
        
        $this->getWorkbench()->getLogger()->logException($exception);
        
        return new Response($status_code, $headers, $body);
    }
}