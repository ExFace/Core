<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr;
use exface\Core\Interfaces\Log\LoggerInterface;

/**
 * This is a simple PSR-15 compilant request handler, that is used in the defualt API
 * endpoint of ExFace: the index.php in the core app.
 * 
 * Middleware can be added to this handler using the add() method. It will be called
 * in the same order as being added.
 * 
 * @author Andrej Kabachnik
 */
class HttpRequestHandler implements RequestHandlerInterface
{
    private $middleware = [];
    private $fallbackHandler = null;
    private $logger = null;
    
    /**
     * 
     * @param RequestHandlerInterface $fallbackHandler
     * @param LoggerInterface $logger
     */
    public function __construct(RequestHandlerInterface $fallbackHandler, LoggerInterface $logger = null)
    {
        $this->fallbackHandler = $fallbackHandler;
        $this->logger = $logger;
    }
    
    /**
     * 
     * @param MiddlewareInterface $middleware
     */
    public function add(MiddlewareInterface $middleware) : HttpRequestHandler
    {
        $this->middleware[] = $middleware;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        // Last middleware in the queue has called on the request handler.
        if (empty($this->middleware)) {
            return $this->fallbackHandler->handle($request);
        }
        
        $middleware = array_shift($this->middleware);
        return $middleware->process($request, $this);
    }
    
    /**
     * Send an HTTP response
     * 
     * @param ResponseInterface $response
     *
     * @return void
     */
    public function send(ResponseInterface $response)
    {
        $http_line = sprintf('HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
            );
        header($http_line, true, $response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header("$name: $value", false);
            }
        }
        $stream = $response->getBody();
        $isStreamByLine = $stream instanceof IteratorStream || $response->getHeader('Content-Type')[0] === 'text/plain-stream';
        $chunk = $isStreamByLine ? 1 : 1024 * 8;
        
        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            while (!$stream->eof()) {
                echo $stream->read($chunk);
            }
        } catch (\Throwable $e) {
            // If in stream-by-line mode, we don't need any HTML errors, so we print the exception here
            // manually (as if in the command line)
            if ($isStreamByLine) {
                echo PHP_EOL . PHP_EOL . 'ERROR: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
                // The str_replace helps prevent strange white spaces in jQuery Terminal
                echo PHP_EOL . str_replace(array("\r", "\n"), PHP_EOL, $e->getTraceAsString());
                
                if ($this->logger !== null) {
                    $this->logger->logException($e);
                }
                
                // Send the response to the browser focibly to prevent the default
                // HTML error handler to append anything else
                header('Connection: close');
                header('Content-Length: '.ob_get_length());
                ob_end_flush();
                flush();
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                end;
            } else {
                // Let the default error handler do its job
                throw $e;
            }
        }
        
        return;
    }
}