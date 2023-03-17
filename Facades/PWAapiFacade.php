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

/**
 * 
 * ## Routes
 * 
 * - `api/pwa/data/<pwaUrl>/<dataSetUid>`
 * - `api/pwa/action/ui5/offline/...`
 * - `api/pwa/errors/<deviceId>`
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
        $path = ltrim(StringDataType::substringAfter($path, $this->getUrlRouteDefault(), $path), '/');
        list($route, $routePath) = explode('/', $path, 2);
        
        $headers = $this->buildHeadersCommon();
        switch (mb_strtolower($route)) {
            case self::ROUTE_ACTION:
                return parent::createResponse($request);
            case self::ROUTE_MODEL:
                $pwaUrl = $routePath;
                $pwa = PWAFactory::createFromURL($this->getWorkbench(), $pwaUrl);
                $pwa->loadModel([
                    OfflineStrategyDataType::PRESYNC
                ]);
                
                $result = [
                    'uid' => $pwa->getUid(),
                    'name' => $pwa->getName(),
                    'scope' => $pwa->getURL(),
                    'data_sets' => []
                ];
                foreach ($pwa->getDatasets() as $dataSet) {
                    $result['data_sets'][] = [
                        'uid' => $dataSet->getUid(),
                        'object_alias' => $dataSet->getMetaObject()->getAliasWithNamespace(),
                        'object_name' => $dataSet->getMetaObject()->getName(),
                        'url' => $this->buildUrlToGetOfflineData($dataSet)
                    ];
                }
                $headers = array_merge($headers, ['Content-Type' => 'application/json']);
                return new Response(200, $headers, JsonDataType::encodeJson($result));
            case self::ROUTE_DATA:
                list($pwaUrl, $dataSetUid) = explode('/', $routePath, 2);
                if (! $pwaUrl) {
                    throw new FacadeRoutingError('PWA not specified in request for offline data');
                }
                if (! $dataSetUid) {
                    throw new FacadeRoutingError('PWA data set not specified in request for offline data');
                }
                
                $pwa = PWAFactory::createFromURL($this->getWorkbench(), $pwaUrl);
                $pwa->loadModel([
                    OfflineStrategyDataType::PRESYNC
                ]);
                
                try {
                    $ds = $pwa->getDataset($dataSetUid)->readData();
                    $result = [
                        'uid' => $dataSetUid,
                        'status' => 'fresh',
                        'uid_column_name' => ($ds->hasUidColumn() ? $ds->getUidColumn()->getName() : null)
                    ];
                    $result = array_merge($result, $ds->exportUxonObject()->toArray());
                } catch (PWADatasetNotFoundError $e) {
                    $result = [
                        'uid' => $dataSetUid,
                        'status' => 'remove'
                    ];
                }
                $headers = array_merge($headers, ['Content-Type' => 'application/json']);
                return new Response(200, $headers, JsonDataType::encodeJson($result));
            case self::ROUTE_ERRORS:
                $deviceId = $routePath;
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
                return new Response(404, $headers);
        }
    }
    
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
    
    protected function buildUrlToGetOfflineData(PWADatasetInterface $dataSet) : string
    {
        return $this->buildUrlToFacade(true) . "/" . self::ROUTE_DATA . "/{$dataSet->getPWA()->getUrl()}/{$dataSet->getUid()}";
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