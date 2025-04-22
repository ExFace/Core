<?php
namespace exface\Core\Facades\AbstractHttpFacade\Middleware;

use exface\Core\Exceptions\Facades\HttpBadRequestError;
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
    const ROUTE_NAME_ATTRIBUTE = 'route_name';
    const ROUTE_VERSION_ATTRIBUTE = 'route_version';
    const ROUTE_PATH_ATTRIBUTE = 'route_path';
    private $facade = null;
    
    private $routeData = null;
    
    private $routePatternAttributeAlias = null;
    
    private $routeConfigAttributeAlias = null;
    
    private $storeInRequestAttr = null;

    private $routeVersionAttributeAlias = null;
    private ?string $basePath;

    /**
     *
     * @param HttpFacadeInterface $facade
     * @param DataSheetInterface $routeData
     * @param string[] $routePatternAttributeAlias
     * @param string $routeConfigAttributeAlias
     * @param string $storeInRequestAttr
     */
    public function __construct(HttpFacadeInterface $facade, DataSheetInterface $routeData, string $routePatternAttributeAlias, string $routeConfigAttributeAlias, string $routeVersionAttributeAlias = null, string $basePath = null, string $storeInRequestAttr = 'route')
    {
        $this->facade = $facade;
        $this->basePath = $basePath;
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
        try {
            $routeComponents = $this->extractUrlComponents($path, $this->basePath);
            $routeData = $this->getRouteData($path, $routeComponents);
        } catch (FacadeRoutingError $e) {
            throw new HttpBadRequestError($request, $e->getMessage(), null, $e);
        }
        
        if (null !== $json = $routeData[$this->routeConfigAttributeAlias]) {
            $this->facade->importUxonObject(UxonObject::fromJson($json));
        }
        $request = $request->withAttribute(self::ROUTE_NAME_ATTRIBUTE, $routeComponents[self::ROUTE_NAME_ATTRIBUTE]);
        $request = $request->withAttribute(self::ROUTE_VERSION_ATTRIBUTE, $routeComponents[self::ROUTE_VERSION_ATTRIBUTE]);
        $request = $request->withAttribute(self::ROUTE_PATH_ATTRIBUTE, $routeComponents[self::ROUTE_PATH_ATTRIBUTE]);
        return $handler->handle($request->withAttribute($this->storeInRequestAttr, $routeData));
    }
    
    /**
     * @param string $path
     * @return string[]
     *@throws FacadeRoutingError
     */
    protected function getRouteData(string $path, array $routeComponents) : array
    {
        // url has to be: /service_name/version/route_name like bmdb-gis-export/1.24.4/Massnahmen
        $version = $routeComponents[self::ROUTE_VERSION_ATTRIBUTE] ?? null;
        $matchedRoute = null;
        $rowVersions = [];
        foreach ($this->routeData->getRows() as $row) {
            $currentRouteUrl = $row[$this->routePatternAttributeAlias];
            if (strcasecmp($routeComponents[self::ROUTE_NAME_ATTRIBUTE], $currentRouteUrl) === 0) {
                $rowVersions[] = $row;
            }
        }
        
        // If no versions are used (= no version in URL or route definiton) and there is only one
        // match, take it. Otherwise apply the version constraint and find the best fit.
        if ($version === null && count($rowVersions) === 1 && empty($rowVersions[0][$this->routeVersionAttributeAlias])) {
            $matchedRoute = $rowVersions[0];
        } else {
            $versions = array_column($rowVersions, $this->routeVersionAttributeAlias);
            $bestMatch = SemanticVersionDataType::findVersionBest($version ?? SemanticVersionDataType::WILDCARD, $versions);
            if ($bestMatch !== null) {
                $matchedRoute = $rowVersions[array_search($bestMatch, $versions)]; 
            }
        }

        if ($matchedRoute === null) {
            throw new FacadeRoutingError('No route configuration found for "' . $path . '"');
        }

        return $matchedRoute;
    }

    /**
     * Extracts all important components from the given url into an array.
     * Expected format: 'domain.com/api/dataflow/webservice/(version)/route'.
     *
     * @param $url
     * @param $basePath
     * @throws \exface\Core\Exceptions\Facades\FacadeRoutingError
     * @return array{webservice: string, version: string, route: string}
     */
    public static function extractUrlComponents($url, $basePath): array
    {
        $path = StringDataType::substringAfter($url, $basePath . '/');
        $components = explode('/', $path);

        // Ensure we have at least webservice and version within the url
        if (count($components) < 2) {
            throw new FacadeRoutingError('Requested URL ´' . $url . '´ is invalid.');
        }

        $webservice = $components[0];
        $version = null;
        $routeStartIndex = 1;

        // Check if path contains version
        if (SemanticVersionDataType::isValueVersion($components[1])) {
            $version = $components[1];
            $routeStartIndex = 2;
        }

        // TODO: any sub path parameter like /{id} are not yet possible, we need the API to specify its path components without their values
        $route = $components[$routeStartIndex];

        return [
            self::ROUTE_NAME_ATTRIBUTE => $webservice,
            self::ROUTE_VERSION_ATTRIBUTE => $version,
            // route always starts with ´/´ in OpenApi definition
            self::ROUTE_PATH_ATTRIBUTE => '/' . $route
        ];
    }

    public static function getRouteName($request) {
        return $request->getAttribute(self::ROUTE_NAME_ATTRIBUTE);
    }

    public static function getRouteVersion($request) {
        return $request->getAttribute(self::ROUTE_VERSION_ATTRIBUTE);
    }

    public static function getRoutePath($request) {
        return $request->getAttribute(self::ROUTE_PATH_ATTRIBUTE);
    }
}