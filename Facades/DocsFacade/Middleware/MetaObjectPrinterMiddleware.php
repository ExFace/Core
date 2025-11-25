<?php
namespace exface\Core\Facades\DocsFacade\Middleware;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\Facades\DocsFacade\MarkdownContent;
use exface\Core\Facades\DocsFacade\MarkdownPrinters\ObjectMarkdownPrinter;
use exface\Core\Facades\DocsFacade\MarkdownPrinters\UxonPrototypeMarkdownPrinter;
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
class MetaObjectPrinterMiddleware implements MiddlewareInterface
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
        if (! StringDataType::endsWith($request->getUri()->getPath(), $this->fileUrl)) {
            return $handler->handle($request);
        }
        
        $query = $request->getUri()->getQuery();
        $params = [];
        parse_str($query, $params);
        $selector = $this->normalize($params['selector']);
        $printer = new ObjectMarkdownPrinter($this->getWorkbench(), $selector);
        $markdown = $printer->getMarkdown();

        $templatePath = Filemanager::pathJoin([$this->facade->getApp()->getDirectoryAbsolutePath(), 'Facades/DocsFacade/template.html']);
        $template = new PlaceholderFileTemplate($templatePath, $this->baseUrl . '/' . $this->facade->buildUrlToFacade(true));
        $template->setBreadcrumbsRootName('Documentation');
        $vendorFolder = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . '/';
        $folder = 'exface/Core/Docs/creating_metamodels/';
        $file = 'Available_metaobjects.md';
        $content = new MarkdownContent($vendorFolder . $folder . $file, $folder . $file, $this->reader->readFolder($vendorFolder . $folder, $folder), $markdown);

        $html = $template->render($content);
        $response = new Response(200, [], $html);
        $response = $response->withHeader('Content-Type', 'text/html');
        return $response;
    }

    protected function normalize(string $raw): string
    {
        $decoded = urldecode($raw);

        $start = strpos($decoded, '[');
        $end   = strpos($decoded, ']');

        if ($start === false || $end === false || $end <= $start) {
            return $raw;
        }

        return substr($decoded, $start + 1, $end - $start - 1);
    }


    protected function getWorkbench(): WorkbenchInterface
    {
        return $this->facade->getWorkbench();
    }
}