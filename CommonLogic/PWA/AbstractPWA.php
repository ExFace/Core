<?php
namespace exface\Core\CommonLogic\PWA;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\PWA\PWAInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Selectors\PWASelectorInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Model\UiMenuItemInterface;
use exface\Core\Interfaces\PWA\PWARouteInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\PWA\PWADatasetInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Widgets\Data;
use exface\Core\Widgets\InputComboTable;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Widgets\Dialog;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Tasks\HttpTask;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\Interfaces\UserImpersonationInterface;
use exface\Core\Exceptions\PWA\PWADatasetNotFoundError;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Interfaces\Selectors\UiPageSelectorInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\CommonLogic\Security\Authorization\UiPageAuthorizationPoint;

abstract class AbstractPWA implements PWAInterface
{
    use ImportUxonObjectTrait;
    
    const KEY_ACTION = 'action';
    const KEY_WIDGET = 'widget';
    const KEY_UID = 'UID';
    const KEY_DATASET = 'dataset';
    const KEY_OFFLINE_STRATEGY = 'offline strategy';
    
    private $workbench = null;
    
    private $routes = [];
    
    private $actions = [];
    
    private $dataSets = [];
    
    private $selector = null;
    
    private $facade = null;
    
    private $startPageSelector = null;
    
    private $startPage = null;
    
    private $modelLoadedForStrategies = null;
    
    private $dsPWA = null;
    
    public function __construct(PWASelectorInterface $selector, FacadeInterface $facade)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
        $this->facade = $facade;
    }
    
    public function generateModel(DataTransactionInterface $transaction = null) : \Generator
    {
        if ($transaction === null) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        $this->routes = [];
        $this->dataSets = [];
        $this->actions = [];
        
        yield from $this->generateModelForWidget($this->getStartPage()->getWidgetRoot());
        $resultMsg = '';
        foreach ($this->saveModel($transaction) as $msg) {
            $resultMsg .= $msg;
            yield $msg;
        }
        
        if ($commit) {
            $transaction->commit();
        }
        
        return $resultMsg;
    }
    
    public function getStartPageSelector() : UiPageSelectorInterface
    {
        if ($this->startPageSelector === null) {
            $this->startPageSelector = new UiPageSelector($this->getWorkbench(), $this->getDataForPWA()->getCellValue('START_PAGE', 0));
        }
        return $this->startPageSelector;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWAInterface::getStartPage()
     */
    public function getStartPage() : UiPageInterface
    {
        if ($this->startPage === null) {
            $this->startPage = UiPageFactory::createFromModel($this->getWorkbench(), $this->getStartPageSelector());
        }
        return $this->startPage;
    }
    
    /**
     * 
     * @return FacadeInterface
     */
    public function getFacade(): FacadeInterface
    {
        return $this->facade;
    }
    
    /**
     * 
     * @return PWARouteInterface[]
     */
    public function getRoutes() : array
    {
        return $this->routes;
    }
    
    /**
     * 
     * @param PWARouteInterface $route
     * @return PWAInterface
     */
    protected function addRoute(PWARouteInterface $route) : PWAInterface
    {
        $this->routes[] = $route;
        $action = $route->getAction();
        if ($action !== null) {
            $this->addAction($action, $route->getWidget());
        }
        return $this;
    }
    
    /**
     * 
     * @return ActionInterface[]
     */
    public function getActions() : array
    {
        $actions = [];
        foreach ($this->getActionCache() as $a) {
            $actions[] = $a[self::KEY_ACTION];
        }
        return $actions;
    }
    
    /**
     * 
     * @param string $uid
     * @return ActionInterface|NULL
     */
    protected function getActionByPWAModelUID(string $uid) : ?ActionInterface
    {
        foreach ($this->getActionCache() as $a) {
            if (strcasecmp($a[self::KEY_UID] ?? '', $uid) === 0) {
                return $a[self::KEY_ACTION];
            }
        }
        return null;
    }
    
    protected function getActionIndex(ActionInterface $action) : ?int
    {
        foreach ($this->getActionCache() as $i => $a) {
            if ($a[self::KEY_ACTION] === $action) {
                return $i;
            }
        }
        return null;
    }
    
    /**
     * 
     * @param PWARouteInterface $action
     * @return PWAInterface
     */
    protected function addAction(ActionInterface $action, WidgetInterface $triggerWidget, string $modelUID = null) : int
    {
        $idx = $this->getActionIndex($action);
        if ($idx === null) {
            $this->actions[] = [
                self::KEY_ACTION => $action,
                self::KEY_WIDGET => $triggerWidget,
                self::KEY_UID => $modelUID,
                self::KEY_OFFLINE_STRATEGY => $this->findOfflineStrategy($action, $triggerWidget),
                self::KEY_DATASET => null
            ];
            $idx = array_key_last($this->actions);
        }
        return $idx;
    }
    
    /**
     * 
     * @return array
     */
    protected function getActionCache() : array
    {
        return $this->actions;
    }
    
    /**
     * 
     * @param ActionInterface $action
     * @param string $itemKey
     * @return null|array|ActionInterface|WidgetInterface|PWADatasetInterface
     */
    protected function getActionCacheItem(ActionInterface $action, string $itemKey = null)
    {
        foreach ($this->getActionCache() as $a) {
            if ($a[self::KEY_ACTION] === $action) {
                if ($itemKey !== null) {
                    return $a[$itemKey] ?? null;
                } else {
                    return $a;
                }
            } 
        }
        return null;
    }
    
    protected function setActionCacheItem(ActionInterface $action, string $itemKey, $item) : PWAInterface
    {
        foreach ($this->getActionCache() as $i => $a) {
            if ($a[self::KEY_ACTION] === $action) {
                $this->actions[$i][$itemKey] = $item;
                return $this;
            }
        }
        throw new UnexpectedValueException('Cannot add ' . $itemKey . ' to PWA action: action ' . $action->getAliasWithNamespace() . ' not yet included');
    }
    
    protected function getActionDataSet(ActionInterface $action) : ?PWADatasetInterface
    {
        return $this->getActionCacheItem($action, self::KEY_DATASET);
    }
    
    protected function getActionWidget(ActionInterface $action) : WidgetInterface
    {
        return $this->getActionCacheItem($action, self::KEY_WIDGET);
    }
    
    protected function getActionPWAModelUID(ActionInterface $action) : ?string
    {
        return $this->getActionCacheItem($action, self::KEY_UID);
    }
    
    public function getActionOfflineStrategy(ActionInterface $action) : string
    {
        return $this->getActionCacheItem($action, self::KEY_OFFLINE_STRATEGY);
    }
    
    /**
     * 
     * @return PWADatasetInterface[]
     */
    public function getDatasets() : array
    {
        return $this->dataSets;        
    }
    
    public function getDataset(string $uid) : PWADatasetInterface
    {
        foreach ($this->getDatasets() as $set) {
            if ($set->getUid() === $uid) {
                return $set;
            }
        }
        throw new PWADatasetNotFoundError('Data set with UID "' . $uid . '" not found in PWA "' . $this->getUid() . '"');
    }
    
    public function addData(DataSheetInterface $dataSheet, ActionInterface $action, WidgetInterface $widget) : PWADatasetInterface
    {
        $set = $this->findDataSet($dataSheet);
        if ($set === null) {
            $set = new PWADataset($this, $dataSheet->copy()->setRowsLimit(null)->setRowsOffset(0));
            $this->dataSets[] = $set;
        } else {
            $set->includeData($dataSheet);
        }
        
        $setSheet = $set->getDataSheet();
        if ($widget instanceof iHaveFilters) {
            foreach ($widget->getFilters() as $filter) {
                if ($filter->isBoundToAttribute()) {
                    if (! $setSheet->getColumns()->getByAttribute($filter->getAttribute())) {
                        $setSheet->getColumns()->addFromAttribute($filter->getAttribute(), true);
                    }
                }
            }
        }
        
        $this->setActionCacheItem($action, self::KEY_DATASET, $set);
        return $set;
    }
    
    public function addDataSet(PWADatasetInterface $dataSet) : PWAInterface
    {
        $this->dataSets[] = $dataSet;
        return $this;
    }
    
    public function findDataSet(DataSheetInterface $dataSheet) : ?PWADatasetInterface
    {
        $match = null;
        foreach ($this->getDatasets() as $set) {
            if ($set->canInclude($dataSheet) === true) {
                $match = $set;
                break;
            }
        }
        return $match;
    }
    
    /**
     * 
     * @param DataTransactionInterface $transaction
     * @return \Generator
     */
    protected function saveModel(DataTransactionInterface $transaction) : \Generator
    {
        // Data sets
        $dsDatasets = $this->getDataForDatasets();
        $newDataSets = $dsDatasets->copy()->removeRows();
        foreach ($this->getDatasets() as $set) {
            $dataSheetJson = $set->getDataSheet()->exportUxonObject()->toJson();
            if (false !== $rowIdx = $dsDatasets->getColumns()->get('DATA_SHEET_UXON')->findRowByValue($dataSheetJson)) {
                $row = $dsDatasets->getRow($rowIdx);
            } else {
                $row = [];
            }
            try {
                $rowCnt = $set->estimateRows();
            } catch (\Throwable $e) {
                $rowCnt = null;
                $this->getWorkbench()->getLogger()->logException(new RuntimeException('Cannot estimate size of offline data set: ' . $e->getMessage(), null, $e));
            }
            $newDataSets->addRow(array_merge($row, [
                'PWA' => $this->getUid(),
                'DESCRIPTION' => $this->getDescriptionOf($set),
                'OBJECT' => $set->getMetaObject()->getId(),
                'DATA_SHEET_UXON' => $set->getDataSheet()->exportUxonObject()->toJson($dataSheetJson),
                'USER_DEFINED_FLAG' => 0,
                'ROWS_AT_GENERATION_TIME' => $rowCnt
            ]), false, false);
        }
        yield 'Generated ' . $newDataSets->countRows() . ' offline data sets' . PHP_EOL;
        $newDataSets->dataReplaceByFilters($transaction);
        $uids = $newDataSets->getUidColumn()->getValues();
        foreach ($this->getDatasets() as $i => $set) {
            $set->setUid($uids[$i]);
        }
        
        // Actions
        $dsActions = $this->getDataForActions();
        $dsActions->removeRows();
        foreach ($this->getActions() as $action) {
            $widget = $this->getActionWidget($action);
            $dsActions->addRow([
                'PWA' => $this->getUid(),
                'PAGE' => $widget ? $widget->getPage()->getId() : null,
                'TRIGGER_WIDGET_ID' => $widget ? $widget->getId() : null,
                'TRIGGER_WIDGET_TYPE' => $widget ? $widget->getWidgetType() : null,
                'DESCRIPTION' => $this->getDescriptionOf($action),
                'OFFLINE_STRATEGY_IN_FACADE' => $this->getActionOfflineStrategy($action),
                'ACTION_ALIAS' => $action->getAliasWithNamespace(),
                'OBJECT' => $action->getMetaObject()->getId(),
                'PWA_DATASET' => null !== ($set = $this->getActionDataSet($action)) ? $set->getUid() : null
            ], false, false);
        }
        yield 'Generated ' . $dsActions->countRows() . ' actions' . PHP_EOL;
        $dsActions->dataReplaceByFilters($transaction);
        foreach ($dsActions->getUidColumn()->getValues() as $i => $uid) {
            $this->actions[$i][self::KEY_UID] = $uid;
        }
        
        // Routes
        $dsRoutes = $this->getDataForRoutes();
        $newRoutes = [];
        $urlCol = $dsRoutes->getColumns()->get('URL');
        $oldRoutes = $urlCol->getValues();
        $dsRoutes->removeRows();
        foreach($this->getRoutes() as $route) {
            $newRoutes[] = $route->getUrl();
            /*
            if (in_array($route->getUrl(), $oldRoutes, true)) {
                $dsRoutes->removeRow(array_search($route->getUrl(), $oldRoutes));
            }*/
            $dsRoutes->addRow([
                'PWA' => $this->getUid(),
                'PWA_ACTION' => $this->getActionPWAModelUID($route->getAction()),
                'URL' => $route->getUrl(),
                'DESCRIPTION' => $this->getDescriptionOf($route),
                'USER_DEFINED_FLAG' => 0
            ], false, false);
        }
        yield 'Generated ' . count($newRoutes) . ' routes' . PHP_EOL;
        $deletedRoutes = array_diff($oldRoutes, $newRoutes);
        // TODO remove routes if they are not user defined
        
        $dsRoutes->dataReplaceByFilters($transaction);
        
        // Remove builds
        $dsBuilds = $this->getDataForBuilds();
        $cntBuilds = $dsBuilds->dataDelete($transaction);
        yield 'Removed ' . $cntBuilds . ' cached builds' . PHP_EOL;
        
        // Update PWA
        $dsPWA = $this->getDataForPWA();
        $dsPWA->setCellValue('GENERATED_ON', 0, DateTimeDataType::now());
        $dsPWA->dataUpdate(false, $transaction);
    }
    
    protected abstract function generateModelForWidget(WidgetInterface $widget, int $linkDepth = 100) : \Generator;
    
    protected abstract function findOfflineStrategy(ActionInterface $action, WidgetInterface $triggerWidget) : string;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWAInterface::loadModel()
     */
    public function loadModel(array $offlineStrategies = []) : PWAInterface
    {
        $this->modelLoadedForStrategies = $offlineStrategies;
        
        $this->checkAuthorization($this->getWorkbench()->getSecurity()->getAuthenticatedToken());
        
        // Load actions
        $ds = $this->getDataForActions($offlineStrategies);
        foreach ($ds->getRows() as $row) {
            $task = new HttpTask($this->getWorkbench());
            $task->setActionSelector($row['ACTION_ALIAS']);
            $task->setPageSelector($row['PAGE']);
            $task->setWidgetIdTriggeredBy($row['TRIGGER_WIDGET_ID']);
            $task->setMetaObjectSelector($row['OBJECT']);
            $action = $task->getAction();
            $this->addAction($action, $task->getWidgetTriggeredBy(), $row['UID']);
        }
        
        // Load routes
        $ds = $this->getDataForRoutes($offlineStrategies, ['PWA_ACTION__PAGE', 'PWA_ACTION__TRIGGER_WIDGET_ID']);
        foreach ($ds->getRows() as $row) {
            $this->addRoute(new PWARoute($this, $row['URL']));
        }
        
        // Load data sets
        $ds = $this->getDataForDatasets($offlineStrategies);
        foreach ($ds->getRows() as $row) {
            $dataSheet = DataSheetFactory::createFromUxon($this->getWorkbench(), UxonObject::fromJson($row['DATA_SHEET_UXON']));
            $this->addDataSet(new PWADataset($this, $dataSheet, $row['UID']));
        }
        return $this;
    }
    
    protected function checkAuthorization(UserImpersonationInterface $userOrToken) : PWAInterface
    {
        $pageAP = $this->getWorkbench()->getSecurity()->getAuthorizationPoint(UiPageAuthorizationPoint::class);
        $pageAP->authorize($this->getStartPage(), $userOrToken);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWAInterface::isModelLoaded()
     */
    public function isModelLoaded() : bool
    {
        return $this->modelLoadedForStrategies !== null;
    }
    
    /**
     * 
     * @param array $offlineStrategies
     * @param array $extraAttributes
     * @return DataSheetInterface
     */
    protected function getDataForRoutes(array $offlineStrategies = [], array $extraAttributes = []) : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_ROUTE');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if (! empty($extraAttributes)) {
            $ds->getColumns()->addMultiple($extraAttributes);
        }
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromString('PWA', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('PWA__ALIAS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        if (! empty($offlineStrategies)) {
            $ds->getFilters()->addConditionFromValueArray('PWA_ACTION__OFFLINE_STRATEGY', $offlineStrategies);
        }
        $ds->dataRead();
        return $ds;
    }
    
    /**
     *
     * @param array $offlineStrategies
     * @param array $extraAttributes
     * @return DataSheetInterface
     */
    protected function getDataForBuilds() : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_BUILD');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        $ds->getColumns()->removeByKey('CONTENT');
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromString('PWA', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('PWA__ALIAS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        $ds->dataRead();
        return $ds;
    }
    
    /**
     *
     * @param array $offlineStrategies
     * @param array $extraAttributes
     * @return DataSheetInterface
     */
    protected function getDataForPWA() : DataSheetInterface
    {
        if ($this->dsPWA === null) {
            $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA');
            $ds = DataSheetFactory::createFromObject($obj);
            $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
            if ($this->selector->isUid()) {
                $ds->getFilters()->addConditionFromString('UID', $this->selector->toString(), ComparatorDataType::EQUALS);
            } else {
                $ds->getFilters()->addConditionFromString('ALIAS', $this->selector->toString(), ComparatorDataType::EQUALS);
            }
            $ds->dataRead();
            $this->dsPWA = $ds;
        }
        return $this->dsPWA;
    }
    
    protected function clearCache() : PWAInterface
    {
        $this->startPage = null;
        $this->startPageSelector = null;
        return $this;
    }
    
    /**
     * 
     * @param array $offlineStrategies
     * @return DataSheetInterface
     */
    protected function getDataForActions(array $offlineStrategies = []) : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_ACTION');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromString('PWA', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('PWA__ALIAS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        if (! empty($offlineStrategies)) {
            $ds->getFilters()->addConditionFromValueArray('OFFLINE_STRATEGY', $offlineStrategies);
        }
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * 
     * @param array $offlineStrategies
     * @return DataSheetInterface
     */
    protected function getDataForDatasets(array $offlineStrategies = []) : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_DATASET');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromString('PWA', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('PWA__ALIAS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        /* TODO
        if (! empty($offlineStrategies)) {
            $ds->getFilters()->addConditionFromValueArray('OFFLINE_STRATEGY', $offlineStrategies);
        }*/
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * 
     * @param ActionInterface|PWARouteInterface|PWADatasetInterface $subject
     * @return string
     */
    public function getDescriptionOf($subject) : string
    {
        switch (true) {
            case $subject instanceof ActionInterface:
                $triggerWidget = $this->getActionWidget($subject);
                if ($triggerWidget->hasParent() === false) {
                    return 'Page ' . $triggerWidget->getPage()->getAliasWithNamespace();
                }
                
                if (null !== $triggerWidget) {
                    if ($triggerWidget instanceof iUseInputWidget) {
                        $inputWidget = $triggerWidget->getInputWidget();
                    } else {
                        $inputWidget = $triggerWidget;
                    }
                    
                    $triggerName = $triggerWidget->getCaption() ?? '';
                    if ($triggerName === '' && $triggerWidget instanceof iTriggerAction && $triggerWidget->hasAction()) {
                        $triggerName = $triggerWidget->getAction()->getName();
                    }
                }
                
                return ($inputWidget ? $this->getDescriptionOfWidget($inputWidget) : $triggerWidget->getWidgetType()) . ' > ' . $triggerName;
            case $subject instanceof PWARouteInterface:
                if (null !== $action = $subject->getAction()) {
                    return $this->getDescriptionOf($action);
                } else {
                    return $subject->getURL();
                }
            case $subject instanceof PWADatasetInterface:
                $descr = $subject->getMetaObject()->getName() . ' [' . $subject->getMetaObject()->getAliasWithNamespace() . ']';
                $ds = $subject->getDataSheet();
                if ($ds->hasAggregateAll()) {
                    $ds .= '; aggregated';
                }
                if ($ds->hasAggregations()) {
                    $descr .= '; aggregated by ';
                    foreach ($ds->getAggregations() as $aggr) {
                        $descr .= $aggr->getAttributeAlias() . ', ';
                    }
                    $descr = mb_substr($descr, 0, -2);
                }
                return $descr;
        }
        return '';
    }
    
    /**
     *
     * @param WidgetInterface $widget
     * @return string
     */
    protected function getDescriptionOfWidget(WidgetInterface $widget) : string
    {
        $inputName = $widget->getCaption();
        switch (true) {
            case $widget instanceof Dialog && $widget->hasParent():
                $btn = $widget->getParent();
                if ($btn instanceof Button) {
                    if ($btnCaption = $btn->getCaption()) {
                        $inputName = $btnCaption;
                    }
                    $btnInput = $btn->getInputWidget();
                    $inputName = $this->getDescriptionOfWidget($btnInput) . ' > ' . $inputName;
                }
                break;
            case $widget instanceof Data && (null !== $parent = $widget->getParent()) && ($parent instanceof InputComboTable || $parent instanceof iUseData):
                $inputName = $parent->getWidgetType() . ' "' . $parent->getCaption() . '"';
                break;
            case $inputName !== null && $inputName !== '':
                $inputName = $widget->getWidgetType() . ' "' . $inputName . '"';
                break;
        }
        return $inputName ?? $widget->getWidgetType() . ($widget->getCaption() ? ' "' . $widget->getCaption() . '"' : " [{$widget->getMetaObject()->getAliasWithNamespace()}]");
    }
    
    /**
     * 
     * @return UiMenuItemInterface[]
     */
    public function getMenuRoots() : array
    {
        return [$this->getStartPage()];
    }
    
    public function getUid() : string
    {
        if ($this->selector->isUid()) {
            return $this->selector->toString();
        }
        return $this->getDataForPWA()->getColumns()->get('UID')->getValue(0);
    }
    
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    public function isAvailableOffline() : bool
    {
        return $this->getDataForPWA()->getCellValue('AVAILABLE_OFFLINE_FLAG', 0);
    }
    
    public function isAvailableOfflineHelp() : bool
    {
        return $this->getDataForPWA()->getCellValue('AVAILABLE_OFFLINE_HELP_FLAG', 0);
    }
    
    public function isAvailableOfflineUnpublished() : bool
    {
        return $this->getDataForPWA()->getCellValue('AVAILABLE_OFFLINE_UNPUBLISHED_FLAG', 0);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWAInterface::getBuildCache()
     */
    public function getBuildCache(string $filename, UserImpersonationInterface $userOrToken) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PWA_BUILD');
        $ds->getFilters()->addConditionFromString('PWA', $this->getUid(), ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('FILENAME', $filename, ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('USERNAME', $userOrToken->getUsername(), ComparatorDataType::EQUALS);
        $ds->getColumns()->addMultiple([
            'CONTENT',
            'MIMETYPE',
            'FILENAME'
        ]);
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\PWA\PWAInterface::setBuildCache()
     */
    public function setBuildCache(string $filename, string $content, string $mimetype, UserImpersonationInterface $userOrToken) : DataSheetInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.PWA_BUILD');
        $ds->addRow([
            'FILENAME' => $filename,
            'MIMETYPE' => $mimetype,
            'PWA' => $this->getUid(),
            'CONTENT' => $content,
            'SIZE' => mb_strlen($content),
            'USERNAME' => $userOrToken->getUsername()
        ]);
        $ds->dataCreate();
        return $ds;
    }
    
    public function getName() : string
    {
        return $this->getDataForPWA()->getCellValue('NAME', 0);
    }
    
    public function getURL() : string
    {
        return $this->getDataForPWA()->getCellValue('URL', 0);
    }
}