<?php
namespace exface\Core\Facades;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\ForeignExceptions\JsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use GuzzleHttp\Psr7\Response;


/**
 * Facade to collect logs from the UI or even external systems
 * 
 * @author Andrej Kabachnik
 *
 */
class LogHubFacade extends AbstractHttpFacade
{

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault(): string
    {
        return 'api/loghub';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        
        $headers = $this->buildHeadersCommon();
        
        $logLevel = $this->getWorkbench()->getConfig()->getOption('DEBUG.LOG_LEVEL_HUB_JAVASCRIPT_ERROR');
        // IDEA add different routes for different foreign exception - javascript, c#, possible routes for specific external systems.
        $exception = $this->createExceptionFromRequest($request);
        $this->getWorkbench()->getLogger()->logException($exception, $logLevel);
        
        return new Response(200, $headers);        
    }

    /**
     * IDEA add a ForeignExceptionInterface
     * 
     * @param ServerRequestInterface $request
     * @return \Throwable
     */
    protected function createExceptionFromRequest(ServerRequestInterface $request): \Throwable
    {
        $payload = JsonDataType::decodeJson($request->getBody()->__toString());
        // TODO
        return new JsException($payload);
    }
}