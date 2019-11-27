<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;

/**
 * This is a simple PSR-15 compilant request handler that allways returns an empty 200 response.
 * 
 * @author Andrej Kabachnik
 */
class OKHandler implements RequestHandlerInterface
{    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return new Response(200);
    }
}