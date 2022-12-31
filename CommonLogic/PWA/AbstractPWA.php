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
use exface\Core\Widgets\Dialog;

abstract class AbstractPWA implements PWAInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $routes = [];
    
    private $dataSets = [];
    
    private $selector = null;
    
    private $facade = null;
    
    private $startPage = null;
    
    public function __construct(PWASelectorInterface $selector, FacadeInterface $facade)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
        $this->facade = $facade;
    }
    
    public function generateModel() : \Generator
    {
        $this->routes = [];
        $this->dataSets = [];
        yield from $this->generateModelForWidget($this->getStartPage()->getWidgetRoot());
        yield from $this->saveModel();
    }
        
    public function getStartPage() : UiPageInterface
    {
        if ($this->startPage === null) {
            $this->startPage = UiPageFactory::createFromModel($this->getWorkbench(), $this->getPWAData()->getColumns()->get('START_PAGE')->getValue(0));
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
        return $this;
    }
    
    protected function saveModel() : \Generator
    {
        $dsRoutes = $this->getRouteData();
        $newRoutes = [];
        $urlCol = $dsRoutes->getColumns()->get('URL');
        $oldRoutes = $urlCol->getValues();
        $dsRoutes->removeRows();
        foreach($this->getRoutes() as $route) {
            $newRoutes[] = $route->getUrl();
            /*
            if (in_array($route->getUrl(), $oldRoutes)) {
                $dsRoutes->removeRow(array_search($route->getUrl(), $oldRoutes));
            }*/
            $dsRoutes->addRow([
                'PWA' => $this->getUid(),
                'PAGE' => $route->getPage()->getUid(),
                'WIDGET_ID' => $route->getWidget()->getId(),
                'WIDGET_TYPE' => $route->getWidget()->getWidgetType(),
                'URL' => $route->getUrl(),
                'DESCRIPTION' => $route->getDescription(),
                'OFFLINE_STRATEGY' => $this->getRouteOfflineStrategy($route),
                'ACTION_ALIAS' => $route->getAction() !== null ? $route->getAction()->getAliasWithNamespace() : null,
                'USER_DEFINED_FLAG' => 0
            ]);
        }
        yield 'Generated ' . count($newRoutes) . ' routes' . PHP_EOL;
        $deletedRoutes = array_diff($oldRoutes, $newRoutes);
        // TODO remove routes if they are not user defined
        
        $dsRoutes->dataReplaceByFilters();
    }
    
    protected abstract function generateModelForWidget(WidgetInterface $widget, int $linkDepth = 100) : \Generator;
    
    protected abstract function getRouteOfflineStrategy(PWARouteInterface $route) : string;
    
    protected function getRouteData() : DataSheetInterface
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
    
    protected function getPWAData() : DataSheetInterface
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
        return $this->getPWAData()->getColumns()->get('UID')->getValue(0);
    }
    
    public function exportUxonObject()
    {
        // TODO
        return new UxonObject();
    }

    public function getWorkbench()
    {
        return $this->workbench;
    }
}