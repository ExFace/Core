<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * This PSR-15 middleware parses a JSON request body, so $request->getParsedBody() returns an array
 * 
 * @author Andrej Kabachnik
 *
 */
class JsonBodyParser implements MiddlewareInterface
{    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (empty($request->getParsedBody()) && stripos($request->getHeaderLine('content-type'), 'application/json') !== false) {
            $json = $request->getBody()->__toString();
            if (! empty($json)) {
                $array = json_decode($json, true);
                if (is_array($array)) {
                    $request = $request->withParsedBody($array);
                }
            }
        }
        
        return $handler->handle($request);
    }
}