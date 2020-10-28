<?php
namespace exface\Core\Facades\AbstractPWAFacade;

use exface\Core\CommonLogic\UxonObject;

/**
 * Generates the JS for a ServiceWorker based on the workbox toolkit.
 * 
 * @author Andrej Kabachnik
 *
 */
class ServiceWorkerBuilder
{
    private $routesToCache = [];
    
    private $customCode = [];
    
    private $imports = [];
    
    private $workboxImportPath = null;
    
    private $basePath = '';
    
    /**
     * Creates a service worker builder for a specific base path (relative URL path from the
     * service worker location to the includes location).
     * 
     * @param string $basePath
     * @param string $workboxImportPath
     */
    public function __construct(string $basePath = '', string $workboxImportPath = 'workbox-sw/build/workbox-sw.js')
    {
        $this->workboxImportPath = $workboxImportPath;
        if ($basePath) {
            $this->basePath = rtrim($basePath, "/") . "/";
        }
    }
    
    public function buildJs() : string
    {
        return <<<JS
importScripts('{$this->basePath}{$this->workboxImportPath}');

{$this->buildJsLogic()}
JS;
    }

    public function buildJsImports() : string
    {
        $imports = array_unique($this->imports);
        foreach ($imports as $import) {
            $importScript .= PHP_EOL . "importScripts('{$this->basePath}{$import}');";
        }
        
        return <<<JS
importScripts('{$this->basePath}{$this->workboxImportPath}');
{$importScript}

JS;
    }

    public function buildJsLogic() : string
    {
        return <<<JS

{$this->buildJsCustomCode()}
{$this->buildJsRoutesToCache()}
JS;
    }
    
    public function addRouteToCache(
        string $id, 
        string $matcher, 
        string $strategy, 
        string $method = null,
        string $description = null, 
        string $cacheName = null, 
        int $maxEntries = null, 
        int $maxAgeSeconds = null)
    {
        $this->routesToCache[$id] = [
            'description' => $description,
            'matcher' => $matcher,
            'strategy' => $strategy,
            'method' => $method,
            'cacheName' => $cacheName, 
            'maxEntries' => $maxEntries,
            'maxAgeSeconds' => $maxAgeSeconds
        ];
        return $this;
    }
    
    /**
     * 
     * @param string $routeId
     * @param UxonObject $uxon
     * @return ServiceWorkerBuilder
     */
    public function addRouteFromUxon(string $routeId, UxonObject $uxon) : ServiceWorkerBuilder
    {
        $this->addRouteToCache(
            $routeId,
            $uxon->getProperty('matcher'),
            $uxon->getProperty('strategy'),
            $uxon->getProperty('method'),
            $uxon->getProperty('description'),
            $uxon->getProperty('cacheName'),
            $uxon->getProperty('maxEntries'),
            $uxon->getProperty('maxAgeSeconds')
        );
        return $this;
    }
    
    protected function getRoutesToCache() : array
    {
        return $this->routesToCache;
    }
    
    protected function buildJsRoutesToCache() : string
    {
        $js = '';
        foreach ($this->getRoutesToCache() as $id => $route) {
            
            $plugins = '';
            $params = [];
            if ($route['maxAgeSeconds']) {
                $params[] = 'maxAgeSeconds: ' . $route['maxAgeSeconds'];
            }
            if ($route['maxEntries']) {
                $params[] = 'maxEntries: ' . $route['maxEntries'];
            }
            if (! empty($params)) {
                $plugins .= "new workbox.expiration.ExpirationPlugin({" . implode(', ', $params) . "})\n            ";
            }
            
            $cacheName = $route['cacheName'] ? 'cacheName : "' . $route['cacheName'] . '",' : '';
            
            if (substr($route['strategy'], 0, strlen('workbox.strategies.')) === 'workbox.strategies.') {
                $handler = <<<JS
                
    new {$route['strategy']}({
        {$cacheName}
        plugins: [
            {$plugins}
        ],
    })
JS;
            } elseif (substr($route['strategy'], 0, strlen('swTools.strategies.')) === 'swTools.strategies.') {
                $handler = $route['strategy'] . "()";
            } else {
                $handler = $route['strategy'];
            }
            
            $method = $route['method'] !== null ? ", '{$route['method']}'" : '';
            
            $js .= <<<JS

// Route "{$id}"
// {$route['description']}
workbox.routing.registerRoute(
    {$route['matcher']},
    {$handler}
    {$method}
);

JS;
        }
        return $js;
    }
    
    public function addCustomCode(string $js, string $description = '') : ServiceWorkerBuilder
    {
        $this->customCode[$js] = $description;
        return $this;
    }
    
    protected function buildJsCustomCode() : string
    {
        $js = '';
        foreach ($this->customCode as $code => $comment) {
            $js .= <<<JS

// {$comment}
{$code}

JS;
        }
        return $js;
    }
    
    public function addImport(string $path) : ServiceWorkerBuilder
    {
        $this->imports[] = $path;
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getImports() : array
    {
        return $this->imports;
    }
}