<?php

namespace exface\Core\Facades;

use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles health check requests, responding with the appropriate status and headers.
 */
class HealthCheckFacade extends AbstractHttpFacade
{
    protected function createResponse(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, $this->buildHeadersCommon(), '{}');
    }

    public function getUrlRouteDefault(): string
    {
        return 'api/health';
    }
}