<?php
namespace exface\Core\CommonLogic\Debugger;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\DataTypes\HtmlDataType;
use exface\Core\DataTypes\JsonDataType;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\DataTypes\ByteSizeDataType;

/**
 * Renders debug widget tabs for PSR7 requests and responses
 * 
 * @author Andrej Kabachnik
 *
 */
class HttpMessageDebugWidgetRenderer implements iCanGenerateDebugWidgets
{
    const MAX_BODY_PRINT_SIZE = 200*1024; // 200 kb in bytes
    
    const MAX_PARAM_PRINT_SIZE = 10*1024; // 10 kb in bytes 
    
    private $request = null;
    
    private $requestTabCaption = null;
    
    private $response = null;
    
    private $responseTabCaption = null;
    
    /**
     * 
     * @param RequestInterface $psr7Request
     * @param ResponseInterface $psr7Response
     * @param string $requestTabCaption
     * @param string $responseTabCaption
     */
    public function __construct(RequestInterface $psr7Request, ResponseInterface $psr7Response = null, string $requestTabCaption = null, string $responseTabCaption = null)
    {
        $this->request = $psr7Request;
        $this->response = $psr7Response;
        $this->requestTabCaption = $requestTabCaption;
        $this->responseTabCaption = $responseTabCaption;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $page = $debug_widget->getPage();
        
        // Request
        $request_tab = $debug_widget->createTab();
        $request_tab->setCaption($this->requestTabCaption ?? 'HTTP-Request');
        try {
            $url = $this->request->getUri()->__toString();
        } catch (\Throwable $e) {
            $url = 'Unavailable: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        }
        $request_widget = WidgetFactory::create($page, 'Markdown', $request_tab);
        
        $serverParams = '';
        if ($this->request instanceof ServerRequestInterface) {
            $serverParams = <<<MD

## Server parameters
{$this->buildMarkdownServerParams($this->request)}
MD;
        }
        
        $urlParams = '';
        if ($query = $this->request->getUri()->getQuery()) {
            $urlParams = <<<MD
            
```
?{$this->prettifyUrlParams($query)}
```
MD;
        }
        $request_widget->setValue(<<<MD
## Request URL
            
[{$url}]({$url}){$urlParams}

## Request headers

{$this->buildMarkdownRequestHeaders($this->request)}{$serverParams}

## Request body

{$this->buildMarkdownMessageBody($this->request)}

MD);
$request_widget->setWidth('100%');
$request_tab->addWidget($request_widget);
$debug_widget->addTab($request_tab);

// Response
if ($this->response !== null) {
    $response_tab = $debug_widget->createTab();
    $response_tab->setCaption($this->responseTabCaption ?? 'HTTP-Response');
    
    $response_widget = WidgetFactory::create($page, 'Markdown', $response_tab);
    $response_widget->setValue(<<<MD
    ## Response headers
        
    {$this->buildMarkdownResponseHeaders($this->response)}
    
    ## Response body
    
    {$this->buildMarkdownMessageBody($this->response)}
    
    MD);
    $response_widget->setWidth('100%');
    $response_tab->addWidget($response_widget);
    $debug_widget->addTab($response_tab);
}

return $debug_widget;
    }
    
    /**
     * Generates a HTML-representation of the request-headers.
     *
     * @return string
     */
    protected function buildMarkdownRequestHeaders(RequestInterface $request) : string
    {
        $requestHeaders = $request->getMethod() . ' ' . $request->getRequestTarget() . ' HTTP/' . $request->getProtocolVersion() . PHP_EOL . PHP_EOL;
        $requestHeaders .= $this->buildMarkdownMessageHeaders($request);
        
        return $requestHeaders;
    }
    
    /**
     * Generates a HTML-representation of the request or response headers.
     *
     * @return string
     */
    protected function buildMarkdownMessageHeaders(MessageInterface $message = null) : string
    {
        if (! is_null($message)) {
            try {
                $messageHeaders  = "| HTTP header | Value |" . PHP_EOL;
                $messageHeaders .= "| ----------- | ----- |" . PHP_EOL;
                foreach ($message->getHeaders() as $header => $values) {
                    foreach ($values as $value) {
                        if ($this->isSensitiveData($header)) {
                            $value = '***';
                        }
                        $value = $this->escapeMardownTableCellContents($value ?? '');
                        $messageHeaders .= "| $header | $value |" . PHP_EOL;
                    }
                }
            } catch (\Throwable $e) {
                $messageHeaders = 'Error reading message headers: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            }
        } else {
            $messageHeaders = 'No HTTP message.';
        }
        
        return $messageHeaders;
    }
    
    /**
     * 
     * @param string $text
     * @return string
     */
    protected function escapeMardownTableCellContents(string $text) : string
    {
        return str_replace('|', '\|', $text);
    }
    
    /**
     * 
     * @param ServerRequestInterface $message
     * @return string
     */
    protected function buildMarkdownServerParams(ServerRequestInterface $message) : string
    {
        try {
            $mdTable  = "| Server parameter | Value |" . PHP_EOL;
            $mdTable .= "| ----------- | ----- |" . PHP_EOL;
            foreach ($message->getServerParams() as $param => $value) {
                if ($this->isSensitiveData($param)) {
                    $value = '***';
                }
                if (is_array($value) || is_object($value)) {
                    $value = JsonDataType::encodeJson($value);
                }
                $value = $this->escapeMardownTableCellContents($value ?? '');
                $mdTable .= "| $param | $value |" . PHP_EOL;
            }
        } catch (\Throwable $e) {
            $mdTable = 'Error reading server params: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        }
        
        return $mdTable;
    }
    
    /**
     * Generates a HTML-representation of the response-headers.
     *
     * @return string
     */
    protected function buildMarkdownResponseHeaders(ResponseInterface $response) : string
    {
        $responseHeaders = 'HTTP/' . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . PHP_EOL . PHP_EOL;
        $responseHeaders .= $this->buildMarkdownMessageHeaders($response);
        
        return $responseHeaders;
    }
    
    /**
     * Generates a HTML-representation of the request or response body.
     *
     * @param MessageInterface $message
     * @return string
     */
    protected function buildMarkdownMessageBody(MessageInterface $message = null) : string
    {
        if ($message === null) {
            return 'Message empty.';
        }
        try {
            $bodySize = $message->getBody()->getSize();
            if ($bodySize === null || $bodySize === 0) {
                return 'Message body is empty.';
            } 
            $contentType = mb_strtolower($message->getHeader('Content-Type')[0]);
            
            $messageBody = 'Message body: ' . ByteSizeDataType::formatWithScale($bodySize) . ' of "' . $contentType . '"' . PHP_EOL;
            switch (true) {
                case stripos($contentType, 'application/x-www-form-urlencoded') !== false:
                    if (! $message->getBody()->isReadable() && $message instanceof ServerRequestInterface) {
                        $bodyString = http_build_query($message->getParsedBody());
                    } else {
                        $bodyString = $message->getBody()->__toString();
                    }
                    $messageBody .= <<<MD
                            
```
{$this->prettifyUrlParams($bodyString)}
```
MD;
                    break;
                case stripos($contentType, 'json') !== false:
                    $json = $message->getBody()->__toString();
                    if ($bodySize > self::MAX_BODY_PRINT_SIZE) {
                        $jsonArray = JsonDataType::decodeJson($json, true);
                        array_walk_recursive($jsonArray, function($val, $key){
                            if (is_string($val) && mb_strlen($val) > self::MAX_PARAM_PRINT_SIZE) {
                                return $this->prettifyTruncateValue($val);
                            }
                        });
                        $messageBody .= 'Message truncated!' . PHP_EOL;
                        $prettified = JsonDataType::prettify($jsonArray);
                        if (mb_strlen($prettified) > self::MAX_BODY_PRINT_SIZE) {
                            $prettified = mb_substr($prettified, 0, self::MAX_BODY_PRINT_SIZE);
                            $prettified = $this->prettifyTruncateValue($prettified);
                        }
                    } else {
                        $prettified = JsonDataType::prettify($json);
                    }
                    $messageBody .= <<<MD
                            
```json
{$prettified}
```
MD;
                    break;
                case stripos($contentType, 'xml') !== false:
                    if ($bodySize > self::MAX_BODY_PRINT_SIZE) {
                        return 'XML message body is too big to display: ' . ByteSizeDataType::formatWithScale($bodySize);
                    }
                    $domxml = new \DOMDocument();
                    $domxml->preserveWhiteSpace = false;
                    $domxml->formatOutput = true;
                    $domxml->loadXML($message->getBody());
                    $messageBody .= <<<MD
                            
```xml
{$domxml->saveXML()}
```
MD;
                    break;
                case stripos($contentType, 'html') !== false:
                    if ($bodySize > self::MAX_BODY_PRINT_SIZE) {
                        return 'HTML message body is too big to display: ' . ByteSizeDataType::formatWithScale($bodySize);
                    }
                    $prettified = HtmlDataType::prettify($message->getBody()->__toString());
                    $messageBody .= <<<MD
                            
```html
{$prettified}
```
MD;
                    break;
                default:
                    if (! $message->getBody()->isReadable()) {
                        return 'Message body is no readable or detached!';
                    }
                    $messageBody .= <<<MD
                            
```
{$message->getBody()->__toString()}
```
MD;
                    break;
            }
            
        } catch (\Throwable $e) {
            $messageBody = 'Error reading message body: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
        }
        
        return $messageBody;
    }
    
    /**
     * 
     * @param string $urlencoded
     * @return string
     */
    protected function prettifyUrlParams(string $urlencoded) : string
    {
        $params = explode('&', $urlencoded);
        $prettified = '';
        foreach ($params as $param) {
            if (is_string($param)) {
                if (mb_strlen($param) > self::MAX_PARAM_PRINT_SIZE) {
                    $param = $this->prettifyTruncateValue($param);  
                }
            }
            $prettified .= ($prettified ? "\n&" : '') . urldecode($param);
        }
        return $prettified;
    }
    
    /**
     * 
     * @param string $value
     * @return string
     */
    protected function prettifyTruncateValue(string $value, int $length = 100) : string
    {
        return mb_substr($value, 0, $length) . '... (truncated value of ' . ByteSizeDataType::formatWithScale(mb_strlen($value)) . ')';
    }
    
    /**
     *
     * @param string $headerName
     * @return bool
     */
    protected function isSensitiveData(string $headerName) : bool
    {
        switch (true) {
            case stripos($headerName, 'auth') !== false:
            case stripos($headerName, 'key') !== false:
                return true;
        }
        return false;
    }
}
