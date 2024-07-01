<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use Composer\Semver\Comparator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\SemanticVersionDataType;

/**
 * Allows to load facade configuration stored in a meta object holding web routes and their parameters
 * 
 * This middleware requires a data sheet with routes and corresponding facade configuration
 * UXONs. It will match the route of every request against this data, find its configuration
 * and load it. It will also store the entire data row in a request parameter, so it can
 * be easily accessed by subsequent request handlers.
 * 
 * This allows to use different facade configuration depending on the route - like in
 * the DataFlowFacade (object `axenox.ETL.webservice`). One of the most important use 
 * cases is the configuration of available authentication options - see 
 * `AbstractHttpFacade::setAuthentication()`
 * 
 * @author Andrej Kabachnik
 *
 */
class RouteConfigLoader implements MiddlewareInterface
{
    private $facade = null;
    
    private $routeData = null;
    
    private $routePatternAttributeAlias = null;
    
    private $routeConfigAttributeAlias = null;
    
    private $storeInRequestAttr = null;

    private $routeVersionAttributeAlias = null;

    /**
     *
     * @param HttpFacadeInterface $facade
     * @param DataSheetInterface $routeData
     * @param string[] $routePatternAttributeAlias
     * @param string $routeConfigAttributeAlias
     * @param string $storeInRequestAttr
     */
    public function __construct(HttpFacadeInterface $facade, DataSheetInterface $routeData, string $routePatternAttributeAlias, string $routeConfigAttributeAlias, string $routeVersionAttributeAlias = null, string $storeInRequestAttr = 'route')
    {
        $this->facade = $facade;
        $this->storeInRequestAttr = $storeInRequestAttr;
        $this->routeData = $routeData;
        $this->routePatternAttributeAlias = $routePatternAttributeAlias;
        $this->routeConfigAttributeAlias = $routeConfigAttributeAlias;
        $this->routeVersionAttributeAlias = $routeVersionAttributeAlias;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $path = StringDataType::substringAfter($path, $this->facade->getUrlRouteDefault() . '/', '');
        $routeData = $this->getRouteData($path);
        
        if (null !== $json = $routeData[$this->routeConfigAttributeAlias]) {
            $this->facade->importUxonObject(UxonObject::fromJson($json));
        }
        
        return $handler->handle($request->withAttribute($this->storeInRequestAttr, $routeData));
    }
    
    /**
     * @param string $route
     * @throws FacadeRoutingError
     * @return string[]
     */
    protected function getRouteData(string $route) : array
    {
        $route = strtok($route, '/');
        $version = trim(strtok($route), '/');
        $hasValidVersion = SemanticVersionDataType::isValueVersion($version);
        $matchedRoute = null;
        $highestVersion = null;
        foreach ($this->routeData->getRows() as $row) {
            $currentRouteUrl = $row[$this->routePatternAttributeAlias];
            $currentRouteVersion = $row[$this->routeVersionAttributeAlias];

            if (strcasecmp($route, $currentRouteUrl) === 0) {
                if ($hasValidVersion && $currentRouteVersion === $version) {
                    return $row;
                }

                if (!$hasValidVersion) {
                    if ($highestVersion === null || SemanticVersionDataType::isVersionGreaterThan($currentRouteVersion, $highestVersion)) {
                        $highestVersion = $currentRouteVersion;
                        $matchedRoute = $row;
                    }
                }
            }
        }

        if ($matchedRoute === null) {
            throw new FacadeRoutingError('No route configuration found for "' . $route . '"');
        }

        return $matchedRoute;
    }
}