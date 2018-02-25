<?php
namespace exface\Core\Templates\AbstractHttpTemplate;

use exface\Core\Templates\AbstractTemplate\AbstractTemplate;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Interfaces\Tasks\TaskResultInterface;

abstract class AbstractHttpTemplate extends AbstractTemplate implements HttpTemplateInterface
{
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        // IDEA Middleware goes here!        
        try {
            $task = new GenericHttpTask($this, $request);
            $result = $this->getWorkbench()->handle($task);
            return $this->createResponse($result);
        } catch (\Throwable $e) {
            return $this->createResponseError($e);
        }
    }
    
    protected function createResponse(TaskResultInterface $result)
    {
        $headers = [];
        $status_code = $result->getResponseCode();
        $body = $result->getTask()->getActionSelector()->toString() . ' Done!';
        return new Response($status_code, $headers, $body);
    }
    
    protected function createResponseError(\Throwable $e) {
        throw $e;
    }
}