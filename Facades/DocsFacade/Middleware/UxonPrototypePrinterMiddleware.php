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
 * This middeware rewrites URLs in documentation files to make them usable with the DocsFacade.
 * 
 * This middleware only works with apps, that have a composer.json with `support/docs` or `support/source`
 * properties!
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonPrototypePrinterMiddleware implements MarkdownPrinterMiddlewareInterface
{
    private $workbench = null;
    
    private $facade = null;
    private string $baseUrl;
    private FileReaderInterface $reader;
    private string $fileUrl;
    
    public function __construct(HttpFacadeInterface $facade, string $baseUrl, string $fileUrl, FileReaderInterface $reader)
    {
        $this->workbench = $facade->getWorkbench();
        $this->facade = $facade;
        $this->baseUrl = $baseUrl;
        $this->reader = $reader;
        $this->fileUrl = $fileUrl;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldSkip($request)) { 
            return $handler->handle($request); 
        }
        
        $markdown = $this->getMarkdown($request);

        $templatePath = Filemanager::pathJoin([$this->facade->getApp()->getDirectoryAbsolutePath(), 'Facades/DocsFacade/template.html']);
        $template = new PlaceholderFileTemplate($templatePath, $this->baseUrl . '/' . $this->facade->buildUrlToFacade(true));
        $template->setBreadcrumbsRootName('Documentation');
        $vendorFolder = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . '/';
        $folder = 'exface/Core/Docs/UXON/';
        $file = 'UXON_prototypes.md';
        $content = new MarkdownContent($vendorFolder . $folder . $file, $folder . $file, $this->reader->readFolder($vendorFolder . $folder, $folder), $markdown);

        $html = $template->render($content);
        $response = new Response(200, [], $html);
        $response = $response->withHeader('Content-Type', 'text/html');
        return $response;
    }
    
    public function getMarkdown(ServerRequestInterface $request) : string
    {
        $query = $request->getUri()->getQuery();
        $params = [];
        parse_str($query, $params);
        $selector = urldecode($params['selector']);
        $printer = new UxonPrototypeMarkdownPrinter($this->getWorkbench(), $selector);
        return $printer->getMarkdown();
    }

    public function shouldSkip(ServerRequestInterface $request): bool
    {
        return ! StringDataType::endsWith(
            $request->getUri()->getPath(),
            $this->fileUrl
        );
    }
    
    protected function getWorkbench(): WorkbenchInterface
    {
        return $this->facade->getWorkbench();
    }
}