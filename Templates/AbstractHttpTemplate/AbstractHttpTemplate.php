<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\Templates\AbstractTemplate\AbstractTemplate;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;

abstract class AbstractHttpTemplate extends AbstractTemplate implements HttpTemplateInterface
{
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $task = new GenericHttpTask($this, $request);
        
        $headers = [];
        $status_code = 200;
        $response = new Response($status_code, $headers, $task->getOriginWidget()->getId() . ' Done!');
        return $response;
    }
}