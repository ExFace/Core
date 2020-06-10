<?php
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\Facades\AbstractHttpFacade\Middleware\FacadeResolverMiddleware;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;

error_reporting(E_ALL & ~E_NOTICE);

// Start the workbench
require_once('CommonLogic/Workbench.php');
$workbench = new \exface\Core\CommonLogic\Workbench();

// Create a simple request handler and add the default routing middleware
// If the middleware will not be able to match a rout, the handler will return the
// fallback response created here - which is an empty 404. This is enough for a
// simple API endpoint.
$handler = new HttpRequestHandler(new NotFoundHandler());
$handler->add(new FacadeResolverMiddleware($workbench));
$response = $handler->handle(ServerRequest::fromGlobals());

// Send the response
$handler::send($response);