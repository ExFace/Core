<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use GuzzleHttp\Psr7\Response;

/**
 * This PSR-15 middleware handles authentication.
 * 
 * @author Andrej Kabachnik
 *
 */
class AuthenticationMiddleware implements MiddlewareInterface
{
    private $workbench = null;
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $forbid = false;
        $disableGuest = $this->workbench->getConfig()->getOption('SECURITY.DISABLE_ANONYMOUS_ACCESS');
        if ($disableGuest === true) {
            try {
                $forbid = $this->workbench->getContext()->getScopeUser()->getUserCurrent()->isUserAnonymous();
            } catch (\Throwable $e) {
                $forbid = true;    
            }
        }
        
        if ($forbid === true) {
            return $this->createResponseAccessDenied('Access denied! Please log in first!');
        }
        
        return $handler->handle($request);
    }
    
    protected function createResponseAccessDenied(string $content) : ResponseInterface
    {
        return new Response(403, [], $content);
    }
}