<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

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
    
    /**
     * 
     * @param ResponseInterface $fallbackResponse
     */
    public function __construct(RequestHandlerInterface $fallbackHandler)
    {
        $this->fallbackHandler = $fallbackHandler;
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
    public static function send(ResponseInterface $response)
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
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        while (!$stream->eof()) {
            echo $stream->read(1024 * 8);
        }
        
        return;
    }
}