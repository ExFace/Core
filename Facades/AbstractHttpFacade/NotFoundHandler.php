<?php
namespace exface\Core\Facades\AbstractHttpFacade;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use GuzzleHttp\Psr7\Response;

/**
 * This is a simple PSR-15 compliant request handler that always returns a 404 error.
 * 
 * @author Andrej Kabachnik
 */
class NotFoundHandler implements RequestHandlerInterface
{    
    private ?string $message = null;
    public function __construct(string $notFoundMessage = null)
    {
        $this->message = $notFoundMessage;
    }
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        return new Response(404, [], $this->message);
    }
}