<?php
use GuzzleHttp\Psr7\ServerRequest;
use exface\Core\Facades\AbstractHttpFacade\Middleware\FacadeResolverMiddleware;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;
use exface\Core\Facades\AbstractHttpFacade\NotFoundHandler;
use exface\Core\Exceptions\RuntimeException;
use GuzzleHttp\Psr7\Utils;

// Start the workbench
require_once('CommonLogic/Workbench.php');
$workbench = new \exface\Core\CommonLogic\Workbench();

// Create a simple request handler and add the default routing middleware
// If the middleware will not be able to match a rout, the handler will return the
// fallback response created here - which is an empty 404. This is enough for a
// simple API endpoint.
$handler = new HttpRequestHandler(new NotFoundHandler(), $workbench->getLogger());
$handler->add(new FacadeResolverMiddleware($workbench));

// Create a PSR-7 request object from $_GET, $_POST,etc.
$request = ServerRequest::fromGlobals();

// Check if the request was created correctly
$lastError = error_get_last();
if (! empty($lastError)) {
    switch (true) {
        // If the max_input_vars limit in php.ini is exceeded, only a WARNING is
        // issued and the body is truncated. This may lead to broken data, so we
        // actively look for this warning and empty the body in this case to avaiod
        // unforseable truncated data.
        case mb_stripos(($lastError['message'] ?? ''), 'Input variables exceeded') !== false:
            // Log an error, but do not throw it to allow gracefull error handling
            // by the facade. An empty request will still lead to an error.
            $workbench->getLogger()->logException(new RuntimeException($lastError['message']));
            $request = $request
                ->withBody(Utils::streamFor(''))
                ->withParsedBody(null);
            // Clean the output buffer to remove the WARNING output
            ob_clean();
            break;
    }
}

// Process the request
$response = $handler->handle($request);

// Send the response
$handler->send($response);