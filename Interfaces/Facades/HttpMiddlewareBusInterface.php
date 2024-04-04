<?php
namespace exface\Core\Interfaces\Facades;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface HttpMiddlewareBusInterface extends RequestHandlerInterface
{    
    /**
     * 
     * @param MiddlewareInterface $middleware
     * @return HttpMiddlewareBusInterface
     */
    public function add(MiddlewareInterface $middleware) : HttpMiddlewareBusInterface;
}
