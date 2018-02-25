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

abstract class AbstractHttpTemplate extends AbstractTemplate implements HttpTemplateInterface
{
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        // IDEA Middleware goes here!        
        try {
            $task = new GenericHttpTask($this, $request);
            $result = $this->getWorkbench()->handle($task);
            return $this->createResponse($result);
        } catch (\Throwable $e) {
            return $this->createResponseError($e);
        }
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
    protected function createResponseError(\Throwable $e) {
        throw $e;
    }
}