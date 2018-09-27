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
    
    private $customCode = [];
    
    private $imports = [];
    
    private $workboxImportPath = null;
    
    public function __construct(string $workboxImportPath = 'workbox-sw/build/workbox-sw.js')
    {
        $this->workboxImportPath = $workboxImportPath;
    }
    
    public function buildJs() : string
    {
        return <<<JS
importScripts('{$this->workboxImportPath}');

{$this->buildJsLogic()}
JS;
    }

    public function buildJsImports() : string
    {
        $imports = array_unique($this->imports);
        if (! empty($imports)) {
            $importScript = "importScripts('" . implode("'); \nimportScripts('", $imports) . "');";
        }
        
        return <<<JS
importScripts('{$this->workboxImportPath}');
{$importScript}

JS;
    }

    public function buildJsLogic() : string
    {
        return <<<JS

{$this->buildJsRoutesToCache()}
{$this->buildJsCustomCode()}
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
            
            if (substr($route['strategy'], 0, strlen('workbox.strategies.')) === 'workbox.strategies.') {
                $handler = <<<JS
                
    {$route['strategy']}({
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