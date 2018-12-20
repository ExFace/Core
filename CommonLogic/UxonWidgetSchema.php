<?php
namespace exface\Core\CommonLogic;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\DataSheetFactory;

/**
 * UXON-schema class for widgets.
 * 
 * @see UxonSchema for general information.
 * 
 * @author Andrej Kabachnik
 *
 */
class UxonWidgetSchema extends UxonSchema
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\UxonSchema::getEntityClass()
     */
    public function getEntityClass(UxonObject $uxon, array $path, string $rootEntityClass = '\exface\Core\Widgets\AbstractWidget') : string
    {
        $name = $rootEntityClass;
        
        foreach ($uxon as $key => $value) {
            if (strcasecmp($key, 'widget_type') === 0) {
                $name = $this->getEntityClassFromWidgetType($value);
            }
        }
        
        if (count($path) > 1) {
            return parent::getEntityClass($uxon, $path, $name);
        }
        
        return $name;
    }
    
    /**
     * 
     * @param string $widgetType
     * @return string
     */
    protected function getEntityClassFromWidgetType(string $widgetType) : string
    {
        return WidgetFactory::getWidgetClassFromType($widgetType);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\UxonSchema::getValidValues()
     */
    public function getValidValues(UxonObject $uxon, array $path, string $search = null) : array
    {
        $prop = mb_strtolower(end($path));
        if ($prop === 'widget_type') {
            return $this->getWidgetTypes();
        }
        
        return parent::getValidValues($uxon, $path, $search);
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getWidgetTypes() : array
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.WIDGET');
        $ds->getColumns()->addFromExpression('NAME');
        $ds->dataRead();
        return $ds->getColumns()->get('NAME')->getValues(false);
    }
}