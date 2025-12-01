<?php
namespace exface\Core\Facades\DocsFacade\Middleware;

use exface\Core\DataTypes\UrlDataType;
use exface\Core\Facades\DocsFacade\MarkdownPrinters\UxonPrototypeMarkdownPrinter;
use kabachello\FileRoute\Interfaces\FileReaderInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;

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
        $params = UrlDataType::findUrlParams($request->getUri());
        $selector = urldecode($params[$this->prototypeSelectorUrlParam]);
        $printer = new UxonPrototypeMarkdownPrinter($this->getWorkbench(), $selector);
        return $printer->getMarkdown();
    }
}