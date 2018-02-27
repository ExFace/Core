<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;

/**
 * This is a simple PSR-15 compilant request handler that allways returns a 404 error.
 * 
 * @author Andrej Kabachnik
 */
class NotFoundHandler implements RequestHandlerInterface
{    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return new Response(404);
    }
}