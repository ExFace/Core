<?php
namespace exface\Core\CommonLogic\PWA;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\PWA\ProgressiveWebAppInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\Selectors\PWASelectorInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Selectors\FacadeSelectorInterface;

class ProgressiveWebApp implements ProgressiveWebAppInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $routes = [];
    
    private $dataSets = [];
    
    private $selector = null;
    
    private $facade = null;
    
    public function __construct(PWASelectorInterface $selector, FacadeSelectorInterface $facade)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
        $this->facade = $facade;
    }
    
    public function generateModel() : ProgressiveWebAppInterface
    {
        $this->routes = [];
        $this->dataSets = [];
        $this->generateModelForWidget($this->getStartPage()->getWidgetRoot());
        $this->saveModel();
        return $this;
    }
        
    public function getStartPage() : UiPageInterface
    {
        
    }
    
    protected function saveModel() : ProgressiveWebAppInterface
    {
        $dsRoutes = $this->getRouteData();
        $newRoutes = [];
        $oldRoutes = $dsRoutes->getColumns()->get('URL')->getValues();
        foreach($this->routes as $route) {
            $newRoutes[] = $route->getUrl();
            $dsRoutes->addRow([
                'PWA' => $this->getUid(),
                'PAGE' => $route->getPage()->getUid(),
                'WIDGET_ID' => $route->getWidget()->getId(),
                'URL' => $route->getUrl(),
                'USER_DEFINED_FLAG' => 0
            ]);
        }
        $deletedRoutes = array_diff($oldRoutes, $newRoutes);
        // TODO remove routes if they are not user defined
        
        $dsRoutes->dataReplaceByFilters();
        
        return $this;
    }
    
    protected function generateModelForWidget(WidgetInterface $widget, int $linkDepth = 100) : ProgressiveWebAppInterface
    {
        if ($linkDepth > 0) {
            foreach ($widget->getChildren() as $child) {
                if ($child instanceof iTriggerAction && $child->hasAction() && $child->getAction() instanceof iShowWidget) {
                    $this->routes[] = new PWARoute($this, $child->getAction()->getWidget());
                } else {
                    $this->generateModelForWidget($child, ($linkDepth-1));
                }
            }
        }
        return $this;
    }
    
    protected function getRouteData() : DataSheetInterface
    {
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), 'exface.Core.PWA_ROUTE');
        $ds = DataSheetFactory::createFromObject($obj);
        $ds->getColumns()->addFromAttributeGroup($obj->getAttributeGroup('~ALL'));
        if ($this->selector->isUid()) {
            $ds->getFilters()->addConditionFromAttribute($obj->getUidAttribute(), $this->selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('ALIAS', $this->selector->toString(), ComparatorDataType::EQUALS);
        }
        $ds->dataRead();
        return $ds;
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