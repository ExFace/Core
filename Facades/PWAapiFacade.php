<?php
namespace exface\Core\Facades;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use exface\Core\DataTypes\StringDataType;
use GuzzleHttp\Psr7\Response;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\SortingDirectionsDataType;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\Facades\FacadeRoutingError;
use exface\Core\Factories\PWAFactory;
use exface\Core\DataTypes\OfflineStrategyDataType;
use exface\Core\DataTypes\JsonDataType;
use exface\Core\Exceptions\PWA\PWADatasetNotFoundError;
use exface\Core\Interfaces\PWA\PWADatasetInterface;
use exface\Core\Exceptions\PWA\PWANotFoundError;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Exceptions\Facades\HttpBadRequestError;

/**
 * 
 * ## Routes
 * 
 * - `api/pwa/<pwaUrl>/data?dataset_uid=<dataSetUid>`
 * - `api/pwa/action/ui5/offline/...`
 * - `api/pwa/errors?deviceId=<deviceId>`
 * 
 * @author Andrej Kabachnik
 *
 */
class PWAapiFacade extends HttpTaskFacade
{
    const ROUTE_MODEL = 'model';
    
    const ROUTE_ACTION = 'action';
    
    const ROUTE_DATA = 'data';
    
    const ROUTE_ERRORS = 'errors';
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        $path = $request->getUri()->getPath();
        // data/pwa_url/dataset_uid
        $path = ltrim(StringDataType::substringAfter($path, $this->getUrlRouteDefault(), $path), '/');
        // pwa_url/data/dataset_uid
        list($pwaUrl, $route, $routePath) = explode('/', $path, 3);
        
        // Switch $pwaUrl and $route for compatibility with old routings
        if(($pwaUrl === self::ROUTE_MODEL || self::ROUTE_DATA) && empty($route) ? false : $this->isPwaUrl($route)) {
            $tmp = $pwaUrl;
            $pwaUrl = $route;
            $route = $tmp;
        }
        
        $headers = $this->buildHeadersCommon();
        $route = mb_strtolower($route);
        switch (true) {
            case $route === self::ROUTE_ACTION:
                return parent::createResponse($request);

            case $route === self::ROUTE_MODEL:
                $pwa = PWAFactory::createFromURL($this->getWorkbench(), $pwaUrl);
                $pwa->loadModel([
                    OfflineStrategyDataType::PRESYNC
                ]);
                
                $result = [
                    'uid' => $pwa->getUid(),
                    'name' => $pwa->getName(),
                    'scope' => $pwa->getURL(),
                    'username' => $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername(),
                    'version' => $pwa->getVersion(),
                    'data_sets' => []
                ];
                foreach ($pwa->getDatasets() as $dataSet) {
                    $result['data_sets'][] = [
                        'uid' => $dataSet->getUid(),
                        'object_alias' => $dataSet->getMetaObject()->getAliasWithNamespace(),
                        'object_name' => $dataSet->getMetaObject()->getName(),
                        'url' => $this->buildUrlToGetOfflineData($dataSet),
                        'columns_with_download_urls' => $dataSet->getBinaryDataTypeColumnNames(),
                        'columns_with_image_urls' => $dataSet->getImageUrlDataTypeColumnNames()
                    ];
                }
                $headers = array_merge($headers, ['Content-Type' => 'application/json']);
                return new Response(200, $headers, JsonDataType::encodeJson($result));

            case $route === self::ROUTE_DATA:
                // Check if array contains DataSetUid, if not, set dataSetUid to old routePath
                // api/pwa/<pwaUrl>/data?dataSetUid=<dataSetUid>
                if (array_key_exists("dataset", $request->getQueryParams())) {
                    $dataSetUid = $request->getQueryParams()['dataset'];
                } else {
                    // api/pwa/<pwaUrl>/data/<dataSetUid>
                    $dataSetUid = $routePath;
                }

                if (! $pwaUrl) {
                    throw new FacadeRoutingError('PWA not specified in request for offline data');
                }
                if (! $dataSetUid) {
                    throw new FacadeRoutingError('PWA data set not specified in request for offline data');
                }
                
                try {
                    $pwa = PWAFactory::createFromURL($this->getWorkbench(), $pwaUrl);
                } catch (PWANotFoundError $e) {
                    $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
                    return new Response(404, $headers);
                }
                $pwa->loadModel([
                    OfflineStrategyDataType::PRESYNC
                ]);

                try {
                    $ds = $pwa->getDataset($dataSetUid)->readData();
                    $result = [
                        'uid' => $dataSetUid,
                        'status' => 'fresh',
                        'uid_column_name' => ($ds->hasUidColumn() ? $ds->getUidColumn()->getName() : null),
                        'username' => $this->getWorkbench()->getSecurity()->getAuthenticatedToken()->getUsername(),
                        'version' => $pwa->getVersion()
                    ];
                    $result = array_merge($result, $ds->exportUxonObject()->toArray());
                } catch (PWADatasetNotFoundError $e) {
                    $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::DEBUG);
                    return new Response(404, $headers);
                }
                $headers = array_merge($headers, ['Content-Type' => 'application/json']);
                return new Response(200, $headers, JsonDataType::encodeJson($result));
                
            case $pwaUrl === self::ROUTE_ERRORS:
                // Check if url parameter deviceId is set, if not, set deviceId to old routePath
                // api/pwa/errors?deviceId=<deviceId>
                if (array_key_exists("deviceId", $request->getQueryParams())) {
                    $deviceId = $request->getQueryParams()['deviceId'];
                } else {
                    // api/pwa/errors/<deviceId>
                    $deviceId = $route;
                }
                
                if ($deviceId) {
                    $uxon = $this->getErrorsDataSheet($deviceId)->exportUxonObject();
                    $uxon->unsetProperty('filters');
                    $json = $uxon->toJson();
                    return new Response(200, $headers, $json);
                } else {
                    return new response(200, $headers, '{}');
                }
                break;
            default:
                $this->getWorkbench()->getLogger()->logException(new HttpBadRequestError('Route "' . $route . '" not found in facade "' . $this->getAliasWithNamespace() . '"'));
                return new Response(400, $headers);
        }
    }
    
    /**
     * 
     * @param string $value
     * @return bool
     */
    protected function isPwaUrl(string $value) : bool
    {
        try {
            PWAFactory::createFromURL($this->getWorkbench(), $value);
            return true;
        } catch (PWANotFoundError $e) {
            return false;
        }
    }
    
    /**
     * 
     * @param string $deviceId
     * @return DataSheetInterface
     */
    protected function getErrorsDataSheet(string $deviceId) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.QUEUED_TASK');
        $ds->getColumns()->addMultiple([
            "MESSAGE_ID",
            "OBJECT_ALIAS",
            "ACTION_ALIAS",
            "TASK_ASSIGNED_ON",
            "ERROR_LOGID",
            "ERROR_MESSAGE",
        ]);
        $ds->getFilters()->addConditionFromValueArray('STATUS', [20,70]);
        $ds->getFilters()->addConditionFromString('PRODUCER', $deviceId, ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('OWNER', $this->getWorkbench()->getSecurity()->getAuthenticatedUser()->getUid());
        $ds->getSorters()->addFromString("TASK_ASSIGNED_ON", SortingDirectionsDataType::ASC);
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * New formatting of routes
     * api/pwa/<pwaUrl>/data?dataset_uid=<dataSetUid>
     * @param PWADatasetInterface $dataSet
     * @return string
     */
    protected function buildUrlToGetOfflineData(PWADatasetInterface $dataSet) : string
    {
        return $this->buildUrlToFacade(true) . "/{$dataSet->getPWA()->getUrl()}/" . self::ROUTE_DATA . "?dataset={$dataSet->getUid()}";
    }
    
    /**
     * Old formatting of routes
     * api/pwa/<pwaUrl>/data/<dataSetUid>
     * @param PWADatasetInterface $dataSet
     * @return string
     */
    protected function buildUrlToGetOfflineDataDeprecated(PWADatasetInterface $dataSet) : string
    {
        return $this->buildUrlToFacade(true) . "/{$dataSet->getPWA()->getUrl()}/" . self::ROUTE_DATA . "/{$dataSet->getUid()}";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault() : string
    {
        return 'api/pwa';
    }
    
    /**
     *
     * @return string[]
     */
    protected function buildHeadersCommon() : array
    {
        return array_filter($this->getConfig()->getOption('FACADES.PWAAPIFACADE.HEADERS.COMMON')->toArray());
    }
}