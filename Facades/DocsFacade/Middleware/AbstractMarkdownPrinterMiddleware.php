<?php
namespace exface\Core\Facades\DocsFacade\Middleware;

use exface\Core\CommonLogic\Filemanager;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\MimeTypeDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Facades\DocsFacade\MarkdownContent;
use exface\Core\Interfaces\Facades\MarkdownPrinterMiddlewareInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use GuzzleHttp\Psr7\Response;
use kabachello\FileRoute\Interfaces\FileReaderInterface;
use kabachello\FileRoute\Templates\PlaceholderFileTemplate;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\DataTypes\StringDataType;

/**
 * Base class for middlewares, that generate content for certain URLs via markdown printer classes
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractMarkdownPrinterMiddleware implements MarkdownPrinterMiddlewareInterface
{
    private $workbench = null;
    
    private DocsFacade $facade;
    private string $baseUrl;
    private FileReaderInterface $reader;
    private string $fileUrl;

    /**
     * @param HttpFacadeInterface $facade
     * @param string $baseUrl
     * @param string $fileUrl
     * @param FileReaderInterface $reader
     */
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

        $templatePath = Filemanager::pathJoin([$this->getFacade()->getApp()->getDirectoryAbsolutePath(), 'Facades/DocsFacade/template.html']);
        $template = new PlaceholderFileTemplate($templatePath, $this->getBaseUrl() . '/' . $this->getFacade()->buildUrlToFacade(true));
        $template->setBreadcrumbsRootName('Documentation');
        $vendorFolder = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . '/';
        $folder = FilePathDataType::findFolderPath($this->getFileUrl());
        $file = FilePathDataType::findFileName($this->getFileUrl(), true);
        $content = new MarkdownContent($vendorFolder . $folder . $file, $folder . $file, $this->getFileReader()->readFolder($vendorFolder . $folder, $folder), $markdown);

        $needRawMarkdown = $request->getHeaderLine('Accept') === MimeTypeDataType::MARKDOWN;
        if (! $needRawMarkdown) {
            $html = $template->render($content);
            $response = new Response(200, [
                'Content-Type' => 'text/html',
            ], $html);
        } else {
            $markdown = $content;
            $response = new Response(200, [
                'Content-Type' => MimeTypeDataType::MARKDOWN,
            ], $markdown);
        }
        
        return $response;
    }

    protected function shouldSkip(ServerRequestInterface $request): bool
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
    
    protected function getFacade() : DocsFacade
    {
        return $this->facade;
    }
    
    protected function getBaseUrl() : string
    {
        return $this->baseUrl;
    }
    
    protected function getFileReader() : FileReaderInterface
    {
        return $this->reader;
    }
    
    protected function getFileUrl() : string
    {
        return $this->fileUrl;
    }
}