<?php
namespace exface\Core\Facades\DocsFacade\Middleware;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Factories\DataSheetFactory;
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
class AppUrlRewriterMiddleware implements MiddlewareInterface
{
    private $workbench = null;
    
    private $facade = null;
    
    public function __construct(HttpFacadeInterface $facade)
    {
        $this->workbench = $facade->getWorkbench();
        $this->facade = $facade;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        $html = $response->getBody()->__toString();
        $html = $this->rewriteSourceUrls($html);
        $html = $this->rewriteLocalUrls($html);
        
        return $response->withBody(Utils::streamFor($html));
    }
    
    /**
     * Corrects some typical issues in local URLs.
     * 
     * @param string $html
     * @return string
     */
    protected function rewriteLocalUrls(string $html) : string
    {
        return preg_replace('#(href|src)="(.*)(api\/docs\/)((?:(?!\.md\b)[^"])+)"#','$1="$2vendor/$4"', $html);
    }
    
    /**
     * Rewrites URLs to documentation in other app's sources to local paths if the corresponding app is installed.
     * 
     * @param string $html
     * @return string
     */
    protected function rewriteSourceUrls(string $html) : string
    {
        foreach ($this->getSourceUrlMap() as $repoUrl => $localUrl) {
            $html = preg_replace('#(href|src)="' . $repoUrl . '/(.*\.md\b)"#i', '$1="' . $localUrl . '/$2"', $html);
        }
        
        return $html;
    }
    
    /**
     * Returns an array of URLs to app sources, that can be rewritten to local URLs.
     * 
     * The array has remote URLs as keys and corresponding local URLs as values: e.g.
     * 
     * [
     *  "https://github.com/exface/core/Docs" : "http://localhost/exface/api/docs/exface/Core/Docs"
     * ]
     * 
     * @return string[]
     */
    protected function getSourceUrlMap() : array
    {
        // Get all installed apps
        $appSheet = DataSheetFactory::createFromObjectIdOrAlias($this->workbench, 'exface.Core.APP');
        $cols = $appSheet->getColumns();
        $cols->addFromExpression('ALIAS');
        $cols->addFromExpression('PACKAGE');
        $appSheet->dataRead();
        
        $urls = [];
        foreach ($appSheet->getRows() as $row) {
            if ($url = $this->getRepoDocsUrl($row['PACKAGE'])) {
                $urls[$url] = $this->facade->buildUrlToFacade(). '/' . $row['PACKAGE'] . '/' . StringDataType::substringAfter($url, '/', '', false, true); 
            }
        }
        
        return $urls;
    }
    
    /**
     * Looks for documentation URLs in the given composer package.
     * 
     * This method searchs the composer.json of all installed apps for support/docs
     * or support/source entries (automatically appending "/Docs" to the latter).
     * 
     * @param string $package
     * @return string|NULL
     */
    protected function getRepoDocsUrl(string $package) : ?string
    {
        $appPath = $this->workbench->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $package;
        $composerJsonPath = $appPath . DIRECTORY_SEPARATOR . 'composer.json';
        if (file_exists($composerJsonPath) === false) {
            return null;
        }
        
        $composerJson = json_decode(file_get_contents($composerJsonPath));
        
        if ($composerJson->support === null) {
            return null;
        }
        
        if (! $url = $composerJson->support->docs) {
            if ($url = $composerJson->support->source) {
                return $url . '/Docs';
            } else {
                return null;
            }
        }
        
        return $url;
    }
}