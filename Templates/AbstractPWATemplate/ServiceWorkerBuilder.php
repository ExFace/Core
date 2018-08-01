<?php
namespace exface\Core\Templates\AbstractPWATemplate;

/**
 * Generates the JS for a ServiceWorker based on the workbox toolkit.
 * 
 * @author Andrej Kabachnik
 *
 */
class ServiceWorkerBuilder
{
    private $routesToCache = [];
    
    private $workboxImportPath = null;
    
    public function __construct(string $workboxImportPath = 'workbox-sw/build/workbox-sw.js')
    {
        $this->workboxImportPath = $workboxImportPath;
    }
    
    public function buildJs() : string
    {
        return <<<JS
importScripts('{$this->workboxImportPath}');

{$this->buildJsRoutesToCache()}

JS;
    }
    
    public function addRouteToCache(string $id, string $regex, string $strategy, string $description = null, string $cacheName = null, int $maxEntries = null, int $maxAgeSeconds = null)
    {
        $this->routesToCache[$id] = [
            'description' => $description,
            'regex' => $regex,
            'strategy' => $strategy,
            'cacheName' => $cacheName, 
            'maxEntries' => $maxEntries,
            'maxAgeSeconds' => $maxAgeSeconds
        ];
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
                $plugins .= "new workbox.expiration.Plugin({" . implode(', ', $params) . "})\n            ";
            }
            
            $cacheName = $route['cacheName'] ? 'cacheName : "' . $route['cacheName'] . '",' : '';
            
            $js .= <<<JS

// Route "{$id}"
// {$route['description']}
workbox.routing.registerRoute(
    /{$route['regex']}/,
    workbox.strategies.{$route['strategy']}({
        {$cacheName}
        plugins: [
            {$plugins}
        ],
    })
);
JS;
        }
        return $js;
    }
}