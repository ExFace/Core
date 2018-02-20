<?php
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Exceptions\Templates\TemplateIncompatibleError;
use Psr\Http\Message\ResponseInterface;

/**
 * Send an HTTP response
 *
 * @return void
 */
function send(ResponseInterface $response)
{
    $http_line = sprintf('HTTP/%s %s %s',
        $response->getProtocolVersion(),
        $response->getStatusCode(),
        $response->getReasonPhrase()
        );
    header($http_line, true, $response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("$name: $value", false);
        }
    }
    $stream = $response->getBody();
    if ($stream->isSeekable()) {
        $stream->rewind();
    }
    while (!$stream->eof()) {
        echo $stream->read(1024 * 8);
    }
}


error_reporting(E_ALL & ~E_NOTICE);

// instantiate the main class
require_once('CommonLogic/Workbench.php');
$workbench = new \exface\Core\CommonLogic\Workbench();
$workbench->start();

$request = ServerRequest::fromGlobals();
$template = $workbench->ui()->getTemplateForUri($request->getUri());
if (! ($template instanceof RequestHandlerInterface)) {
    throw new TemplateIncompatibleError('Template "' . $template->getAliasWithNamespace() . '" is cannot be used as a standard HTTP request handler - please check system configuration option TEMPLATE.ROUTES!');
}

$response = $template->handle($request);
send($response);

$workbench->stop();