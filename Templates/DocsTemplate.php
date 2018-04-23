<?php
namespace exface\Core\Templates;

use exface\Core\Templates\AbstractTemplate\AbstractTemplate;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use kabachello\FileRoute\FileRouteMiddleware;
use Psr\Http\Message\UriInterface;
use kabachello\FileRoute\FileReaders\MarkdownReader;
use kabachello\FileRoute\Templates\PlaceholderFileTemplate;
use exface\Core\Templates\AbstractHttpTemplate\NotFoundHandler;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Filemanager;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;

/**
 *  
 * @author Andrej Kabachnik
 *
 */
class DocsTemplate extends AbstractTemplate implements HttpTemplateInterface
{    
    private $url = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $matcher = function(UriInterface $uri) {
            $path = $uri->getPath();
            return StringDataType::substringAfter($path, 'api/docs/');
        };
        $reader = new MarkdownReader();
        $templatePath = Filemanager::pathJoin([$this->getApp()->getDirectoryAbsolutePath(), 'Templates/DocsTemplate/template.html']);
        $template = new PlaceholderFileTemplate($templatePath, $this->getBaseUrl());
        $router = new FileRouteMiddleware($matcher, $this->getWorkbench()->filemanager()->getPathToVendorFolder(), $reader, $template);
        
        $response = $router->process($request, new NotFoundHandler());
        $html = $response->getBody()->__toString();
        
        $html = preg_replace('#(href|src)="(.*)(api\/docs\/)((?:(?!\.md\b)[^"])+)"#','$1="$2vendor/$4"', $html);
        
        return $response->withBody(stream_for($html));
    }
    
    public function getBaseUrl() : string{
        if (is_null($this->url)) {
            $this->url = $this->getWorkbench()->getCMS()->buildUrlToApi() . '/api/docs';
        }
        return $this->url;
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