<?php
namespace exface\Core\Templates\FileServerTemplate;

use exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class FileServerTemplate extends AbstractHttpTemplate
{
    public static function buildUrlForDownload(WorkbenchInterface $workbench, $absolutePath)
    {
        return $workbench->getCMS()->createLinkToFile($absolutePath);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request, TaskResultInterface $result): ResponseInterface
    {
        // TODO
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::createResponseError()
     */
    protected function createResponseError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null): ResponseInterface
    {
        // TODO
    }

    
}