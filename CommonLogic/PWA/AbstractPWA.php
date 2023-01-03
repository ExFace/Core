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

abstract class AbstractPWA implements PWAInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $routes = [];
    
    private $actions = [];
    
    private $actionsWidgets = [];
    
    private $actionsModelUIDs = [];
    
    private $actionsDatasets = [];
    
    private $dataSets = [];
    
    private $dataSetsModelUIDs = [];
    
    private $selector = null;
    
    private $facade = null;
    
    private $startPage = null;
    
    public function __construct(PWASelectorInterface $selector, FacadeInterface $facade)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
        $this->facade = $facade;
    }
    
    public function generateModel(DataTransactionInterface $transaction = null) : \Generator
    {
        $transaction = $transaction ?? $this->getWorkbench()->data()->startTransaction();
        $this->routes = [];
        $this->dataSets = [];
        yield from $this->generateModelForWidget($this->getStartPage()->getWidgetRoot());
        yield from $this->saveModel($transaction);
        $transaction->commit();
    }
        
    public function getStartPage() : UiPageInterface
    {
        if ($this->startPage === null) {
            $this->startPage = UiPageFactory::createFromModel($this->getWorkbench(), $this->getDataForPWA()->getColumns()->get('START_PAGE')->getValue(0));
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
    protected function getRoutes() : array
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
        $this->addAction($action, $route->getWidget(), true);
        return $this;
    }
    
    /**
     * 
     * @return ActionInterface[]
     */
    protected function getActions() : array
    {
        return $this->actions;
    }
    
    /**
     * 
     * @param PWARouteInterface $action
     * @return PWAInterface
     */
    protected function addAction(ActionInterface $action, WidgetInterface $triggerWidget) : int
    {
        $actionKey = array_search($action, $this->actions, true);
        if ($actionKey === false) {
            $this->actions[] = $action;
            $actionKey = array_key_last($this->actions);
            $this->actionsWidgets[$actionKey] = $triggerWidget;
        }
        return $actionKey;
    }
    
    protected function getActionDataSet(ActionInterface $action) : ?PWADatasetInterface
    {
        $actionKey = array_search($action, $this->actions, true);
        return $this->actionsDatasets[$actionKey] ?? null;
    }
    
    protected function getActionWidget(ActionInterface $action) : WidgetInterface
    {
        $actionKey = array_search($action, $this->actions, true);
        return $this->actionsWidgets[$actionKey];
    }
    
    protected function getActionPWAModelUID(ActionInterface $action) : ?string
    {
        $actionKey = array_search($action, $this->actions, true);
        return $this->actionsModelUIDs[$actionKey];
    }
    
    protected function getDatasetPWAModelUID(PWADatasetInterface $set) : ?string
    {
        $key = array_search($set, $this->dataSets, true);
        return $this->dataSetsModelUIDs[$key];
    }
    
    /**
     * 
     * @return PWADatasetInterface[]
     */
    public function getDatasets() : array
    {
        return $this->dataSets;        
    }
    
    public function addData(DataSheetInterface $dataSheet, ActionInterface $action, WidgetInterface $widget) : PWADatasetInterface
    {
        $set = $this->findDataSet($dataSheet);
        if ($set === null) {
            $set = new PWADataset($this, $dataSheet->getMetaObject());
            $this->dataSets[] = $set;
        }
        
        $actionKey = $this->addAction($action, $widget);
        $this->actionsDatasets[$actionKey] = $set;
        
        $setSheet = $set->getDataSheet();
        $setCols = $setSheet->getColumns();
        foreach ($dataSheet->getColumns() as $col) {
            if (! $setCols->getByExpression($col->getExpressionObj())) {
                $setCols->addFromExpression($col->getExpressionObj());
            }
        }
        return $set;
    }
    
    public function findDataSet(DataSheetInterface $dataSheet) : ?PWADatasetInterface
    {
        $obj = $dataSheet->getMetaObject();
        $match = null;
        foreach ($this->getDatasets() as $set) {
            if (! $set->getMetaObject()->is($obj)) {
                continue;
            }
            $setSheet = $set->getDataSheet();
            if ($setSheet->hasAggregations() && $dataSheet->hasAggregations()) {
                foreach ($dataSheet->hasAggregations() as $a => $aggr) {
                    if ($setSheet->getAggregations()->get($a)->getAttributeAlias() !== $aggr->getAttributeAlias()) {
                        continue 2;
                    }
                }
                return $set;
            }
            if ($setSheet->hasAggregateAll() !== $dataSheet->hasAggregateAll()) {
                continue;
            }
            // TODO compare filters too!!!
            $match = $set;
            break;
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
        $dsDatasets = $this->getDataForDatasets();
        $dsDatasets->removeRows();
        foreach ($this->getDatasets() as $set) {
            $dsDatasets->addRow([
                'PWA' => $this->getUid(),
                'DESCRIPTION' => $this->getDescriptionOf($set),
                'OBJECT' => $set->getMetaObject()->getId(),
                'DATA_SHEET_UXON' => $set->getDataSheet()->exportUxonObject()->toJson(),
                'USER_DEFINED_FLAG' => 0
            ]);
        }
        yield 'Generated ' . $dsDatasets->countRows() . ' actions' . PHP_EOL;
        $dsDatasets->dataReplaceByFilters($transaction);
        $this->dataSetsModelUIDs = $dsDatasets->getUidColumn()->getValues();
        
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
                'OFFLINE_STRATEGY' => $this->getActionOfflineStrategy($action),
                'ACTION_ALIAS' => $action->getAliasWithNamespace(),
                'PWA_DATASET' => null !== ($set = $this->getActionDataSet($action)) ? $this->getDatasetPWAModelUID($set) : null
            ]);
        }
        yield 'Generated ' . $dsActions->countRows() . ' actions' . PHP_EOL;
        $dsActions->dataReplaceByFilters($transaction);
        $this->actionsModelUIDs = $dsActions->getUidColumn()->getValues();
        
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
            ]);
        }
        yield 'Generated ' . count($newRoutes) . ' routes' . PHP_EOL;
        $deletedRoutes = array_diff($oldRoutes, $newRoutes);
        // TODO remove routes if they are not user defined
        
        $dsRoutes->dataReplaceByFilters($transaction);
    }
    
    protected abstract function generateModelForWidget(WidgetInterface $widget, int $linkDepth = 100) : \Generator;
    
    protected abstract function getActionOfflineStrategy(ActionInterface $action) : string;
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getDataForRoutes() : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_ROUTE');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromString('PWA', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('PWA__ALIAS_WITH_NS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getDataForActions() : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_ACTION');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromString('PWA', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('PWA__ALIAS_WITH_NS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getDataForDatasets() : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_DATASET');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromString('PWA', $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('PWA__ALIAS_WITH_NS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        $ds->dataRead();
        return $ds;
    }
    
    /**
     * 
     * @return DataSheetInterface
     */
    protected function getDataForPWA() : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromAttribute($obj->getUidAttribute(), $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('ALIAS_WITH_NS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
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
                return $this->getDescriptionOf($subject->getAction());
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
    protected function getMenuRoots() : array
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
}