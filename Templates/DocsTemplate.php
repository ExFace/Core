<?php
namespace exface\Core\Templates;

use exface\Core\Templates\AbstractTemplate\AbstractTemplate;
use exface\Core\Interfaces\Templates\HttpTemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use kabachello\FileRoute\FileRouteMiddleware;
use Psr\Http\Message\UriInterface;
use kabachello\FileRoute\Templates\PlaceholderFileTemplate;
use exface\Core\Templates\AbstractHttpTemplate\NotFoundHandler;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\Filemanager;
use function GuzzleHttp\Psr7\stream_for;
use exface\Core\Templates\DocsTemplate\MarkdownDocsReader;

/**
 *  
 * @author Andrej Kabachnik
 *
 */
class DocsTemplate extends AbstractTemplate implements HttpTemplateInterface
{    
    private $url = null;
    
    protected function init()
    {
        parent::init();
        if (! $this->getWorkbench()->isStarted()){
            $this->getWorkbench()->start();
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $matcher = function(UriInterface $uri) {
            $path = $uri->getPath();
            $url = StringDataType::substringAfter($path, '/api/docs');
            $url = ltrim($url, "/");
            if ($q = $uri->getQuery()) {
                $url .= '?' . $q;
            }
            return $url;
        };
        $reader = new MarkdownDocsReader($this->getWorkbench());
        $templatePath = Filemanager::pathJoin([$this->getApp()->getDirectoryAbsolutePath(), 'Templates/DocsTemplate/template.html']);
        $template = new PlaceholderFileTemplate($templatePath, $this->getBaseUrl());
        $template->setBreadcrumbsRootName('Documentation');
        $router = new FileRouteMiddleware($matcher, $this->getWorkbench()->filemanager()->getPathToVendorFolder(), $reader, $template);
        
        $response = $router->process($request, new NotFoundHandler());
        $html = $response->getBody()->__toString();
        
        $html = preg_replace('#(href|src)="(.*)(api\/docs\/)((?:(?!\.md\b)[^"])+)"#','$1="$2vendor/$4"', $html);
        
        return $response->withBody(stream_for($html));
    }
    
    /**
     * 
     * @return string
     */
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
            "/\/api\/docs[\/?]/"
        ];
    }
}