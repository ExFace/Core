<?php
namespace exface\Core\Widgets\Parts\Maps;

use exface\Core\Interfaces\Widgets\WidgetPartInterface;
use exface\Core\Widgets\Map;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 *
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractMapPart implements WidgetPartInterface
{
    use ImportUxonObjectTrait;
    
    private $chart = null;
    
    private $workbench = null;
    
    private $uxon = null;
    
    public function __construct(Map $widget, UxonObject $uxon = null)
    {
        $this->chart = $widget;
        $this->workbench = $widget->getWorkbench();
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
            $this->uxon = $uxon;
        }
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\WidgetPartInterface::getWidget()
     */
    public function getWidget() : WidgetInterface
    {
        return $this->getMap();
    }
    
    /**
     *
     * @return Map
     */
    public function getMap() : Map
    {
        return $this->chart;
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
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }
    
    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject() : MetaObjectInterface
    {
        return $this->chart->getMetaObject();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return $this->uxon ?? (new UxonObject());
    }
}