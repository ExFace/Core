<?php
namespace exface\Core\Templates;

use exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class HttpFileServerTemplate extends AbstractHttpTemplate
{
    public static function buildUrlForDownload(WorkbenchInterface $workbench, $absolutePath)
    {
        return $workbench->getCMS()->buildUrlToFile($absolutePath);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::createResponseFromTaskResult()
     */
    protected function createResponseFromTaskResult(ServerRequestInterface $request, ResultInterface $result): ResponseInterface
    {
        // TODO
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractHttpTemplate\AbstractHttpTemplate::createResponseFromError()
     */
    protected function createResponseFromError(ServerRequestInterface $request, \Throwable $exception, UiPageInterface $page = null): ResponseInterface
    {
        // TODO
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Templates\HttpTemplateInterface::getUrlRoutePatterns()
     */
    public function getUrlRoutePatterns() : array
    {
        return [
            "/\/api\/files[\/?]/"
        ];
    }
}