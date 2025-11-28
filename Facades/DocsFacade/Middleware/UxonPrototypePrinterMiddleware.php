<?php
namespace exface\Core\Facades\DocsFacade\Middleware;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Facades\DocsFacade\MarkdownContent;
use exface\Core\Facades\DocsFacade\MarkdownPrinters\UxonPrototypeMarkdownPrinter;
use exface\Core\Interfaces\Facades\MarkdownPrinterMiddlewareInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use GuzzleHttp\Psr7\Response;
use kabachello\FileRoute\Interfaces\FileReaderInterface;
use kabachello\FileRoute\Templates\PlaceholderFileTemplate;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * This middleware rewrites URLs in documentation files to make them usable with the DocsFacade.
 * 
 * This middleware only works with apps, that have a composer.json with `support/docs` or `support/source`
 * properties!
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonPrototypePrinterMiddleware extends AbstractMarkdownPrinterMiddleware
{
    private string $prototypeSelectorUrlParam;
    
    public function __construct(HttpFacadeInterface $facade, string $baseUrl, string $fileUrl, FileReaderInterface $reader, string $prototypeSelectorUrlParam = 'selector')
    {
        parent::__construct($facade, $baseUrl, $fileUrl, $reader);
        $this->prototypeSelectorUrlParam = $prototypeSelectorUrlParam;
    }
    
    public function getMarkdown(ServerRequestInterface $request) : string
    {
        $params = $request->getQueryParams();
        $selector = urldecode($params[$this->prototypeSelectorUrlParam]);
        $printer = new UxonPrototypeMarkdownPrinter($this->getWorkbench(), $selector);
        return $printer->getMarkdown();
    }
}